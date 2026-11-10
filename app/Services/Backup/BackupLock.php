<?php

namespace App\Services\Backup;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Single-flight lock + heartbeat for Full Backups.
 *
 * With one application instance the default cache store (file) is enough. With several instances
 * the lock and heartbeat MUST live in a shared store: set BACKUP_LOCK_STORE to a shared cache store
 * (database or redis). `php artisan backup:doctor` warns when a per-instance store is used in production.
 */
class BackupLock
{
    private const KEY = 'full-backup';

    private static ?string $currentId = null;

    public function store(): Repository
    {
        $name = config('backup.lock_store');

        return $name ? Cache::store($name) : Cache::store();
    }

    public function storeName(): string
    {
        return (string) (config('backup.lock_store') ?: config('cache.default'));
    }

    /** True when the lock store is shared between application instances. */
    public function isShared(): bool
    {
        $name = $this->storeName();
        $driver = (string) config("cache.stores.{$name}.driver", '');

        return !in_array($driver, ['file', 'array', 'null', ''], true);
    }

    public function acquire(?int $ttl = null): ?Lock
    {
        $lock = $this->store()->lock(self::KEY, $ttl ?? (int) config('backup.lock_ttl_seconds', 7200));

        return $lock->get() ? $lock : null;
    }

    public function isHeld(): bool
    {
        $lock = $this->store()->lock(self::KEY, 5);
        if ($lock->get()) {
            $lock->release();

            return false;
        }

        return true;
    }

    // ── Heartbeat (proves a running backup is still alive) ──

    public function begin(string $backupId): void
    {
        self::$currentId = $backupId;
        $this->beat();
    }

    public function end(): void
    {
        self::$currentId = null;
    }

    /** Cheap; safe to call often (per object, per pg_dump poll). */
    public function beat(): void
    {
        if (self::$currentId !== null) {
            $this->store()->put('backup_heartbeat:' . self::$currentId, time(), now()->addHours(12));
        }
    }

    public function lastHeartbeat(string $backupId): ?int
    {
        $value = $this->store()->get('backup_heartbeat:' . $backupId);

        return $value === null ? null : (int) $value;
    }
}
