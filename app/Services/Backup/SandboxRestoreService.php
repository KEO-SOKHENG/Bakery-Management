<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Restore REHEARSAL into an isolated sandbox. The Production database and media are never a
 * target: the sandbox database name must carry the sandbox prefix, must differ from the
 * application database, must not already exist, and in a production environment a separate
 * sandbox server must be configured explicitly.
 */
class SandboxRestoreService
{
    private const SANDBOX_CONNECTION = 'backup_sandbox';
    private const ADMIN_CONNECTION = 'backup_sandbox_admin';

    public function __construct(
        private DatabaseBackupService $database,
        private MediaBackupService $media,
        private SettingsBackupService $settings,
        private BackupVerificationService $verification,
        private BackupHistoryService $history,
        private MediaReferenceChecker $references,
        private BackupSpaceGuard $space,
    ) {
    }

    /**
     * Resolve and validate the sandbox target. Throws when it is not provably isolated.
     *
     * @return array{host:string,port:int|string,username:string,password:?string,database:string,media_root:string}
     */
    public function resolveTarget(string $backupId): array
    {
        $app = (array) config('database.connections.' . config('database.default'));
        $cfg = (array) config('backup.sandbox');

        $host = $cfg['host'] ?: null;
        $isProductionEnv = app()->environment('production');

        if ($isProductionEnv) {
            // Production environment: everything must be explicit; never fall back to the application's credentials.
            foreach (['host', 'username', 'password'] as $required) {
                if (empty($cfg[$required])) {
                    throw new RuntimeException('Sandbox restore is unavailable: no isolated sandbox database server is configured for this environment'
                        . " (BACKUP_SANDBOX_DB_" . strtoupper($required) . ' is missing).');
                }
            }
            if ($host === (string) ($app['host'] ?? '') && ($cfg['port'] ?: ($app['port'] ?? 5432)) == ($app['port'] ?? 5432)) {
                throw new RuntimeException('Sandbox server must be different from the Production database server.');
            }
            if ((string) $cfg['username'] === (string) ($app['username'] ?? '') && (string) $cfg['password'] === (string) ($app['password'] ?? '')) {
                throw new RuntimeException('Sandbox credentials must be different from the Production database credentials.');
            }
        } elseif ($host === null) {
            // Local development only: reuse the local server, and only when it really is local.
            if (!in_array((string) ($app['host'] ?? ''), ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new RuntimeException('Sandbox restore is unavailable: the application database is not a local development server and no sandbox server is configured.');
            }
            $host = (string) $app['host'];
        }

        $database = $cfg['database_prefix'] . strtolower(substr(str_replace('-', '', $backupId), 0, 12)) . '_' . date('His');
        if (!preg_match('/^[a-z0-9_]{1,60}$/', $database) || !str_starts_with($database, (string) $cfg['database_prefix'])) {
            throw new RuntimeException('Invalid sandbox database name.');
        }
        if ($database === ($app['database'] ?? null)) {
            throw new RuntimeException('Sandbox database must differ from the application database.');
        }

        return [
            'host' => $host,
            'port' => $cfg['port'] ?: ($app['port'] ?? 5432),
            'username' => $cfg['username'] ?: ($isProductionEnv ? '' : (string) ($app['username'] ?? '')),
            'password' => $cfg['password'] ?: ($isProductionEnv ? null : ($app['password'] ?? null)),
            'database' => $database,
            'media_root' => rtrim((string) $cfg['media_root'], '/\\') . DIRECTORY_SEPARATOR . substr($backupId, 0, 8),
        ];
    }

    /**
     * Run the complete rehearsal and return a report. The sandbox is removed afterwards
     * (when backup.sandbox.drop_after is true).
     */
    public function rehearse(string $backupId): array
    {
        set_time_limit(0);
        $record = $this->history->find($backupId);
        $report = [
            'backup_id' => $backupId,
            'started_at' => now()->toIso8601String(),
            'target' => null,
            'steps' => [],
            'ok' => false,
        ];
        $step = function (string $name, bool $pass, string $message = '', bool $warn = false) use (&$report): bool {
            $report['steps'][] = ['name' => $name, 'status' => $pass ? ($warn ? 'warn' : 'pass') : 'fail', 'message' => $message];

            return $pass;
        };

        $workDir = null;
        $target = null;
        $created = false;

        try {
            if (!$record || !$this->history->packageExists($backupId)) {
                throw new RuntimeException('Backup package was not found.');
            }
            if (empty($record['verified'])) {
                throw new RuntimeException('Only a verified backup can be restored to the sandbox.');
            }

            $this->space->assertFor((int) ($record['package_size'] ?? 0), 'run the sandbox rehearsal', BackupSpaceGuard::REHEARSAL);

            // Fresh verification of the stored artifact.
            $workDir = BackupWorkspace::make('rehearsal');
            $local = $workDir . DIRECTORY_SEPARATOR . 'package.bkp';
            $in = $this->history->disk()->readStream($this->history->packagePath($backupId));
            $out = fopen($local, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            $verify = $this->verification->verifyFile($local);
            $step('Package verification', $verify['ok'], $verify['ok'] ? 'All checks passed.' : 'Verification failed.');
            if (!$verify['ok']) {
                BackupWorkspace::destroy($verify['work_dir']);
                throw new RuntimeException('Package verification failed; rehearsal aborted.');
            }
            $manifest = $verify['manifest'];
            $extracted = $verify['extracted_dir'];

            // Target identity
            $target = $this->resolveTarget($backupId);
            $report['target'] = ['host_role' => 'sandbox', 'database' => $target['database'], 'media_root' => basename(dirname($target['media_root'])) . '/' . basename($target['media_root'])];
            $step('Sandbox identity', true, 'Isolated database "' . $target['database'] . '" (not the application database).');

            $this->registerConnections($target);
            $exists = DB::connection(self::ADMIN_CONNECTION)->selectOne('select 1 as e from pg_database where datname = ?', [$target['database']]);
            if ($exists) {
                throw new RuntimeException('Sandbox database already exists; refusing to overwrite it.');
            }
            DB::connection(self::ADMIN_CONNECTION)->statement('CREATE DATABASE "' . $target['database'] . '"');
            $created = true;
            $existing = DB::connection(self::SANDBOX_CONNECTION)->selectOne("select count(*) c from information_schema.tables where table_schema = 'public'");
            $step('Sandbox is empty', (int) $existing->c === 0, 'New empty database created.');

            // Database restore
            $this->database->restoreInto($extracted . '/database/' . $manifest['database']['filename'], $this->sandboxConnectionConfig($target));
            $step('Database restored', true, 'pg_restore completed without errors.');

            // Database verification
            $counts = $this->database->tableCounts(self::SANDBOX_CONNECTION);
            $expected = (array) $manifest['database']['table_counts'];
            $diffs = [];
            foreach ($expected as $table => $n) {
                if (!array_key_exists($table, $counts)) {
                    $diffs[] = "{$table}: missing";
                } elseif ((int) $counts[$table] !== (int) $n) {
                    $diffs[] = "{$table}: {$counts[$table]} != {$n}";
                }
            }
            foreach (array_diff_key($counts, $expected) as $table => $n) {
                $diffs[] = "{$table}: unexpected table";
            }
            $step('Tables and row counts', empty($diffs), empty($diffs)
                ? count($expected) . ' tables restored with matching row counts.'
                : implode('; ', array_slice($diffs, 0, 4)));

            $schema = $this->database->schemaSummary(self::SANDBOX_CONNECTION);
            $expectedSchema = (array) ($manifest['database']['schema_summary'] ?? []);
            $schemaDiff = array_keys(array_filter($expectedSchema, fn ($v, $k) => ($schema[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));
            $step('Constraints, indexes and sequences', empty($schemaDiff), empty($schemaDiff)
                ? "{$schema['foreign_keys']} foreign keys, {$schema['indexes']} indexes, {$schema['sequences']} sequences preserved."
                : 'Differences: ' . implode(', ', $schemaDiff));

            // Media restore into the isolated sandbox destination
            $mediaResult = $this->media->restoreTo((array) $manifest['media']['objects'], $extracted . DIRECTORY_SEPARATOR . 'media', $target['media_root']);
            $mediaOk = empty($mediaResult['mismatches']) && $mediaResult['restored'] === (int) $manifest['media']['count'];
            $step('Media restored and checksums verified', $mediaOk, $mediaOk
                ? "{$mediaResult['restored']} object(s) restored; SHA-256 verified."
                : 'Problems: ' . implode('; ', array_slice($mediaResult['mismatches'], 0, 3)));

            // Settings import
            $payload = json_decode((string) file_get_contents($extracted . '/settings/settings.json'), true);
            $imported = $this->settings->importAndVerify(self::SANDBOX_CONNECTION, (array) ($payload['settings'] ?? []));
            $step('Settings import', empty($imported['mismatches']), empty($imported['mismatches'])
                ? "{$imported['imported']} setting(s) imported and verified."
                : 'Mismatched keys: ' . implode(', ', array_slice($imported['mismatches'], 0, 5)));

            // Database image references (restored database) vs restored media files
            $restoredObjects = [];
            foreach ((array) $manifest['media']['objects'] as $object) {
                if (is_file($target['media_root'] . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $object['path']))) {
                    $restoredObjects[] = ['path' => $object['path']];
                }
            }
            $evaluation = $this->evaluateReferences(
                $this->references->collectReferences(self::SANDBOX_CONNECTION),
                $restoredObjects,
                (array) ($manifest['media']['references']['missing'] ?? [])
            );
            $report['references'] = $evaluation['report'];
            $step('Database image references', $evaluation['status'] !== 'fail', $evaluation['message'], $evaluation['status'] === 'warn');

            BackupWorkspace::destroy($verify['work_dir']);
            $report['ok'] = collect($report['steps'])->every(fn ($s) => $s['status'] !== 'fail');
        } catch (Throwable $e) {
            $message = BackupRedactor::redact($e->getMessage());
            Log::warning('Sandbox restore rehearsal failed', ['backup_id' => $backupId, 'error' => $message]);
            $step('Rehearsal', false, mb_substr($message, 0, 400));
            $report['ok'] = false;
        } finally {
            $report['cleanup'] = $this->cleanup($target, $created);
            BackupWorkspace::destroy($workDir);
            $report['finished_at'] = now()->toIso8601String();
        }

        $this->storeReport($backupId, $report);

        return $report;
    }

    /**
     * Compares the image references found in the RESTORED database with the files present in the RESTORED media.
     *
     *  - fail: a referenced file is missing that the backup did not already know about;
     *  - warn: only files that were already missing when the backup was made (reported by the backup itself);
     *  - pass: every reference resolves. Orphaned files (present but unreferenced) are always listed.
     *
     * @param array<string,array<int,string>> $refs            referenced media key => where it is referenced
     * @param array<int,array{path:string}>   $restoredObjects files that exist in the restored media
     * @param array<int,array{path:string}>   $knownMissing    manifest.media.references.missing from backup time
     * @return array{status:string,message:string,report:array<string,mixed>}
     */
    public function evaluateReferences(array $refs, array $restoredObjects, array $knownMissing): array
    {
        $cmp = $this->references->compare($refs, $restoredObjects);
        $missingNow = array_column($cmp['missing'], 'path');
        $known = array_column($knownMissing, 'path');
        $unexpected = array_values(array_diff($missingNow, $known));
        $expected = array_values(array_intersect($missingNow, $known));

        $summary = "{$cmp['references']} referenced file(s) checked, {$cmp['orphans']} orphaned file(s) in the restored media"
            . ($cmp['orphans'] ? ' (e.g. ' . implode(', ', array_slice($cmp['orphan_sample'], 0, 3)) . ')' : '') . '.';

        if (!empty($unexpected)) {
            $status = 'fail';
            $message = count($unexpected) . ' referenced file(s) are missing after the restore: ' . implode(', ', array_slice($unexpected, 0, 5)) . '. ' . $summary;
        } elseif (!empty($expected)) {
            $status = 'warn';
            $message = count($expected) . ' file(s) were already missing when the backup was made (' . implode(', ', array_slice($expected, 0, 3)) . '). ' . $summary;
        } else {
            $status = 'pass';
            $message = 'Every database image reference resolves to a restored file. ' . $summary;
        }

        return [
            'status' => $status,
            'message' => $message,
            'report' => [
                'checked' => $cmp['references'],
                'missing' => $cmp['missing'],
                'missing_unexpected' => $unexpected,
                'missing_known_at_backup' => $expected,
                'orphans' => $cmp['orphans'],
                'orphan_paths' => array_slice($cmp['orphan_paths'] ?? [], 0, 50),
            ],
        ];
    }

    // ── Internals ──

    private function registerConnections(array $target): void
    {
        Config::set('database.connections.' . self::SANDBOX_CONNECTION, $this->sandboxConnectionConfig($target));
        Config::set('database.connections.' . self::ADMIN_CONNECTION, array_replace($this->sandboxConnectionConfig($target), ['database' => 'postgres']));
        DB::purge(self::SANDBOX_CONNECTION);
        DB::purge(self::ADMIN_CONNECTION);
    }

    private function sandboxConnectionConfig(array $target): array
    {
        $app = DB::connection()->getConfig();

        return [
            'driver' => 'pgsql',
            'host' => $target['host'],
            'port' => $target['port'],
            'database' => $target['database'],
            'username' => $target['username'],
            'password' => $target['password'],
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => 'public',
            'sslmode' => $app['sslmode'] ?? 'prefer',
        ];
    }

    private function cleanup(?array $target, bool $created): array
    {
        $result = ['database_dropped' => false, 'media_removed' => false];
        if (!$target) {
            return $result;
        }

        if (config('backup.sandbox.drop_after')) {
            try {
                if ($created && str_starts_with($target['database'], (string) config('backup.sandbox.database_prefix'))) {
                    DB::purge(self::SANDBOX_CONNECTION);
                    $admin = DB::connection(self::ADMIN_CONNECTION);
                    $admin->statement('select pg_terminate_backend(pid) from pg_stat_activity where datname = ? and pid <> pg_backend_pid()', [$target['database']]);
                    $admin->statement('DROP DATABASE IF EXISTS "' . $target['database'] . '"');
                    $result['database_dropped'] = true;
                }
            } catch (Throwable $e) {
                Log::warning('Sandbox cleanup could not drop database', ['error' => BackupRedactor::redact($e->getMessage())]);
            }
            $root = $target['media_root'];
            if (is_dir($root) && str_contains(str_replace('\\', '/', $root), '/sandbox-restore/')) {
                \Illuminate\Support\Facades\File::deleteDirectory($root);
                $result['media_removed'] = true;
            }
        }

        return $result;
    }

    private function storeReport(string $backupId, array $report): void
    {
        if (!$this->history->find($backupId)) {
            return; // nothing to attach the report to
        }

        $name = 'reports/' . $backupId . '_' . date('Ymd_His') . '.json';
        $this->history->disk()->put($name, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->history->update($backupId, ['rehearsal' => [
            'ok' => $report['ok'],
            'at' => $report['finished_at'] ?? now()->toIso8601String(),
            'report' => $name,
            'steps_passed' => count(array_filter($report['steps'], fn ($s) => $s['status'] !== 'fail')),
            'steps_total' => count($report['steps']),
        ]]);
    }
}
