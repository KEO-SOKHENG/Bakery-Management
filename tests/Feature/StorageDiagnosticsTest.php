<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StorageDiagnosticsService;
use Aws\Command;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $cashier;
    private StorageDiagnosticsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name'     => 'Admin Test User',
            'email'    => 'admin.storage@bakery.com',
            'username' => 'admin_storage',
            'role'     => 'admin',
            'status'   => 'active',
        ]);

        $this->manager = User::factory()->create([
            'name'     => 'Manager Test User',
            'email'    => 'manager.storage@bakery.com',
            'username' => 'manager_storage',
            'role'     => 'manager',
            'status'   => 'active',
        ]);

        $this->cashier = User::factory()->create([
            'name'     => 'Cashier Test User',
            'email'    => 'cashier.storage@bakery.com',
            'username' => 'cashier_storage',
            'role'     => 'cashier',
            'status'   => 'active',
        ]);

        $this->service = app(StorageDiagnosticsService::class);
    }

    /**
     * 1. Actual configured storage driver detection and provider metadata.
     */
    public function test_actual_configured_storage_driver_detection(): void
    {
        Config::set('filesystems.settings_disk', 'supabase-s3');
        Config::set('filesystems.disks.supabase-s3', [
            'driver'                  => 'supabase-s3',
            'key'                     => 's3_test_key_abc',
            'secret'                  => 's3_test_secret_xyz_super_confidential',
            'region'                  => 'ap-northeast-1',
            'bucket'                  => 'bakery-media',
            'endpoint'                => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
            'url'                     => 'https://rtrnlnezucgjkgqluofv.supabase.co/storage/v1/object/public/bakery-media',
            'use_path_style_endpoint' => true,
        ]);

        $config = $this->service->getProviderConfig('supabase-s3');

        $this->assertEquals('Supabase Storage (S3-compatible)', $config['provider']);
        $this->assertEquals('supabase-s3', $config['driver']);
        $this->assertEquals('supabase-s3', $config['disk_name']);
        $this->assertEquals('bakery-media', $config['bucket']);
        $this->assertEquals('ap-northeast-1', $config['region']);
        $this->assertEquals('rtrnlnezucgjkgqluofv.storage.supabase.co', $config['endpoint_host']);
        $this->assertTrue($config['is_public']);
        $this->assertEquals('Public (Read-only object access)', $config['public_access']);

        // Verify credentials are NEVER exposed in config output
        $this->assertArrayNotHasKey('key', $config);
        $this->assertArrayNotHasKey('secret', $config);
        $this->assertStringNotContainsString('s3_test_secret_xyz', json_encode($config));
    }

    /**
     * 2. Storage connection success with operational bucket.
     */
    public function test_storage_connection_success(): void
    {
        Storage::fake('test-connected-disk');
        $conn = $this->service->checkConnection('test-connected-disk');

        $this->assertTrue($conn['is_connected']);
        $this->assertEquals('Connected', $conn['status']);
        $this->assertIsFloat($conn['latency_ms']);
        $this->assertGreaterThanOrEqual(0, $conn['latency_ms']);
    }

    /**
     * 3. Storage connection failure on network unreachable/timeout.
     */
    public function test_storage_connection_failure_on_unreachable_endpoint(): void
    {
        Config::set('filesystems.disks.unreachable-disk', [
            'driver'   => 'local',
            'root'     => 'Z:\\invalid_non_existent_drive_letter_xyz_123\\path',
            'throw'    => true,
        ]);

        $conn = $this->service->checkConnection('unreachable-disk');

        // Handles unreachable storage safely without uncaught fatal exception
        $this->assertFalse($conn['is_connected']);
        $this->assertContains($conn['status'], ['Connection failed', 'Data unavailable']);
    }

    /**
     * 4. Empty bucket handling without division by zero or errors.
     */
    public function test_empty_bucket_handling(): void
    {
        Storage::fake('empty-bucket-disk');
        $usage = $this->service->getStorageUsage('empty-bucket-disk');

        $this->assertEquals(0, $usage['total_files']);
        $this->assertEquals(0, $usage['total_bytes']);
        $this->assertEquals('0 B', $usage['total_bytes_formatted']);
        $this->assertNull($usage['quota_bytes']);
        $this->assertEquals('Unavailable (Unconstrained)', $usage['quota_formatted']);
        $this->assertNull($usage['quota_percent']);
        $this->assertEmpty($usage['recent_files']);
        $this->assertEmpty($usage['largest_files']);
        $this->assertNull($usage['error']);
    }

    /**
     * 5. Object metadata calculations and category breakdown.
     */
    public function test_object_metadata_and_category_calculations(): void
    {
        Storage::fake('populated-disk');
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('populated-disk');

        // Populate verified test objects across specific application categories
        $disk->put('logos/bakery_logo.png', str_repeat('A', 1024)); // 1 KB
        $disk->put('products/sourdough.webp', str_repeat('B', 2048)); // 2 KB
        $disk->put('categories/bread.jpg', str_repeat('C', 512)); // 512 B
        $disk->put('avatars/admin_avatar.png', str_repeat('D', 4096)); // 4 KB
        $disk->put('receipts/receipt_001.pdf', str_repeat('E', 1024)); // 1 KB
        $disk->put('misc/notes.txt', str_repeat('F', 256)); // 256 B

        $usage = $this->service->getStorageUsage('populated-disk');

        $this->assertEquals(6, $usage['total_files']);
        $this->assertEquals(8960, $usage['total_bytes']);
        $this->assertEquals('8.8 KB', $usage['total_bytes_formatted']);

        // Check category assignments
        $categoriesByKey = collect($usage['categories'])->keyBy('key');
        $this->assertTrue($categoriesByKey->has('logos'));
        $this->assertTrue($categoriesByKey->has('products'));
        $this->assertTrue($categoriesByKey->has('categories'));
        $this->assertTrue($categoriesByKey->has('avatars'));
        $this->assertTrue($categoriesByKey->has('receipts'));
        $this->assertTrue($categoriesByKey->has('other'));

        $this->assertEquals(1, $categoriesByKey['logos']['count']);
        $this->assertEquals(1024, $categoriesByKey['logos']['bytes']);
        $this->assertEquals(1, $categoriesByKey['products']['count']);
        $this->assertEquals(2048, $categoriesByKey['products']['bytes']);

        // Check file extension breakdown
        $exts = collect($usage['file_types'])->pluck('extension')->all();
        $this->assertContains('PNG', $exts);
        $this->assertContains('WEBP', $exts);
        $this->assertContains('JPG', $exts);
        $this->assertContains('PDF', $exts);

        // Check largest files sorting
        $this->assertCount(5, $usage['largest_files']);
        $this->assertEquals('admin_avatar.png', $usage['largest_files'][0]['name']);
        $this->assertEquals(4096, $usage['largest_files'][0]['size_bytes']);

        // Check recent files
        $this->assertCount(6, $usage['recent_files']);
        $this->assertNotEmpty($usage['recent_files'][0]['mime_type']);
    }

    /**
     * 6. Storage quota is reported as unavailable/unconstrained without fabricating 1 GB.
     */
    public function test_missing_quota_reported_as_unconstrained_without_invented_limit(): void
    {
        Storage::fake('quota-test-disk');
        $usage = $this->service->getStorageUsage('quota-test-disk');

        $this->assertNull($usage['quota_bytes']);
        $this->assertEquals('Unavailable (Unconstrained)', $usage['quota_formatted']);
        $this->assertNull($usage['quota_percent']);
        $this->assertStringContainsString('does not expose account storage quota', $usage['quota_notice']);
    }

    /**
     * 7. Unauthorized users are blocked from storage diagnostics API.
     */
    public function test_unauthorized_users_are_blocked_from_storage_diagnostics_api(): void
    {
        // 1. Guest is redirected / unauthorized
        $guestRes = $this->get(route('admin.settings.storageStatus'));
        $guestRes->assertRedirect(route('login'));

        // 2. Cashier receives 403 Forbidden
        $cashierRes = $this->actingAs($this->cashier)->json('GET', route('admin.settings.storageStatus'));
        $cashierRes->assertStatus(403);

        // 3. Manager receives 403 Forbidden
        $managerRes = $this->actingAs($this->manager)->json('GET', route('admin.settings.storageStatus'));
        $managerRes->assertStatus(403);
    }

    /**
     * 8. Admin can access real-time storage diagnostics API.
     */
    public function test_admin_can_access_storage_diagnostics_api(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/test_logo.png', 'sample image content');

        $response = $this->actingAs($this->admin)->json('GET', route('admin.settings.storageStatus'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'provider' => [
                'provider',
                'driver',
                'disk_name',
                'bucket',
                'region',
                'endpoint_host',
                'public_access',
                'is_public',
            ],
            'connection' => [
                'status',
                'is_connected',
                'latency_ms',
                'message',
                'checked_at',
            ],
            'usage' => [
                'total_files',
                'total_bytes',
                'total_bytes_formatted',
                'quota_formatted',
                'categories',
                'file_types',
                'recent_files',
                'largest_files',
            ],
            'synced_at',
            'synced_at_formatted',
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertGreaterThanOrEqual(1, $response->json('usage.total_files'));
    }

    /**
     * 9. Admin can force-refresh storage diagnostics.
     */
    public function test_admin_can_force_refresh_storage_diagnostics(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->admin)->json('GET', route('admin.settings.storageStatus', ['refresh' => 1]));

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertNotEmpty($response->json('synced_at_formatted'));
    }

    /**
     * 10. Sensitive S3 credentials are never leaked into API responses.
     */
    public function test_sensitive_credentials_never_leaked_into_api_response(): void
    {
        Config::set('filesystems.settings_disk', 's3');
        Config::set('filesystems.disks.s3', [
            'driver'                  => 'supabase-s3',
            'key'                     => 'CRITICAL_SECRET_AWS_KEY_12345',
            'secret'                  => 'SUPER_CONFIDENTIAL_S3_SECRET_98765',
            'region'                  => 'ap-northeast-1',
            'bucket'                  => 'bakery-media',
            'endpoint'                => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
            'use_path_style_endpoint' => true,
        ]);
        Storage::fake('s3');

        $response = $this->actingAs($this->admin)->json('GET', route('admin.settings.storageStatus'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('CRITICAL_SECRET_AWS_KEY_12345', $content);
        $this->assertStringNotContainsString('SUPER_CONFIDENTIAL_S3_SECRET_98765', $content);
    }

    /**
     * 11. Production environment flags configuration mismatch when ephemeral local disk is configured.
     */
    public function test_production_environment_flags_configuration_mismatch_when_local_disk_configured(): void
    {
        $this->app->detectEnvironment(fn() => 'production');

        Config::set('filesystems.settings_disk', 'public');
        $conn = $this->service->checkConnection('public');

        $this->assertFalse($conn['is_connected']);
        $this->assertEquals('Configuration mismatch', $conn['status']);
        $this->assertStringContainsString('Production environment requires persistent cloud storage', $conn['message']);

        $usage = $this->service->getStorageUsage('public');
        $this->assertEquals('Data unavailable', $usage['total_bytes_formatted']);
        $this->assertEquals('Configuration mismatch: local disk in production', $usage['error']);
    }

    /**
     * 12. Missing S3 credentials or bucket reports explicit configuration mismatch.
     */
    public function test_missing_s3_credentials_or_bucket_reports_configuration_mismatch(): void
    {
        Config::set('filesystems.settings_disk', 'supabase-s3');
        Config::set('filesystems.disks.supabase-s3', [
            'driver'   => 'supabase-s3',
            'key'      => null,
            'secret'   => null,
            'bucket'   => null,
            'endpoint' => 'https://rtrnlnezucgjkgqluofv.storage.supabase.co/storage/v1/s3',
        ]);

        $conn = $this->service->checkConnection('supabase-s3');
        $this->assertFalse($conn['is_connected']);
        $this->assertEquals('Configuration mismatch', $conn['status']);
        $this->assertStringContainsString('Missing required S3 credentials', $conn['message']);
    }

    /**
     * 13. Local disk is labeled as development storage, never Supabase Connected.
     */
    public function test_local_disk_labeled_as_development_storage_never_supabase_connected(): void
    {
        $this->app->detectEnvironment(fn() => 'local');

        Config::set('filesystems.settings_disk', 'public');
        Storage::fake('public');

        $prov = $this->service->getProviderConfig('public');
        $this->assertEquals('Local Public Filesystem (Development)', $prov['provider']);

        $conn = $this->service->checkConnection('public');
        $this->assertTrue($conn['is_connected']);
        $this->assertEquals('Connected (Local Dev)', $conn['status']);
        $this->assertStringNotContainsString('Supabase Connected', $conn['status']);
        $this->assertStringContainsString('Local development storage disk is operational', $conn['message']);
    }

    /**
     * 14. Artisan storage:diagnostic command executes and outputs formatted or JSON diagnostics.
     */
    public function test_artisan_storage_diagnostic_command_executes_successfully(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/cmd_logo.png', 'content');

        $this->artisan('storage:diagnostic')
            ->expectsOutputToContain('Storage Diagnostic Inspection')
            ->assertExitCode(0);

        $this->artisan('storage:diagnostic', ['--json' => true])
            ->expectsOutputToContain('"provider":')
            ->assertExitCode(0);
    }

    /**
     * 15. Artisan storage:diagnostic command fails safely when disk configuration is mismatched.
     */
    public function test_artisan_storage_diagnostic_fails_on_configuration_mismatch(): void
    {
        Config::set('filesystems.disks.broken-s3', [
            'driver' => 'supabase-s3',
            'key'    => null,
            'secret' => null,
            'bucket' => null,
        ]);

        $this->artisan('storage:diagnostic', ['--disk' => 'broken-s3'])
            ->expectsOutputToContain('Configuration mismatch')
            ->assertExitCode(1);
    }
}
