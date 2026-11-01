# Production Readiness Checklist
## Moon Cake Bakery Management System

---

## 1. Pre-Deployment Readiness Matrix

| Verification Category | Requirement Specification | Verification Evidence / Command | Status |
| :--- | :--- | :--- | :---: |
| **PHP Runtime** | `PHP >= 8.3.0` with OPcache enabled | `php -v` (Local verified: 8.3.28) | **VERIFIED** |
| **PHP Extensions** | `pdo_mysql` / `pdo_pgsql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`, `fileinfo` | `php -m` | **VERIFIED** |
| **Framework Version** | Laravel `13.25.0` locked | `composer.lock` inspection | **VERIFIED** |
| **Unit & Feature Tests**| 245 tests, 1,200 assertions, 0 failures | `php artisan test` (SQLite :memory:) | **VERIFIED** |
| **Delivery Tests** | 27 tests passing, snapshot immutability verified | `php artisan test --filter=DeliveryManagementTest` | **VERIFIED** |
| **File Permissions** | `storage/` and `bootstrap/cache/` writable (`775` / `www-data`) | `ls -la storage bootstrap/cache` | *Pending Host Setup* |
| **Environment Config** | `APP_ENV=production`, `APP_DEBUG=false`, secure `APP_KEY` | Production `.env` inspection | *Pending Production Gate* |
| **Database Compatibility**| MySQL 8.0+ `InnoDB` OR PostgreSQL 14+ | Storage engine audit query | *Pending Live Check* |
| **Backup Integrity** | Exit code 0, SHA-256 hash, completion marker | Section 2 of Backup Guide | *Pending Operator Execution* |
| **Disposable Restore** | Verified restore into dedicated non-production host | Section 2/3 of Backup Guide | *Pending Operator Execution* |
| **Duplicate Preflight** | `sales.order_id` duplicates count = 0 | Preflight SQL query | *Pending Quiesced Check* |
| **Financial Baseline** | `COUNT(id)` and `SUM(total)` captured under zero-write | Financial baseline SQL query | *Pending Quiesced Check* |
| **Approval Gates** | DBA (Gate C) and Accounting (Gate D) formal sign-offs | Change management ticket signatures | *Pending Dual Sign-Off* |

---

## 2. Server & Runtime Configuration

### 2.1 Web Server & PHP-FPM Configuration
- **Web Server**: Nginx or Apache with `mod_proxy_fcgi`.
- **PHP-FPM Pool**: Dedicated pool running under user `www-data` (or target web user).
- **OPcache Settings (`php.ini`)**:
  ```ini
  opcache.enable=1
  opcache.memory_consumption=128
  opcache.interned_strings_buffer=8
  opcache.max_accelerated_files=10000
  opcache.revalidate_freq=0
  opcache.validate_timestamps=0 ; For maximum performance in production
  ```
- **Web Runtime Reload Command**:
  - Linux PHP-FPM: `sudo systemctl reload php8.3-fpm`
  - Apache mod_php: `sudo systemctl reload apache2`

### 2.2 File and Directory Permissions
```bash
sudo chown -R www-data:www-data /var/www/Bakery_Management_System
sudo find /var/www/Bakery_Management_System -type f -exec chmod 644 {} \;
sudo find /var/www/Bakery_Management_System -type d -exec chmod 755 {} \;
sudo chmod -R 775 /var/www/Bakery_Management_System/storage /var/www/Bakery_Management_System/bootstrap/cache
```

### 2.3 Queue Worker Configuration (Supervisor)
- Queue Driver: `database` ([config/queue.php](file:///c:/wamp64/www/Bakery_Management_System/config/queue.php#L16))
- Supervisor Configuration File (`/etc/supervisor/conf.d/laravel-worker.conf`):
  ```ini
  [program:laravel-worker]
  process_name=%(program_name)s_%(process_num)02d
  command=php /var/www/Bakery_Management_System/artisan queue:work --sleep=3 --tries=3 --max-time=3600
  autostart=true
  autorestart=true
  stopasgroup=true
  killasgroup=true
  user=www-data
  numprocs=2
  redirect_stderr=true
  stdout_logfile=/var/log/supervisor/laravel-worker.log
  stopwaitsecs=300
  ```

### 2.4 Scheduler & Cron Configuration
- Crontab entry for user `www-data` (`sudo crontab -u www-data -e`):
  ```text
  * * * * * cd /var/www/Bakery_Management_System && php artisan schedule:run >> /dev/null 2>&1
  ```
- Executes scheduled command: `notifications:sync-alerts` (hourly).

---

## 3. The Definitive 16-Step Production Deployment Sequence

The deployment must follow this exact sequential order:

```
[PHASE 1: PRE-FLIGHT VERIFICATION & BACKUP]
  Step 1:  Pre-Flight Runtime Verification (PHP >= 8.3, Extensions, Disk Space, Staging Host Identity)
  Step 2:  Backup & Isolated Restore Verification (Dump to $BACKUP_FILE, Checksum, Restore to Staging)
  Step 3:  Administrative Pre-Deployment Approval (DBA & Accounting Sign-Off on Staging Parity)

[PHASE 2: TRAFFIC SUSPENSION & QUIESCENCE]
  Step 4:  Maintenance Mode Activation (php artisan down --render="errors::503")
  Step 5:  Queue Worker Drain and Stop (sudo supervisorctl stop laravel-worker:*)
  Step 6:  Scheduler & External Writer Suspension (Comment crontab, pause external webhooks)
  Step 7:  Multi-Layer Zero-Write Verification (Check innodb_trx / pg_stat_activity for 0 active queries)

[PHASE 3: PREFLIGHT AUDIT & BASELINE CAPTURE]
  Step 8:  Preflight Duplicate Scan & Financial Baseline Capture (sales.order_id duplicates = 0; capture SUM(total))
  Step 9:  DBA & Accounting Final Go/No-Go Confirmation

[PHASE 4: APPLICATION DEPLOYMENT & SCHEMA MIGRATION]
  Step 10: Code Deployment & Schema Migrations (Deploy Category A & B; php artisan migrate --force)
  Step 11: Cache Optimization & Web Runtime Reload (optimize:clear, optimize, view:cache, systemctl reload php8.3-fpm)

[PHASE 5: INTERNAL VALIDATION & FINANCIAL PARITY]
  Step 12: Internal Health Checks While Public Traffic Remains Blocked (curl confirms 503; CLI checks pass)
  Step 13: Financial Parity Verification (Post-migration SUM(total) matches Step 8 baseline with 0.00 variance)

[PHASE 6: PROCESS RESUMPTION & SERVICE RESTORATION]
  Step 14: Queue Worker & Scheduler Resumption (artisan queue:restart, start supervisor workers, uncomment crontab)
  Step 15: Maintenance Mode Exit (php artisan up)
  Step 16: Final Public HTTP Verification & Release Sign-Off (curl https://bakery.com/login returns 200 OK)
```

---

## 4. Dual Sign-Off Authorization Gates

### Gate C: Database Administrator (DBA) Sign-Off
- [ ] Physical production schema and storage engine verified (`InnoDB` / `PostgreSQL`).
- [ ] Isolated disposable staging restore verified with matching row counts.
- [ ] Preflight duplicate check query on `sales.order_id` confirmed returning 0 rows under zero-write conditions.
- [ ] Forward-only corrective strategy confirmed in the event of unexpected migration failure.
- **DBA Name**: __________________________ **Signature**: __________________ **Date**: ____________

### Gate D: Accounting Administrator Sign-Off
- [ ] Pre-migration sales ledger baseline recorded (`COUNT(id)` and `SUM(total)`).
- [ ] Post-migration sales ledger parity confirmed with exact 0.00 discrepancy prior to writer resumption.
- [ ] Certification that zero sales records were deleted, modified, or set to null.
- **Accounting Lead Name**: ________________ **Signature**: __________________ **Date**: ____________

