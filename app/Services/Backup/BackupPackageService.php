<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Builds a Full Backup package:
 *
 *   database/bakery_database.dump     pg_dump -Fc archive
 *   media/<prefix>/...                original object paths
 *   settings/settings.json            settings export (secrets excluded)
 *   manifest.json                     component summary + media object manifest
 *   backup_metadata.json              small descriptive record (no secrets)
 *   checksums.sha256                  SHA-256 of every other file
 *
 * The directory is zipped, then encrypted (libsodium secretstream) into a single .bkp file
 * stored on the private backup disk. Files are streamed; nothing is loaded whole into memory.
 *
 * Consistency note: the database dump is transactionally consistent (pg_dump snapshot), but
 * media objects are copied afterwards, so database and media are NOT guaranteed to describe the
 * exact same instant. See docs/BACKUP_RESTORE.md.
 */
class BackupPackageService
{
    public function __construct(
        private DatabaseBackupService $database,
        private MediaBackupService $media,
        private SettingsBackupService $settings,
        private BackupEncryptionService $encryption,
        private BackupVerificationService $verification,
        private BackupHistoryService $history,
        private BackupDestinationGuard $guard,
        private MediaReferenceChecker $references,
        private BackupLock $backupLock,
    ) {
    }

    /**
     * @param array{user_id?:int|null,user_label?:string|null} $actor
     * @return array the final history record
     */
    public function create(string $backupId, array $actor = []): array
    {
        $this->history->assertId($backupId);

        $lock = $this->backupLock->acquire();
        if (!$lock) {
            $record = [
                'id' => $backupId,
                'type' => 'full',
                'created_at' => now()->toIso8601String(),
                'status' => 'failed',
                'verified' => false,
                'destination' => $this->history->destinationLabel(),
                'created_by' => $actor['user_label'] ?? null,
                'components' => [],
                'error' => 'Another backup is already running. Try again when it has finished.',
            ];
            try {
                $this->history->save($record);
            } catch (Throwable) {
                // destination may be unavailable; the caller still receives the failed record
            }
            $this->progress($backupId, $record);

            return $record;
        }

        try {
            $this->backupLock->begin($backupId);
            // Anything still "running" from a crashed earlier process can no longer be running now.
            $this->history->reapStale(true);

            return $this->runBackup($backupId, $actor);
        } finally {
            $this->backupLock->end();
            $lock->release();
        }
    }

    private function runBackup(string $backupId, array $actor): array
    {
        set_time_limit(0);

        $createdAt = now();
        $record = [
            'id' => $backupId,
            'type' => 'full',
            'created_at' => $createdAt->toIso8601String(),
            'status' => 'running',
            'verified' => false,
            'destination' => $this->history->destinationLabel(),
            'created_by' => $actor['user_label'] ?? null,
            'components' => [
                'database' => ['status' => 'pending'],
                'media' => ['status' => 'pending'],
                'settings' => ['status' => 'pending'],
                'package' => ['status' => 'pending'],
                'verification' => ['status' => 'pending'],
            ],
        ];
        $this->history->save($record);
        $this->progress($backupId, $record);

        $workDir = BackupWorkspace::make('backup');
        $pkgDir = $workDir . DIRECTORY_SEPARATOR . 'pkg';
        $zipPath = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
        $encPath = $workDir . DIRECTORY_SEPARATOR . 'package.bkp';

        try {
            // Preflight: private durable destination, stale temp cleanup, free disk space.
            $this->guard->assertPrivateAndDurable();
            BackupWorkspace::sweep();
            $this->assertFreeSpace($workDir);

            foreach (['database', 'media', 'settings'] as $d) {
                mkdir($pkgDir . DIRECTORY_SEPARATOR . $d, 0700, true);
            }

            // A. Database
            $this->mark($record, 'database', 'running');
            // Row counts, schema fingerprint and media references are read inside the same REPEATABLE READ
            // snapshot that pg_dump uses (--snapshot), so they always describe exactly what was dumped.
            $db = $this->database->dump(
                $pkgDir . '/database/bakery_database.dump',
                null,
                fn (?string $snapshotConnection) => $this->references->collectReferences($snapshotConnection)
            );
            $this->mark($record, 'database', 'completed', ['size' => $db['size']]);

            // B. Media
            $this->mark($record, 'media', 'running');
            $media = $this->media->backup($pkgDir . DIRECTORY_SEPARATOR . 'media');
            $this->mark($record, 'media', 'completed', ['count' => $media['count'], 'bytes' => $media['bytes']]);

            // C. Settings
            $this->mark($record, 'settings', 'running');
            $settings = $this->settings->export();
            file_put_contents($pkgDir . '/settings/settings.json', json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->mark($record, 'settings', 'completed', ['count' => count($settings['settings'])]);

            // Media/database reference integrity (warnings never block a backup, but are always reported)
            $references = $this->references->compare(
                is_array($db['snapshot_extra'] ?? null) ? $db['snapshot_extra'] : $this->references->collectReferences(),
                $media['objects']
            );
            $warnings = [];
            if (!empty($references['missing'])) {
                $warnings[] = count($references['missing']) . ' media file(s) referenced by the database are not in the backup.';
            }

            // Manifest + metadata
            $manifest = [
                'backup_id' => $backupId,
                'created_at' => $createdAt->toIso8601String(),
                'package_format_version' => config('backup.package_format_version'),
                'application_version' => config('backup.app_version'),
                'checksum_algorithm' => 'sha256',
                'database' => [
                    'engine' => 'PostgreSQL',
                    'server_version' => $db['server_version'],
                    'client_version' => $db['client_version'],
                    'filename' => $db['filename'],
                    'size' => $db['size'],
                    'sha256' => $db['sha256'],
                    'toc_entries' => $db['toc_entries'],
                    'snapshot_consistent' => (bool) ($db['snapshot_consistent'] ?? false),
                    'table_counts' => $db['table_counts'],
                    'schema_summary' => $db['schema_summary'],
                ],
                'media' => [
                    'source' => $media['source'],
                    'prefixes' => $media['prefixes'],
                    'count' => $media['count'],
                    'bytes' => $media['bytes'],
                    'objects' => $media['objects'],
                    'references' => [
                        'checked' => $references['references'],
                        'missing' => array_slice($references['missing'], 0, 100),
                        'missing_count' => count($references['missing']),
                        'orphans' => $references['orphans'],
                        'orphan_sample' => $references['orphan_sample'],
                        'external' => $references['external'],
                    ],
                ],
                'settings' => [
                    'included' => true,
                    'schema' => SettingsBackupService::SCHEMA,
                    'count' => count($settings['settings']),
                    'excluded_sensitive_keys' => $settings['excluded_count'],
                ],
                'encryption_key_id' => $this->encryption->currentKeyId(),
                'consistency' => 'database_snapshot_then_media',
            ];
            file_put_contents($pkgDir . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            file_put_contents($pkgDir . '/backup_metadata.json', json_encode([
                'backup_id' => $backupId,
                'created_at' => $createdAt->toIso8601String(),
                'type' => 'full',
                'application' => config('app.name'),
                'application_version' => config('backup.app_version'),
                'framework' => 'Laravel ' . app()->version(),
                'created_by' => $actor['user_label'] ?? null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->writeChecksums($pkgDir);

            // Package: zip -> encrypt -> private disk
            $this->mark($record, 'package', 'running');
            $this->zipDirectory($pkgDir, $zipPath);
            $this->encryption->encryptFile($zipPath, $encPath);
            @unlink($zipPath);

            $stream = fopen($encPath, 'rb');
            try {
                $this->history->disk()->writeStream(
                    $this->history->packagePath($backupId),
                    $stream,
                    $this->history->uploadOptions($createdAt)
                );
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $packageSize = filesize($encPath);
            $packageSha = hash_file('sha256', $encPath);
            // Make sure what is stored is complete: size always, SHA-256 by reading it back.
            $this->history->verifyStoredPackage($backupId, $packageSize, $packageSha);
            $this->mark($record, 'package', 'completed', ['size' => $packageSize]);

            // Verification of the stored artifact (marks Verified only if every step passes)
            $this->mark($record, 'verification', 'running');
            $verify = $this->verification->verifyFile($encPath);
            BackupWorkspace::destroy($verify['work_dir']);

            $verified = $verify['ok'];
            $this->mark($record, 'verification', $verified ? 'completed' : 'failed');

            $record = array_replace($record, [
                'status' => $verified ? 'completed' : 'failed',
                'verified' => $verified,
                'verified_at' => $verified ? now()->toIso8601String() : null,
                'verification_steps' => $verify['steps'],
                'filename' => basename($this->history->packagePath($backupId)),
                'package_size' => $packageSize,
                'database_size' => $db['size'],
                'media_count' => $media['count'],
                'media_bytes' => $media['bytes'],
                'settings_count' => count($settings['settings']),
                'package_sha256' => $packageSha,
                'upload_verified' => true,
                'warnings' => $warnings,
                'encryption_key_id' => $this->encryption->currentKeyId(),
                'media_references' => ['missing' => count($references['missing']), 'orphans' => $references['orphans']],
                'server_version' => $db['server_version'],
                'error' => $verified ? null : 'Verification did not pass.',
            ]);
            $this->history->save($record);
            $this->progress($backupId, $record);

            return $record;
        } catch (Throwable $e) {
            $message = BackupRedactor::redact($e->getMessage());
            Log::error('Full backup failed', ['backup_id' => $backupId, 'error' => $message]);

            $record['status'] = 'failed';
            $record['verified'] = false;
            $record['error'] = mb_substr($message, 0, 500);
            foreach ($record['components'] as $name => $component) {
                if (($component['status'] ?? '') === 'running') {
                    $record['components'][$name]['status'] = 'failed';
                }
            }
            $this->history->save($record);
            $this->progress($backupId, $record);
            // Remove a half-written package so it can never be mistaken for a valid one.
            try {
                $this->history->disk()->delete($this->history->packagePath($backupId));
            } catch (Throwable) {
            }

            return $record;
        } finally {
            BackupWorkspace::destroy($workDir);
        }
    }

    // ── Internals ──

    /** Needs about (database + media) x factor of free temp space (staging, zip and encrypted copy). */
    private function assertFreeSpace(string $dir): void
    {
        $estimate = $this->database->databaseSizeBytes() + $this->media->estimateBytes();
        $required = (int) ($estimate * (float) config('backup.free_space_factor', 3)) + (int) config('backup.min_free_space_mb', 200) * 1048576;
        $free = @disk_free_space($dir);
        if ($free !== false && $free < $required) {
            throw new RuntimeException(sprintf(
                'Not enough free disk space for the backup: %s needed, %s available.',
                self::human($required), self::human((int) $free)
            ));
        }
    }

    private static function human(int $bytes): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $p = $bytes > 0 ? min((int) floor(log($bytes, 1024)), 4) : 0;

        return round($bytes / (1024 ** $p), 1) . ' ' . $u[$p];
    }

    private function mark(array &$record, string $component, string $status, array $extra = []): void
    {
        $this->backupLock->beat();
        $record['components'][$component] = array_merge(['status' => $status], $extra);
        $this->history->save($record);
        $this->progress($record['id'], $record);
    }

    private function progress(string $id, array $record): void
    {
        $this->history->setProgress($id, [
            'status' => $record['status'],
            'components' => $record['components'],
            'verified' => $record['verified'] ?? false,
            'error' => $record['error'] ?? null,
        ]);
    }

    private function writeChecksums(string $dir): void
    {
        $lines = [];
        $base = strlen(rtrim(str_replace('\\', '/', realpath($dir)), '/')) + 1;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $file->getRealPath()), $base);
            if ($relative === 'checksums.sha256') {
                continue;
            }
            $lines[$relative] = hash_file('sha256', $file->getRealPath()) . '  ' . $relative;
        }
        ksort($lines);
        file_put_contents($dir . '/checksums.sha256', implode("\n", $lines) . "\n");
    }

    private function zipDirectory(string $dir, string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Unable to create the package archive.');
        }
        $base = strlen(rtrim(str_replace('\\', '/', realpath($dir)), '/')) + 1;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relative = substr(str_replace('\\', '/', $file->getRealPath()), $base);
                $zip->addFile($file->getRealPath(), $relative);
            }
        }
        if (!$zip->close()) {
            throw new RuntimeException('Unable to finalise the package archive.');
        }
    }
}
