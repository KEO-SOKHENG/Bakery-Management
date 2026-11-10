<?php

namespace App\Services\Backup;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Backup history is derived from real metadata files stored next to the packages on the
 * private backup disk (history/<id>.json). No database table (and no migration) is needed.
 * Records contain metadata only: never credentials, URLs with secrets or file contents.
 */
class BackupHistoryService
{
    public function disk()
    {
        return Storage::disk((string) config('backup.disk', 'backups'));
    }

    public function newId(): string
    {
        return (string) Str::uuid();
    }

    public function packagePath(string $id): string
    {
        $this->assertId($id);

        return "packages/{$id}.bkp";
    }

    public function historyPath(string $id): string
    {
        $this->assertId($id);

        return "history/{$id}.json";
    }

    public function assertId(string $id): void
    {
        if (!Str::isUuid($id)) {
            throw new RuntimeException('Invalid backup identifier.');
        }
    }

    public function save(array $record): void
    {
        $this->disk()->put($this->historyPath($record['id']), json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function find(string $id): ?array
    {
        $path = $this->historyPath($id);
        if (!$this->disk()->exists($path)) {
            return null;
        }

        return json_decode((string) $this->disk()->get($path), true) ?: null;
    }

    public function update(string $id, array $changes): array
    {
        $record = $this->find($id) ?? throw new RuntimeException('Backup record not found.');
        $record = array_replace_recursive($record, $changes);
        $this->save($record);

        return $record;
    }

    /** @return array<int,array<string,mixed>> newest first */
    public function all(): array
    {
        $records = [];
        foreach ($this->disk()->files('history') as $file) {
            if (!str_ends_with($file, '.json')) {
                continue;
            }
            $data = json_decode((string) $this->disk()->get($file), true);
            if (is_array($data) && isset($data['id'])) {
                $records[] = $data;
            }
        }
        usort($records, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));

        return $records;
    }

    public function packageExists(string $id): bool
    {
        return $this->disk()->exists($this->packagePath($id));
    }

    // ── Live progress (short-lived cache, not persisted) ──

    public function setProgress(string $id, array $progress): void
    {
        app(BackupLock::class)->store()->put("backup_progress:{$id}", $progress, now()->addHours(2));
    }

    public function getProgress(string $id): ?array
    {
        return app(BackupLock::class)->store()->get("backup_progress:{$id}");
    }

    public function driver(): string
    {
        $disk = (string) config('backup.disk', 'backups');

        return (string) config("filesystems.disks.{$disk}.driver", 'local');
    }

    /** True when the destination is an S3-compatible object store. */
    public function isRemote(): bool
    {
        return in_array($this->driver(), ['s3', 's3-noacl', 'supabase-s3'], true);
    }

    public function destinationLabel(): string
    {
        return $this->isRemote() ? 'Private cloud backup storage' : 'Private server storage (local)';
    }

    /**
     * Write options for a new package. On S3 the package is tagged with its retention class so bucket
     * lifecycle rules (and Object Lock retention set by the s3-noacl driver) can expire it by class.
     *
     * @return array<string,mixed>
     */
    public function uploadOptions(?Carbon $createdAt = null): array
    {
        $options = ['visibility' => 'private'];
        if ($this->isRemote() && config('backup.s3.tag_retention_class', true)) {
            $options['Tagging'] = 'retention=' . $this->retentionClass($createdAt ?: now());
        }

        return $options;
    }

    /** monthly = 1st of the month, weekly = Sunday, otherwise daily. */
    public function retentionClass(Carbon $at): string
    {
        if ((int) $at->format('j') === 1) {
            return 'monthly';
        }

        return $at->dayOfWeek === Carbon::SUNDAY ? 'weekly' : 'daily';
    }

    /**
     * Confirms the object that was just written is complete: size always, SHA-256 by reading it back.
     * A mismatching object is deleted (best effort) so it can never be mistaken for a valid backup.
     */
    public function verifyStoredPackage(string $id, int $expectedSize, string $expectedSha256): void
    {
        $path = $this->packagePath($id);
        $disk = $this->disk();

        $fail = function (string $message) use ($disk, $path) {
            try {
                $disk->delete($path);
            } catch (\Throwable) {
            }
            throw new RuntimeException($message);
        };

        if (!$disk->exists($path)) {
            $fail('The backup package is missing from the destination after upload.');
        }
        $size = (int) $disk->size($path);
        if ($size !== $expectedSize) {
            $fail("Upload verification failed: stored {$size} bytes, expected {$expectedSize}.");
        }

        if (config('backup.verify_upload_checksum', true)) {
            $in = $disk->readStream($path);
            if (!is_resource($in)) {
                $fail('Upload verification failed: the stored package could not be read back.');
            }
            $ctx = hash_init('sha256');
            hash_update_stream($ctx, $in);
            fclose($in);
            if (!hash_equals($expectedSha256, hash_final($ctx))) {
                $fail('Upload verification failed: the stored package checksum does not match.');
            }
        }
    }

    // ── Stale "running" detection ──

    /**
     * Marks records that claim to be running but whose process is gone as failed.
     * A run is considered dead when there is no heartbeat for backup.stale_after_minutes and the lock is free
     * (or the caller itself holds the lock, which proves no other process is running).
     *
     * @return array<int,string> ids that were marked failed
     */
    public function reapStale(bool $callerHoldsLock = false): array
    {
        $lock = app(BackupLock::class);
        $threshold = max(1, (int) config('backup.stale_after_minutes', 30)) * 60;
        $reaped = [];

        foreach ($this->all() as $record) {
            if (($record['status'] ?? '') !== 'running') {
                continue;
            }
            $last = $lock->lastHeartbeat($record['id']) ?? strtotime((string) ($record['created_at'] ?? 'now'));
            if (time() - $last < $threshold || (!$callerHoldsLock && $lock->isHeld())) {
                continue;
            }
            $components = (array) ($record['components'] ?? []);
            foreach ($components as $name => $component) {
                if (in_array($component['status'] ?? '', ['running', 'pending'], true)) {
                    $components[$name]['status'] = 'failed';
                }
            }
            $this->save(array_replace($record, [
                'status' => 'failed',
                'verified' => false,
                'components' => $components,
                'error' => 'Interrupted: the backup process stopped before it finished (no activity for '
                    . (int) config('backup.stale_after_minutes', 30) . ' minutes).',
                'interrupted_at' => now()->toIso8601String(),
            ]));
            $this->setProgress($record['id'], ['status' => 'failed', 'components' => $components, 'verified' => false, 'error' => 'Interrupted']);
            try {
                $this->disk()->delete($this->packagePath($record['id'])); // never leave a half-written package
            } catch (\Throwable) {
            }
            $reaped[] = $record['id'];
        }

        return $reaped;
    }
}
