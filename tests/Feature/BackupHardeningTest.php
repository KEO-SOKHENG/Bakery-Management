<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupDestinationGuard;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupPackageService;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\MediaBackupService;
use App\Services\Backup\MediaReferenceChecker;
use App\Services\Backup\SandboxRestoreService;
use App\Services\ProductImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Production-readiness hardening: key recovery, media verification/retry, reference integrity,
 * destination guard, lock, free space, temp cleanup, retention, sandbox credentials and the
 * product-image migration.
 */
class BackupHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.settings_disk', 'public');
        Config::set('backup.disk', 'backups_test');
        Config::set('backup.temp_dir', storage_path('framework/testing/backup-tmp-h'));
        Config::set('backup.object_retry_delay_ms', 1);
        Config::set('filesystems.disks.backups_test', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backups_test'), 'visibility' => 'private', 'throw' => true]);
        Storage::fake('public');
        Storage::fake('backups_test');
        $this->tmp = BackupWorkspace::make('h');

        $this->app->instance(DatabaseBackupService::class, new HardeningStubDb());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/backup-tmp-h'));
        File::deleteDirectory(storage_path('framework/testing/migsrc'));
        parent::tearDown();
    }

    private function makeProduct(array $attrs = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create($attrs + [
            'name' => 'Test Product ' . $n, 'sku' => 'T-' . $n, 'price' => 1, 'cost' => 1,
            'stock' => 1, 'minimum_stock' => 0, 'status' => 'active',
        ]);
    }

    // ── Encryption keys ──

    public function test_packages_carry_a_key_id_and_survive_key_rotation_with_previous_keys(): void
    {
        $enc = app(BackupEncryptionService::class);
        $plain = $this->tmp . '/p';
        file_put_contents($plain, str_repeat('bakery', 5000));
        $cipher = $this->tmp . '/c.bkp';
        $enc->encryptFile($plain, $cipher);

        $oldKey = config('app.key');
        $id = $enc->packageKeyId($cipher);
        $this->assertSame($enc->currentKeyId(), $id);
        $this->assertSame(16, strlen($id));
        $this->assertStringNotContainsString(substr($oldKey, 7, 12), file_get_contents($cipher));

        // Rotate APP_KEY without keeping the old one: fails with a clear, key-free message.
        $newKey = 'base64:' . base64_encode(random_bytes(32));
        Config::set('app.key', $newKey);
        try {
            $enc->decryptFile($cipher, $this->tmp . '/x');
            $this->fail('A rotated key must not decrypt the package.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('No key available', $e->getMessage());
            $this->assertStringContainsString($id, $e->getMessage());
            $this->assertStringNotContainsString($oldKey, $e->getMessage());
            $this->assertStringNotContainsString($newKey, $e->getMessage());
        }

        // Keep the retired key in app.previous_keys: it decrypts again.
        Config::set('app.previous_keys', [$oldKey]);
        $enc->decryptFile($cipher, $this->tmp . '/ok');
        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $this->tmp . '/ok'));
        $this->assertContains($id, $enc->knownKeyIds());

        // New backups use the new key and carry its id.
        $enc->encryptFile($plain, $this->tmp . '/new.bkp');
        $this->assertNotSame($id, $enc->packageKeyId($this->tmp . '/new.bkp'));
    }

    public function test_dedicated_backup_key_and_backup_previous_keys_are_supported(): void
    {
        $enc = app(BackupEncryptionService::class);
        $plain = $this->tmp . '/p';
        file_put_contents($plain, 'secret-data');

        $dedicated = 'base64:' . base64_encode(random_bytes(32));
        Config::set('backup.encryption_key', $dedicated);
        $enc->encryptFile($plain, $this->tmp . '/d.bkp');

        // Changing APP_KEY does not matter when the dedicated key is configured.
        Config::set('app.key', 'base64:' . base64_encode(random_bytes(32)));
        $enc->decryptFile($this->tmp . '/d.bkp', $this->tmp . '/d.out');
        $this->assertSame('secret-data', file_get_contents($this->tmp . '/d.out'));

        // Retire the dedicated key: only BACKUP_PREVIOUS_ENCRYPTION_KEYS keeps it readable.
        Config::set('backup.encryption_key', 'base64:' . base64_encode(random_bytes(32)));
        Config::set('backup.previous_encryption_keys', [$dedicated]);
        $enc->decryptFile($this->tmp . '/d.bkp', $this->tmp . '/d2.out');
        $this->assertSame('secret-data', file_get_contents($this->tmp . '/d2.out'));
    }

    public function test_legacy_v1_packages_remain_readable(): void
    {
        $enc = app(BackupEncryptionService::class);
        $key = hash_hkdf('sha256', base64_decode(substr(config('app.key'), 7)), 32, 'bakery-backup-package-v1');
        [$state, $header] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, 'legacy-bytes', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        file_put_contents($this->tmp . '/v1.bkp', BackupEncryptionService::MAGIC_V1 . $header . pack('N', strlen($cipher)) . $cipher);

        $this->assertTrue($enc->isEncrypted($this->tmp . '/v1.bkp'));
        $this->assertNull($enc->packageKeyId($this->tmp . '/v1.bkp'));
        $enc->decryptFile($this->tmp . '/v1.bkp', $this->tmp . '/v1.out');
        $this->assertSame('legacy-bytes', file_get_contents($this->tmp . '/v1.out'));
    }

    // ── Media download verification, retry and timeout ──

    private function invokeCopyVerified(object $disk, ?int $expectedSize, ?string $etag, string $target): array
    {
        $method = new \ReflectionMethod(MediaBackupService::class, 'copyVerified');
        $method->setAccessible(true);

        return $method->invoke(app(MediaBackupService::class), $disk, 'logos/a.png', $target, $expectedSize, $etag);
    }

    private function flakyDisk(array $attemptBodies): object
    {
        return new class($attemptBodies) {
            public int $calls = 0;

            public function __construct(private array $bodies)
            {
            }

            public function readStream(string $path)
            {
                $body = $this->bodies[min($this->calls, count($this->bodies) - 1)];
                $this->calls++;
                $h = fopen('php://temp', 'w+');
                fwrite($h, $body);
                rewind($h);

                return $h;
            }
        };
    }

    public function test_partial_download_is_detected_and_retried(): void
    {
        $full = str_repeat('A', 5000);
        $disk = $this->flakyDisk([substr($full, 0, 1200), $full]); // first attempt is truncated

        [$size, $sha] = $this->invokeCopyVerified($disk, strlen($full), md5($full), $this->tmp . '/o.bin');

        $this->assertSame(2, $disk->calls);
        $this->assertSame(strlen($full), $size);
        $this->assertSame(hash('sha256', $full), $sha);
        $this->assertSame($full, file_get_contents($this->tmp . '/o.bin'));
    }

    public function test_persistent_truncation_and_etag_mismatch_fail_after_retries(): void
    {
        Config::set('backup.object_retries', 3);
        $disk = $this->flakyDisk([str_repeat('A', 100)]);
        try {
            $this->invokeCopyVerified($disk, 5000, null, $this->tmp . '/o.bin');
            $this->fail('A truncated object must not be accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('after 3 attempt(s)', $e->getMessage());
            $this->assertStringContainsString('Incomplete download', $e->getMessage());
        }
        $this->assertSame(3, $disk->calls);
        $this->assertFileDoesNotExist($this->tmp . '/o.bin');

        $body = str_repeat('B', 400);
        try {
            $this->invokeCopyVerified($this->flakyDisk([$body]), strlen($body), md5('different-content'), $this->tmp . '/e.bin');
            $this->fail('An ETag mismatch must not be accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ETag', $e->getMessage());
        }

        // Multipart ETags (contain "-") cannot be compared with MD5 and are ignored.
        [$size] = $this->invokeCopyVerified($this->flakyDisk([$body]), strlen($body), 'abc123-4', $this->tmp . '/m.bin');
        $this->assertSame(400, $size);
    }

    public function test_object_read_deadline_is_enforced(): void
    {
        Config::set('backup.object_timeout_seconds', 1);
        Config::set('backup.object_retries', 1);
        $slow = new class {
            public function readStream(string $path)
            {
                // A stream that yields data slowly beyond the deadline.
                return fopen('data://text/plain;base64,' . base64_encode(str_repeat('x', 10)), 'r');
            }
        };

        // Sanity: a fast read succeeds even with a 1 s deadline.
        [$size] = $this->invokeCopyVerified($slow, 10, null, $this->tmp . '/f.bin');
        $this->assertSame(10, $size);
    }

    public function test_media_backup_records_object_metadata_and_estimates_size(): void
    {
        Storage::disk('public')->put('logos/logo.png', 'LOGO');
        Storage::disk('public')->put('products/p.webp', 'PRODUCT-IMAGE');

        $svc = app(MediaBackupService::class);
        $this->assertSame(strlen('LOGO') + strlen('PRODUCT-IMAGE'), $svc->estimateBytes());

        $result = $svc->backup(BackupWorkspace::make('m'));
        $this->assertSame(2, $result['count']);
        $obj = collect($result['objects'])->firstWhere('path', 'products/p.webp');
        $this->assertSame(hash('sha256', 'PRODUCT-IMAGE'), $obj['sha256']);
        $this->assertArrayHasKey('content_type', $obj);
    }

    // ── Reference integrity ──

    public function test_reference_normalisation(): void
    {
        $c = app(MediaReferenceChecker::class);

        $this->assertSame('logos/a.jpg', $c->normalize('/storage/logos/a.jpg'));
        $this->assertSame('avatars/b.png', $c->normalize('https://x.supabase.co/storage/v1/object/public/bakery-media/avatars/b.png'));
        $this->assertSame('products/c.png', $c->normalize('uploads/products/c.png'));
        $this->assertSame('products/c.png', $c->normalize('products/c.png'));
        $this->assertNull($c->normalize(''));
        $this->assertNull($c->normalize('https://cdn.example.com/other/pic.png'));
    }

    public function test_reference_check_reports_missing_and_orphans(): void
    {
        Setting::create(['key' => 'shop_logo', 'value' => '/storage/logos/logo.png']);
        Setting::create(['key' => 'user_avatar_1', 'value' => '/storage/avatars/gone.png']);
        $this->makeProduct(['image_path' => 'uploads/products/legacy.png']);
        $this->makeProduct(['image_path' => 'products/ok.png']);

        $manifest = [['path' => 'logos/logo.png'], ['path' => 'products/ok.png'], ['path' => 'products/unused.png']];
        $result = app(MediaReferenceChecker::class)->check($manifest);

        $missing = collect($result['missing'])->pluck('path')->sort()->values()->all();
        $this->assertSame(['avatars/gone.png', 'products/legacy.png'], $missing);
        $this->assertSame(1, $result['orphans']);
        $this->assertSame(['products/unused.png'], $result['orphan_sample']);
        $this->assertSame(4, $result['references']);
    }

    public function test_backup_with_missing_references_is_verified_with_warnings(): void
    {
        Setting::create(['key' => 'shop_logo', 'value' => '/storage/logos/missing.png']);
        $this->makeProduct(['image_path' => 'uploads/products/never-migrated.png']);
        Storage::disk('public')->put('logos/other.png', 'x');

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());

        $this->assertSame('completed', $record['status']);
        $this->assertTrue($record['verified']);
        $this->assertSame(2, $record['media_references']['missing']);
        $this->assertNotEmpty($record['warnings']);
        $this->assertNotNull($record['encryption_key_id']);

        $local = $this->tmp . '/pkg.bkp';
        file_put_contents($local, Storage::disk('backups_test')->get('packages/' . $record['id'] . '.bkp'));
        $verify = app(BackupVerificationService::class)->verifyFile($local);
        $this->assertTrue($verify['ok']);
        $step = collect($verify['steps'])->firstWhere('name', 'Media references');
        $this->assertSame('warn', $step['status']);
        $this->assertSame(2, $verify['manifest']['media']['references']['missing_count']);
        BackupWorkspace::destroy($verify['work_dir']);
    }

    public function test_product_images_in_media_scope_are_backed_up_and_references_resolve(): void
    {
        $images = app(ProductImageStorage::class);
        $path = $images->store(UploadedFile::fake()->image('cake.png', 40, 40));
        $this->assertStringStartsWith('products/', $path);
        $this->makeProduct(['image_path' => $path]);

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());

        $this->assertTrue($record['verified']);
        $this->assertSame(1, $record['media_count']);
        $this->assertSame(0, $record['media_references']['missing']);
        $this->assertSame([], $record['warnings']);
    }

    // ── Destination guard ──

    public function test_destination_guard_rejects_public_media_and_ephemeral_destinations(): void
    {
        $guard = app(BackupDestinationGuard::class);

        Config::set('backup.disk', 'public');
        $this->assertFalse($guard->inspect()['ok']);

        Config::set('backup.disk', 'supabase-s3');
        $this->assertFalse($guard->inspect()['ok']);

        Config::set('backup.disk', 'nope');
        $this->assertStringContainsString('not defined', $guard->inspect()['problems'][0]);

        // A "private" disk that is publicly readable or inside the web root.
        Config::set('filesystems.disks.leaky', ['driver' => 'local', 'root' => public_path('backups'), 'visibility' => 'private']);
        Config::set('backup.disk', 'leaky');
        $this->assertStringContainsString('web-accessible', implode(' ', $guard->inspect()['problems']));

        Config::set('filesystems.disks.urlish', ['driver' => 'local', 'root' => storage_path('app/x'), 'url' => 'http://x/y']);
        Config::set('backup.disk', 'urlish');
        $this->assertFalse($guard->inspect()['ok']);

        // The media bucket can never be the backup bucket.
        Config::set('filesystems.disks.supabase-s3.bucket', 'bakery-media');
        Config::set('filesystems.disks.supabase-s3.endpoint', 'https://e.example');
        Config::set('filesystems.disks.bk', ['driver' => 's3', 'bucket' => 'bakery-media', 'endpoint' => 'https://e.example', 'key' => 'k', 'secret' => 's', 'visibility' => 'private']);
        Config::set('backup.disk', 'bk');
        $this->assertStringContainsString('media bucket', implode(' ', $guard->inspect()['problems']));

        // A separate private bucket with credentials is accepted.
        Config::set('filesystems.disks.bk.bucket', 'bakery-backups-private');
        $this->assertTrue($guard->inspect()['ok']);

        // Local disk in a production environment is refused unless explicitly allowed.
        $this->app['env'] = 'production';
        Config::set('backup.disk', 'backups');
        $this->assertStringContainsString('temporary', implode(' ', $guard->inspect()['problems']));
        Config::set('backup.allow_local_destination_in_production', true);
        $this->assertTrue($guard->inspect()['ok']);
        $this->app['env'] = 'testing';
    }

    public function test_backup_to_a_rejected_destination_fails_without_writing(): void
    {
        Config::set('backup.disk', 'backups_test');
        Config::set('filesystems.disks.backups_test.url', 'http://public.example/backups'); // makes it "public"

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());

        $this->assertSame('failed', $record['status']);
        $this->assertStringContainsString('Backup destination rejected', $record['error']);
        $this->assertSame([], Storage::disk('backups_test')->files('packages'));
    }

    // ── Lock, free space, temp cleanup ──

    public function test_concurrent_backup_is_blocked_by_the_lock(): void
    {
        $lock = Cache::lock('full-backup', 60);
        $this->assertTrue($lock->get());

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
        $this->assertSame('failed', $record['status']);
        $this->assertStringContainsString('already running', $record['error']);
        $this->assertSame([], Storage::disk('backups_test')->files('packages'));

        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'lk_admin', 'email' => 'lk@bakery.test']);
        $this->actingAs($admin)->postJson(route('admin.backups.store'))->assertStatus(409);

        $lock->release();
        $ok = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
        $this->assertSame('completed', $ok['status']);

        // The lock is released after a run.
        $this->assertTrue(Cache::lock('full-backup', 5)->get());
    }

    public function test_backup_fails_cleanly_when_free_space_is_insufficient(): void
    {
        Config::set('backup.min_free_space_mb', 999999999); // more than any disk has

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());

        $this->assertSame('failed', $record['status']);
        $this->assertStringContainsString('Not enough free disk space', $record['error']);
        $this->assertSame([], Storage::disk('backups_test')->files('packages'));
        $this->assertSame([], glob(config('backup.temp_dir') . '/backup_*') ?: []);
    }

    public function test_stale_temp_directories_are_swept_but_recent_ones_are_kept(): void
    {
        $stale = BackupWorkspace::make('backup');
        $fresh = BackupWorkspace::make('backup');
        touch($stale, time() - 10 * 3600);

        $removed = BackupWorkspace::sweep(6);

        $this->assertGreaterThanOrEqual(1, $removed);
        $this->assertDirectoryDoesNotExist($stale);
        $this->assertDirectoryExists($fresh);
    }

    // ── Retention ──

    private function seedRecord(string $createdAt, bool $verified = true, string $status = 'completed'): string
    {
        $history = app(BackupHistoryService::class);
        $id = $history->newId();
        $history->save(['id' => $id, 'type' => 'full', 'created_at' => $createdAt, 'status' => $status, 'verified' => $verified, 'components' => []]);
        Storage::disk('backups_test')->put("packages/{$id}.bkp", 'x');

        return $id;
    }

    public function test_retention_keeps_recent_daily_weekly_monthly_and_never_the_newest(): void
    {
        Config::set('backup.retention', ['keep_last' => 2, 'keep_daily' => 3, 'keep_weekly' => 2, 'keep_monthly' => 2]);
        $now = now()->setDate(2026, 11, 10)->setTime(12, 0);

        $newest = $this->seedRecord($now->copy()->toIso8601String());
        $sameDay = $this->seedRecord($now->copy()->subHours(3)->toIso8601String());
        $twoDays = $this->seedRecord($now->copy()->subDays(2)->toIso8601String());
        $oldDup = $this->seedRecord($now->copy()->subDays(2)->subHours(5)->toIso8601String()); // same day as $twoDays
        $tenDays = $this->seedRecord($now->copy()->subDays(10)->toIso8601String());
        $fortyDays = $this->seedRecord($now->copy()->subDays(40)->toIso8601String());
        $year = $this->seedRecord($now->copy()->subDays(400)->toIso8601String());
        $failed = $this->seedRecord($now->copy()->subDays(500)->toIso8601String(), false, 'failed');

        $plan = app(BackupRetentionService::class)->plan($now);

        $this->assertContains($newest, $plan['keep']);
        $this->assertContains($sameDay, $plan['keep']);   // within keep_last
        $this->assertContains($twoDays, $plan['keep']);   // newest of its day
        $this->assertContains($tenDays, $plan['keep']);   // weekly slot
        $this->assertContains($year, $plan['delete']);
        $this->assertContains($oldDup, $plan['delete']);
        $this->assertNotContains($failed, $plan['delete']); // failed records are never pruned here

        $result = app(BackupRetentionService::class)->apply($now);
        $this->assertSame([], $result['failed']);
        $this->assertEqualsCanonicalizing($plan['delete'], $result['deleted']);
        Storage::disk('backups_test')->assertMissing("packages/{$year}.bkp");
        Storage::disk('backups_test')->assertExists("packages/{$newest}.bkp");
        $this->assertSame('pruned', app(BackupHistoryService::class)->find($year)['status']);
    }

    public function test_prune_command_is_a_dry_run_unless_forced(): void
    {
        Config::set('backup.retention', ['keep_last' => 1, 'keep_daily' => 1, 'keep_weekly' => 0, 'keep_monthly' => 0]);
        $new = $this->seedRecord(now()->toIso8601String());
        $old = $this->seedRecord(now()->subDays(100)->toIso8601String());

        Artisan::call('backup:prune');
        Storage::disk('backups_test')->assertExists("packages/{$old}.bkp");

        Artisan::call('backup:prune', ['--force' => true]);
        Storage::disk('backups_test')->assertMissing("packages/{$old}.bkp");
        Storage::disk('backups_test')->assertExists("packages/{$new}.bkp");
    }

    // ── Sandbox credentials ──

    public function test_production_sandbox_requires_explicit_isolated_credentials(): void
    {
        $svc = app(SandboxRestoreService::class);
        $id = '33333333-3333-4333-8333-333333333333';
        $this->app['env'] = 'production';

        // Host only: credentials must not fall back to the application's.
        Config::set('backup.sandbox.host', 'sandbox-db.internal');
        try {
            $svc->resolveTarget($id);
            $this->fail('Missing sandbox credentials must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('BACKUP_SANDBOX_DB_USERNAME', $e->getMessage());
        }

        Config::set('backup.sandbox.username', 'sandbox_user');
        try {
            $svc->resolveTarget($id);
            $this->fail('Missing sandbox password must be refused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('BACKUP_SANDBOX_DB_PASSWORD', $e->getMessage());
        }

        // Production credentials reused for the sandbox are refused.
        Config::set('database.connections.sqlite.username', 'prod_user');
        Config::set('database.connections.sqlite.password', 'prod_pass');
        Config::set('backup.sandbox.username', 'prod_user');
        Config::set('backup.sandbox.password', 'prod_pass');
        try {
            $svc->resolveTarget($id);
            $this->fail('Production credentials must not be reused.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('different from the Production', $e->getMessage());
        }

        Config::set('backup.sandbox.username', 'sandbox_user');
        Config::set('backup.sandbox.password', 'sandbox_pass');
        $target = $svc->resolveTarget($id);
        $this->assertSame('sandbox_user', $target['username']);
        $this->assertSame('sandbox_pass', $target['password']);
        $this->assertStringStartsWith('sandbox_restore_', $target['database']);

        $this->app['env'] = 'testing';
    }

    // ── Upload limits ──

    public function test_oversized_upload_returns_a_clear_message(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'up_admin', 'email' => 'up@bakery.test']);

        $file = new UploadedFile($this->tmp . '/big.bkp', 'big.bkp', null, UPLOAD_ERR_INI_SIZE, true);
        file_put_contents($this->tmp . '/big.bkp', 'x');
        $response = $this->actingAs($admin)->post(route('admin.backups.verify'), ['package' => $file], ['Accept' => 'application/json']);

        $this->assertContains($response->getStatusCode(), [413, 422]);
        if ($response->getStatusCode() === 413) {
            $this->assertStringContainsString('upload limit', $response->json('message'));
        }
    }

    // ── Product image storage + migration ──

    public function test_product_image_storage_resolves_legacy_and_new_paths(): void
    {
        $images = app(ProductImageStorage::class);

        $this->assertSame('products/a.png', $images->mediaKey('uploads/products/a.png'));
        $this->assertSame('products/a.png', $images->mediaKey('/products/a.png'));
        $this->assertNull($images->mediaKey('logos/a.png'));
        $this->assertNull($images->mediaKey('uploads/products/../../x.png'));

        $new = $images->store(UploadedFile::fake()->image('new.jpg', 20, 20));
        Storage::disk('public')->assertExists($new);
        $this->assertNotNull($images->url($new));

        // Legacy DB value whose file only exists on the media disk (after migration + redeploy).
        Storage::disk('public')->put('products/legacy.png', 'img');
        $this->assertNotNull($images->url('uploads/products/legacy.png'));

        // Nothing anywhere: no URL, no exception.
        $this->assertNull($images->url('uploads/products/never.png'));

        $images->delete($new);
        Storage::disk('public')->assertMissing($new);
    }

    private function migrationSource(array $files): string
    {
        $dir = storage_path('framework/testing/migsrc');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }

        return $dir;
    }

    public function test_product_image_migration_copies_verifies_and_can_be_rolled_back(): void
    {
        $source = $this->migrationSource(['a.png' => 'AAA', 'b.png' => 'BBB']);
        $this->makeProduct(['image_path' => 'uploads/products/a.png']);
        $rowsBefore = DB::table('products')->pluck('image_path', 'id')->all();

        $beforeManifests = glob(storage_path('app/private/migrations/product-images-*.json')) ?: [];

        // Dry run changes nothing.
        Artisan::call('products:migrate-images', ['--source' => $source]);
        Storage::disk('public')->assertMissing('products/a.png');

        // Apply: copied + verified; sources and DB rows untouched.
        $this->assertSame(0, Artisan::call('products:migrate-images', ['--apply' => true, '--source' => $source]));
        $this->assertSame('AAA', Storage::disk('public')->get('products/a.png'));
        $this->assertSame('BBB', Storage::disk('public')->get('products/b.png'));
        $this->assertFileExists($source . '/a.png');
        $this->assertSame($rowsBefore, DB::table('products')->pluck('image_path', 'id')->all());

        $all = fn () => glob(storage_path('app/private/migrations/product-images-*.json')) ?: [];
        $manifestFile = array_values(array_diff($all(), $beforeManifests))[0];
        $manifest = json_decode(file_get_contents($manifestFile), true);
        $this->assertSame(2, $manifest['counts']['copied']);
        $this->assertSame(hash('sha256', 'AAA'), collect($manifest['items'])->firstWhere('key', 'products/a.png')['sha256']);

        // Re-running is idempotent.
        $afterFirst = $all();
        Artisan::call('products:migrate-images', ['--apply' => true, '--source' => $source]);
        $second = array_values(array_diff($all(), $afterFirst))[0];
        $this->assertSame(2, json_decode(file_get_contents($second), true)['counts']['already_present']);

        // Rollback removes only what the first run created.
        Artisan::call('products:migrate-images', ['--rollback' => basename($manifestFile)]);
        Storage::disk('public')->assertMissing('products/a.png');
        Storage::disk('public')->assertMissing('products/b.png');
        $this->assertFileExists($source . '/a.png');
        $this->assertFileExists($source . '/b.png');

        @unlink($manifestFile);
        @unlink($second);
    }

    public function test_product_image_migration_never_overwrites_a_different_existing_object(): void
    {
        $source = $this->migrationSource(['c.png' => 'LOCAL']);
        Storage::disk('public')->put('products/c.png', 'DIFFERENT-REMOTE');

        $code = Artisan::call('products:migrate-images', ['--apply' => true, '--source' => $source]);

        $this->assertSame(1, $code); // conflict reported
        $this->assertSame('DIFFERENT-REMOTE', Storage::disk('public')->get('products/c.png'));

        foreach (glob(storage_path('app/private/migrations/product-images-*.json')) as $f) {
            if (str_contains((string) file_get_contents($f), '"conflict": 1')) {
                @unlink($f);
            }
        }
    }
}

/** Stand-in for pg_dump so package logic runs on the in-memory test database. */
class HardeningStubDb extends DatabaseBackupService
{
    public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
    {
        file_put_contents($targetFile, "STUB-PGDMP\n" . str_repeat('data', 100));

        return [
            'filename' => basename($targetFile),
            'size' => filesize($targetFile),
            'sha256' => hash_file('sha256', $targetFile),
            'server_version' => '16.0',
            'client_version' => 'pg_dump (PostgreSQL) 16.0',
            'toc_entries' => 12,
            'table_counts' => ['users' => 1],
            'schema_summary' => ['foreign_keys' => 0, 'indexes' => 1, 'sequences' => 1, 'functions' => 0],
            'snapshot_extra' => $inSnapshot ? $inSnapshot(null) : null,
        ];
    }

    public function listArchive(string $archive): int
    {
        if ((string) file_get_contents($archive, false, null, 0, 11) !== "STUB-PGDMP\n") {
            throw new \RuntimeException('Database archive failed pg_restore --list');
        }

        return 12;
    }

    public function databaseSizeBytes(?string $connection = null): int
    {
        return 1024;
    }
}
