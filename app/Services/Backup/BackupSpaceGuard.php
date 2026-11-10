<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Free-disk-space preflight for every operation that materialises a package in the temp directory
 * (create, verify, rehearse, extract, download).
 */
class BackupSpaceGuard
{
    /** Typical multiples of the package size needed in temp space. */
    public const VERIFY = 3.0;     // decrypted zip + extracted files + headroom
    public const EXTRACT = 3.0;
    public const REHEARSAL = 5.0;  // verify copy + restored media + database restore files
    public const DOWNLOAD = 0.0;   // streamed straight from the destination; only the minimum free space applies

    public function required(int $packageBytes, float $factor): int
    {
        return (int) ($packageBytes * $factor) + (int) config('backup.min_free_space_mb', 200) * 1048576;
    }

    public function assertFor(int $packageBytes, string $purpose, float $factor, ?string $dir = null): void
    {
        $dir = $dir ?: BackupWorkspace::root();
        $required = $this->required($packageBytes, $factor);
        $free = @disk_free_space($dir);

        if ($free !== false && $free < $required) {
            throw new RuntimeException(sprintf(
                'Not enough free disk space to %s: %s needed, %s available.',
                $purpose, self::human($required), self::human((int) $free)
            ));
        }
    }

    public static function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $p = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 4) : 0;

        return round($bytes / (1024 ** $p), 1) . ' ' . $units[$p];
    }
}
