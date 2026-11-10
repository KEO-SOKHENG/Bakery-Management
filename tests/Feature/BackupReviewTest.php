<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupPackageService;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\MediaBackupService;
use App\Services\ProductImageStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Pre-production review checks: product images (legacy + new) through backup and media restore,
 * and the behaviour of retention when the storage credentials cannot delete.
 */
class BackupReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.settings_disk', 'public');
        Config::set('backup.disk', 'backups_test');
        Config::set('backup.temp_dir', storage_path('framework/testing/backup-tmp-r'));
        Config::set('filesystems.disks.backups_test', ['driver' => 'local', 'root' => storage_path('framework/testing/disks/backups_test'), 'visibility' => 'private', 'throw' => true]);
        Storage::fake('public');
        Storage::fake('backups_test');

        $this->app->instance(DatabaseBackupService::class, new ReviewStubDb());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/backup-tmp-r'));
        File::deleteDirectory(storage_path('framework/testing/restored-media'));
        parent::tearDown();
    }

    private function product(string $imagePath): Product
    {
        static $n = 0;
        $n++;

        return Product::create(['name' => 'P' . $n, 'sku' => 'R-' . $n, 'price' => 1, 'cost' => 1, 'stock' => 1, 'minimum_stock' => 0, 'status' => 'active', 'image_path' => $imagePath]);
    }

    public function test_legacy_and_new_product_images_survive_backup_and_media_restore_with_correct_paths(): void
    {
        $images = app(ProductImageStorage::class);

        // New upload: stored on the media disk as products/<file>.
        $newPath = $images->store(UploadedFile::fake()->image('new.png', 30, 30));
        $newBytes = Storage::disk('public')->get($newPath);

        // Legacy row (uploads/products/...) whose file was copied to the media disk by products:migrate-images.
        Storage::disk('public')->put('products/legacy.png', 'LEGACY-IMAGE-BYTES');
        $legacyPath = 'uploads/products/legacy.png';

        $this->product($newPath);
        $this->product($legacyPath);

        // Both resolve before the backup.
        $this->assertNotNull($images->url($newPath));
        $this->assertNotNull($images->url($legacyPath));

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());
        $this->assertTrue($record['verified']);
        $this->assertSame(0, $record['media_references']['missing'], 'Both legacy and new references must be captured');
        $this->assertSame([], $record['warnings']);

        // Verify the package and inspect the manifest paths.
        $local = BackupWorkspace::make('r') . '/p.bkp';
        file_put_contents($local, Storage::disk('backups_test')->get('packages/' . $record['id'] . '.bkp'));
        $verify = app(BackupVerificationService::class)->verifyFile($local);
        $this->assertTrue($verify['ok']);

        $paths = array_column($verify['manifest']['media']['objects'], 'path');
        sort($paths);
        $this->assertSame([$newPath, 'products/legacy.png'], collect($paths)->sort()->values()->all());

        // Media restore into an isolated root, as the sandbox rehearsal does.
        $root = storage_path('framework/testing/restored-media');
        $restored = app(MediaBackupService::class)->restoreTo($verify['manifest']['media']['objects'], $verify['extracted_dir'] . '/media', $root);
        $this->assertSame(2, $restored['restored']);
        $this->assertSame([], $restored['mismatches']);

        // Files land at the same storage keys, byte for byte.
        $this->assertSame('LEGACY-IMAGE-BYTES', file_get_contents($root . '/products/legacy.png'));
        $this->assertSame($newBytes, file_get_contents($root . '/' . $newPath));

        // The database values (unchanged) resolve against the restored media location.
        Config::set('filesystems.disks.restored', ['driver' => 'local', 'root' => $root, 'url' => 'http://restored.test/storage', 'visibility' => 'public']);
        Config::set('filesystems.settings_disk', 'restored');
        $legacyUrl = app(ProductImageStorage::class)->url($legacyPath);
        $newUrl = app(ProductImageStorage::class)->url($newPath);
        $this->assertSame('http://restored.test/storage/products/legacy.png', $legacyUrl);
        $this->assertSame('http://restored.test/storage/' . $newPath, $newUrl);

        BackupWorkspace::destroy($verify['work_dir']);
    }

    public function test_legacy_image_that_was_never_migrated_is_reported_not_silently_lost(): void
    {
        $this->product('uploads/products/never-migrated.png');

        $record = app(BackupPackageService::class)->create(app(BackupHistoryService::class)->newId());

        $this->assertTrue($record['verified']);
        $this->assertSame(1, $record['media_references']['missing']);
        $this->assertNotEmpty($record['warnings']);
    }

    public function test_prune_with_a_delete_denied_destination_fails_without_marking_records_pruned(): void
    {
        Config::set('backup.retention', ['keep_last' => 1, 'keep_daily' => 1, 'keep_weekly' => 0, 'keep_monthly' => 0]);
        $history = app(BackupHistoryService::class);

        $ids = [];
        foreach ([0, 100, 200] as $daysAgo) {
            $id = $history->newId();
            $history->save(['id' => $id, 'type' => 'full', 'created_at' => now()->subDays($daysAgo)->toIso8601String(), 'status' => 'completed', 'verified' => true, 'components' => []]);
            Storage::disk('backups_test')->put("packages/{$id}.bkp", 'x');
            $ids[$daysAgo] = $id;
        }

        // Simulate the restricted S3 key (no s3:DeleteObject): every delete is denied.
        $real = Storage::disk('backups_test');
        $denied = Mockery::mock($real)->makePartial();
        $denied->shouldReceive('delete')->andThrow(new \RuntimeException('AccessDenied: not authorized to perform s3:DeleteObject'));
        Storage::set('backups_test', $denied);

        $result = app(BackupRetentionService::class)->apply();

        // Failures are reported per package; nothing is claimed as deleted and no record is marked pruned.
        $this->assertSame([], $result['deleted']);
        $this->assertCount(2, $result['failed']);
        $this->assertStringContainsString('AccessDenied', implode(' ', $result['failed']));
        foreach ([$ids[100], $ids[200]] as $id) {
            $record = $history->find($id);
            $this->assertSame('completed', $record['status'], 'A failed delete must not mark the record as pruned');
            $this->assertTrue($record['verified']);
            $this->assertStringContainsString('AccessDenied', $record['prune_error']);
        }
        $this->assertTrue($real->exists("packages/{$ids[100]}.bkp"));
    }
}

class ReviewStubDb extends DatabaseBackupService
{
    public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
    {
        file_put_contents($targetFile, "STUB-PGDMP\n" . str_repeat('d', 300));

        return [
            'filename' => basename($targetFile), 'size' => filesize($targetFile), 'sha256' => hash_file('sha256', $targetFile),
            'server_version' => '16.0', 'client_version' => 'pg_dump (PostgreSQL) 16.0', 'toc_entries' => 12,
            'table_counts' => ['users' => 1], 'schema_summary' => ['foreign_keys' => 0, 'indexes' => 1, 'sequences' => 1, 'functions' => 0],
            'snapshot_extra' => $inSnapshot ? $inSnapshot(null) : null,
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
