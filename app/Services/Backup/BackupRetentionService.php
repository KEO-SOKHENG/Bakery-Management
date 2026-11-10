<?php

namespace App\Services\Backup;

use Carbon\Carbon;

/**
 * Grandfather-father-son style retention for verified backup packages.
 * Keeps: the newest N, one per day for D days, one per week for W weeks, one per month for M months.
 * Never touches running/failed records, and never deletes the single newest verified backup.
 */
class BackupRetentionService
{
    public function __construct(private BackupHistoryService $history)
    {
    }

    /**
     * @return array{keep:array<int,string>,delete:array<int,string>}
     */
    public function plan(?Carbon $now = null): array
    {
        $now = $now ?: now();
        $policy = (array) config('backup.retention');

        $candidates = array_values(array_filter($this->history->all(), fn ($r) =>
            ($r['status'] ?? '') === 'completed' && !empty($r['verified']) && empty($r['pruned_at'])
        )); // already newest first

        $keep = [];
        foreach (array_slice($candidates, 0, max(1, (int) ($policy['keep_last'] ?? 3))) as $r) {
            $keep[$r['id']] = true;
        }

        $buckets = [
            ['days', (int) ($policy['keep_daily'] ?? 7), 'Y-m-d'],
            ['weeks', (int) ($policy['keep_weekly'] ?? 4), 'o-W'],
            ['months', (int) ($policy['keep_monthly'] ?? 12), 'Y-m'],
        ];
        foreach ($buckets as [$unit, $count, $format]) {
            $seen = [];
            foreach ($candidates as $r) {
                $at = Carbon::parse($r['created_at']);
                $age = match ($unit) {
                    'days' => $at->diffInDays($now),
                    'weeks' => $at->diffInWeeks($now),
                    default => $at->diffInMonths($now),
                };
                if ($age >= $count) {
                    continue;
                }
                $label = $at->format($format);
                if (!isset($seen[$label])) { // newest in the bucket wins
                    $seen[$label] = true;
                    $keep[$r['id']] = true;
                }
            }
        }

        $delete = [];
        foreach ($candidates as $r) {
            if (!isset($keep[$r['id']])) {
                $delete[] = $r['id'];
            }
        }

        return ['keep' => array_keys($keep), 'delete' => $delete];
    }

    /**
     * Whether this application may delete packages from the backup destination.
     * Remote (S3) destinations are expired by bucket lifecycle rules / Object Lock; the application key
     * normally has no delete permission, so deleting is off unless backup.retention.delete_from_remote is set.
     */
    public function deletionAllowed(): bool
    {
        return !$this->history->isRemote() || (bool) config('backup.retention.delete_from_remote', false);
    }

    /**
     * Deletes packages in the plan. A record is marked pruned only when the delete succeeded AND the package is
     * really gone from the destination. Failures (denied permission, Object Lock, network) leave the record
     * untouched, are recorded on it (prune_error) and reported, and never stop the remaining packages.
     *
     * @return array{deleted:array<int,string>,failed:array<string,string>,skipped_reason:?string}
     */
    public function apply(?Carbon $now = null): array
    {
        if (!$this->deletionAllowed()) {
            return [
                'deleted' => [],
                'failed' => [],
                'skipped_reason' => 'Retention for remote (S3) backup storage is handled by the bucket lifecycle / Object Lock rules. '
                    . 'The application does not delete remote packages (set BACKUP_PRUNE_REMOTE=true only if its key has delete rights).',
            ];
        }

        $plan = $this->plan($now);
        $deleted = [];
        $failed = [];

        foreach ($plan['delete'] as $id) {
            try {
                $path = $this->history->packagePath($id);
                $this->history->disk()->delete($path);
                if ($this->history->disk()->exists($path)) {
                    throw new \RuntimeException('The package still exists after delete (it may be retained by Object Lock or versioning).');
                }
                $this->history->update($id, [
                    'pruned_at' => now()->toIso8601String(),
                    'status' => 'pruned',
                    'verified' => false,
                    'prune_error' => null,
                ]);
                $deleted[] = $id;
            } catch (\Throwable $e) {
                $message = mb_substr(BackupRedactor::redact($e->getMessage()), 0, 300);
                $failed[$id] = $message;
                try {
                    $this->history->update($id, ['prune_error' => $message, 'prune_failed_at' => now()->toIso8601String()]);
                } catch (\Throwable) {
                    // history itself may be read-only; the failure is still reported to the caller
                }
            }
        }

        return ['deleted' => $deleted, 'failed' => $failed, 'skipped_reason' => null];
    }
}
