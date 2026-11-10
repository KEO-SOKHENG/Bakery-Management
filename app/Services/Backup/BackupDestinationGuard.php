<?php

namespace App\Services\Backup;

use RuntimeException;

/**
 * Ensures backups can only be written to a dedicated, private, durable destination:
 * never a public disk, never the media bucket, and (in production) never an ephemeral local disk.
 */
class BackupDestinationGuard
{
    /** @return array{ok:bool,problems:array<int,string>,disk:string,driver:string} */
    public function inspect(): array
    {
        $name = (string) config('backup.disk', 'backups');
        $cfg = config("filesystems.disks.{$name}");
        $problems = [];
        $driver = (string) ($cfg['driver'] ?? '');

        if (!is_array($cfg)) {
            return ['ok' => false, 'problems' => ["Backup disk '{$name}' is not defined in config/filesystems.php."], 'disk' => $name, 'driver' => ''];
        }

        if (in_array($name, ['public', 's3', 'supabase-s3'], true)) {
            $problems[] = "Disk '{$name}' is the media/public disk and cannot be used for backups.";
        }
        if (($cfg['visibility'] ?? 'private') === 'public' || !empty($cfg['url'])) {
            $problems[] = 'The backup disk must be private (no public visibility or public URL).';
        }

        if ($driver === 'local') {
            $root = str_replace('\\', '/', (string) ($cfg['root'] ?? ''));
            foreach ([public_path(), storage_path('app/public')] as $publicRoot) {
                $publicRoot = str_replace('\\', '/', (string) $publicRoot);
                if ($root !== '' && ($root === $publicRoot || str_starts_with($root . '/', rtrim($publicRoot, '/') . '/'))) {
                    $problems[] = 'The backup folder must not be inside a web-accessible directory.';
                }
            }
            if (app()->environment('production') && !config('backup.allow_local_destination_in_production')) {
                $problems[] = 'A local disk is temporary on this host. Configure a durable private disk (e.g. BACKUP_DISK=backups-s3).';
            }
        } elseif (in_array($driver, ['s3', 's3-noacl', 'supabase-s3'], true)) {
            $bucket = (string) ($cfg['bucket'] ?? '');
            if ($bucket === '') {
                $problems[] = 'The backup bucket is not configured.';
            }
            foreach (['s3', 'supabase-s3'] as $mediaDisk) {
                $mediaBucket = (string) config("filesystems.disks.{$mediaDisk}.bucket", '');
                if ($bucket !== '' && $mediaBucket !== '' && $bucket === $mediaBucket
                    && (string) ($cfg['endpoint'] ?? '') === (string) config("filesystems.disks.{$mediaDisk}.endpoint", '')) {
                    $problems[] = 'The backup bucket must not be the media bucket.';
                }
            }
            if (empty($cfg['key']) || empty($cfg['secret'])) {
                $problems[] = 'Backup storage credentials are not configured.';
            }
        } else {
            $problems[] = "Unsupported backup disk driver '{$driver}'.";
        }

        return ['ok' => empty($problems), 'problems' => $problems, 'disk' => $name, 'driver' => $driver];
    }

    public function assertPrivateAndDurable(): void
    {
        $result = $this->inspect();
        if (!$result['ok']) {
            throw new RuntimeException('Backup destination rejected: ' . implode(' ', $result['problems']));
        }
    }
}
