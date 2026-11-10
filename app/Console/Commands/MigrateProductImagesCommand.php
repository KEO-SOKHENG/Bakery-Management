<?php

namespace App\Console\Commands;

use App\Services\ProductImageStorage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Copies legacy product images (public/uploads/products) to the media disk under products/.
 *
 *  - COPY only: source files are never moved or deleted.
 *  - Database rows are never changed; "uploads/products/x" references keep working (see ProductImageStorage).
 *  - Every copy is read back from the destination and verified with SHA-256.
 *  - Existing destination objects are never overwritten (a different checksum is reported as a conflict).
 *  - A manifest is written so the copy can be undone (--rollback), which only removes objects this command created.
 *  - Default is a dry run; pass --apply to copy.
 */
class MigrateProductImagesCommand extends Command
{
    protected $signature = 'products:migrate-images
                            {--apply : Copy the files (default is a dry run)}
                            {--source= : Source directory (default public/uploads/products)}
                            {--rollback= : Manifest file name or path to undo a previous --apply}';

    protected $description = 'Copy legacy product images to the media disk (products/) with checksum verification. Never deletes or overwrites.';

    public function handle(ProductImageStorage $images): int
    {
        if ($this->option('rollback')) {
            return $this->rollback($images, (string) $this->option('rollback'));
        }

        $source = rtrim((string) ($this->option('source') ?: public_path('uploads/products')), '/\\');
        $disk = $images->disk();
        $apply = (bool) $this->option('apply');

        $files = is_dir($source) ? array_values(array_filter(scandir($source), fn ($f) => is_file($source . DIRECTORY_SEPARATOR . $f) && $f !== '.gitignore' && $f !== '.gitkeep')) : [];
        $referenced = DB::table('products')->whereNotNull('image_path')->pluck('image_path')
            ->map(fn ($p) => $images->mediaKey($p))->filter()->flip()->all();

        $this->info(($apply ? 'APPLY' : 'DRY RUN') . ": {$source} -> disk '{$images->diskName()}' products/  (" . count($files) . ' file(s))');

        $items = [];
        $counts = ['copied' => 0, 'already_present' => 0, 'conflict' => 0, 'failed' => 0, 'would_copy' => 0];

        foreach ($files as $file) {
            $path = $source . DIRECTORY_SEPARATOR . $file;
            $key = 'products/' . $file;
            $sha = hash_file('sha256', $path);
            $item = ['source' => 'uploads/products/' . $file, 'key' => $key, 'sha256' => $sha, 'size' => filesize($path), 'referenced' => isset($referenced[$key])];

            try {
                if ($disk->exists($key)) {
                    $item['status'] = hash_equals($sha, $this->remoteSha($disk, $key)) ? 'already_present' : 'conflict';
                } elseif (!$apply) {
                    $item['status'] = 'would_copy';
                } else {
                    $in = fopen($path, 'rb');
                    $disk->put($key, $in, ['visibility' => 'public']);
                    if (is_resource($in)) {
                        fclose($in);
                    }
                    $item['status'] = hash_equals($sha, $this->remoteSha($disk, $key)) ? 'copied' : 'failed';
                    if ($item['status'] === 'failed') {
                        $disk->delete($key); // remove the bad copy we just made
                    }
                }
            } catch (\Throwable $e) {
                $item['status'] = 'failed';
                $item['error'] = mb_substr($e->getMessage(), 0, 200);
            }

            $counts[$item['status']]++;
            $items[] = $item;
            $this->line(sprintf('  %-16s %s%s', $item['status'], $item['key'], $item['referenced'] ? '' : '  (not referenced by any product)'));
        }

        $dangling = array_values(array_filter(array_keys($referenced), fn ($k) => !is_file($source . DIRECTORY_SEPARATOR . basename($k)) && !$disk->exists($k)));
        foreach ($dangling as $k) {
            $this->warn("  missing         {$k}  (referenced by a product but found neither locally nor on the media disk)");
        }

        $this->line('');
        $this->line(json_encode($counts));

        if ($apply) {
            $dir = storage_path('app/private/migrations');
            File::ensureDirectoryExists($dir);
            $manifest = $dir . DIRECTORY_SEPARATOR . 'product-images-' . date('Ymd_His') . '-' . bin2hex(random_bytes(2)) . '.json';
            file_put_contents($manifest, json_encode([
                'created_at' => now()->toIso8601String(),
                'disk' => $images->diskName(),
                'source_dir' => 'uploads/products',
                'counts' => $counts,
                'dangling_references' => $dangling,
                'items' => $items,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Manifest: ' . $manifest);
            $this->comment('Rollback: php artisan products:migrate-images --rollback=' . basename($manifest));
        }

        return ($counts['failed'] > 0 || $counts['conflict'] > 0) ? self::FAILURE : self::SUCCESS;
    }

    private function rollback(ProductImageStorage $images, string $manifestArg): int
    {
        $path = is_file($manifestArg) ? $manifestArg : storage_path('app/private/migrations/' . basename($manifestArg));
        if (!is_file($path)) {
            $this->error('Manifest not found.');

            return self::FAILURE;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        $disk = $images->disk();
        $removed = 0;
        $skipped = 0;

        foreach ((array) ($manifest['items'] ?? []) as $item) {
            if (($item['status'] ?? '') !== 'copied') {
                continue; // objects that already existed were not created by this migration
            }
            $key = (string) $item['key'];
            if (!str_starts_with($key, 'products/') || str_contains($key, '..')) {
                $skipped++;
                continue;
            }
            if (!$disk->exists($key)) {
                continue;
            }
            if (hash_equals((string) $item['sha256'], $this->remoteSha($disk, $key))) {
                $disk->delete($key);
                $removed++;
                $this->line("  removed {$key}");
            } else {
                $skipped++;
                $this->warn("  kept    {$key} (content changed since the migration)");
            }
        }

        $this->info("Rollback complete: {$removed} removed, {$skipped} kept. Source files and database rows were never modified.");

        return self::SUCCESS;
    }

    private function remoteSha($disk, string $key): string
    {
        $in = $disk->readStream($key);
        $ctx = hash_init('sha256');
        hash_update_stream($ctx, $in);
        fclose($in);

        return hash_final($ctx);
    }
}
