# Disaster recovery: manual restore from a Full Backup

> **Production restore is disabled in the application and is not automated anywhere.** This runbook is for a
> deliberate, owner-authorised recovery performed by a person. Do not run any step against Production without
> the written approval of the system owner, a verified current backup of what exists now, and a rollback plan.
> Every command below was exercised only against **temporary local databases** (see the last section).

## What you need

1. The backup package (`.bkp`) or its backup id in the history, and the **encryption key** with the key id shown in `manifest.json` (`encryption_key_id`). See [BACKUP_KEY_RECOVERY.md](BACKUP_KEY_RECOVERY.md).
2. The application code (same or newer release) with PHP and the PostgreSQL client tools (`pg_restore`) whose **major version equals** the version in `manifest.json` (`database.server_version`).
3. A **new, empty** PostgreSQL database (never an existing one) and a storage target for media.
4. Free disk space of roughly 3x the package size.

## Step 1 - Verify and extract (read-only for every database and storage)

```
php artisan backup:extract <backup-id | /path/to/package.bkp> /secure/restore-work/<name>
```

The command decrypts and authenticates the package, rejects unsafe paths, checks every SHA-256, lists the database archive, checks the media manifest and settings, and only then writes the files into the **new** directory (mode 0700; it refuses a non-empty directory or anything inside a web-accessible folder). It cleans every temporary file, also on failure. If it reports a failure, **stop**: use another package.

The directory then contains:

```
database/bakery_database.dump   media/<logos|avatars|products|categories|receipts>/...
settings/settings.json          manifest.json   checksums.sha256   backup_metadata.json
```

Read `manifest.json` (creation time, `database.table_counts`, `database.schema_summary`, `media.count`, `media.references`).

## Step 2 - Restore the database into a NEW empty database

```
createdb <new_database>                      # new and empty; never an existing database
pg_restore --no-owner --no-privileges --exit-on-error \
           -h <host> -p <port> -U <user> -d <new_database> database/bakery_database.dump
```

Supply the password through `PGPASSWORD` (environment) or `~/.pgpass`, never on the command line. If `pg_restore` stops with an error about roles/extensions, the dump contains provider-managed objects: restore into a server of the same provider/major version, or ask for a schema-only application dump.

Verify against the manifest (row counts of every table must equal `database.table_counts`; constraints, indexes and sequences must equal `database.schema_summary`):

```
SELECT relname, n_live_tup FROM pg_stat_user_tables ORDER BY relname;   -- quick check
SELECT count(*) FROM <table>;                                            -- exact, per table
```

The counts in the manifest come from the **same snapshot** as the dump, so they match exactly even if the source was busy.

## Step 3 - Media

Copy `media/` into the media storage **preserving the object keys** (`logos/...`, `avatars/...`, `products/...`). Rules:

* **Never overwrite** existing objects (use `--ignore-existing`, `rclone copy --immutable`, or `aws s3 sync --exact-timestamps` into a *new* bucket/prefix).
* Never delete anything that is not in the package.
* Compare each copied file with `checksums.sha256` / `manifest.json media.objects[].sha256`.
* Product rows that say `uploads/products/<file>` resolve to `products/<file>`: no database change is needed.
* `manifest.json -> media.references.missing` lists files that were already missing when the backup was made (reported, not lost by the restore); `orphans` lists unreferenced files.

## Step 4 - Settings (optional)

`settings/settings.json` is already inside the restored database (the `settings` table). It is provided separately for reviewing or re-importing only the settings (*Settings Backup* in the UI).

## Step 5 - Smoke test before any cut-over

1. Point a **staging** copy of the application (separate environment variables) at the restored database and media.
2. Sign in, open the dashboard, products with images, one order, Settings and Backup & Restore.
3. Run `php artisan backup:doctor`.
4. Only then decide, with the owner, about switching traffic. The cut-over itself (DNS / environment variables / connection strings) is intentionally **not** scripted.

## Rehearsal first

Use *Backup & Restore -> Restore System -> Restore to Sandbox* (or `php artisan backup:full --rehearse`). It performs steps 2-3 automatically in an isolated sandbox database and folder and additionally compares **the image references in the restored database with the files in the restored media**, reporting missing files and orphans. Do this regularly so the procedure is known to work.

## Evidence (local, development data)

`backup:extract` on a real local package: verified and extracted, 5 media files + database + settings; a second extraction into the same folder was refused; a destination inside `public/` was refused; no temporary files remained. Steps 2-3 against a temporary database: 30 tables restored, **row counts and the constraint/index/sequence fingerprint equal the manifest**, 5/5 media checksums matched; the temporary database was dropped.
