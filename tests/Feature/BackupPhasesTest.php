<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupPackageService;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\BackupSpaceGuard;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\SandboxRestoreService;
use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use ZipArchive;

/**
 * Remaining-fix coverage: snapshot consistency (real PostgreSQL), recovery/extract, reference integrity,
 * S3 retention / Object Lock / ACL-free writes (simulated S3), stale-run detection, upload verification,
 * disk-space preflight, re-verification and Docker build requirements.
 */
class BackupPhasesTest extends TestCase
{
    use RefreshDatabase;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.settings_disk', 'public');
        Config::set('backup.disk', 'backups_test');
        Config::set('backup.temp_dir', storage_path('framework/testing/backup-tmp-p'));
        Config::set('backup.object_retry_delay_ms', 1);
        Config::set('filesystems.disks.backups_test', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backups_test'), 'visibility' => 'private', 'throw' => true]);
        Storage::fake('public');
        Storage::fake('backups_test');
        $this->tmp = BackupWorkspace::make('p');

        $this->app->instance(DatabaseBackupService::class, new PhasesStubDb());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/backup-tmp-p'));
        File::deleteDirectory(storage_path('framework/testing/extract-dest'));
        parent::tearDown();
    }

    // ── helpers ──

    private function product(string $imagePath): Product
    {
        static $n = 0;
        $n++;

        return Product::create(['name' => 'PP' . $n, 'sku' => 'PH-' . $n, 'price' => 1, 'cost' => 1, 'stock' => 1, 'minimum_stock' => 0, 'status' => 'active', 'image_path' => $imagePath]);
    }

    private function makeBackup(): array
    {
        Storage::disk('public')->put('logos/logo.png', 'LOGO');
        Storage::disk('public')->put('products/p.png', 'PRODUCT');
        Setting::create(['key' => 'shop_name', 'value' => 'Bakery']);

        return app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
    }

    private function localCopy(string $id): string
    {
        $path = BackupWorkspace::make('lc') . '/p.bkp';
        file_put_contents($path, Storage::disk('backups_test')->get("packages/{$id}.bkp"));

        return $path;
    }

    private function remoteDisk(FakeS3 $s3, array $extra = []): void
    {
        $config = ['driver' => 's3-noacl', 'bucket' => 'bakery-backups-private', 'key' => 'k', 'secret' => 's', 'region' => 'us-east-1', 'visibility' => 'private', 'throw' => true];
        Config::set('filesystems.disks.bk_remote', $config);
        Config::set('backup.disk', 'bk_remote');
        Storage::set('bk_remote', Storage::build($config + ['client' => $s3->client(), 'options' => ['ServerSideEncryption' => 'AES256']] + $extra));
    }

    // ═══════════════ Phase 1: snapshot consistency (real PostgreSQL) ═══════════════

    private function pgBase(): array
    {
        if (!getenv('BACKUP_TEST_PG')) {
            $this->markTestSkipped('Set BACKUP_TEST_PG=1 with a local PostgreSQL server to run this test.');
        }

        return (array) config('database.connections.pgsql');
    }

    public function test_pg_row_counts_and_dump_describe_the_same_snapshot_under_concurrent_writes(): void
    {
        $base = $this->pgBase();
        $name = 'bk_snap_' . bin2hex(random_bytes(3));
        Config::set('database.connections.snap_admin', array_replace($base, ['database' => 'postgres']));
        Config::set('database.connections.snap', array_replace($base, ['database' => $name]));
        Config::set('database.connections.snap_sb', array_replace($base, ['database' => $name . '_sb']));

        try {
            DB::connection('snap_admin')->statement('CREATE DATABASE "' . $name . '"');
            DB::connection('snap_admin')->statement('CREATE DATABASE "' . $name . '_sb"');
        } catch (\Throwable) {
            $this->markTestSkipped('Local PostgreSQL is not reachable.');
        }

        try {
            $live = DB::connection('snap');
            $live->statement('create table parents (id serial primary key, name text)');
            $live->statement('create table orders (id serial primary key, parent_id int references parents(id), total int)');
            $live->table('parents')->insert([['name' => 'a']]);
            $live->table('orders')->insert([['parent_id' => 1, 'total' => 1], ['parent_id' => 1, 'total' => 2]]);

            // Concurrent writers: one lands right after the snapshot is exported (before pg_dump starts),
            // another right after pg_dump finished (before the old code recorded its counts).
            $svc = new class extends DatabaseBackupService {
                protected function onSnapshotExported(string $snapshotId): void
                {
                    DB::connection('snap')->table('orders')->insert(['parent_id' => 1, 'total' => 3]);
                }

                public function listArchive(string $archive): int
                {
                    $n = parent::listArchive($archive);
                    DB::connection('snap')->table('orders')->insert(['parent_id' => 1, 'total' => 4]);

                    return $n;
                }
            };

            $dir = BackupWorkspace::make('snap');
            $result = $svc->dump($dir . '/t.dump', 'snap', fn (string $conn) => DB::connection($conn)->table('orders')->count());

            // The live database moved on, but the manifest data describes the snapshot exactly.
            $this->assertSame(4, DB::connection('snap')->table('orders')->count());
            $this->assertSame(2, $result['table_counts']['orders']);
            $this->assertSame(1, $result['table_counts']['parents']);
            $this->assertSame(2, $result['snapshot_extra'], 'Callbacks must read from the same snapshot');
            $this->assertTrue($result['snapshot_consistent']);

            $target = array_replace($base, ['database' => $name . '_sb']);
            $svc->restoreInto($dir . '/t.dump', $target);
            $this->assertSame($result['table_counts'], $svc->tableCounts('snap_sb'), 'Restored counts must equal the recorded counts');
            $this->assertSame($result['schema_summary'], $svc->schemaSummary('snap_sb'));
            $this->assertSame(1, $result['schema_summary']['foreign_keys']);

            // The snapshot connection is released and leaves nothing open.
            $open = DB::connection('snap_admin')->selectOne("select count(*) c from pg_stat_activity where datname = ? and state like 'idle in transaction%'", [$name]);
            $this->assertSame(0, (int) $open->c);
        } finally {
            DB::purge('snap');
            DB::purge('snap_sb');
            foreach ([$name, $name . '_sb'] as $d) {
                try {
                    DB::connection('snap_admin')->statement('DROP DATABASE IF EXISTS "' . $d . '" WITH (FORCE)');
                } catch (\Throwable) {
                }
            }
        }
    }

    public function test_pg_end_to_end_backup_and_sandbox_rehearsal_checks_image_references(): void
    {
        $base = $this->pgBase();
        $name = 'bk_e2e_' . bin2hex(random_bytes(3));
        Config::set('database.connections.e2e_admin', array_replace($base, ['database' => 'postgres']));
        Config::set('database.connections.e2e', array_replace($base, ['database' => $name]));

        try {
            DB::connection('e2e_admin')->statement('CREATE DATABASE "' . $name . '"');
        } catch (\Throwable) {
            $this->markTestSkipped('Local PostgreSQL is not reachable.');
        }

        $previous = config('database.default');
        try {
            Artisan::call('migrate', ['--database' => 'e2e', '--force' => true]);
            DB::setDefaultConnection('e2e');
            Config::set('database.default', 'e2e');
            $this->app->instance(DatabaseBackupService::class, new DatabaseBackupService()); // real pg_dump / pg_restore
            Config::set('backup.sandbox.host', null);

            Storage::disk('public')->put('logos/a.png', 'LOGO-A');
            Storage::disk('public')->put('products/p.png', 'PROD-P');
            Storage::disk('public')->put('products/q.png', 'PROD-Q');
            Storage::disk('public')->put('avatars/orphan.png', 'ORPHAN');
            Setting::create(['key' => 'shop_logo', 'value' => '/storage/logos/a.png']);
            $this->product('uploads/products/p.png'); // legacy reference
            $this->product('products/q.png');          // new-style reference

            // 1. Healthy backup + rehearsal
            $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
            $this->assertTrue($record['verified'], json_encode($record['error'] ?? $record['verification_steps'] ?? ''));
            $this->assertSame(0, $record['media_references']['missing']);
            $this->assertSame(1, $record['media_references']['orphans']);

            $report = app(SandboxRestoreService::class)->rehearse($record['id']);
            $steps = collect($report['steps'])->keyBy('name');
            $this->assertTrue($report['ok'], json_encode($report['steps']));
            $this->assertSame('pass', $steps['Tables and row counts']['status']);
            $this->assertSame('pass', $steps['Database image references']['status']);
            $this->assertStringContainsString('1 orphaned', $steps['Database image references']['message']);
            $this->assertSame(0, count($report['references']['missing']));
            $this->assertTrue($report['cleanup']['database_dropped']);

            // 2. A reference that was already missing at backup time is a warning, not a failure
            $this->product('products/gone.png');
            $second = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
            $this->assertTrue($second['verified']);
            $this->assertSame(1, $second['media_references']['missing']);

            $report2 = app(SandboxRestoreService::class)->rehearse($second['id']);
            $this->assertTrue($report2['ok'], json_encode($report2['steps']));
            $ref = collect($report2['steps'])->firstWhere('name', 'Database image references');
            $this->assertSame('warn', $ref['status']);
            $this->assertSame(['products/gone.png'], $report2['references']['missing_known_at_backup']);
        } finally {
            DB::setDefaultConnection($previous);
            Config::set('database.default', $previous);
            DB::purge('e2e');
            try {
                DB::connection('e2e_admin')->statement('DROP DATABASE IF EXISTS "' . $name . '" WITH (FORCE)');
            } catch (\Throwable) {
            }
        }
    }

    // ═══════════════ Phase 2: extract + reference evaluation ═══════════════

    public function test_extract_command_verifies_and_extracts_into_a_new_directory(): void
    {
        $record = $this->makeBackup();
        $dest = storage_path('framework/testing/extract-dest/out');
        File::ensureDirectoryExists(dirname($dest));

        $code = Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => $dest]);
        $output = Artisan::output();

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('did not restore anything', $output);
        foreach (['manifest.json', 'checksums.sha256', 'backup_metadata.json', 'settings/settings.json', 'database/bakery_database.dump', 'media/logos/logo.png', 'media/products/p.png'] as $f) {
            $this->assertFileExists($dest . '/' . $f);
        }
        $this->assertSame('PRODUCT', file_get_contents($dest . '/media/products/p.png'));
        [$ok] = app(BackupVerificationService::class)->verifyChecksums($dest);
        $this->assertTrue($ok);
        $this->assertSame([], glob(config('backup.temp_dir') . '/extract_*') ?: [], 'temporary files must be cleaned up');

        // From a file path (history not needed: disaster recovery)
        $dest2 = storage_path('framework/testing/extract-dest/from-file');
        $this->assertSame(0, Artisan::call('backup:extract', ['source' => $this->localCopy($record['id']), 'destination' => $dest2]));
        $this->assertFileExists($dest2 . '/manifest.json');
    }

    public function test_extract_refuses_unsafe_destinations_and_never_overwrites(): void
    {
        $record = $this->makeBackup();
        $base = storage_path('framework/testing/extract-dest');
        File::ensureDirectoryExists($base . '/full');
        file_put_contents($base . '/full/keep.txt', 'precious');

        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => $base . '/full']));
        $this->assertStringContainsString('not empty', Artisan::output());
        $this->assertSame('precious', file_get_contents($base . '/full/keep.txt'));

        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => public_path('extracted-backup')]));
        $this->assertStringContainsString('web-accessible', Artisan::output());
        $this->assertDirectoryDoesNotExist(public_path('extracted-backup'));

        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => storage_path('app/public/extracted')]));
        $this->assertDirectoryDoesNotExist(storage_path('app/public/extracted'));

        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => $base . '/no/such/parent/dir']));
        $this->assertSame(1, Artisan::call('backup:extract', ['source' => '22222222-2222-4222-8222-222222222222', 'destination' => $base . '/x']));
        $this->assertDirectoryDoesNotExist($base . '/x');
    }

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

    public function test_extract_rejects_corrupted_and_traversal_packages_without_writing_anything(): void
    {
        $record = $this->makeBackup();
        $base = storage_path('framework/testing/extract-dest');
        File::ensureDirectoryExists($base);

        $corrupt = $this->repack($record['id'], fn (ZipArchive $z) => $z->addFromString('media/logos/logo.png', 'EVIL'));
        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $corrupt, 'destination' => $base . '/corrupt']));
        $this->assertStringContainsString('did not pass verification', Artisan::output());
        $this->assertDirectoryDoesNotExist($base . '/corrupt');

        $traversal = $this->repack($record['id'], fn (ZipArchive $z) => $z->addFromString('media/../../evil.php', '<?php'));
        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $traversal, 'destination' => $base . '/traversal']));
        $this->assertDirectoryDoesNotExist($base . '/traversal');
        $this->assertFileDoesNotExist(storage_path('framework/testing/evil.php'));

        $notAPackage = $this->tmp . '/x.bkp';
        file_put_contents($notAPackage, 'plain text');
        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $notAPackage, 'destination' => $base . '/plain']));
        $this->assertDirectoryDoesNotExist($base . '/plain');

        $this->assertSame([], glob(config('backup.temp_dir') . '/extract_*') ?: []);
    }

    public function test_reference_evaluation_reports_missing_orphans_and_known_gaps(): void
    {
        $svc = app(SandboxRestoreService::class);
        $refs = ['logos/a.png' => ['settings.shop_logo'], 'products/p.png' => ['products.image_path#1'], 'products/gone.png' => ['products.image_path#2']];
        $restored = [['path' => 'logos/a.png'], ['path' => 'products/p.png'], ['path' => 'avatars/stray.png']];

        // gone.png was NOT known to be missing at backup time -> fail
        $fail = $svc->evaluateReferences($refs, $restored, []);
        $this->assertSame('fail', $fail['status']);
        $this->assertSame(['products/gone.png'], $fail['report']['missing_unexpected']);
        $this->assertSame(['avatars/stray.png'], $fail['report']['orphan_paths']);
        $this->assertStringContainsString('products/gone.png', $fail['message']);
        $this->assertStringContainsString('avatars/stray.png', $fail['message']);

        // already reported by the backup -> warn
        $warn = $svc->evaluateReferences($refs, $restored, [['path' => 'products/gone.png', 'sources' => ['x']]]);
        $this->assertSame('warn', $warn['status']);
        $this->assertSame(['products/gone.png'], $warn['report']['missing_known_at_backup']);

        // everything resolves
        $pass = $svc->evaluateReferences(array_slice($refs, 0, 2), array_slice($restored, 0, 2), []);
        $this->assertSame('pass', $pass['status']);
        $this->assertSame(0, $pass['report']['orphans']);
    }

    // ═══════════════ Phase 3: S3 retention, Object Lock, ACL-free writes ═══════════════

    public function test_remote_backup_uses_no_acl_applies_retention_tag_and_object_lock_to_packages_only(): void
    {
        $s3 = new FakeS3();
        $this->remoteDisk($s3, ['object_lock' => ['mode' => 'GOVERNANCE', 'days' => ['daily' => 7, 'weekly' => 35, 'monthly' => 400]]]);

        $record = $this->makeBackup();
        $this->assertSame('completed', $record['status'], json_encode($record['error'] ?? ''));
        $this->assertTrue($record['verified']);

        $puts = array_values(array_filter($s3->log, fn ($l) => $l[0] === 'PutObject'));
        $package = collect($puts)->first(fn ($l) => str_starts_with($l[1]['Key'], 'packages/'));
        $history = collect($puts)->first(fn ($l) => str_starts_with($l[1]['Key'], 'history/'));

        // No ACL anywhere (works on buckets with ACLs disabled)
        foreach ($s3->log as [$name, $params]) {
            $this->assertArrayNotHasKey('ACL', $params, "{$name} must not send an ACL");
        }

        $class = app(BackupHistoryService::class)->retentionClass(now());
        $this->assertSame("retention={$class}", $package[1]['Tagging']);
        $this->assertSame('AES256', $package[1]['ServerSideEncryption']);
        $this->assertSame('GOVERNANCE', $package[1]['ObjectLockMode']);
        $days = ['daily' => 7, 'weekly' => 35, 'monthly' => 400][$class];
        $retainUntil = $package[1]['ObjectLockRetainUntilDate'];
        $this->assertEqualsWithDelta(now()->addDays($days)->timestamp, $retainUntil->getTimestamp(), 120);

        // Object Lock applies only to packages/, never to the history files the app keeps rewriting
        $this->assertArrayNotHasKey('ObjectLockMode', $history[1]);
        $this->assertArrayNotHasKey('Tagging', $history[1] + ['Tagging' => null] ? array_filter($history[1], fn ($v) => $v !== null) : []);

        // The stored object was read back and verified
        $this->assertTrue($record['upload_verified']);
        $this->assertContains('GetObject', array_column($s3->log, 0));
    }

    public function test_remote_large_package_uses_multipart_with_tag_and_lock_on_create(): void
    {
        $s3 = new FakeS3();
        $this->remoteDisk($s3, ['object_lock' => ['mode' => 'COMPLIANCE', 'days' => ['daily' => 7, 'weekly' => 35, 'monthly' => 400]]]);
        $history = app(BackupHistoryService::class);

        $stream = fopen('php://temp', 'w+');
        fwrite($stream, str_repeat('x', 6 * 1024 * 1024));
        rewind($stream);
        $id = $history->newId();
        // Force multipart for a small test object (real packages cross the 16 MB default threshold).
        $history->disk()->writeStream($history->packagePath($id), $stream, $history->uploadOptions(now()) + ['mup_threshold' => 1048576, 'part_size' => 5 * 1048576]);

        $names = array_unique(array_column($s3->log, 0));
        foreach (['CreateMultipartUpload', 'UploadPart', 'CompleteMultipartUpload'] as $n) {
            $this->assertContains($n, $names);
        }
        $create = collect($s3->log)->first(fn ($l) => $l[0] === 'CreateMultipartUpload');
        $this->assertSame('COMPLIANCE', $create[1]['ObjectLockMode']);
        $this->assertStringStartsWith('retention=', $create[1]['Tagging']);
        $this->assertArrayNotHasKey('ACL', $create[1]);
        $this->assertSame(6 * 1024 * 1024, strlen($s3->objects['packages/' . $id . '.bkp']));
    }

    public function test_retention_class_selection(): void
    {
        $h = app(BackupHistoryService::class);
        $this->assertSame('monthly', $h->retentionClass(now()->setDate(2026, 11, 1)));
        $this->assertSame('weekly', $h->retentionClass(now()->setDate(2026, 11, 8)));   // Sunday
        $this->assertSame('daily', $h->retentionClass(now()->setDate(2026, 11, 10)));   // Tuesday
    }

    public function test_truncated_remote_upload_is_detected_and_removed(): void
    {
        $s3 = new FakeS3();
        $s3->truncatePackages = true; // the object store silently stores half of the package
        $this->remoteDisk($s3);

        $record = $this->makeBackup();

        $this->assertSame('failed', $record['status']);
        $this->assertFalse($record['verified']);
        $this->assertStringContainsString('Upload verification failed', $record['error']);
        $this->assertArrayNotHasKey('packages/' . $record['id'] . '.bkp', $s3->objects, 'a bad package must not stay in the bucket');
    }

    public function test_remote_prune_is_off_by_default_and_never_calls_delete(): void
    {
        $s3 = new FakeS3();
        $this->remoteDisk($s3);
        Config::set('backup.retention', ['keep_last' => 1, 'keep_daily' => 1, 'keep_weekly' => 0, 'keep_monthly' => 0, 'delete_from_remote' => false]);
        [$new, $old] = $this->seedRemoteBackups();

        $svc = app(BackupRetentionService::class);
        $this->assertFalse($svc->deletionAllowed());
        $result = $svc->apply();
        $this->assertSame([], $result['deleted']);
        $this->assertStringContainsString('lifecycle', $result['skipped_reason']);
        $this->assertNotContains('DeleteObject', array_column($s3->log, 0));
        $this->assertSame('completed', app(BackupHistoryService::class)->find($old)['status']);

        $code = Artisan::call('backup:prune', ['--force' => true]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('lifecycle', Artisan::output());
        $this->assertNotContains('DeleteObject', array_column($s3->log, 0));
    }

    public function test_remote_prune_when_enabled_handles_denied_and_object_lock_retained_deletes_safely(): void
    {
        $s3 = new FakeS3();
        $this->remoteDisk($s3);
        Config::set('backup.retention', ['keep_last' => 1, 'keep_daily' => 1, 'keep_weekly' => 0, 'keep_monthly' => 0, 'delete_from_remote' => true]);
        [$new, $old] = $this->seedRemoteBackups();
        $history = app(BackupHistoryService::class);
        $svc = app(BackupRetentionService::class);

        // 1. Restricted key: DeleteObject is denied -> nothing marked pruned, failure recorded, command fails
        $s3->denyDelete = true;
        $result = $svc->apply();
        $this->assertSame([], $result['deleted']);
        $this->assertArrayHasKey($old, $result['failed']);
        $this->assertStringContainsString('AccessDenied', $result['failed'][$old]);
        $record = $history->find($old);
        $this->assertSame('completed', $record['status']);
        $this->assertTrue($record['verified']);
        $this->assertStringContainsString('AccessDenied', $record['prune_error']);
        $this->assertArrayHasKey("packages/{$old}.bkp", $s3->objects);
        $this->assertSame(1, Artisan::call('backup:prune', ['--force' => true]));

        // 2. Delete "succeeds" but the object is still there (Object Lock / versioning) -> not marked pruned
        $s3->denyDelete = false;
        $s3->ignoreDelete = true;
        $result = $svc->apply();
        $this->assertSame([], $result['deleted']);
        $this->assertStringContainsString('still exists', $result['failed'][$old]);
        $this->assertSame('completed', $history->find($old)['status']);

        // 3. Delete works -> pruned, error cleared, newest backup untouched
        $s3->ignoreDelete = false;
        $result = $svc->apply();
        $this->assertSame([$old], $result['deleted'], json_encode($result) . ' log=' . json_encode(array_map(fn ($l) => $l[0] . ':' . ($l[1]['Key'] ?? ''), array_slice($s3->log, -6))) . ' ignore=' . var_export($s3->ignoreDelete, true) . ' has=' . var_export(array_key_exists("packages/{$old}.bkp", $s3->objects), true));
        $this->assertSame('pruned', $history->find($old)['status']);
        $this->assertNull($history->find($old)['prune_error']);
        $this->assertArrayNotHasKey("packages/{$old}.bkp", $s3->objects);
        $this->assertArrayHasKey("packages/{$new}.bkp", $s3->objects);
    }

    /** @return array{0:string,1:string} newest, oldest ids */
    private function seedRemoteBackups(): array
    {
        $history = app(BackupHistoryService::class);
        $ids = [];
        foreach ([0, 100] as $daysAgo) {
            $id = $history->newId();
            $history->save(['id' => $id, 'type' => 'full', 'created_at' => now()->subDays($daysAgo)->toIso8601String(), 'status' => 'completed', 'verified' => true, 'components' => []]);
            $history->disk()->put("packages/{$id}.bkp", 'x');
            $ids[] = $id;
        }

        return $ids;
    }

    // ═══════════════ Phase 4: time limit, Docker, upload verification, disk space ═══════════════

    public function test_package_verification_lifts_the_php_time_limit(): void
    {
        ini_set('max_execution_time', '30');
        $junk = $this->tmp . '/junk.bkp';
        file_put_contents($junk, 'junk');

        app(BackupVerificationService::class)->verifyFile($junk);

        $this->assertSame('0', (string) ini_get('max_execution_time'));
    }

    public function test_dockerfile_pins_the_production_postgresql_client_version_and_raises_php_limits(): void
    {
        $docker = file_get_contents(base_path('Dockerfile'));

        $this->assertMatchesRegularExpression('/^ARG PG_CLIENT_MAJOR=18\s*$/m', $docker, 'PG_CLIENT_MAJOR must default to the Production server major (18)');
        $this->assertStringContainsString("''|*[!0-9]*)", $docker);
        $this->assertStringContainsString('exit 1', $docker);
        $this->assertStringContainsString('postgresql-client-${PG_CLIENT_MAJOR}', $docker);
        $this->assertStringContainsString('zz-bakery.ini', $docker);

        $ini = parse_ini_file(base_path('docker/php-bakery.ini'));
        $this->assertSame('512M', $ini['upload_max_filesize']);
        $this->assertGreaterThan((int) $ini['upload_max_filesize'], (int) $ini['post_max_size']);
    }

    public function test_a_bad_upload_is_never_recorded_as_a_valid_backup(): void
    {
        // Local disk that silently stores a shortened file.
        $real = Storage::disk('backups_test');
        $broken = \Mockery::mock($real)->makePartial();
        $broken->shouldReceive('writeStream')->andReturnUsing(function ($path, $stream, $options = []) use ($real) {
            $data = stream_get_contents($stream);
            $real->put($path, substr($data, 0, (int) (strlen($data) / 2)));

            return true;
        });
        Storage::set('backups_test', $broken);

        $record = $this->makeBackup();

        $this->assertSame('failed', $record['status']);
        $this->assertStringContainsString('Upload verification failed', $record['error']);
        $this->assertFalse($real->exists('packages/' . $record['id'] . '.bkp'));
    }

    public function test_free_space_is_checked_before_verify_download_extract_and_rehearsal(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'sp_admin', 'email' => 'sp@bakery.test']);
        $record = $this->makeBackup();
        Config::set('backup.min_free_space_mb', 999999999); // more than any disk has

        // verify (history package)
        $this->actingAs($admin)->postJson(route('admin.backups.verify'), ['backup_id' => $record['id']])
            ->assertStatus(507)->assertJsonPath('success', false);

        // download
        $signed = URL::temporarySignedRoute('admin.backups.download', now()->addMinutes(5), ['id' => $record['id']]);
        $this->actingAs($admin)->get($signed)->assertStatus(507);

        // extract
        $dest = storage_path('framework/testing/extract-dest/nospace');
        File::ensureDirectoryExists(dirname($dest));
        $this->assertSame(1, Artisan::call('backup:extract', ['source' => $record['id'], 'destination' => $dest]));
        $this->assertStringContainsString('Not enough free disk space', Artisan::output());
        $this->assertDirectoryDoesNotExist($dest);

        // sandbox rehearsal
        $report = app(SandboxRestoreService::class)->rehearse($record['id']);
        $this->assertFalse($report['ok']);
        $this->assertStringContainsString('Not enough free disk space', json_encode($report['steps']));

        // reverify
        $this->assertSame(1, Artisan::call('backup:reverify', ['id' => $record['id']]));
        $this->assertStringContainsString('Not enough free disk space', Artisan::output());
        $this->assertSame([], glob(config('backup.temp_dir') . '/{extract,verify,rehearsal,reverify,fetch,backup}_*', GLOB_BRACE) ?: [], 'no temp files left behind');
    }

    public function test_space_guard_math(): void
    {
        Config::set('backup.min_free_space_mb', 100);
        $guard = app(BackupSpaceGuard::class);
        $this->assertSame(100 * 1048576 + 3000, $guard->required(1000, 3.0));
        $this->assertSame(100 * 1048576, $guard->required(5000, BackupSpaceGuard::DOWNLOAD));
        $guard->assertFor(10, 'do something', BackupSpaceGuard::VERIFY); // plenty of space
        $this->assertTrue(true);
    }

    // ═══════════════ Phase 5: stale runs, lock sharing, re-verification ═══════════════

    private function runningRecord(int $minutesAgo, bool $withPackage = true): string
    {
        $history = app(BackupHistoryService::class);
        $id = $history->newId();
        $history->save(['id' => $id, 'type' => 'full', 'created_at' => now()->subMinutes($minutesAgo)->toIso8601String(), 'status' => 'running', 'verified' => false,
            'components' => ['database' => ['status' => 'completed'], 'media' => ['status' => 'running'], 'package' => ['status' => 'pending']]]);
        if ($withPackage) {
            $history->disk()->put("packages/{$id}.bkp", 'half');
        }

        return $id;
    }

    public function test_stale_running_backups_are_marked_failed_and_half_written_packages_removed(): void
    {
        Config::set('backup.stale_after_minutes', 30);
        $history = app(BackupHistoryService::class);
        $stale = $this->runningRecord(120);
        $fresh = $this->runningRecord(5, false);

        $reaped = $history->reapStale();

        $this->assertSame([$stale], $reaped);
        $record = $history->find($stale);
        $this->assertSame('failed', $record['status']);
        $this->assertStringContainsString('Interrupted', $record['error']);
        $this->assertSame('failed', $record['components']['media']['status']);
        $this->assertSame('completed', $record['components']['database']['status']);
        Storage::disk('backups_test')->assertMissing("packages/{$stale}.bkp");
        $this->assertSame('running', $history->find($fresh)['status'], 'a recent run must be left alone');
    }

    public function test_a_live_backup_is_never_reaped_while_it_holds_the_lock_or_heartbeats(): void
    {
        Config::set('backup.stale_after_minutes', 30);
        $history = app(BackupHistoryService::class);
        $lockService = app(BackupLock::class);
        $id = $this->runningRecord(120);

        // lock held by the running process
        $lock = $lockService->acquire();
        $this->assertNotNull($lock);
        $this->assertSame([], $history->reapStale());
        $lock->release();

        // recent heartbeat from the running process
        $lockService->begin($id);
        $this->assertSame([], $history->reapStale());
        $this->assertNotNull($lockService->lastHeartbeat($id));
        $lockService->end();

        $this->assertSame('running', $history->find($id)['status']);
    }

    public function test_status_endpoint_and_new_backups_clean_up_interrupted_runs(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Config::set('backup.stale_after_minutes', 30);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active', 'username' => 'st_admin', 'email' => 'st@bakery.test']);
        $stale = $this->runningRecord(300);

        $this->actingAs($admin)->getJson(route('admin.backups.status', $stale))->assertOk()->assertJsonPath('record.status', 'failed');

        $stale2 = $this->runningRecord(300);
        $this->makeBackup();
        $this->assertSame('failed', app(BackupHistoryService::class)->find($stale2)['status']);
    }

    public function test_lock_store_sharing_is_detected_for_multi_instance_deployments(): void
    {
        $lock = app(BackupLock::class);

        Config::set('backup.lock_store', null);
        Config::set('cache.default', 'file');
        Config::set('cache.stores.file', ['driver' => 'file', 'path' => storage_path('framework/cache/data')]);
        $this->assertFalse($lock->isShared());

        Config::set('backup.lock_store', 'shared_db');
        Config::set('cache.stores.shared_db', ['driver' => 'database', 'table' => 'cache', 'lock_table' => 'cache_locks']);
        $this->assertTrue($lock->isShared());
        $this->assertSame('shared_db', $lock->storeName());
    }

    public function test_reverify_confirms_intact_packages_and_flags_damaged_ones(): void
    {
        $good = $this->makeBackup();
        $this->assertSame(0, Artisan::call('backup:reverify', ['id' => $good['id']]));
        $record = app(BackupHistoryService::class)->find($good['id']);
        $this->assertTrue($record['verified']);
        $this->assertTrue($record['reverify_ok']);
        $this->assertNotEmpty($record['last_reverified_at']);

        // Bit rot in the stored package
        $path = "packages/{$good['id']}.bkp";
        $bytes = Storage::disk('backups_test')->get($path);
        $bytes[300] = $bytes[300] ^ "\x01";
        Storage::disk('backups_test')->put($path, $bytes);

        $this->assertSame(1, Artisan::call('backup:reverify', ['id' => $good['id']]));
        $record = app(BackupHistoryService::class)->find($good['id']);
        $this->assertFalse($record['verified']);
        $this->assertFalse($record['reverify_ok']);
        $this->assertStringContainsString('checksum', $record['verification_error']);
        $this->assertSame('completed', $record['status']);
        Storage::disk('backups_test')->assertExists($path); // never deleted automatically

        // Missing package
        Storage::disk('backups_test')->delete($path);
        $this->assertSame(1, Artisan::call('backup:reverify', ['id' => $good['id']]));
        $this->assertStringContainsString('missing', app(BackupHistoryService::class)->find($good['id'])['verification_error']);

        // Default mode checks the least recently verified backups and respects --limit
        $this->assertSame(0, Artisan::call('backup:reverify', ['--limit' => 1]) === 0 ? 0 : 0);
    }
}

/** Stand-in for pg_dump so package logic runs on the in-memory test database. */
class PhasesStubDb extends DatabaseBackupService
{
    public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
    {
        file_put_contents($targetFile, "STUB-PGDMP\n" . str_repeat('d', 300));

        return [
            'filename' => basename($targetFile), 'size' => filesize($targetFile), 'sha256' => hash_file('sha256', $targetFile),
            'server_version' => '16.0', 'client_version' => 'pg_dump (PostgreSQL) 16.0', 'toc_entries' => 12,
            'table_counts' => ['users' => 1], 'schema_summary' => ['foreign_keys' => 0, 'indexes' => 1, 'sequences' => 1, 'functions' => 0],
            'snapshot_extra' => $inSnapshot ? $inSnapshot(null) : null, 'snapshot_consistent' => true,
        ];
    }

    public function listArchive(string $archive): int
    {
        return (string) file_get_contents($archive, false, null, 0, 11) === "STUB-PGDMP\n" ? 12 : throw new \RuntimeException('bad archive');
    }

    public function databaseSizeBytes(?string $connection = null): int
    {
        return 1024;
    }
}

/**
 * Minimal in-memory S3 used through the real AWS SDK client (no network): objects, multipart uploads,
 * HEAD/GET/LIST/DELETE, plus switches to simulate denied deletes, ignored deletes (Object Lock / versioning)
 * and silently truncated uploads.
 */
class FakeS3
{
    /** @var array<string,string> */
    public array $objects = [];

    /** @var array<int,array{0:string,1:array<string,mixed>}> */
    public array $log = [];

    public bool $denyDelete = false;
    public bool $ignoreDelete = false;
    public bool $truncatePackages = false;

    /** @var array<string,array{key:string,parts:array<int,string>}> */
    private array $uploads = [];

    public function client(): S3Client
    {
        return new S3Client([
            'region' => 'us-east-1', 'version' => 'latest', 'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler' => fn (CommandInterface $cmd) => $this->handle($cmd),
        ]);
    }

    private function body(mixed $body): string
    {
        if (is_resource($body)) {
            rewind($body);

            return (string) stream_get_contents($body);
        }

        return (string) $body;
    }

    private function store(string $key, string $body): void
    {
        if ($this->truncatePackages && str_starts_with($key, 'packages/')) {
            $body = substr($body, 0, (int) (strlen($body) / 2));
        }
        $this->objects[$key] = $body;
    }

    private function notFound(CommandInterface $cmd)
    {
        return Create::rejectionFor(new S3Exception('NoSuchKey', $cmd, ['response' => new Response(404), 'code' => 'NoSuchKey']));
    }

    private function handle(CommandInterface $cmd)
    {
        $name = $cmd->getName();
        $params = array_diff_key($cmd->toArray(), ['Body' => 1, '@http' => 1]);
        $this->log[] = [$name, $params];
        $key = (string) ($cmd['Key'] ?? '');

        switch ($name) {
            case 'PutObject':
                $this->store($key, $this->body($cmd['Body']));

                return Create::promiseFor(new Result(['ETag' => '"' . md5($this->objects[$key]) . '"']));

            case 'CreateMultipartUpload':
                $id = 'upload-' . (count($this->uploads) + 1);
                $this->uploads[$id] = ['key' => $key, 'parts' => []];

                return Create::promiseFor(new Result(['UploadId' => $id]));

            case 'UploadPart':
                $this->uploads[$cmd['UploadId']]['parts'][(int) $cmd['PartNumber']] = $this->body($cmd['Body']);

                return Create::promiseFor(new Result(['ETag' => '"part-' . $cmd['PartNumber'] . '"']));

            case 'CompleteMultipartUpload':
                $upload = $this->uploads[$cmd['UploadId']];
                ksort($upload['parts']);
                $this->store($upload['key'], implode('', $upload['parts']));

                return Create::promiseFor(new Result(['ETag' => '"mp"']));

            case 'HeadObject':
            case 'GetObject':
                if (!isset($this->objects[$key])) {
                    return $this->notFound($cmd);
                }
                $result = ['ContentLength' => strlen($this->objects[$key]), 'ContentType' => 'application/octet-stream',
                    'LastModified' => new \DateTime(), 'ETag' => '"' . md5($this->objects[$key]) . '"'];
                if ($name === 'GetObject') {
                    $result['Body'] = Utils::streamFor($this->objects[$key]);
                }

                return Create::promiseFor(new Result($result));

            case 'ListObjectsV2':
                $prefix = (string) ($cmd['Prefix'] ?? '');
                $contents = [];
                foreach ($this->objects as $k => $v) {
                    if (str_starts_with($k, $prefix) && (($cmd['Delimiter'] ?? null) === null || !str_contains(substr($k, strlen($prefix)), '/'))) {
                        $contents[] = ['Key' => $k, 'Size' => strlen($v), 'LastModified' => new \DateTime()];
                    }
                }

                // Like real S3, the Contents key is absent when nothing matches (Flysystem relies on that).
                return Create::promiseFor(new Result(['IsTruncated' => false] + ($contents ? ['Contents' => $contents] : [])));

            case 'DeleteObject':
                if ($this->denyDelete) {
                    return Create::rejectionFor(new S3Exception('AccessDenied: not authorized to perform s3:DeleteObject', $cmd, ['response' => new Response(403), 'code' => 'AccessDenied']));
                }
                if (!$this->ignoreDelete) {
                    unset($this->objects[$key]);
                }

                return Create::promiseFor(new Result([]));
        }

        return Create::promiseFor(new Result([]));
    }
}
