<?php

namespace App\Services\Backup;

use App\Services\StorageDiagnosticsService;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Copies the approved application media (logos, avatars, products, categories, receipts)
 * from the configured media disk into a staging directory, with SHA-256 checksums.
 * Read-only against the source disk: nothing is ever written, moved or deleted there.
 */
class MediaBackupService
{
    public function __construct(private StorageDiagnosticsService $diagnostics)
    {
    }

    public function sourceDiskName(): string
    {
        return $this->diagnostics->getActiveDiskName();
    }

    /** Human-readable, non-secret description of the source (never credentials). */
    public function sourceLabel(?string $diskName = null): array
    {
        $diskName = $diskName ?: $this->sourceDiskName();
        $cfg = config("filesystems.disks.{$diskName}", []);
        $driver = (string) ($cfg['driver'] ?? $diskName);

        return [
            'disk' => $diskName,
            'driver' => $driver,
            'bucket' => in_array($driver, ['s3', 'supabase-s3'], true) ? (string) ($cfg['bucket'] ?? '') : null,
        ];
    }

    /**
     * @return array{objects: array<int,array<string,mixed>>, count:int, bytes:int, prefixes:array<int,string>, source:array}
     */
    public function backup(string $stagingMediaDir, ?string $diskName = null): array
    {
        $diskName = $diskName ?: $this->sourceDiskName();
        $disk = Storage::disk($diskName);
        $prefixes = (array) config('backup.media_prefixes');

        $objects = [];
        $bytes = 0;

        foreach ($prefixes as $prefix) {
            foreach ($disk->getDriver()->listContents(rtrim($prefix, '/'), true) as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $path = ltrim($item->path(), '/');
                if (basename($path) === '.gitignore' || !str_starts_with($path, $prefix)) {
                    continue;
                }
                self::assertSafeRelativePath($path);
                app(BackupLock::class)->beat();

                $target = $stagingMediaDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0700, true);
                }

                $expectedSize = $item->fileSize();
                $meta = $this->objectMetadata($disk, $path);
                [$size, $sha] = $this->copyVerified($disk, $path, $target, $expectedSize ?? $meta['size'], $meta['etag']);

                $mime = $meta['content_type'] ?: (method_exists($item, 'mimeType') ? $item->mimeType() : null);
                $modified = $item->lastModified();
                $objects[] = [
                    'path' => $path,
                    'size' => $size,
                    'sha256' => $sha,
                    'content_type' => $mime ?: StorageDiagnosticsService::guessMimeType(pathinfo($path, PATHINFO_EXTENSION)),
                    'last_modified' => $modified ? date('c', $modified) : null,
                    'metadata' => $meta['metadata'] ?: null,
                ];
                $bytes += $size;
            }
        }

        usort($objects, fn ($a, $b) => strcmp($a['path'], $b['path']));

        return [
            'objects' => $objects,
            'count' => count($objects),
            'bytes' => $bytes,
            'prefixes' => array_values($prefixes),
            'source' => $this->sourceLabel($diskName),
        ];
    }

    /**
     * Restore staged media into an isolated destination directory and verify every checksum.
     *
     * @param array<int,array<string,mixed>> $objects manifest entries
     * @return array{restored:int, bytes:int, mismatches:array<int,string>}
     */
    public function restoreTo(array $objects, string $extractedMediaDir, string $destinationRoot): array
    {
        $restored = 0;
        $bytes = 0;
        $mismatches = [];

        foreach ($objects as $object) {
            $path = (string) $object['path'];
            self::assertSafeRelativePath($path);

            $source = $extractedMediaDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $dest = $destinationRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0700, true);
            }
            if (!is_file($source) || !copy($source, $dest)) {
                $mismatches[] = $path . ' (missing)';
                continue;
            }
            if (!hash_equals((string) $object['sha256'], hash_file('sha256', $dest))
                || (int) $object['size'] !== filesize($dest)) {
                $mismatches[] = $path . ' (checksum)';
                continue;
            }
            $restored++;
            $bytes += (int) $object['size'];
        }

        return ['restored' => $restored, 'bytes' => $bytes, 'mismatches' => $mismatches];
    }

    public static function assertSafeRelativePath(string $path): void
    {
        if ($path === ''
            || str_contains($path, "\0")
            || str_contains($path, '\\')
            || str_starts_with($path, '/')
            || preg_match('#(^|/)\.\.(/|$)#', $path)
            || preg_match('#^[A-Za-z]:#', $path)) {
            throw new RuntimeException('Unsafe media path rejected: ' . mb_substr($path, 0, 80));
        }
    }

    /** Sum of object sizes in the approved scope (listing only, no downloads). */
    public function estimateBytes(?string $diskName = null): int
    {
        $disk = Storage::disk($diskName ?: $this->sourceDiskName());
        $total = 0;
        foreach ((array) config('backup.media_prefixes') as $prefix) {
            foreach ($disk->getDriver()->listContents(rtrim($prefix, '/'), true) as $item) {
                if ($item->isFile()) {
                    $total += (int) $item->fileSize();
                }
            }
        }

        return $total;
    }

    /**
     * Real object metadata when the disk exposes an S3 client (HeadObject); empty otherwise.
     *
     * @return array{content_type:?string,size:?int,etag:?string,metadata:array<string,string>}
     */
    private function objectMetadata($disk, string $path): array
    {
        $empty = ['content_type' => null, 'size' => null, 'etag' => null, 'metadata' => []];
        if (!method_exists($disk, 'getClient') || !method_exists($disk, 'getConfig')) {
            return $empty;
        }

        try {
            $cfg = $disk->getConfig();
            $head = $disk->getClient()->headObject([
                'Bucket' => $cfg['bucket'],
                'Key' => (isset($cfg['root']) && $cfg['root'] !== '' ? trim($cfg['root'], '/') . '/' : '') . $path,
            ]);

            return [
                'content_type' => $head['ContentType'] ?? null,
                'size' => isset($head['ContentLength']) ? (int) $head['ContentLength'] : null,
                'etag' => isset($head['ETag']) ? trim((string) $head['ETag'], '"') : null,
                'metadata' => (array) ($head['Metadata'] ?? []),
            ];
        } catch (\Throwable) {
            return $empty; // metadata is best-effort; the byte copy itself is still verified below
        }
    }

    /**
     * Download one object with retries. The result is verified against the size reported by the
     * storage listing/HEAD and, for single-part S3 objects, against the ETag (MD5).
     *
     * @return array{0:int,1:string} size, sha256
     */
    private function copyVerified($disk, string $path, string $target, ?int $expectedSize, ?string $etag): array
    {
        $attempts = max(1, (int) config('backup.object_retries', 3));
        $delay = (int) config('backup.object_retry_delay_ms', 500);
        $last = null;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                [$size, $sha, $md5] = $this->copyOut($disk, $path, $target);

                if ($expectedSize !== null && $size !== $expectedSize) {
                    throw new RuntimeException("Incomplete download of {$path}: received {$size} of {$expectedSize} bytes.");
                }
                if ($etag !== null && preg_match('/^[a-f0-9]{32}$/', $etag) && !hash_equals($etag, $md5)) {
                    throw new RuntimeException("Checksum (ETag) mismatch for {$path}.");
                }

                return [$size, $sha];
            } catch (\Throwable $e) {
                $last = $e;
                @unlink($target);
                if ($i < $attempts) {
                    usleep($delay * 1000 * $i);
                }
            }
        }

        throw new RuntimeException('Media object could not be downloaded after ' . $attempts . ' attempt(s): ' . BackupRedactor::redact($last->getMessage()));
    }

    /** @return array{0:int,1:string,2:string} size, sha256, md5 */
    private function copyOut($disk, string $path, string $target): array
    {
        $in = $disk->readStream($path);
        if (!is_resource($in)) {
            throw new RuntimeException('Unable to read media object: ' . $path);
        }
        $out = fopen($target, 'wb');
        $sha = hash_init('sha256');
        $md5 = hash_init('md5');
        $size = 0;
        $deadline = time() + max(1, (int) config('backup.object_timeout_seconds', 120));
        try {
            while (!feof($in)) {
                $chunk = fread($in, 1048576);
                if ($chunk === false) {
                    throw new RuntimeException('Read failed for media object: ' . $path);
                }
                if ($chunk === '') {
                    if (time() > $deadline) {
                        throw new RuntimeException('Timed out reading media object: ' . $path);
                    }
                    continue;
                }
                fwrite($out, $chunk);
                hash_update($sha, $chunk);
                hash_update($md5, $chunk);
                $size += strlen($chunk);
                if (time() > $deadline) {
                    throw new RuntimeException('Timed out reading media object: ' . $path);
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return [$size, hash_final($sha), hash_final($md5)];
    }
}
