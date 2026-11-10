<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupPackageService;
use App\Services\Backup\SandboxRestoreService;
use Illuminate\Console\Command;

class FullBackupCommand extends Command
{
    protected $signature = 'backup:full {--rehearse : Also run a sandbox restore rehearsal on the new backup}';

    protected $description = 'Create a verified Full Backup package (database + media + settings) on the private backup disk.';

    public function handle(BackupPackageService $packages, BackupHistoryService $history, SandboxRestoreService $sandbox): int
    {
        $id = $history->newId();
        $this->info("Creating full backup {$id} ...");
        $record = $packages->create($id, ['user_label' => 'console']);

        foreach ($record['components'] as $name => $component) {
            $this->line(sprintf('  %-13s %s', $name, $component['status']));
        }

        if (empty($record['verified'])) {
            $this->error('Backup did not verify: ' . ($record['error'] ?? 'unknown error'));

            return self::FAILURE;
        }

        $this->info(sprintf('Verified. package=%s bytes, media=%d object(s)', $record['package_size'], $record['media_count']));

        if ($this->option('rehearse')) {
            $this->info('Running sandbox restore rehearsal ...');
            $report = $sandbox->rehearse($id);
            foreach ($report['steps'] as $step) {
                $this->line(sprintf('  [%s] %s - %s', strtoupper($step['status']), $step['name'], $step['message']));
            }
            $this->line('Cleanup: ' . json_encode($report['cleanup'] ?? []));
            if (!$report['ok']) {
                $this->error('Rehearsal FAILED.');

                return self::FAILURE;
            }
            $this->info('Rehearsal passed.');
        }

        return self::SUCCESS;
    }
}
