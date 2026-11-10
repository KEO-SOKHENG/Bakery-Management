<?php

namespace App\Services\Backup;

use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Verifies a Full Backup package: format, safe extraction, manifest, checksums,
 * database archive, media manifest and settings schema.
 */
class BackupVerificationService
{
    public const TOP_LEVEL_FILES = ['manifest.json', 'checksums.sha256', 'backup_metadata.json'];
    public const ALLOWED_DIRS = ['database/', 'media/', 'settings/'];

    public function __construct(
        private BackupEncryptionService $encryption,
        private DatabaseBackupService $database,
        private SettingsBackupService $settings,
    ) {
    }

    /**
     * @return array{ok:bool, steps:array<int,array{name:string,status:string,message:string}>, manifest:?array, extracted_dir:?string, work_dir:?string}
     *   The caller owns (and must destroy) work_dir via BackupWorkspace::destroy().
     */
    public function verifyFile(string $packagePath): array
    {
        // Large packages take minutes to decrypt, extract and checksum: lift PHP's default web time limit.
        @set_time_limit(0);

        $steps = [];
        $manifest = null;
        $workDir = null;
        $zipFile = null;
        $ok = false;

        $add = function (string $name, bool $pass, string $message = '', bool $warn = false) use (&$steps) {
            $steps[] = ['name' => $name, 'status' => $pass ? ($warn ? 'warn' : 'pass') : 'fail', 'message' => $message];

            return $pass;
        };

        try {
            // 1. File type and size
            $maxBytes = (int) config('backup.max_upload_mb') * 1048576;
            if (!is_file($packagePath) || filesize($packagePath) === 0) {
                $add('Package file', false, 'File is missing or empty.');
                return $this->result(false, $steps, null, null);
            }
            if ($maxBytes > 0 && filesize($packagePath) > $maxBytes * 8) {
                $add('Package size', false, 'File is larger than the supported limit.');
                return $this->result(false, $steps, null, null);
            }

            $workDir = BackupWorkspace::make('verify');

            if ($this->encryption->isEncrypted($packagePath)) {
                $zipFile = $workDir . DIRECTORY_SEPARATOR . 'package.zip';
                $this->encryption->decryptFile($packagePath, $zipFile);
                $add('Decryption', true, 'Package decrypted and authenticated.');
            } else {
                $add('Package type', false, 'Only encrypted Bakery backup packages (.bkp) are supported.');
                return $this->result(false, $steps, null, $workDir);
            }

            // 2. Safe extraction
            $extractDir = $workDir . DIRECTORY_SEPARATOR . 'pkg';
            mkdir($extractDir, 0700, true);
            $this->safeExtract($zipFile, $extractDir);
            @unlink($zipFile);
            $add('Safe extraction', true, 'All entries passed path and size checks.');

            // 3. Manifest
            $manifest = $this->readJson($extractDir . '/manifest.json');
            $metadata = $this->readJson($extractDir . '/backup_metadata.json');
            $required = ['backup_id', 'created_at', 'package_format_version', 'checksum_algorithm', 'database', 'media', 'settings'];
            $missing = array_values(array_filter($required, fn ($k) => !array_key_exists($k, $manifest)));
            if (!$add('Manifest', empty($missing), empty($missing) ? 'Manifest is complete.' : 'Missing: ' . implode(', ', $missing))) {
                return $this->result(false, $steps, $manifest, $extractDir);
            }
            if (!$add('Package version', $manifest['package_format_version'] === config('backup.package_format_version'),
                'Format ' . $manifest['package_format_version'])) {
                return $this->result(false, $steps, $manifest, $extractDir);
            }
            if (($manifest['checksum_algorithm'] ?? '') !== 'sha256') {
                $add('Checksum algorithm', false, 'Unsupported algorithm.');
                return $this->result(false, $steps, $manifest, $extractDir);
            }
            $add('Metadata', ($metadata['backup_id'] ?? null) === $manifest['backup_id'], 'Backup ID matches manifest.');

            // 4. Checksums
            [$checksOk, $checksMessage] = $this->verifyChecksums($extractDir);
            if (!$add('Checksums (SHA-256)', $checksOk, $checksMessage)) {
                return $this->result(false, $steps, $manifest, $extractDir);
            }

            // 5. Required components
            $dbFile = $extractDir . '/database/' . basename((string) ($manifest['database']['filename'] ?? ''));
            $hasDb = is_file($dbFile) && hash_equals((string) ($manifest['database']['sha256'] ?? ''), hash_file('sha256', $dbFile));
            $add('Database component', $hasDb, $hasDb ? 'Database archive present and matches manifest.' : 'Database archive missing or altered.');

            $mediaObjects = (array) ($manifest['media']['objects'] ?? []);
            $mediaProblems = $this->verifyMedia($extractDir, $mediaObjects, $manifest['media']);
            $add('Media component', empty($mediaProblems), empty($mediaProblems)
                ? count($mediaObjects) . ' media object(s) verified.'
                : implode('; ', array_slice($mediaProblems, 0, 3)));

            $settingsPayload = is_file($extractDir . '/settings/settings.json') ? $this->readJson($extractDir . '/settings/settings.json') : null;
            $settingsErrors = $settingsPayload === null ? ['settings.json is missing.'] : $this->settings->validate($settingsPayload);
            $add('Settings component', empty($settingsErrors), empty($settingsErrors) ? 'Settings file is valid.' : implode(' ', array_slice($settingsErrors, 0, 2)));

            // 6. Database archive integrity (pg_restore --list)
            if ($hasDb) {
                try {
                    $toc = $this->database->listArchive($dbFile);
                    $expected = (int) ($manifest['database']['toc_entries'] ?? 0);
                    $add('Database archive integrity', $expected === 0 || $toc === $expected, "{$toc} archive entries readable.");
                } catch (Throwable $e) {
                    $add('Database archive integrity', false, BackupRedactor::redact($e->getMessage()));
                }
            }

            $refs = (array) ($manifest['media']['references'] ?? []);
            $missingRefs = (int) ($refs['missing_count'] ?? 0);
            $add('Media references', true, $missingRefs > 0
                ? "{$missingRefs} media file(s) referenced by the database were not found when the backup was made."
                : 'Every media file referenced by the database is in the backup.', $missingRefs > 0);

            $ok = collect($steps)->every(fn ($s) => $s['status'] !== 'fail');

            return $this->result($ok, $steps, $manifest, $extractDir);
        } catch (Throwable $e) {
            $add('Verification', false, BackupRedactor::redact($e->getMessage()));

            return $this->result(false, $steps, $manifest, $workDir ? ($workDir . DIRECTORY_SEPARATOR . 'pkg') : null);
        }
    }

    /** Human-friendly preview built only from verified manifest data. */
    public function preview(array $manifest): array
    {
        return [
            'backup_id' => $manifest['backup_id'] ?? null,
            'created_at' => $manifest['created_at'] ?? null,
            'app_version' => $manifest['application_version'] ?? null,
            'database_engine' => ($manifest['database']['engine'] ?? 'PostgreSQL') . ' ' . ($manifest['database']['server_version'] ?? ''),
            'database_size' => (int) ($manifest['database']['size'] ?? 0),
            'media_count' => (int) ($manifest['media']['count'] ?? 0),
            'media_bytes' => (int) ($manifest['media']['bytes'] ?? 0),
            'media_prefixes' => $manifest['media']['prefixes'] ?? [],
            'settings_included' => !empty($manifest['settings']['included']),
            'settings_count' => (int) ($manifest['settings']['count'] ?? 0),
        ];
    }

    // ── Internals ──

    private function result(bool $ok, array $steps, ?array $manifest, ?string $dir): array
    {
        $work = $dir && basename($dir) === 'pkg' ? dirname($dir) : $dir;

        return ['ok' => $ok, 'steps' => $steps, 'manifest' => $manifest, 'extracted_dir' => $dir, 'work_dir' => $work];
    }

    /** Rejects traversal, absolute paths, symlinks and oversized archives; extracts via streams. */
    public function safeExtract(string $zipFile, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== true) {
            throw new RuntimeException('Package archive is not a valid ZIP file.');
        }

        try {
            if ($zip->numFiles > (int) config('backup.max_entries')) {
                throw new RuntimeException('Package contains too many entries.');
            }

            $limit = (int) config('backup.max_extracted_mb') * 1048576;
            $total = 0;
            $realDest = realpath($destination);

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (str_ends_with($name, '/')) {
                    continue; // directory entries are recreated from file paths
                }
                $this->assertSafeEntryName($name);

                $stat = $zip->statIndex($i);
                $opsys = null;
                $attr = null;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attr) && $opsys === ZipArchive::OPSYS_UNIX
                    && (($attr >> 16) & 0170000) === 0120000) {
                    throw new RuntimeException('Symbolic links are not allowed in backup packages.');
                }

                $target = $realDest . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $name);
                if (!is_dir(dirname($target))) {
                    mkdir(dirname($target), 0700, true);
                }
                $realParent = realpath(dirname($target));
                if (!$realParent || !str_starts_with($realParent . DIRECTORY_SEPARATOR, $realDest . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Package entry escapes the extraction directory.');
                }

                $in = $zip->getStream($name);
                if (!$in) {
                    throw new RuntimeException('Unable to read package entry.');
                }
                $out = fopen($target, 'wb');
                try {
                    while (!feof($in)) {
                        $chunk = fread($in, 1048576);
                        if ($chunk === false || $chunk === '') {
                            continue;
                        }
                        $total += strlen($chunk);
                        if ($limit > 0 && $total > $limit) {
                            throw new RuntimeException('Package exceeds the maximum extracted size.');
                        }
                        fwrite($out, $chunk);
                    }
                } finally {
                    fclose($in);
                    fclose($out);
                }
            }
        } finally {
            $zip->close();
        }
    }

    public function assertSafeEntryName(string $name): void
    {
        if ($name === ''
            || str_contains($name, "\0")
            || str_contains($name, '\\')
            || str_starts_with($name, '/')
            || preg_match('#^[A-Za-z]:#', $name)
            || preg_match('#(^|/)\.\.(/|$)#', $name)
            || preg_match('#(^|/)\./#', $name)) {
            throw new RuntimeException('Unsafe path in package rejected.');
        }

        $allowed = in_array($name, self::TOP_LEVEL_FILES, true);
        foreach (self::ALLOWED_DIRS as $dir) {
            if (str_starts_with($name, $dir)) {
                $allowed = true;
            }
        }
        if (!$allowed) {
            throw new RuntimeException('Unexpected file in package: ' . mb_substr($name, 0, 60));
        }
    }

    /** @return array{0:bool,1:string} */
    public function verifyChecksums(string $dir): array
    {
        $file = $dir . '/checksums.sha256';
        if (!is_file($file)) {
            return [false, 'checksums.sha256 is missing.'];
        }

        $listed = [];
        foreach (preg_split('/\r?\n/', trim((string) file_get_contents($file))) as $line) {
            if ($line === '') {
                continue;
            }
            if (!preg_match('/^([a-f0-9]{64})  (.+)$/', $line, $m)) {
                return [false, 'checksums.sha256 is malformed.'];
            }
            $listed[$m[2]] = $m[1];
        }

        foreach ($listed as $relative => $hash) {
            $this->assertSafeEntryName($relative);
            $path = $dir . '/' . $relative;
            if (!is_file($path)) {
                return [false, "Listed file is missing: {$relative}"];
            }
            if (!hash_equals($hash, hash_file('sha256', $path))) {
                return [false, "Checksum mismatch: {$relative}"];
            }
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $base = strlen(rtrim(str_replace('\\', '/', realpath($dir)), '/')) + 1;
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $relative = substr(str_replace('\\', '/', $fileInfo->getRealPath()), $base);
            if ($relative !== 'checksums.sha256' && !isset($listed[$relative])) {
                return [false, "Unlisted file in package: {$relative}"];
            }
        }

        return [true, count($listed) . ' file(s) match their SHA-256 checksums.'];
    }

    /** @return array<int,string> */
    private function verifyMedia(string $dir, array $objects, array $media): array
    {
        $problems = [];
        $bytes = 0;
        foreach ($objects as $object) {
            $path = (string) ($object['path'] ?? '');
            try {
                MediaBackupService::assertSafeRelativePath($path);
            } catch (Throwable) {
                $problems[] = 'unsafe media path';
                continue;
            }
            $full = $dir . '/media/' . $path;
            if (!is_file($full)) {
                $problems[] = "missing {$path}";
                continue;
            }
            if (!hash_equals((string) ($object['sha256'] ?? ''), hash_file('sha256', $full)) || (int) ($object['size'] ?? -1) !== filesize($full)) {
                $problems[] = "altered {$path}";
                continue;
            }
            $bytes += filesize($full);
        }
        if ((int) ($media['count'] ?? -1) !== count($objects)) {
            $problems[] = 'object count mismatch';
        }
        if ((int) ($media['bytes'] ?? -1) !== $bytes && empty($problems)) {
            $problems[] = 'total size mismatch';
        }

        return $problems;
    }

    private function readJson(string $file): array
    {
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException(basename($file) . ' is missing or not valid JSON.');
        }

        return $data;
    }
}
