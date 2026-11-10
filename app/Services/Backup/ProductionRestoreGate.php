<?php

namespace App\Services\Backup;

/**
 * Production restore authorization gate.
 *
 * Production restore is DISABLED. This class only reports which authorization conditions are
 * unmet. There is intentionally no code path anywhere in this application that restores a
 * backup into the Production database or storage; enabling it requires a separate, explicit
 * implementation approved by the system owner.
 */
class ProductionRestoreGate
{
    public function __construct(private BackupHistoryService $history)
    {
    }

    /**
     * @return array{allowed:false, executable:false, conditions:array<int,array{key:string,label:string,met:bool}>}
     */
    public function evaluate(?string $backupId): array
    {
        $record = $backupId ? $this->history->find($backupId) : null;

        $conditions = [
            ['key' => 'enabled', 'label' => 'Production restore is enabled by the system owner', 'met' => (bool) config('backup.production_restore_enabled')],
            ['key' => 'owner_approval', 'label' => 'Explicit system-owner approval for this restore', 'met' => false],
            ['key' => 'verified', 'label' => 'Backup package is verified', 'met' => (bool) ($record['verified'] ?? false)],
            ['key' => 'rehearsal', 'label' => 'A sandbox restore rehearsal succeeded', 'met' => (bool) ($record['rehearsal']['ok'] ?? false)],
            ['key' => 'fresh_backup', 'label' => 'A current Production backup exists', 'met' => $this->hasRecentBackup()],
            ['key' => 'rollback_plan', 'label' => 'Rollback plan is documented', 'met' => false],
            ['key' => 'target_confirmed', 'label' => 'Exact database and storage target confirmed', 'met' => false],
        ];

        // Even if every condition were met, no executor exists in this codebase.
        return ['allowed' => false, 'executable' => false, 'conditions' => $conditions];
    }

    private function hasRecentBackup(): bool
    {
        foreach ($this->history->all() as $record) {
            if (($record['type'] ?? '') === 'full' && !empty($record['verified'])
                && strtotime((string) ($record['created_at'] ?? '')) > time() - 86400) {
                return true;
            }
        }

        return false;
    }
}
