<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupDestinationGuard;
use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupWorkspace;
use App\Services\Backup\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read-only readiness check for Full Backup & Restore. Never creates, moves or deletes anything
 * and never prints keys or credentials.
 */
class BackupDoctorCommand extends Command
{
    protected $signature = 'backup:doctor';

    protected $description = 'Check PostgreSQL client compatibility, destination, encryption, PHP limits and disk space (read-only).';

    public function handle(DatabaseBackupService $db, BackupDestinationGuard $guard, BackupEncryptionService $enc, BackupLock $lockService, BackupHistoryService $history): int
    {
        $failures = 0;
        $row = function (string $name, string $status, string $detail) use (&$failures) {
            if ($status === 'FAIL') {
                $failures++;
            }
            $this->line(sprintf('[%-4s] %-28s %s', $status, $name, $detail));
        };

        // PostgreSQL client / server
        try {
            $server = $db->serverInfo();
            $row('Database server', 'OK', 'PostgreSQL ' . $server['version']);
            foreach (['pg_dump', 'pg_restore'] as $tool) {
                try {
                    $client = $db->clientInfo($tool);
                    $ok = $client['major'] >= $server['major'];
                    $newer = $client['major'] > $server['major'];
                    $row($tool, $ok ? ($newer ? 'WARN' : 'OK') : 'FAIL', $client['version']
                        . ($ok ? ($newer ? " (newer than server {$server['major']}: archives may not restore into older servers)" : '') : " (older than server {$server['major']})"));
                } catch (Throwable $e) {
                    $row($tool, 'FAIL', $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            $row('Database server', 'FAIL', $e->getMessage());
        }

        // Destination
        $dest = $guard->inspect();
        $row('Backup destination', $dest['ok'] ? 'OK' : 'FAIL', "disk={$dest['disk']} driver={$dest['driver']}" . ($dest['ok'] ? '' : ' - ' . implode(' ', $dest['problems'])));

        // Encryption
        try {
            $row('Encryption key', 'OK', 'key id ' . $enc->currentKeyId() . ' (' . count($enc->knownKeyIds()) . ' key(s) can decrypt)');
        } catch (Throwable $e) {
            $row('Encryption key', 'FAIL', $e->getMessage());
        }

        // PHP limits for uploading packages
        $need = (int) config('backup.max_upload_mb');
        foreach (['upload_max_filesize', 'post_max_size'] as $ini) {
            $mb = $this->toMb((string) ini_get($ini));
            $row($ini, $mb === 0 || $mb >= $need ? 'OK' : 'WARN', ini_get($ini) . " (backup upload limit is {$need}M)");
        }
        $memory = $this->toMb((string) ini_get('memory_limit'));
        $row('memory_limit', $memory === 0 || $memory >= 256 ? 'OK' : 'WARN', (string) ini_get('memory_limit') . ' (streaming keeps usage low; 256M recommended)');

        // Disk space + lock
        $free = @disk_free_space(BackupWorkspace::root());
        $row('Free temp space', $free !== false && $free > config('backup.min_free_space_mb') * 1048576 ? 'OK' : 'WARN', $free === false ? 'unknown' : round($free / 1048576) . ' MB free');
        $held = $lockService->isHeld();
        $row('Backup lock', 'OK', $held ? 'a backup is running' : 'free');
        $shared = $lockService->isShared();
        $row('Lock store', $shared || !app()->environment('production') ? 'OK' : 'WARN',
            $lockService->storeName() . ($shared ? ' (shared between instances)' : ' (per-instance: only safe with ONE application instance; set BACKUP_LOCK_STORE to a database/redis store when scaling out)'));

        $running = count(array_filter($history->all(), fn ($r) => ($r['status'] ?? '') === 'running'));
        $row('Running / interrupted', 'OK', $running . ' record(s) marked running (stale ones are cleaned automatically after ' . config('backup.stale_after_minutes') . ' min without activity)');

        $row('Retention mode', 'OK', $history->isRemote()
            ? 'remote destination: lifecycle / Object Lock enforce retention; app deletes remote packages: ' . (config('backup.retention.delete_from_remote') ? 'YES' : 'no')
            : 'local destination: backup:prune enforces retention');

        // Sandbox
        $sandbox = (array) config('backup.sandbox');
        $explicit = !empty($sandbox['host']) && !empty($sandbox['username']) && !empty($sandbox['password']);
        $row('Sandbox restore target', app()->environment('production') && !$explicit ? 'WARN' : 'OK',
            $explicit ? 'explicit sandbox server configured' : (app()->environment('production') ? 'not configured (required in production)' : 'local development default'));

        $row('Production restore', 'OK', config('backup.production_restore_enabled') ? 'flag on, but no executor exists' : 'disabled');

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function toMb(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0; // unlimited
        }
        $n = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $n * 1024,
            'M' => $n,
            'K' => (int) ($n / 1024),
            default => (int) ($n / 1048576),
        };
    }
}
