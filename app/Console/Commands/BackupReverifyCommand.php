<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupLock;
use App\Services\Backup\BackupRedactor;
use App\Services\Backup\BackupSpaceGuard;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use Illuminate\Console\Command;
use Throwable;

/**
 * Re-verifies stored backup packages (bit rot, accidental overwrite, truncated uploads, key problems).
 * Read-only for the packages: a failing package is flagged in the history, never deleted or modified.
 * Run it on a schedule, for example weekly:  php artisan backup:reverify --limit=3
 */
class BackupReverifyCommand extends Command
{
    protected $signature = 'backup:reverify
                            {id? : A single backup id (default: the least recently verified ones)}
                            {--limit=3 : Maximum number of packages to check per run}';

    protected $description = 'Re-verify stored backup packages (authentication, checksums, database archive) and flag any that fail.';

    public function handle(BackupHistoryService $history, BackupVerificationService $verification, BackupSpaceGuard $space, BackupLock $lock): int
    {
        $history->reapStale();

        $records = array_values(array_filter($history->all(), fn ($r) => ($r['status'] ?? '') === 'completed' && empty($r['pruned_at'])));
        if ($this->argument('id')) {
            $records = array_values(array_filter($records, fn ($r) => $r['id'] === $this->argument('id')));
        } else {
            // least recently verified first
            usort($records, fn ($a, $b) => strcmp((string) ($a['last_reverified_at'] ?? ''), (string) ($b['last_reverified_at'] ?? '')));
            $records = array_slice($records, 0, max(1, (int) $this->option('limit')));
        }

        if (empty($records)) {
            $this->info('No stored backups to verify.');

            return self::SUCCESS;
        }

        $failures = 0;
        foreach ($records as $record) {
            $id = $record['id'];
            $this->line("Verifying {$id} ...");
            $work = null;
            try {
                if (!$history->packageExists($id)) {
                    throw new \RuntimeException('The package is missing from the destination.');
                }
                $space->assertFor((int) ($record['package_size'] ?? 0), 'verify the stored package', BackupSpaceGuard::VERIFY);

                $work = BackupWorkspace::make('reverify');
                $local = $work . DIRECTORY_SEPARATOR . 'package.bkp';
                $in = $history->disk()->readStream($history->packagePath($id));
                $out = fopen($local, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);

                // The stored object must still be exactly what was uploaded.
                if (!empty($record['package_sha256']) && !hash_equals($record['package_sha256'], hash_file('sha256', $local))) {
                    throw new \RuntimeException('The stored package no longer matches the checksum recorded at upload.');
                }

                $verify = $verification->verifyFile($local);
                BackupWorkspace::destroy($verify['work_dir']);
                if (!$verify['ok']) {
                    $bad = collect($verify['steps'])->firstWhere('status', 'fail');
                    throw new \RuntimeException(($bad['name'] ?? 'Verification') . ': ' . ($bad['message'] ?? 'failed'));
                }

                $history->update($id, ['verified' => true, 'last_reverified_at' => now()->toIso8601String(), 'reverify_ok' => true, 'verification_error' => null]);
                $this->info('  OK');
            } catch (Throwable $e) {
                $failures++;
                $message = mb_substr(BackupRedactor::redact($e->getMessage()), 0, 300);
                $history->update($id, ['verified' => false, 'last_reverified_at' => now()->toIso8601String(), 'reverify_ok' => false, 'verification_error' => $message]);
                $this->error("  FAILED: {$message}");
            } finally {
                BackupWorkspace::destroy($work);
            }
        }

        $this->line($failures === 0 ? 'All checked packages are intact.' : "{$failures} package(s) failed and were flagged as not verified.");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
