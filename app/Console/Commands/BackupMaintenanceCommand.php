<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\BackupWorkspace;
use Illuminate\Console\Command;

class BackupMaintenanceCommand extends Command
{
    protected $signature = 'backup:prune {--force : Actually delete packages outside the retention policy (default is a dry run)}
                            {--cleanup : Also remove stale temporary working directories}';

    protected $description = 'Apply the backup retention policy (dry-run unless --force) and optionally clean stale temp directories.';

    public function handle(BackupRetentionService $retention, BackupLock $backupLock, BackupHistoryService $history): int
    {
        foreach ($history->reapStale() as $id) {
            $this->warn("Marked interrupted backup {$id} as failed.");
        }

        if ($this->option('cleanup')) {
            $lock = $backupLock->acquire(60);
            if ($lock) {
                try {
                    $this->info('Removed ' . BackupWorkspace::sweep() . ' stale temporary director(ies).');
                } finally {
                    $lock->release();
                }
            } else {
                $this->warn('A backup is running; temporary directories were not touched.');
            }
        }

        $plan = $retention->plan();
        $this->line('Keep:   ' . count($plan['keep']) . ' backup(s)');
        $this->line('Delete: ' . count($plan['delete']) . ' backup(s)');
        foreach ($plan['delete'] as $id) {
            $this->line('  - ' . $id);
        }

        if (!$retention->deletionAllowed()) {
            $this->comment('Remote (S3) storage: retention is enforced by bucket lifecycle / Object Lock; the application will not delete packages there.');
        }

        if (!$this->option('force')) {
            $this->comment('Dry run. Re-run with --force to delete.');

            return self::SUCCESS;
        }

        $result = $retention->apply();
        if ($result['skipped_reason']) {
            $this->warn($result['skipped_reason']);

            return self::SUCCESS;
        }

        $this->info('Deleted ' . count($result['deleted']) . ' package(s).');
        foreach ($result['failed'] as $id => $message) {
            $this->error("Could not delete {$id}: {$message}");
        }

        return $result['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
