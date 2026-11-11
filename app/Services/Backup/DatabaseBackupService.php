<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * PostgreSQL backup using the native tools (pg_dump -Fc / pg_restore).
 * Credentials are passed through the process environment only, never on the command line.
 */
class DatabaseBackupService
{
    // ── Environment inspection ──

    public function isPostgres(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'pgsql';
    }

    /** @return array{version: string, major: int} */
    public function serverInfo(?string $connection = null): array
    {
        if (!$this->isPostgres($connection)) {
            throw new RuntimeException('The active database is not PostgreSQL.');
        }
        $row = DB::connection($connection)->selectOne('show server_version');
        $version = (string) ($row->server_version ?? '');

        return ['version' => $version, 'major' => (int) $version];
    }

    public function binary(string $name): string
    {
        $configured = config('backup.' . $name . '_path');
        if (!empty($configured) && is_file($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder())->find($name);
        if ($found) {
            return $found;
        }

        $candidates = glob('C:/Program Files/PostgreSQL/*/bin/' . $name . '.exe') ?: [];
        if (!empty($candidates)) {
            rsort($candidates);
            return $candidates[0];
        }

        throw new RuntimeException("PostgreSQL client tool '{$name}' was not found. Install the PostgreSQL client and set PG_DUMP_PATH / PG_RESTORE_PATH if needed.");
    }

    /** @return array{version: string, major: int} */
    public function clientInfo(string $tool = 'pg_dump'): array
    {
        $process = new Process([$this->binary($tool), '--version'], null, self::toolEnv());
        $process->run();
        if (!$process->isSuccessful() || !preg_match('/(\d+)(?:\.(\d+))?/', $process->getOutput(), $m)) {
            throw new RuntimeException("Unable to read the {$tool} version.");
        }

        return ['version' => trim($process->getOutput()), 'major' => (int) $m[1]];
    }

    public function assertCompatible(?string $connection = null): array
    {
        $server = $this->serverInfo($connection);
        $client = $this->clientInfo('pg_dump');
        if ($client['major'] < $server['major']) {
            throw new RuntimeException("pg_dump {$client['major']} is older than the PostgreSQL server {$server['major']}. Install a matching or newer PostgreSQL client.");
        }

        return ['server' => $server, 'client' => $client];
    }

    /** Current database size in bytes (0 when it cannot be determined). */
    public function databaseSizeBytes(?string $connection = null): int
    {
        try {
            return (int) DB::connection($connection)->selectOne('select pg_database_size(current_database()) as s')->s;
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Backup ──

    /**
     * Dumps the database and records row counts + schema fingerprint from the SAME snapshot.
     *
     * A dedicated connection opens a REPEATABLE READ transaction and exports its snapshot
     * (pg_export_snapshot). pg_dump is started with --snapshot=<id>, and the row counts, the schema
     * fingerprint and (optionally) $inSnapshot are read inside that same transaction. Rows written by
     * the application while the backup runs are therefore in neither the dump nor the recorded counts,
     * and a restore of the dump always matches the manifest.
     *
     * Fails closed: if a snapshot cannot be exported (e.g. a transaction-mode pooler), no backup is
     * produced instead of one whose counts could not be trusted.
     *
     * @param callable|null $inSnapshot function (string $connectionName): mixed, run inside the snapshot transaction
     * @return array{filename:string,size:int,sha256:string,server_version:string,client_version:string,toc_entries:int,table_counts:array<string,int>,schema_summary:array<string,int>,snapshot_extra:mixed}
     */
    public function dump(string $targetFile, ?string $connection = null, ?callable $inSnapshot = null): array
    {
        $versions = $this->assertCompatible($connection);
        $cfg = DB::connection($connection)->getConfig();

        $snapName = 'backup_snapshot';
        Config::set("database.connections.{$snapName}", $cfg);
        DB::purge($snapName);
        $snap = DB::connection($snapName);

        $counts = [];
        $schema = [];
        $extra = null;

        try {
            $snap->beginTransaction();
            $snap->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            try {
                $snapshotId = (string) $snap->selectOne('select pg_export_snapshot() as id')->id;
            } catch (\Throwable $e) {
                throw new RuntimeException('Could not export a consistent database snapshot (' . BackupRedactor::redact($e->getMessage())
                    . '). Use a direct or session-mode database connection for backups.');
            }
            if (!preg_match('/^[0-9A-Fa-f\-]+$/', $snapshotId)) {
                throw new RuntimeException('Unexpected snapshot identifier returned by the database.');
            }

            $this->onSnapshotExported($snapshotId);

            // Everything below reads from the exported snapshot.
            $counts = $this->tableCounts($snapName);
            $schema = $this->schemaSummary($snapName);
            $extra = $inSnapshot ? $inSnapshot($snapName) : null;

            $args = [$this->binary('pg_dump'), '-Fc', '--no-password', '--snapshot=' . $snapshotId, '-f', $targetFile,
                '-h', (string) $cfg['host'], '-p', (string) ($cfg['port'] ?? 5432),
                '-U', (string) $cfg['username'], '-d', (string) $cfg['database']];

            $this->run($args, $this->envFor($cfg));
        } finally {
            try {
                if ($snap->transactionLevel() > 0) {
                    $snap->rollBack();
                }
            } catch (\Throwable) {
                // the connection is discarded below either way
            }
            DB::purge($snapName);
        }

        if (!is_file($targetFile) || filesize($targetFile) === 0) {
            throw new RuntimeException('pg_dump produced no output.');
        }

        $toc = $this->listArchive($targetFile);

        return [
            'filename' => basename($targetFile),
            'size' => filesize($targetFile),
            'sha256' => hash_file('sha256', $targetFile),
            'server_version' => $versions['server']['version'],
            'client_version' => $versions['client']['version'],
            'toc_entries' => $toc,
            'table_counts' => $counts,
            'schema_summary' => $schema,
            'snapshot_extra' => $extra,
            'snapshot_consistent' => true,
        ];
    }

    /** Hook (used by tests to simulate writes arriving right after the snapshot is taken). */
    protected function onSnapshotExported(string $snapshotId): void
    {
    }

    /** @return array{foreign_keys:int,indexes:int,sequences:int,functions:int} structural fingerprint */
    public function schemaSummary(?string $connection = null): array
    {
        $db = DB::connection($connection);

        return [
            'foreign_keys' => (int) $db->selectOne("select count(*) c from pg_constraint con join pg_namespace n on n.oid = con.connamespace where con.contype = 'f' and n.nspname = 'public'")->c,
            'indexes' => (int) $db->selectOne("select count(*) c from pg_indexes where schemaname = 'public'")->c,
            'sequences' => (int) $db->selectOne("select count(*) c from pg_class c join pg_namespace n on n.oid = c.relnamespace where c.relkind = 'S' and n.nspname = 'public'")->c,
            'functions' => (int) $db->selectOne("select count(*) c from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public'")->c,
        ];
    }

    /** Verifies the custom-format archive with pg_restore --list. Returns TOC entry count. */
    public function listArchive(string $archive): int
    {
        $process = new Process([$this->binary('pg_restore'), '--list', $archive], null, self::toolEnv());
        $process->setTimeout(300);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Database archive failed pg_restore --list: ' . BackupRedactor::redact(trim($process->getErrorOutput())));
        }

        $entries = array_filter(explode("\n", $process->getOutput()), fn ($l) => $l !== '' && $l[0] !== ';');

        return count($entries);
    }

    /** @return array<string,int> exact row counts of every table in the public schema */
    public function tableCounts(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $counts = [];
        $tables = $db->select("select tablename from pg_tables where schemaname = 'public' order by tablename");
        foreach ($tables as $t) {
            $counts[$t->tablename] = (int) $db->table($t->tablename)->count();
        }

        return $counts;
    }

    // ── Restore (sandbox only; callers must have verified the target identity) ──

    public function restoreInto(string $archive, array $targetConfig): void
    {
        $args = [$this->binary('pg_restore'), '--no-password', '--no-owner', '--no-privileges', '--exit-on-error',
            '-h', (string) $targetConfig['host'], '-p', (string) ($targetConfig['port'] ?? 5432),
            '-U', (string) $targetConfig['username'], '-d', (string) $targetConfig['database'], $archive];

        $this->run($args, $this->envFor($targetConfig));
    }

    // ── Internals ──

    /** OS variables the PostgreSQL tools need (Windows needs SYSTEMROOT for sockets, TEMP/TMP for scratch files). */
    public const OS_ENV_ALLOWLIST = ['SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP', 'PATH', 'PATHEXT', 'COMSPEC',
        'APPDATA', 'LOCALAPPDATA', 'USERPROFILE', 'HOME', 'LANG', 'LC_ALL', 'TZ'];

    /**
     * The complete environment for a PostgreSQL tool: an explicit allowlist, nothing else.
     *
     * Symfony Process merges inherited variables (getenv() ∩ $_SERVER, plus $_ENV, which holds every .env key)
     * into the child unless a name is set to false. Two problems follow: under `php artisan serve` on Windows
     * $_SERVER holds request data, so SYSTEMROOT/TEMP/PATH were dropped and libpq failed with an empty
     * "pg_dump: error:"; and every Laravel secret (APP_KEY, DB_PASSWORD, MAIL_PASSWORD, AWS_*) was passed on.
     * Here the allowlisted OS values are copied from the real process environment, $pg adds the connection
     * settings, and every other inherited name is set to false so Symfony removes it.
     *
     * @param  array<string,string>  $pg  PostgreSQL variables to pass (e.g. PGPASSWORD, PGSSLMODE)
     * @return array<string,string|false>
     */
    public static function toolEnv(array $pg = []): array
    {
        $os = getenv();
        $env = [];

        // 1. Remove everything a child would otherwise inherit
        $inherited = array_merge(array_keys($os), array_keys($_ENV), array_keys(array_filter($_SERVER, 'is_string')));
        foreach ($inherited as $name) {
            $name = (string) $name;
            if ($name !== '' && !str_contains($name, '=')) {
                $env[$name] = false;
            }
        }

        // 2. Re-allow the OS basics, using the real process values (matched case-insensitively for Windows)
        foreach (self::OS_ENV_ALLOWLIST as $wanted) {
            foreach ($os as $key => $value) {
                if (strcasecmp((string) $key, $wanted) === 0) {
                    unset($env[$key]);
                    if ($value !== '') {
                        $env[$key] = $value;
                    }
                    break;
                }
            }
        }

        // 3. PostgreSQL connection settings
        foreach ($pg as $key => $value) {
            foreach (array_keys($env) as $existing) {
                if (strcasecmp((string) $existing, (string) $key) === 0) {
                    unset($env[$existing]);
                }
            }
            $env[$key] = (string) $value;
        }

        return $env;
    }

    private function envFor(array $cfg): array
    {
        $pg = [];
        if (!empty($cfg['password'])) {
            $pg['PGPASSWORD'] = (string) $cfg['password'];
        }
        if (!empty($cfg['sslmode'])) {
            $pg['PGSSLMODE'] = (string) $cfg['sslmode'];
        }

        return self::toolEnv($pg);
    }

    private function run(array $command, array $env): void
    {
        $process = new Process($command, null, $env);
        $process->setTimeout((float) config('backup.process_timeout', 3600));
        $lock = app(BackupLock::class);
        $process->start();
        while ($process->isRunning()) {
            $lock->beat();
            $process->checkTimeout();
            usleep(500000);
        }

        if (!$process->isSuccessful()) {
            $message = BackupRedactor::redact(self::readableOutput($process->getErrorOutput() ?: $process->getOutput()));
            if ($message === '') {
                $message = 'exit code ' . $process->getExitCode();
            }
            throw new RuntimeException(basename($command[0]) . ' failed: ' . mb_substr($message, 0, 500));
        }
    }

    /**
     * Makes tool output safe to store and show: valid UTF-8 (Windows builds print localised messages in the
     * system code page), no control characters, and multi-line output joined so no part of the reason is lost.
     */
    private static function readableOutput(string $raw): string
    {
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $converted = @mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            $raw = is_string($converted) ? $converted : mb_scrub($raw, 'UTF-8');
        }
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $raw) ?? $raw;
        $lines = array_filter(array_map('trim', preg_split('/\R/u', $raw) ?: []), fn ($l) => $l !== '');

        return implode(' | ', $lines);
    }
}
