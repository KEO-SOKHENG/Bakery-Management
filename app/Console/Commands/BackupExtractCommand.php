<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupHistoryService;
use App\Services\Backup\BackupRedactor;
use App\Services\Backup\BackupSpaceGuard;
use App\Services\Backup\BackupVerificationService;
use App\Services\Backup\BackupWorkspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Disaster-recovery helper: decrypts a backup package, verifies it completely and extracts it into a NEW
 * directory for a manual restore (see docs/BACKUP_DISASTER_RECOVERY.md).
 *
 * Safety:
 *  - the package must pass the full verification (authentication, safe extraction, manifest, every SHA-256,
 *    database archive listing, media manifest, settings schema) before anything is written to the destination;
 *  - the destination must not exist or must be empty, and may not be inside a web-accessible directory;
 *  - nothing is ever overwritten, and nothing is restored into any database or storage by this command;
 *  - temporary files are removed on every path, and a half-written destination is removed again.
 */
class BackupExtractCommand extends Command
{
    protected $signature = 'backup:extract
                            {source : Backup id (uuid, from the history) or the path of a .bkp file}
                            {destination : A new, empty directory to extract into}';

    protected $description = 'Verify a backup package and extract it into a new directory for manual disaster recovery (read-only for databases and storage).';

    public function handle(BackupVerificationService $verification, BackupHistoryService $history, BackupSpaceGuard $space): int
    {
        $workDir = null;
        $verifyDir = null;
        $createdDestination = false;
        $destination = (string) $this->argument('destination');

        try {
            // ── Destination checks ──
            $destination = $this->resolveDestination($destination);
            $createdDestination = !is_dir($destination);

            // ── Source ──
            $source = (string) $this->argument('source');
            $workDir = BackupWorkspace::make('extract');
            $local = $workDir . DIRECTORY_SEPARATOR . 'package.bkp';

            if (Str::isUuid($source)) {
                $record = $history->find($source);
                if (!$record || !$history->packageExists($source)) {
                    throw new \RuntimeException('Backup not found in the history.');
                }
                $size = (int) ($record['package_size'] ?? 0);
                $space->assertFor($size, 'extract the package (temporary files)', BackupSpaceGuard::EXTRACT);
                $space->assertFor($size, 'extract the package (destination)', 1.0, dirname($destination));
                $in = $history->disk()->readStream($history->packagePath($source));
                $out = fopen($local, 'wb');
                stream_copy_to_stream($in, $out);
                fclose($in);
                fclose($out);
            } else {
                $real = realpath($source);
                if (!$real || !is_file($real)) {
                    throw new \RuntimeException('Package file not found.');
                }
                $size = (int) filesize($real);
                $space->assertFor($size, 'extract the package (temporary files)', BackupSpaceGuard::EXTRACT);
                $space->assertFor($size, 'extract the package (destination)', 1.0, dirname($destination));
                copy($real, $local);
            }

            // ── Full verification first ──
            $this->info('Verifying package ...');
            $verify = $verification->verifyFile($local);
            $verifyDir = $verify['work_dir'];
            foreach ($verify['steps'] as $step) {
                $this->line(sprintf('  [%-4s] %s - %s', strtoupper($step['status']), $step['name'], $step['message']));
            }
            if (!$verify['ok']) {
                $this->error('The package did not pass verification. Nothing was extracted.');

                return self::FAILURE;
            }

            // ── Copy the verified tree into the new destination, then re-check it there ──
            if (!is_dir($destination)) {
                mkdir($destination, 0700, true);
            }
            File::copyDirectory($verify['extracted_dir'], $destination);
            @chmod($destination, 0700);

            [$ok, $message] = $verification->verifyChecksums($destination);
            if (!$ok) {
                throw new \RuntimeException('Extracted files failed the checksum re-check: ' . $message);
            }
            BackupWorkspace::destroy($verify['work_dir']);

            $manifest = $verify['manifest'];
            $this->info('Extracted and verified: ' . $destination);
            $this->line('  Backup id:   ' . $manifest['backup_id']);
            $this->line('  Created:     ' . $manifest['created_at']);
            $this->line('  Database:    ' . ($manifest['database']['engine'] ?? 'PostgreSQL') . ' ' . ($manifest['database']['server_version'] ?? '') . ' (' . $manifest['database']['filename'] . ')');
            $this->line('  Media:       ' . $manifest['media']['count'] . ' file(s), prefixes ' . implode(' ', $manifest['media']['prefixes'] ?? []));
            $this->line('  Settings:    ' . ($manifest['settings']['count'] ?? 0) . ' item(s)');
            $this->newLine();
            $this->comment('Next: follow docs/BACKUP_DISASTER_RECOVERY.md. This command did not restore anything.');
            $createdDestination = false; // keep the result

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error(BackupRedactor::redact($e->getMessage()));
            if ($createdDestination && is_dir($destination)) {
                File::deleteDirectory($destination); // never leave a partial extraction behind
            }

            return self::FAILURE;
        } finally {
            BackupWorkspace::destroy($workDir);
            BackupWorkspace::destroy($verifyDir);
        }
    }

    /** The destination must be new/empty and outside every web-accessible directory. */
    private function resolveDestination(string $destination): string
    {
        if ($destination === '' || str_contains($destination, "\0")) {
            throw new \RuntimeException('Invalid destination.');
        }

        $parent = realpath(dirname($destination));
        if (!$parent || !is_dir($parent)) {
            throw new \RuntimeException('The parent directory of the destination does not exist.');
        }
        $full = $parent . DIRECTORY_SEPARATOR . basename($destination);

        if (is_link($full)) {
            throw new \RuntimeException('The destination must not be a symbolic link.');
        }
        if (file_exists($full)) {
            if (!is_dir($full) || count(array_diff(scandir($full), ['.', '..'])) > 0) {
                throw new \RuntimeException('The destination already exists and is not empty. Choose a new directory; nothing is ever overwritten.');
            }
        }

        $norm = fn (string $p) => rtrim(str_replace('\\', '/', $p), '/') . '/';
        foreach ([public_path(), storage_path('app/public'), base_path('public')] as $web) {
            if (str_starts_with($norm($full), $norm((string) realpath($web) ?: $web))) {
                throw new \RuntimeException('The destination must not be inside a web-accessible directory.');
            }
        }

        return $full;
    }
}
