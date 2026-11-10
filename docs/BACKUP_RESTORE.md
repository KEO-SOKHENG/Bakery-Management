# Full Backup & Restore

Status legend used below: **Implemented** (code exists) · **Tested locally** (automated tests, fake disks / stubbed pg tools) · **Verified in sandbox** (real PostgreSQL 16 on the local development machine) · **Not executed in Production**.

## 1. Architecture audit (read-only, local files and local dev server only)

| Item | Finding |
|---|---|
| Laravel / PHP | Laravel 13.25, PHP 8.3 (`composer.json`: `php ^8.3`); Docker image `php:8.3-apache` |
| Database (production) | PostgreSQL via `pdo_pgsql` (`DB_CONNECTION=pgsql`, Supabase). The production server version could **not** be inspected (no production access in this task) and must be confirmed before the first production backup. |
| Database (local dev) | PostgreSQL 16.14 on localhost, 30 tables in `public`; `pg_dump`/`pg_restore` 16.14 installed. |
| Tests | `phpunit.xml` uses in-memory SQLite, so PostgreSQL tooling is covered by a guarded integration test (`BACKUP_TEST_PG=1`). |
| Hosting | Render (Dockerfile + `docker/entrypoint.sh`); ephemeral container filesystem. |
| Media storage | `StorageDiagnosticsService::getActiveDiskName()` → `supabase-s3` / `s3` (bucket `bakery-media`) in production, `public` disk locally. Existing prefixes (from the service): `logos/`, `products/`, `categories/`, `avatars/`, `receipts/`, `backups/`. |
| Existing backup | `SettingController@createBackup` / `restoreBackup`: settings + category/product JSON. **Preserved unchanged.** |
| Authorization | `role:admin` route group + `User::isAdmin()` (also re-checked in the new controller). |
| Logging | Laravel `stack` channel + `AuditLogService` (sanitises metadata). |
| Queue / cache / session | `sync` / `file` / `file` locally; production values not inspected. |

**Gap found:** the production Dockerfile installs `libpq-dev` but **not** `postgresql-client`, so `pg_dump` / `pg_restore` are not present in the production image. Add `postgresql-client` (matching or newer than the Supabase server major version) before using Full Backup in production. This change was **not** made (no deploy authorised).

## 2. Package format (version 1.0)

```
bakery_full_backup_<timestamp>_<id8>.bkp   = encrypted ZIP of:
    database/bakery_database.dump          pg_dump -Fc archive
    media/<logos|avatars|products|categories|receipts>/...   original object paths
    settings/settings.json                 schema bakery.settings.v1 (secrets excluded)
    manifest.json                          backup id, time, app version, DB engine/version,
                                           DB file + sha256 + TOC count + table row counts +
                                           schema fingerprint, media prefixes/count/bytes/object list
                                           (path,size,sha256,content_type,last_modified), settings status,
                                           checksum algorithm, package format version
    backup_metadata.json                   small descriptive record (no secrets)
    checksums.sha256                       SHA-256 of every other file
```

* Encryption at rest: libsodium `secretstream` (XChaCha20-Poly1305), 1 MB chunks, key derived from `APP_KEY` with HKDF. **Keep `APP_KEY` safe: a package cannot be decrypted without it.**
* All copying/zipping/hashing is streamed; nothing is loaded whole into PHP memory.
* Storage: private disk `backups` (`storage/app/private/full-backups`, never the public media disk). `BACKUP_DISK` can point to a dedicated private S3 disk **after the owner approves it** – no bucket is created by this code.
* Backup history = `history/<uuid>.json` records next to the packages (metadata only). **No database table or migration is required.** If a table is preferred later, a proposed schema is: `backups(id uuid pk, type, status, verified bool, package_path, package_size, database_size, media_count, media_bytes, created_by, created_at, verified_at, rehearsal jsonb)` – needs owner approval before any migration is run.

## 3. Components

| Component | Method |
|---|---|
| Database | `pg_dump -Fc --no-password` (credentials only through the process environment, never in arguments or logs); client/server version compatibility check; integrity via `pg_restore --list`; SHA-256; exact per-table row counts and FK/index/sequence/function fingerprint recorded for later comparison. |
| Media | Read-only enumeration of the approved prefixes through the Flysystem driver; streamed download with SHA-256; `backups/` and out-of-scope objects are never included; original paths preserved. |
| Settings | `SettingsBackupService::export()` – every key matching the sensitive pattern (password, secret, token, key, credential, session, hash…) is excluded; schema-validated on import. |

**Consistency:** the database dump, the recorded row counts, the schema fingerprint and the media-reference list are all read from **one exported PostgreSQL snapshot** (`pg_export_snapshot()` + `pg_dump --snapshot`), so writes during the backup (new orders, etc.) can no longer make a restore differ from its manifest. If a snapshot cannot be exported (for example through a transaction-mode pooler) the backup **fails** instead of producing untrustworthy counts; use a direct or session connection. Media objects are still copied *after* the snapshot and are not part of it: files uploaded/removed meanwhile appear as orphans / missing references, which every backup reports ("Verified - warnings") and the sandbox rehearsal re-checks against the restored database.

## 4. Restore workflow

1. **Select** – a verified backup from history or an uploaded `.bkp` (type/size validated; magic header checked).
2. **Verify** – decrypt/authenticate, safe extraction (no `..`, absolute or drive paths, symlinks, unexpected files; size/entry caps), manifest + format version, every SHA-256, DB archive via `pg_restore --list`, media manifest vs files, settings schema. Uploaded packages that pass are stored in history as `imported`.
3. **Preview** – built only from the verified manifest. Nothing is restored.
4. **Sandbox rehearsal** – restores into a brand-new database named `sandbox_restore_*` (never the application database, must not already exist, must be empty) and a sandbox media folder (`storage/app/sandbox-restore/media`). In a production environment it **refuses to run** unless a separate sandbox DB server is configured (`BACKUP_SANDBOX_DB_*`). Verifies tables/row counts, FK/index/sequence fingerprint, media SHA-256 + count, and settings import; writes a report to `reports/` and drops the sandbox afterwards.
5. **Production restore** – **disabled**. `ProductionRestoreGate` only lists unmet conditions; there is intentionally no code path that restores into Production. The endpoint always answers 403 and is audit-logged.

Media-restore conflict rules for a future production restore (must be approved first): never delete objects absent from the backup, never silently overwrite newer objects, verify DB references and media paths together, keep a rollback copy.

## 5. Security controls

Admin-only routes + controller check · throttling (create 3/min, verify 6/min, sandbox 3/min) · UUID-only identifiers · signed short-lived (5 min) download links, never public URLs · encrypted packages · SHA-256 + AEAD authentication · path-traversal/symlink/zip-bomb protection · private temp workspace with guaranteed cleanup · credential redaction (`BackupRedactor`) on every error path · audit log entries for start/download/verify/sandbox/rejected production restore.

## 6. Operations

```
php artisan backup:full              # create + verify a full backup
php artisan backup:full --rehearse   # …and run the sandbox rehearsal
```

Large backups should run on a queue worker (`QUEUE_CONNECTION` other than `sync`); with `sync` the job runs after the response is sent.

## 7. Still requiring owner authorization

* Running any backup against Production (needs `postgresql-client` in the image and a confirmed Supabase server version).
* Choosing/approving a private backup destination for production (Render disk is ephemeral).
* Any Production restore (not implemented by design).
* Any migration (none is required by this implementation).
* Commit, push and deploy.

## 8. Production-readiness addenda

* [BACKUP_PRODUCTION_READINESS.md](BACKUP_PRODUCTION_READINESS.md) - PostgreSQL client compatibility, private durable storage recommendation, reliability controls, owner checklist.
* [BACKUP_KEY_RECOVERY.md](BACKUP_KEY_RECOVERY.md) - key ids, previous keys, rotation and recovery drill.
* [PRODUCT_IMAGE_MIGRATION.md](PRODUCT_IMAGE_MIGRATION.md) - moving product images to the media disk, with rollback.
* Commands: `backup:full [--rehearse]`, `backup:doctor`, `backup:extract <id|file> <dir>`, `backup:reverify [--limit]`, `backup:prune [--force] [--cleanup]`, `products:migrate-images [--apply|--rollback=]`.
* [BACKUP_DISASTER_RECOVERY.md](BACKUP_DISASTER_RECOVERY.md) - manual recovery procedure.
