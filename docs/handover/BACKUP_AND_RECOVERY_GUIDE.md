# Backup and Recovery Guide
## Moon Cake Bakery Management System

---

## 1. Core Operational Principles & Safety Invariants

1. **Explicit Single-Variable Naming Pattern (`$BACKUP_FILE`)**:
   - Every backup session must define and reuse an explicit, timestamped environment variable.
   - **Never use wildcard file paths** (such as `*.sql` or `*.dump`) in restore commands, as shell glob expansion can cause ambiguous redirection or process outdated backups.
2. **Strict Prohibition on Generic Rollbacks**:
   - Commands such as `php artisan migrate:rollback --step=2` are **strictly prohibited** in production. They rollback by batch order, not by targeted migration, and risk cascading drops or foreign key deadlocks.
3. **No In-Place Production Overwrite**:
   - Operators must **never restore a backup directly over the active production database** during an incident without prior delta reconciliation and dual administrative approval.
4. **Mandatory Dual Approval for Disaster Recovery**:
   - Any database restoration or forward-only schema fix requires written authorization from both the **Database Administrator (DBA)** and the **Accounting Administrator**.

---

## 2. MySQL Backup & Restore Procedures

### 2.1 Storage Engine Pre-Requisite Audit
Before dumping MySQL, inspect table engines to determine whether transactional snapshotting applies:
```sql
SELECT table_name, engine 
FROM information_schema.tables 
WHERE table_schema = 'bakery_management_system' 
  AND engine != 'InnoDB';
```
- **If 0 rows returned**: All tables are `InnoDB`. Proceed with Procedure A (`--single-transaction`).
- **If non-InnoDB tables exist**: Proceed with Procedure B (quiesced zero-write backup).

### 2.2 Procedure A: Consistent Logical Dump (Pure InnoDB)
```bash
# 1. Define explicit session backup variable
BACKUP_FILE="/backups/bakery_pre_deploy_$(date +%Y%m%d_%H%M%S).sql"

# 2. Execute dump (OMITTING --databases to prevent embedded USE statements; OMITTING --events)
mysqldump -h <prod_host> -P <prod_port> -u <backup_user> -p \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --hex-blob \
  bakery_management_system > "${BACKUP_FILE}"

# 3. Confirm exit code is 0
echo "Backup Exit Code: $?"

# 4. Verify non-zero file size and generate cryptographic SHA-256 hash
ls -lh "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# 5. Verify physical completion marker (must return final comment line)
tail -n 10 "${BACKUP_FILE}" | grep "Dump completed on"
```

### 2.3 Procedure B: Quiesced Dump (For Mixed or Non-InnoDB Tables)
If non-InnoDB tables exist, `--single-transaction` does not guarantee consistency. The backup **must be executed only after Step 4 (Maintenance Mode) and Step 7 (Zero-Write Verification)**:
```bash
BACKUP_FILE="/backups/bakery_pre_deploy_$(date +%Y%m%d_%H%M%S).sql"

mysqldump -h <prod_host> -P <prod_port> -u <backup_user> -p \
  --quick \
  --routines \
  --triggers \
  --hex-blob \
  bakery_management_system > "${BACKUP_FILE}"

# Verify exit code 0, non-zero size, SHA-256, and completion marker
```

### 2.4 MySQL Isolated Disposable Staging Restore Test
To verify backup validity without touching production:
```bash
# 1. On confirmed isolated staging server, prepare clean target database:
mysql -h <staging_host> -P <staging_port> -u <staging_user> -p \
  -e "CREATE DATABASE IF NOT EXISTS disposable_verify_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Execute restore unambiguously into disposable_verify_db (using explicit $BACKUP_FILE):
mysql -h <staging_host> -P <staging_port> -u <staging_user> -p \
  disposable_verify_db < "${BACKUP_FILE}"

# 3. Confirm exit code is 0
echo "Restore Exit Code: $?"

# 4. Verify table and row count parity on staging against production:
mysql -h <staging_host> -P <staging_port> -u <staging_user> -p disposable_verify_db -e "
  SELECT 'orders' AS tbl, COUNT(*) AS count FROM orders
  UNION ALL
  SELECT 'sales', COUNT(*) AS count FROM sales
  UNION ALL
  SELECT 'users', COUNT(*) AS count FROM users;
"
```

---

## 3. PostgreSQL Backup & Restore Procedures

PostgreSQL operates under native Multi-Version Concurrency Control (MVCC) and transactional DDL. Storage engine distinctions do not apply.

### 3.1 PostgreSQL Binary Custom-Format Dump
```bash
# 1. Define explicit session backup variable
BACKUP_FILE="/backups/bakery_pre_deploy_$(date +%Y%m%d_%H%M%S).dump"

# 2. Execute full binary custom-format dump
pg_dump -h <prod_host> -p <prod_port> -U <backup_user> \
  -Fc -b -v -f "${BACKUP_FILE}" bakery_management_system

# 3. Confirm exit code is 0
echo "Backup Exit Code: $?"

# 4. Verify non-zero file size and generate cryptographic SHA-256 hash
ls -lh "${BACKUP_FILE}"
sha256sum "${BACKUP_FILE}" > "${BACKUP_FILE}.sha256"

# 5. Table of Contents (TOC) structural inspection
pg_restore --list "${BACKUP_FILE}" | grep -E "(TABLE DATA public (orders|sales|users|products|deliveries|migrations))"
```

### 3.2 PostgreSQL Isolated Disposable Staging Restore Test
```bash
# 1. Execute restore into positively verified isolated staging instance
pg_restore -h <staging_host> -p <staging_port> -U <staging_user> \
  -d disposable_verify_db --clean --no-owner "${BACKUP_FILE}"

# 2. Confirm restore exit code is 0
echo "Restore Exit Code: $?"

# 3. Verify row count parity on staging against production:
SELECT 'orders' AS tbl, count(*) FROM orders
UNION ALL
SELECT 'sales', count(*) FROM sales
UNION ALL
SELECT 'users', count(*) FROM users;
```

---

## 4. Disaster Recovery & Incident Management

```
                                [MIGRATION OR SYSTEM INCIDENT]
                                              |
                                              v
                              [1. MAINTAIN MAINTENANCE MODE]
                               (Keep public traffic blocked)
                                              |
                                              v
                              [2. STOP ALL BACKGROUND WRITERS]
                               (Queue workers, schedulers, cron)
                                              |
                                              v
                              [3. CAPTURE POST-BACKUP DELTAS]
                               (Audit records created after backup)
                                              |
                                              v
                              [4. DBA & ACCOUNTING ASSESSMENT]
                                              |
                     +------------------------+------------------------+
                     |                                                 |
                     v                                                 v
         [OPTION A: FORWARD-ONLY FIX]                     [OPTION B: STAGING RESTORE]
          - Preferred industry practice                    - Restore to staging instance
          - Idempotent schema patch                        - Reconcile post-backup delta
          - Zero data rewinding                            - Switch over after dual sign-off
```

### 4.1 Post-Backup Delta Transaction Extraction
If transactions occurred between the backup creation and the maintenance window:
```sql
SELECT * FROM sales WHERE created_at >= '<BACKUP_TIMESTAMP>';
SELECT * FROM orders WHERE created_at >= '<BACKUP_TIMESTAMP>';
SELECT * FROM payments WHERE created_at >= '<BACKUP_TIMESTAMP>';
```
Export delta records to an encrypted audit CSV to prevent financial loss during reconciliation.

### 4.2 Financial Ledger Invariant
- **Never delete, truncate, reassign, or set to null** any financial sales records.
- Every sales row corresponds to legal accounting revenue.
- Dual sign-off (DBA + Accounting Administrator) is mandatory before applying any disaster recovery changes.

