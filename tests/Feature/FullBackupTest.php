<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupPackageService;
use App\Services\Backup\BackupRedactor;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\MediaBackupService;
use App\Services\Backup\ProductionRestoreGate;
use App\Services\Backup\SandboxRestoreService;
use App\Services\Backup\SettingsBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

/**
 * Full Backup & Restore tests. They use fake disks and a stub database service so they run
 * on the in-memory test database. Real PostgreSQL tooling is covered by the guarded
 * integration test at the bottom (set BACKUP_TEST_PG=1 with a local PostgreSQL server).
 */
class FullBackupTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.settings_disk', 'public');
        Config::set('backup.disk', 'backups_test');
        Config::set('backup.temp_dir', storage_path('framework/testing/backup-tmp'));
        Config::set('filesystems.disks.backups_test', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backups_test'), 'visibility' => 'private', 'throw' => true]);
        Storage::fake('public');
        Storage::fake('backups_test');

        $this->admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'bk_admin', 'email' => 'bk_admin@bakery.test']);

        // Stub the PostgreSQL tooling: the unit under test is the package/verification logic.
        $this->app->instance(DatabaseBackupService::class, new StubDatabaseBackupService());
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\File::deleteDirectory(storage_path('framework/testing/backup-tmp'));
        parent::tearDown();
    }

    private function seedMedia(): void
    {
        Storage::disk('public')->put('logos/logo.png', 'LOGO-BYTES');
        Storage::disk('public')->put('avatars/me.jpg', 'AVATAR-BYTES');
        Storage::disk('public')->put('products/cake.webp', 'CAKE-BYTES');
        Storage::disk('public')->put('categories/bread.png', 'BREAD');
        Storage::disk('public')->put('backups/old.json', 'NOT-MEDIA');
        Storage::disk('public')->put('unrelated/secret.txt', 'OUT-OF-SCOPE');
    }

    private function makePackage(): array
    {
        $this->seedMedia();
        Setting::create(['key' => 'shop_name', 'value' => 'Sweet Test Bakery']);
        Setting::create(['key' => 'api_secret_key', 'value' => 'SHOULD-NOT-LEAK']);

        $id = app(BackupHistoryService::class)->newId();

        return app(BackupPackageService::class)->create($id, ['user_label' => 'tester']);
    }

    /** Local copy of the stored package for tampering tests. */
    private function localCopy(string $id): string
    {
        $dir = BackupWorkspace::make('test');
        $path = $dir . DIRECTORY_SEPARATOR . 'p.bkp';
        file_put_contents($path, Storage::disk('backups_test')->get("packages/{$id}.bkp"));

        return $path;
    }

    // ── Encryption ──

    public function test_encryption_round_trip_and_tamper_detection(): void
    {
        $enc = app(BackupEncryptionService::class);
        $dir = BackupWorkspace::make('enc');
        $plain = $dir . '/plain.bin';
        file_put_contents($plain, random_bytes(2_500_000)); // multiple 1 MB chunks

        $cipher = $dir . '/c.bkp';
        $enc->encryptFile($plain, $cipher);
        $this->assertTrue($enc->isEncrypted($cipher));
        $this->assertStringNotContainsString(substr(file_get_contents($plain), 0, 32), file_get_contents($cipher));

        $out = $dir . '/out.bin';
        $enc->decryptFile($cipher, $out);
        $this->assertSame(hash_file('sha256', $plain), hash_file('sha256', $out));

        $bytes = file_get_contents($cipher);
        $bytes[strlen($bytes) - 10] = $bytes[strlen($bytes) - 10] ^ "\x01";
        file_put_contents($dir . '/t.bkp', $bytes);
        $this->expectException(\RuntimeException::class);
        $enc->decryptFile($dir . '/t.bkp', $dir . '/t.out');
    }

    public function test_secrets_are_redacted_from_logs(): void
    {
        $text = 'pg_dump failed: postgresql://bakery:S3cr3tPass@db.example.com:5432/app PGPASSWORD=hunter2 password=abc123 AKIAABCDEFGHIJKLMNOP';
        $redacted = BackupRedactor::redact($text);

        foreach (['S3cr3tPass', 'hunter2', 'abc123', 'AKIAABCDEFGHIJKLMNOP'] as $secret) {
            $this->assertStringNotContainsString($secret, $redacted);
        }
        $this->assertStringContainsString('db.example.com', $redacted);
    }

    // ── Settings ──

    public function test_settings_export_excludes_sensitive_fields_and_validates(): void
    {
        Setting::create(['key' => 'shop_name', 'value' => 'Bakery']);
        Setting::create(['key' => 'admin_password_hash', 'value' => 'x']);
        Setting::create(['key' => 'supabase_access_key', 'value' => 'y']);
        Setting::create(['key' => 'tax_rate', 'value' => '10']);

        $svc = app(SettingsBackupService::class);
        $export = $svc->export();

        $this->assertSame('Bakery', $export['settings']['shop_name']);
        $this->assertSame('10', $export['settings']['tax_rate']);
        $this->assertArrayNotHasKey('admin_password_hash', $export['settings']);
        $this->assertArrayNotHasKey('supabase_access_key', $export['settings']);
        $this->assertSame(2, $export['excluded_count']);
        $this->assertSame([], $svc->validate($export));

        $bad = $export;
        $bad['settings']['db_password'] = 'nope';
        $this->assertNotEmpty($svc->validate($bad));
        $this->assertNotEmpty($svc->validate(['schema' => 'other', 'settings' => []]));
        $this->assertNotEmpty($svc->validate('not-an-array'));
    }

    // ── Media ──

    public function test_media_backup_enumerates_only_approved_scope_with_checksums(): void
    {
        $this->seedMedia();
        $dir = BackupWorkspace::make('media');

        $result = app(MediaBackupService::class)->backup($dir);

        $paths = array_column($result['objects'], 'path');
        sort($paths);
        $this->assertSame(['avatars/me.jpg', 'categories/bread.png', 'logos/logo.png', 'products/cake.webp'], $paths);
        $this->assertSame(4, $result['count']);

        $logo = collect($result['objects'])->firstWhere('path', 'logos/logo.png');
        $this->assertSame(hash('sha256', 'LOGO-BYTES'), $logo['sha256']);
        $this->assertSame(strlen('LOGO-BYTES'), $logo['size']);
        $this->assertSame('LOGO-BYTES', file_get_contents($dir . '/logos/logo.png'));
        $this->assertFileDoesNotExist($dir . '/backups/old.json');
        $this->assertFileDoesNotExist($dir . '/unrelated/secret.txt');

        // Source disk is untouched.
        Storage::disk('public')->assertExists('logos/logo.png');
        Storage::disk('public')->assertExists('unrelated/secret.txt');
    }

    public function test_media_restore_to_sandbox_verifies_checksums_and_rejects_unsafe_paths(): void
    {
        $this->seedMedia();
        $stage = BackupWorkspace::make('stage');
        $svc = app(MediaBackupService::class);
        $result = $svc->backup($stage);

        $dest = BackupWorkspace::make('dest');
        $restored = $svc->restoreTo($result['objects'], $stage, $dest);
        $this->assertSame(4, $restored['restored']);
        $this->assertSame([], $restored['mismatches']);
        $this->assertSame('CAKE-BYTES', file_get_contents($dest . '/products/cake.webp'));

        file_put_contents($stage . '/logos/logo.png', 'TAMPERED');
        $again = $svc->restoreTo($result['objects'], $stage, BackupWorkspace::make('dest2'));
        $this->assertNotEmpty($again['mismatches']);

        $this->expectException(\RuntimeException::class);
        MediaBackupService::assertSafeRelativePath('logos/../../etc/passwd');
    }

    // ── Package creation and verification ──

    public function test_full_package_is_created_encrypted_verified_and_recorded(): void
    {
        $record = $this->makePackage();

        $this->assertSame('completed', $record['status']);
        $this->assertTrue($record['verified']);
        $this->assertSame(4, $record['media_count']);
        $this->assertSame('Private server storage (local)', $record['destination']);
        $this->assertSame(['database', 'media', 'settings', 'package', 'verification'], array_keys($record['components']));
        foreach ($record['components'] as $component) {
            $this->assertSame('completed', $component['status']);
        }

        Storage::disk('backups_test')->assertExists('packages/' . $record['id'] . '.bkp');
        Storage::disk('backups_test')->assertExists('history/' . $record['id'] . '.json');
        $this->assertStringStartsWith(BackupEncryptionService::MAGIC, Storage::disk('backups_test')->get('packages/' . $record['id'] . '.bkp'));

        // Verify it independently and inspect the contents for secrets.
        $verify = app(BackupVerificationService::class)->verifyFile($this->localCopy($record['id']));
        $this->assertTrue($verify['ok'], json_encode($verify['steps']));

        $manifest = $verify['manifest'];
        $this->assertSame('1.0', $manifest['package_format_version']);
        $this->assertSame('sha256', $manifest['checksum_algorithm']);
        $this->assertSame(4, $manifest['media']['count']);
        $this->assertSame(['logos/', 'avatars/', 'products/', 'categories/', 'receipts/'], $manifest['media']['prefixes']);

        $settings = json_decode(file_get_contents($verify['extracted_dir'] . '/settings/settings.json'), true);
        $this->assertSame('Sweet Test Bakery', $settings['settings']['shop_name']);
        $this->assertArrayNotHasKey('api_secret_key', $settings['settings']);

        foreach (['manifest.json', 'backup_metadata.json', 'checksums.sha256'] as $file) {
            $content = file_get_contents($verify['extracted_dir'] . '/' . $file);
            $this->assertStringNotContainsString('SHOULD-NOT-LEAK', $content);
            $this->assertStringNotContainsString(config('app.key'), $content);
        }
        BackupWorkspace::destroy($verify['work_dir']);
    }

    public function test_tampered_encrypted_package_is_rejected(): void
    {
        $record = $this->makePackage();
        $path = $this->localCopy($record['id']);

        $bytes = file_get_contents($path);
        $bytes[200] = $bytes[200] ^ "\x01";
        file_put_contents($path, $bytes);

        $verify = app(BackupVerificationService::class)->verifyFile($path);
        $this->assertFalse($verify['ok']);
    }

    public function test_corrupted_media_checksum_is_rejected(): void
    {
        $record = $this->makePackage();
        $tampered = $this->repack($record['id'], function (ZipArchive $zip) {
            $zip->addFromString('media/logos/logo.png', 'EVIL-CONTENT');
        });

        $verify = app(BackupVerificationService::class)->verifyFile($tampered);
        $this->assertFalse($verify['ok']);
        $this->assertStringContainsString('Checksum mismatch', collect($verify['steps'])->pluck('message')->implode(' '));
    }

    public function test_path_traversal_entry_is_rejected(): void
    {
        $record = $this->makePackage();
        $tampered = $this->repack($record['id'], function (ZipArchive $zip) {
            $zip->addFromString('media/../../evil.php', '<?php echo 1;');
        });

        $verify = app(BackupVerificationService::class)->verifyFile($tampered);
        $this->assertFalse($verify['ok']);
        $this->assertStringContainsString('Unsafe path', collect($verify['steps'])->pluck('message')->implode(' '));
        $this->assertFileDoesNotExist(storage_path('framework/testing/evil.php'));
    }

    public function test_unexpected_files_and_missing_components_are_rejected(): void
    {
        $record = $this->makePackage();

        $extra = $this->repack($record['id'], fn (ZipArchive $zip) => $zip->addFromString('shell.php', 'x'));
        $this->assertFalse(app(BackupVerificationService::class)->verifyFile($extra)['ok']);

        $noDb = $this->repack($record['id'], fn (ZipArchive $zip) => $zip->deleteName('database/bakery_database.dump'));
        $result = app(BackupVerificationService::class)->verifyFile($noDb);
        $this->assertFalse($result['ok']);

        $unsupported = $this->repack($record['id'], function (ZipArchive $zip) {
            $manifest = json_decode($zip->getFromName('manifest.json'), true);
            $manifest['package_format_version'] = '9.9';
            $zip->addFromString('manifest.json', json_encode($manifest));
        });
        $this->assertFalse(app(BackupVerificationService::class)->verifyFile($unsupported)['ok']);
    }

    public function test_plain_zip_and_garbage_uploads_are_rejected(): void
    {
        $dir = BackupWorkspace::make('plain');
        file_put_contents($dir . '/x.bkp', 'just some text');
        $this->assertFalse(app(BackupVerificationService::class)->verifyFile($dir . '/x.bkp')['ok']);

        $zip = new ZipArchive();
        $zip->open($dir . '/y.zip', ZipArchive::CREATE);
        $zip->addFromString('manifest.json', '{}');
        $zip->close();
        $this->assertFalse(app(BackupVerificationService::class)->verifyFile($dir . '/y.zip')['ok']);
    }

    /** Decrypt a stored package, let $mutate change the zip, re-encrypt, return the new local path. */
    private function repack(string $id, callable $mutate): string
    {
        $enc = app(BackupEncryptionService::class);
        $dir = BackupWorkspace::make('repack');
        $enc->decryptFile($this->localCopy($id), $dir . '/p.zip');

        $zip = new ZipArchive();
        $zip->open($dir . '/p.zip');
        $mutate($zip);
        $zip->close();

        $enc->encryptFile($dir . '/p.zip', $dir . '/out.bkp');

        return $dir . '/out.bkp';
    }

    // ── Authorization & endpoints ──

    public function test_non_admin_users_and_guests_cannot_use_backup_endpoints(): void
    {
        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'username' => 'bk_mgr', 'email' => 'bk_mgr@bakery.test']);
        $cashier = User::factory()->create(['role' => 'cashier', 'status' => 'active', 'username' => 'bk_cash', 'email' => 'bk_cash@bakery.test']);
        $this->withoutMiddleware(ThrottleRequests::class);
        $uuid = '11111111-1111-4111-8111-111111111111';

        $requests = [
            ['get', route('admin.backups.index')],
            ['post', route('admin.backups.store')],
            ['post', route('admin.backups.verify')],
            ['get', route('admin.backups.status', $uuid)],
            ['post', route('admin.backups.sandboxRestore', $uuid)],
            ['post', route('admin.backups.productionRestore')],
            ['get', route('admin.backups.productionGate')],
        ];

        foreach ([$manager, $cashier] as $user) {
            foreach ($requests as [$method, $url]) {
                // The role middleware either answers 403 or redirects away; never 2xx.
                $status = $this->actingAs($user)->{$method}($url)->getStatusCode();
                $this->assertContains($status, [302, 403], "{$method} {$url} returned {$status}");
            }
        }
        foreach ($requests as [$method, $url]) {
            $this->{$method}($url)->assertRedirect();
        }
    }

    public function test_admin_can_start_a_backup_and_history_shows_real_records_only(): void
    {
        $this->actingAs($this->admin)->getJson(route('admin.backups.index'))
            ->assertOk()->assertJson(['success' => true, 'backups' => []]);

        Bus::fake();
        $response = $this->actingAs($this->admin)->postJson(route('admin.backups.store'));
        $response->assertStatus(202)->assertJsonStructure(['success', 'backup_id']);

        $record = $this->makePackage();
        $list = $this->actingAs($this->admin)->getJson(route('admin.backups.index'))->assertOk()->json('backups');
        $this->assertCount(1, $list);
        $this->assertSame($record['id'], $list[0]['id']);
        $this->assertSame(4, $list[0]['media_count']);
        $this->assertNotNull($list[0]['download_url']);
        $this->assertStringContainsString('signature=', $list[0]['download_url']);
        $this->assertArrayNotHasKey('verification_steps', $list[0]);
    }

    public function test_download_requires_a_valid_signature_and_admin(): void
    {
        $record = $this->makePackage();

        $unsigned = $this->actingAs($this->admin)->get(route('admin.backups.download', $record['id']));
        $this->assertContains($unsigned->getStatusCode(), [302, 403]);

        $signed = URL::temporarySignedRoute('admin.backups.download', now()->addMinutes(5), ['id' => $record['id']]);
        $this->actingAs($this->admin)->get($signed)->assertOk();

        $expired = URL::temporarySignedRoute('admin.backups.download', now()->subMinute(), ['id' => $record['id']]);
        $this->assertContains($this->actingAs($this->admin)->get($expired)->getStatusCode(), [302, 403]);

        $manager = User::factory()->create(['role' => 'manager', 'status' => 'active', 'username' => 'bk_m2', 'email' => 'bk_m2@bakery.test']);
        $this->assertContains($this->actingAs($manager)->get($signed)->getStatusCode(), [302, 403]);
    }

    public function test_verify_endpoint_accepts_valid_package_and_rejects_invalid_ones(): void
    {
        $record = $this->makePackage();
        $local = $this->localCopy($record['id']);

        // Existing backup from history
        $this->actingAs($this->admin)->postJson(route('admin.backups.verify'), ['backup_id' => $record['id']])
            ->assertOk()->assertJson(['ok' => true])->assertJsonPath('preview.media_count', 4);

        // Uploaded package
        $upload = new UploadedFile($local, 'backup.bkp', null, null, true);
        $this->actingAs($this->admin)->post(route('admin.backups.verify'), ['package' => $upload], ['Accept' => 'application/json'])
            ->assertOk()->assertJson(['ok' => true]);

        // Not a package
        $junk = UploadedFile::fake()->createWithContent('evil.bkp', 'not a backup');
        $this->actingAs($this->admin)->post(route('admin.backups.verify'), ['package' => $junk], ['Accept' => 'application/json'])
            ->assertStatus(422);

        // Missing input
        $this->actingAs($this->admin)->postJson(route('admin.backups.verify'), [])->assertStatus(422);
        $this->actingAs($this->admin)->postJson(route('admin.backups.verify'), ['backup_id' => 'not-a-uuid'])->assertStatus(422);
    }

    // ── Restore safety ──

    public function test_production_restore_is_always_rejected(): void
    {
        $record = $this->makePackage();

        $response = $this->actingAs($this->admin)->postJson(route('admin.backups.productionRestore'), [
            'backup_id' => $record['id'],
            'confirm' => true,
            'owner_approval' => 'yes',
        ]);
        $response->assertForbidden()->assertJson(['success' => false]);
        $this->assertNotEmpty($response->json('conditions'));

        $gate = app(ProductionRestoreGate::class)->evaluate($record['id']);
        $this->assertFalse($gate['allowed']);
        $this->assertFalse($gate['executable']);

        // Even with the feature flag on, there is no executor and the gate stays closed.
        Config::set('backup.production_restore_enabled', true);
        $gate = app(ProductionRestoreGate::class)->evaluate($record['id']);
        $this->assertFalse($gate['allowed']);
        $this->assertFalse(method_exists(ProductionRestoreGate::class, 'execute'));
    }

    public function test_sandbox_target_is_isolated_from_the_application_database(): void
    {
        $svc = app(SandboxRestoreService::class);

        // Production environment without a dedicated sandbox server is refused.
        $this->app['env'] = 'production';
        try {
            $svc->resolveTarget('11111111-1111-4111-8111-111111111111');
            $this->fail('Sandbox target must be refused in production without a sandbox server.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('isolated sandbox', $e->getMessage());
        }

        // With an explicit separate server, the database name still carries the sandbox prefix.
        Config::set('backup.sandbox.host', 'sandbox-db.internal');
        Config::set('backup.sandbox.username', 'sandbox_user');
        Config::set('backup.sandbox.password', 'sandbox_pass');
        $target = $svc->resolveTarget('11111111-1111-4111-8111-111111111111');
        $this->assertStringStartsWith('sandbox_restore_', $target['database']);
        $this->assertSame('sandbox-db.internal', $target['host']);
        $this->assertStringContainsString('sandbox-restore', $target['media_root']);

        $this->app['env'] = 'testing';
    }

    public function test_unverified_or_missing_backups_cannot_be_rehearsed(): void
    {
        $svc = app(SandboxRestoreService::class);

        $missing = $svc->rehearse('22222222-2222-4222-8222-222222222222');
        $this->assertFalse($missing['ok']);

        $history = app(BackupHistoryService::class);
        $id = $history->newId();
        $history->save(['id' => $id, 'type' => 'full', 'created_at' => now()->toIso8601String(), 'status' => 'completed', 'verified' => false, 'components' => []]);
        Storage::disk('backups_test')->put("packages/{$id}.bkp", 'x');
        $unverified = $svc->rehearse($id);
        $this->assertFalse($unverified['ok']);
        $this->assertStringContainsString('verified backup', json_encode($unverified['steps']));
    }

    public function test_failed_backup_is_recorded_without_leaking_secrets_and_leaves_no_package(): void
    {
        $this->app->instance(DatabaseBackupService::class, new class extends StubDatabaseBackupService {
            public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
            {
                throw new \RuntimeException('pg_dump failed: postgresql://bakery:TopSecret99@db.prod.example:5432/app');
            }
        });

        $id = app(BackupHistoryService::class)->newId();
        $record = app(BackupPackageService::class)->create($id);

        $this->assertSame('failed', $record['status']);
        $this->assertFalse($record['verified']);
        $this->assertStringNotContainsString('TopSecret99', json_encode($record));
        Storage::disk('backups_test')->assertMissing("packages/{$id}.bkp");
        $this->assertSame('failed', $record['components']['database']['status']);
        $this->assertSame([], glob(storage_path('framework/testing/backup-tmp') . '/backup_*') ?: []);
    }

    public function test_history_ids_must_be_uuids(): void
    {
        $this->expectException(\RuntimeException::class);
        app(BackupHistoryService::class)->packagePath('../../etc/passwd');
    }

    // ── Guarded real-PostgreSQL integration test ──

    public function test_real_postgres_dump_verify_and_restore_rehearsal(): void
    {
        if (!getenv('BACKUP_TEST_PG')) {
            $this->markTestSkipped('Set BACKUP_TEST_PG=1 with a local PostgreSQL server to run this test.');
        }

        $base = config('database.connections.pgsql');
        $name = 'bk_it_' . bin2hex(random_bytes(4));
        $admin = array_replace($base, ['database' => 'postgres']);
        Config::set('database.connections.pg_it_admin', $admin);

        try {
            DB::connection('pg_it_admin')->statement('CREATE DATABASE "' . $name . '"');
        } catch (\Throwable $e) {
            $this->markTestSkipped('Local PostgreSQL is not reachable.');
        }

        try {
            Config::set('database.connections.pg_it', array_replace($base, ['database' => $name]));
            $db = DB::connection('pg_it');
            $db->statement('create table parents (id serial primary key, name text not null)');
            $db->statement('create table children (id serial primary key, parent_id int not null references parents(id), note text)');
            $db->statement('create index children_note_idx on children (note)');
            $db->table('parents')->insert([['name' => 'a'], ['name' => 'b']]);
            $db->table('children')->insert([['parent_id' => 1, 'note' => 'x'], ['parent_id' => 2, 'note' => 'y'], ['parent_id' => 2, 'note' => 'z']]);

            $svc = new DatabaseBackupService();
            $dir = BackupWorkspace::make('pgit');
            $result = $svc->dump($dir . '/t.dump', 'pg_it');

            $this->assertSame(['children' => 3, 'parents' => 2], $result['table_counts']);
            $this->assertSame(1, $result['schema_summary']['foreign_keys']);
            $this->assertGreaterThan(0, $result['toc_entries']);
            $this->assertSame(64, strlen($result['sha256']));
            $this->assertGreaterThan(0, $svc->listArchive($dir . '/t.dump'));

            // Corrupted archive is rejected by pg_restore --list
            file_put_contents($dir . '/bad.dump', 'PGDMP-garbage');
            try {
                $svc->listArchive($dir . '/bad.dump');
                $this->fail('A corrupted archive must be rejected.');
            } catch (\RuntimeException $e) {
                $this->assertStringNotContainsString((string) ($base['password'] ?? 'no-password-set'), $e->getMessage());
            }

            // Restore into a separate sandbox database and compare
            $sandbox = $name . '_sb';
            DB::connection('pg_it_admin')->statement('CREATE DATABASE "' . $sandbox . '"');
            $target = array_replace($base, ['database' => $sandbox]);
            Config::set('database.connections.pg_it_sb', $target);
            $svc->restoreInto($dir . '/t.dump', $target);

            $this->assertSame($result['table_counts'], $svc->tableCounts('pg_it_sb'));
            $this->assertSame($result['schema_summary'], $svc->schemaSummary('pg_it_sb'));
            // Foreign key still enforced after restore
            $this->expectException(\Illuminate\Database\QueryException::class);
            DB::connection('pg_it_sb')->table('children')->insert(['parent_id' => 999, 'note' => 'orphan']);
        } finally {
            DB::purge('pg_it');
            DB::purge('pg_it_sb');
            foreach ([$name, $name . '_sb'] as $d) {
                try {
                    DB::connection('pg_it_admin')->statement('DROP DATABASE IF EXISTS "' . $d . '" WITH (FORCE)');
                } catch (\Throwable) {
                }
            }
        }
    }
}

/** Stand-in for pg_dump: writes a deterministic "archive" and reports plausible metadata. */
class StubDatabaseBackupService extends DatabaseBackupService
{
    public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
    {
        file_put_contents($targetFile, "STUB-PGDMP\n" . str_repeat('data', 200));

        return [
            'filename' => basename($targetFile),
            'size' => filesize($targetFile),
            'sha256' => hash_file('sha256', $targetFile),
            'server_version' => '16.0',
            'client_version' => 'pg_dump (PostgreSQL) 16.0',
            'toc_entries' => 12,
            'table_counts' => ['users' => 1, 'settings' => 2],
            'schema_summary' => ['foreign_keys' => 1, 'indexes' => 2, 'sequences' => 1, 'functions' => 0],
            'snapshot_extra' => $inSnapshot ? $inSnapshot(null) : null,
        ];
    }

    public function listArchive(string $archive): int
    {
        $head = (string) file_get_contents($archive, false, null, 0, 11);
        if ($head !== "STUB-PGDMP\n") {
            throw new \RuntimeException('Database archive failed pg_restore --list');
        }

        return 12;
    }
}
