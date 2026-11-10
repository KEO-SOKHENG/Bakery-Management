<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Private temporary working directories for backup / restore operations.
 */
class BackupWorkspace
{
    public static function root(): string
    {
        $root = (string) config('backup.temp_dir');
        if (!is_dir($root)) {
            mkdir($root, 0700, true);
        }

        return $root;
    }

    public static function make(string $prefix = 'ws'): string
    {
        $dir = self::root() . DIRECTORY_SEPARATOR . $prefix . '_' . bin2hex(random_bytes(8));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create a temporary working directory.');
        }

        return $dir;
    }

    /** Deletes a directory only if it lives inside the backup temp root. */
    public static function destroy(?string $dir): void
    {
        if (!$dir || !is_dir($dir)) {
            return;
        }
        $root = realpath(self::root());
        $real = realpath($dir);
        if ($root && $real && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($real);
        }
    }

    /**
     * Removes stale working directories left by crashed or killed runs.
     * Call only while holding the backup lock, so anything older than the threshold is orphaned.
     *
     * @return int number of directories removed
     */
    public static function sweep(?int $maxAgeHours = null): int
    {
        $maxAgeHours = $maxAgeHours ?? (int) config('backup.temp_max_age_hours', 6);
        $cutoff = time() - max(0, $maxAgeHours) * 3600;
        $removed = 0;

        foreach (glob(self::root() . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (filemtime($dir) <= $cutoff) {
                self::destroy($dir);
                $removed++;
            }
        }

        // Leftover sandbox media from interrupted rehearsals
        $sandbox = (string) config('backup.sandbox.media_root');
        $sandboxParent = dirname($sandbox);
        if (is_dir($sandboxParent) && str_contains(str_replace(DIRECTORY_SEPARATOR, '/', $sandboxParent), '/sandbox-restore')) {
            foreach (glob($sandbox . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (filemtime($dir) <= $cutoff) {
                    File::deleteDirectory($dir);
                    $removed++;
                }
            }
        }

        return $removed;
    }

    public static function destroyFile(?string $file): void
    {
        if (!$file || !is_file($file)) {
            return;
        }
        $root = realpath(self::root());
        $real = realpath($file);
        if ($root && $real && str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
            @unlink($real);
        }
    }
}
