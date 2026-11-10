# Full Backup & Restore: production readiness

Facts verified locally are marked **verified**. Everything about Production is **unverified** until the owner authorizes a read-only check.

## 1. PostgreSQL client compatibility

| Requirement | Detail |
|---|---|
| `pg_dump` | Major version **>= the Production server major version**. |
| `pg_restore` | Same major as the `pg_dump` that wrote the archive (or newer). |
| Restorability | Archives made by a newer `pg_dump` can contain `SET` statements that older servers reject (e.g. `transaction_timeout`, added in 17). Keep the client major **equal** to the server major, and make the sandbox server the **same or newer** major. |
| Production server version | **Unverified.** Do not assume 16. Confirm with `SHOW server_version;` (read-only) before building the image. |
| Connection type | `pg_dump` needs a direct or session connection. A transaction pooler (e.g. Supabase port 6543) breaks it. **Unverified** which one `DB_URL` uses. |
| Managed schemas | If Production is Supabase, a whole-database dump includes Supabase-owned schemas/extensions; restoring them into a plain PostgreSQL sandbox may fail with `--exit-on-error`. Decide (with the owner) whether to dump only the application schema. |

### Dockerfile (prepared, not deployed)

* Installs `postgresql-client-${PG_CLIENT_MAJOR}` from the official PostgreSQL (PGDG) apt repository (Debian's own package is older than current servers).
* `PG_CLIENT_MAJOR` is a **required build argument with no default**: the build stops with an error unless it is digits (e.g. `--build-arg PG_CLIENT_MAJOR=<verified production major>`). Do not assume 16 or 17; confirm the Production server major version first.
* Sets `PG_DUMP_PATH` / `PG_RESTORE_PATH` to `/usr/lib/postgresql/<major>/bin/...` and prints both versions during the build, so a wrong repository fails the build.
* Copies `docker/php-bakery.ini` (upload 512M, post 520M, memory 512M) so backup packages can be uploaded.
* **Not built here:** Docker Desktop's engine was not running on this machine, so the image build could not be tested. The snippet follows PostgreSQL's documented apt procedure. Test it once with `docker build --build-arg PG_CLIENT_MAJOR=<major> .` and then run `php artisan backup:doctor` inside the container.

Local compatibility report (`php artisan backup:doctor`, verified): server 16.14, `pg_dump` 16.14, `pg_restore` 16.14 - compatible.

## 2. Permanent private backup storage

**Recommendation (simplest secure durable option):** a dedicated private bucket on **AWS S3** (Backblaze B2 or Cloudflare R2 are cheaper S3-compatible alternatives), in an account/project separate from the media bucket.

Required bucket settings (owner action - nothing is created by the code):

1. Block all public access; no public ACLs or policies.
2. Default encryption at rest (SSE-S3 or SSE-KMS). Packages are additionally encrypted by the application.
3. Versioning on, plus Object Lock (retention mode) for ransomware protection.
4. Lifecycle rule matching the retention policy (default 3 newest, 7 daily, 4 weekly, 12 monthly).
5. A dedicated access key with the exact permissions in section 2a (no delete, no bucket administration).
6. Two named people with recovery access.

Application configuration (the `backups-s3` disk already exists in `config/filesystems.php` and is inert until these are set):

```
BACKUP_DISK=backups-s3
BACKUP_S3_OBJECT_LOCK_MODE=GOVERNANCE   # optional; bucket must have Object Lock enabled
BACKUP_LOCK_STORE=database               # REQUIRED if more than one application instance runs
BACKUP_S3_BUCKET=<private backup bucket>
BACKUP_S3_REGION=<region>
BACKUP_S3_KEY=<restricted key id>
BACKUP_S3_SECRET=<restricted secret>
BACKUP_S3_ENDPOINT=<only for B2/R2/other S3-compatible>
BACKUP_S3_PATH_STYLE=true|false
BACKUP_S3_SSE=AES256            # or aws:kms
BACKUP_ENCRYPTION_KEY=base64:...  # dedicated key, see BACKUP_KEY_RECOVERY.md
```

Guard rails in code (verified by tests): the backup disk may not be `public`/`s3`/`supabase-s3`, may not have a public URL/visibility, may not be inside the web root, may not use the **media bucket**, and a **local disk is refused in production** (it is ephemeral on Render) unless `BACKUP_ALLOW_LOCAL_IN_PRODUCTION=true`.

## 3. Reliability controls added (all verified by automated tests)

| Control | Behaviour |
|---|---|
| Backup lock | One backup at a time (`Cache::lock`). A second request returns HTTP 409 / a failed record. |
| Free disk space | Needs `(database + media) x 3 + 200 MB` free in the temp directory, otherwise it fails before copying anything. |
| Temp cleanup | Stale working folders older than 6 h are swept at the start of each backup (`backup:prune --cleanup` can also run it). Folders are removed in `finally` on every path. |
| Retention | `php artisan backup:prune` (dry run) / `--force`: keeps newest 3 + 7 daily + 4 weekly + 12 monthly; never touches failed/unverified records. |
| Upload limits | PHP limits in `docker/php-bakery.ini`; a clear 413 message when a package exceeds the server limit; restore from history needs no upload. |
| Media download verification | Size is compared with the storage listing/HEAD, single-part S3 ETags are compared with MD5, the real Content-Type and custom metadata are recorded, partial downloads fail. |
| Retry/timeout | 3 attempts per object with back-off, 120 s read deadline per object; failures abort the backup (fail closed) with a redacted message. |
| Reference integrity | Every backup compares DB references (`shop_logo`, avatars, `products.image_path`) with the captured media, reports missing references and orphans, and shows "Verified - warnings" instead of hiding them. |
| Sandbox credentials | In production the sandbox host, username **and** password must be set explicitly and must differ from the application database's; there is no fallback to Production credentials. |
| Key id + previous keys | See `BACKUP_KEY_RECOVERY.md`. |
| Readiness check | `php artisan backup:doctor` (read-only). |

## 4. Remaining items that need the owner

1. Confirm the Production PostgreSQL major version and connection type (read-only), then build the image with that `PG_CLIENT_MAJOR`.
2. Create/approve the private backup bucket and restricted key; set the `BACKUP_*` variables.
3. Provide a separate sandbox database server for production rehearsals (`BACKUP_SANDBOX_DB_*`).
4. Back up the keys as described in `BACKUP_KEY_RECOVERY.md`.
5. Run `products:migrate-images --apply` in the live container before the next deploy (see `PRODUCT_IMAGE_MIGRATION.md`).
6. Decide whether to dump only the application schema (Supabase-managed schemas).
7. Any Production backup run, any Production restore (not implemented by design), commit, push and deploy.


## 2a. S3 retention, Object Lock, lifecycle and permissions (final design)

### Who is allowed to delete what

| Actor | Allowed | Why |
|---|---|---|
| Application key | **Never deletes remote packages** | A key that can delete is the key an attacker (or a bug) uses to destroy every backup. |
| `backup:prune` | Local destinations only. On S3 it prints the plan, deletes nothing and exits 0 (`BACKUP_PRUNE_REMOTE=false`, the default). | Retention on S3 is enforced by the bucket, so the app never fights Object Lock / lifecycle. |
| Bucket lifecycle rules | Expire packages **by retention class tag** | The app tags every package `retention=daily|weekly|monthly` at upload (`BACKUP_S3_TAG_RETENTION=true`). |
| Object Lock | Keeps each package immutable until its class period ends | The app sets mode + retain-until at upload for `packages/` only (via the `s3-noacl` driver). |

If you do set `BACKUP_PRUNE_REMOTE=true` (not recommended with Object Lock), failures are handled safely: a record is marked *pruned* only when the delete succeeded **and** the package is really gone. A denied delete, or an object that survives the delete (Object Lock / versioning), leaves the record `completed`, stores `prune_error` on it, never aborts the remaining packages, and `backup:prune` exits non-zero.

### Object Lock design (no conflicts)

1. Enable **versioning and Object Lock** at bucket creation (cannot be added later on some providers). Do **not** set a bucket-wide default retention: it would also lock the `history/*.json` files the app rewrites many times per backup (every rewrite is a new locked version).
2. The app applies retention per package: `BACKUP_S3_OBJECT_LOCK_MODE` = `GOVERNANCE` (recommended: an admin with `s3:BypassGovernanceRetention` can still remove a package in an emergency) or `COMPLIANCE` (irrevocable). Days per class: `BACKUP_LOCK_DAYS_DAILY=7`, `_WEEKLY=35`, `_MONTHLY=400`.
3. Lifecycle expiration must be **later than** the lock so the object is deletable when the rule fires (below uses lock + 1 day).
4. Retention classes: monthly = the 1st of the month, weekly = Sunday, daily = every other day. Newer backups are never touched by older rules.

### Lifecycle rules (create in the bucket; nothing is created by the application)

```json
{ "Rules": [
  { "ID": "daily",   "Status": "Enabled", "Filter": { "Tag": { "Key": "retention", "Value": "daily" } },
    "Expiration": { "Days": 8 },   "NoncurrentVersionExpiration": { "NoncurrentDays": 1 } },
  { "ID": "weekly",  "Status": "Enabled", "Filter": { "Tag": { "Key": "retention", "Value": "weekly" } },
    "Expiration": { "Days": 36 },  "NoncurrentVersionExpiration": { "NoncurrentDays": 1 } },
  { "ID": "monthly", "Status": "Enabled", "Filter": { "Tag": { "Key": "retention", "Value": "monthly" } },
    "Expiration": { "Days": 401 }, "NoncurrentVersionExpiration": { "NoncurrentDays": 1 } },
  { "ID": "history", "Status": "Enabled", "Filter": { "Prefix": "history/" },
    "NoncurrentVersionExpiration": { "NoncurrentDays": 30 } },
  { "ID": "cleanup", "Status": "Enabled", "Filter": { "Prefix": "" },
    "AbortIncompleteMultipartUpload": { "DaysAfterInitiation": 2 },
    "Expiration": { "ExpiredObjectDeleteMarker": true } }
] }
```

`reports/` (rehearsal reports) are small and can stay. Adjust the day counts together with `BACKUP_LOCK_DAYS_*` (expiration = lock + 1).

### IAM policy for the application key (verified against the calls the code makes)

A 6 MB+ package is uploaded with a **multipart upload** (`CreateMultipartUpload`, `UploadPart`, `CompleteMultipartUpload`), which needs more than `PutObject`:

```json
{ "Version": "2012-10-17", "Statement": [
  { "Sid": "ListBackups", "Effect": "Allow",
    "Action": ["s3:ListBucket", "s3:ListBucketMultipartUploads"],
    "Resource": "arn:aws:s3:::BUCKET" },
  { "Sid": "ReadWriteBackups", "Effect": "Allow",
    "Action": ["s3:GetObject", "s3:PutObject", "s3:PutObjectTagging", "s3:PutObjectRetention",
               "s3:AbortMultipartUpload", "s3:ListMultipartUploadParts"],
    "Resource": "arn:aws:s3:::BUCKET/*" },
  { "Sid": "NeverDeleteOrReconfigure", "Effect": "Deny",
    "Action": ["s3:DeleteObject", "s3:DeleteObjectVersion", "s3:BypassGovernanceRetention",
               "s3:PutBucketObjectLockConfiguration", "s3:PutLifecycleConfiguration"],
    "Resource": ["arn:aws:s3:::BUCKET", "arn:aws:s3:::BUCKET/*"] } ] }
```

Add `kms:GenerateDataKey` and `kms:Decrypt` on the key when `BACKUP_S3_SSE=aws:kms`. `s3:PutObjectRetention` is only needed when `BACKUP_S3_OBJECT_LOCK_MODE` is set.

### ACL-disabled buckets (the AWS default)

The backup disk uses the `s3-noacl` driver: the standard S3 client with the `ACL` parameter and `x-amz-acl` header removed from every request (the same approach already used for Supabase). Verified with a simulated S3 in the tests: no request carries an ACL; packages carry `Tagging`, `ServerSideEncryption` and (when configured) `ObjectLockMode` / `ObjectLockRetainUntilDate`; history files carry no Object Lock. Not verified against real AWS (no bucket is authorised): test once with an isolated bucket before Production.

### Upload verification

After every upload the stored package size is compared and the package is **read back and SHA-256 compared** (`BACKUP_VERIFY_UPLOAD=true`). A short or altered object is deleted and the backup is reported as failed, never as verified.

## 2b. Multiple application instances

The backup lock and heartbeat live in the cache store named by `BACKUP_LOCK_STORE` (default: the app cache, `file`). A per-instance store only protects **one** instance. When the service is scaled out set `BACKUP_LOCK_STORE=database` (or `redis`); `php artisan backup:doctor` warns about a per-instance store in production. Interrupted runs are detected by heartbeat: a run showing no activity for `BACKUP_STALE_AFTER_MINUTES` (30) while no lock is held is marked failed and its half-written package removed.

## 2c. Operations

* `php artisan backup:reverify [--limit=3]` re-checks stored packages (decrypt, SHA-256, archive listing) and flags damaged ones (`verified=false`, `verification_error`) without deleting anything. Run weekly (cron / Render cron job).
* `php artisan backup:extract <id|file> <new-dir>` + [BACKUP_DISASTER_RECOVERY.md](BACKUP_DISASTER_RECOVERY.md) for manual recovery.
* Free-disk preflight (database + media + 200 MB, x3 to x5 the package size for verify/extract/rehearsal) runs before create, verify, rehearsal, extract, reverify and download; too little space returns HTTP 507 / a clear error before anything is copied.
