<?php

return [

    /*
    | Full Backup & Restore configuration.
    | Nothing here points at Production resources by default.
    */

    // Private destination disk for backup packages (never the public media disk).
    // Production should use a dedicated private S3-compatible disk, e.g. BACKUP_DISK=backups-s3.
    'disk' => env('BACKUP_DISK', 'backups'),

    // A local disk is ephemeral on Render. In production it is refused unless explicitly allowed.
    'allow_local_destination_in_production' => (bool) env('BACKUP_ALLOW_LOCAL_IN_PRODUCTION', false),

    'package_format_version' => '1.0',
    'app_version' => env('APP_VERSION', '1.0'),

    // Approved media scope (object prefixes inside the active media disk). "backups/" is excluded on purpose.
    'media_prefixes' => ['logos/', 'avatars/', 'products/', 'categories/', 'receipts/'],

    // PostgreSQL client tools (auto-detected when empty).
    'pg_dump_path' => env('PG_DUMP_PATH'),
    'pg_restore_path' => env('PG_RESTORE_PATH'),

    'temp_dir' => env('BACKUP_TEMP_DIR', storage_path('app/backup-tmp')),
    'temp_max_age_hours' => (int) env('BACKUP_TEMP_MAX_AGE_HOURS', 6),

    // Encryption. When BACKUP_ENCRYPTION_KEY is empty, APP_KEY is used.
    // BACKUP_PREVIOUS_ENCRYPTION_KEYS is a comma-separated list of retired keys that must stay decryptable.
    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),
    'previous_encryption_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_PREVIOUS_ENCRYPTION_KEYS', ''))))),

    // Limits
    'max_upload_mb' => (int) env('BACKUP_MAX_UPLOAD_MB', 512),
    'max_extracted_mb' => (int) env('BACKUP_MAX_EXTRACTED_MB', 4096),
    'max_entries' => 200000,
    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 3600),
    'download_link_minutes' => 5,

    // Execution lock + free-space guard.
    // With several application instances set BACKUP_LOCK_STORE to a SHARED cache store (database or redis);
    // the default (file) store only protects a single instance.
    'lock_store' => env('BACKUP_LOCK_STORE'),
    'lock_ttl_seconds' => (int) env('BACKUP_LOCK_TTL', 7200),
    // A backup that has shown no activity for this long (and holds no lock) is marked failed (interrupted).
    'stale_after_minutes' => (int) env('BACKUP_STALE_AFTER_MINUTES', 30),
    'min_free_space_mb' => (int) env('BACKUP_MIN_FREE_SPACE_MB', 200),
    'free_space_factor' => 3, // staging + zip + encrypted copy

    // Media download resilience
    'object_retries' => (int) env('BACKUP_OBJECT_RETRIES', 3),
    'object_retry_delay_ms' => (int) env('BACKUP_OBJECT_RETRY_DELAY_MS', 500),
    'object_timeout_seconds' => (int) env('BACKUP_OBJECT_TIMEOUT', 120),

    // Retention (applied by `php artisan backup:prune`; dry-run unless --force)
    'retention' => [
        // Remote (S3) retention is enforced by bucket lifecycle / Object Lock. The application only deletes
        // remote packages when this is true AND its key has delete rights (not recommended with Object Lock).
        'delete_from_remote' => (bool) env('BACKUP_PRUNE_REMOTE', false),
        'keep_last' => (int) env('BACKUP_KEEP_LAST', 3),
        'keep_daily' => (int) env('BACKUP_KEEP_DAILY', 7),
        'keep_weekly' => (int) env('BACKUP_KEEP_WEEKLY', 4),
        'keep_monthly' => (int) env('BACKUP_KEEP_MONTHLY', 12),
    ],

    // S3 destination behaviour
    's3' => [
        // Tag each package with retention=daily|weekly|monthly so lifecycle rules can expire it by class.
        'tag_retention_class' => (bool) env('BACKUP_S3_TAG_RETENTION', true),
    ],

    // Read the stored package back after upload and compare size + SHA-256 (one extra download of the package).
    'verify_upload_checksum' => (bool) env('BACKUP_VERIFY_UPLOAD', true),

    // Sandbox rehearsal target. Never reuse Production credentials here.
    // In a production environment host, username and password MUST be provided explicitly.
    'sandbox' => [
        'database_prefix' => 'sandbox_restore_',
        'host' => env('BACKUP_SANDBOX_DB_HOST'),
        'port' => env('BACKUP_SANDBOX_DB_PORT'),
        'username' => env('BACKUP_SANDBOX_DB_USERNAME'),
        'password' => env('BACKUP_SANDBOX_DB_PASSWORD'),
        'media_root' => storage_path('app/sandbox-restore/media'),
        'drop_after' => (bool) env('BACKUP_SANDBOX_DROP_AFTER', true),
    ],

    // Production restore is disabled and has no execution path in this codebase.
    'production_restore_enabled' => (bool) env('BACKUP_PRODUCTION_RESTORE_ENABLED', false),

    // Settings that must never be exported.
    'sensitive_setting_pattern' => '/(password|passwd|secret|token|api[_-]?key|private|credential|session|hash|access[_-]?key)/i',
];
