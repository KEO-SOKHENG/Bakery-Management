# Database Documentation
## Moon Cake Bakery Management System

---

## 1. Database Overview & Production Recommendations

The application data layer is built on Laravel's Eloquent ORM. It is architected for dual-engine production deployment supporting either **MySQL 8.0+** or **PostgreSQL 14+**, with automated testing executed in **SQLite** memory isolation.

### 1.1 Production Database Requirements
- **MySQL Target**:
  - Version: MySQL 8.0 or 8.4 LTS
  - Default Storage Engine: `InnoDB` (Mandatory for foreign keys and consistent snapshots)
  - Character Set & Collation: `utf8mb4` with `utf8mb4_unicode_ci`
- **PostgreSQL Target**:
  - Version: PostgreSQL 14, 15, or 16
  - Default Character Encoding: `UTF8`

---

## 2. Actual Database Tables Manifest (23 Tables)

| Table Name | Description | Key Foreign Keys | Critical Indexes |
| :--- | :--- | :--- | :--- |
| **`users`** | User accounts, roles, credentials, and custom permissions | None | `email` (unique), `username` (unique), `role` |
| **`cache`** / **`cache_locks`** | Framework caching, rate limiting, and queue restart flags | None | `key` (primary) |
| **`jobs`** / **`failed_jobs`** | Background queue processing table (`database` queue driver) | None | `queue`, `reserved_at` |
| **`categories`** | Product categories and classification | None | `name` (unique) |
| **`suppliers`** | Raw material suppliers and procurement terms | None | `email`, `phone` |
| **`products`** | Finished bakery goods, retail pricing, stock, shelf life | `category_id` $\rightarrow$ `categories` | `barcode`, `category_id`, `expiry_date` |
| **`ingredients`** | Raw baking ingredients, unit stock, reorder levels | None | `name`, `current_stock`, `expiry_date` |
| **`recipes`** | Baking formulas, batch yields, production minutes | `product_id` $\rightarrow$ `products` | `product_id` |
| **`recipe_ingredients`** | Pivot table mapping recipes to required raw ingredients | `recipe_id`, `ingredient_id` | `[recipe_id, ingredient_id]` |
| **`customers`** | Customer records, loyalty points, purchase tier | None | `phone` (unique), `email` |
| **`orders`** | Bakery customer orders, custom cake specs, pickup dates | `user_id`, `customer_id` | `order_number` (unique), `customer_id`, `order_status` |
| **`order_items`** | Line items for orders, unit prices, subtotal | `order_id`, `product_id` | `order_id`, `product_id` |
| **`deliveries`** | Delivery dispatch, driver routing, tracking, order snapshot | `order_id` (nullOnDelete), `user_id` | `order_number`, `tracking_number` (unique), `delivery_status` |
| **`sales`** | Sales revenue receipts, payment methods, sold timestamp | `order_id`, `user_id` | `order_id` (UNIQUE), `sold_at` |
| **`payments`** | Detailed payment records, transactions, payment reference | `order_id`, `user_id` | `order_id`, `payment_method` |
| **`productions`** | Production batches, baking lifecycle, baker assignments | `recipe_id`, `baker_id`, `user_id` | `status`, `baker_id`, `scheduled_date` |
| **`purchase_orders`** | Procurement POs sent to suppliers | `supplier_id`, `user_id` | `po_number` (unique), `supplier_id`, `status` |
| **`purchase_order_items`**| Line items for POs, ordered quantity, unit price | `purchase_order_id`, `ingredient_id` | `purchase_order_id`, `ingredient_id` |
| **`stock_movements`** | Audit trail of raw material additions and batch deductions | `ingredient_id`, `user_id` | `ingredient_id`, `type`, `created_at` |
| **`attendances`** | Staff check-in and check-out records | `user_id` $\rightarrow$ `users` | `user_id`, `date` |
| **`work_schedules`** | Staff shift assignments | `user_id` $\rightarrow$ `users` | `user_id`, `date` |
| **`salaries`** | Staff payroll disbursement records | `user_id` $\rightarrow$ `users` | `user_id`, `month`, `year` |
| **`notifications`** | System condition alerts (stock, expiry, custom orders) | `user_id` $\rightarrow$ `users` (nullable) | `type`, `is_read`, `created_at` |
| **`audit_logs`** | Security audit trail for administrative changes | `user_id` $\rightarrow$ `users` | `action`, `model_type`, `created_at` |
| **`settings`** | Global application key-value configuration pairs | None | `key` (unique) |

---

## 3. Detailed Delivery Schema & Snapshot Safeguards

The Delivery Management module was recreated with enterprise-grade data protection to decouple delivery logistics from order purging.

### 3.1 Migration: `2026_10_28_000001_recreate_deliveries_table.php`
```sql
CREATE TABLE deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NULL,
    order_number VARCHAR(255) NULL,
    user_id BIGINT UNSIGNED NULL,
    recipient_name VARCHAR(255) NULL,
    recipient_phone VARCHAR(255) NULL,
    delivery_address TEXT NOT NULL,
    delivery_status VARCHAR(255) NOT NULL DEFAULT 'pending',
    scheduled_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    tracking_number VARCHAR(255) NULL UNIQUE,
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    notes TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    CONSTRAINT fk_deliveries_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_deliveries_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deliveries_order_number (order_number)
);
```

### 3.2 Safeguard Mechanisms Verified in Implementation
1. **Foreign Key Policy (`ON DELETE SET NULL`)**: If a historical order is permanently archived or purged, the delivery record is preserved.
2. **Immutable Snapshot (`order_number`)**:
   - Captured automatically on creation via model hook.
   - Protected against modification during subsequent updates via model immutability guard.
   - Displays as `ORD-XXXXX (Historical Archive)` in views when the parent order is purged.
3. **Soft Deletes (`deleted_at`)**: Delivery records support soft deletion, preventing catastrophic physical loss during administrative cancellation.

### 3.3 Migration: `2026_10_28_000002_add_delivery_safeguards_to_orders_and_sales.php`
- **Preflight Check**: Contains a defensive non-destructive check that counts duplicate `sales.order_id` records. If historical duplicates exist, the migration aborts with a descriptive `RuntimeException` before attempting DDL.
- **Unique Constraint**: Adds a unique index (`sales_order_id_unique`) on `sales.order_id` to guarantee 1:1 financial integrity between orders and sales receipts.
- **Loyalty Safeguard**: Adds nullable column `orders.loyalty_points_awarded` to prevent duplicate loyalty point awards during delivery settlement.

---

## 4. Chronological Migration History (38 Migrations)

> [!IMPORTANT]
> **Documentation Integrity & Historical Preservation Rule**: Historical migration files must **never be modified, renamed, or deleted**. Any schema changes must be applied strictly via new, forward-only migration files.

```
# Baseline System Migrations (2026-08-24)
0001_01_01_000000_create_users_table.php
0001_01_01_000001_create_cache_table.php
0001_01_01_000002_create_jobs_table.php
2026_08_24_000001_create_categories_table.php
2026_08_24_000002_create_suppliers_table.php
2026_08_24_000003_create_products_table.php
2026_08_24_000004_create_ingredients_table.php
2026_08_24_000005_create_recipes_table.php
2026_08_24_000006_create_recipe_ingredients_table.php
2026_08_24_000007_create_orders_table.php
2026_08_24_000008_create_order_items_table.php
2026_08_24_000009_create_productions_table.php
2026_08_24_000010_create_sales_table.php
2026_08_24_000011_create_settings_table.php
2026_08_24_000012_create_customers_table.php
2026_08_24_000013_add_customer_id_to_orders_table.php
2026_08_24_000014_create_payments_table.php
2026_08_24_000015_create_deliveries_table.php (Legacy original table)

# Production & Procurement Enhancements (2026-09-08)
2026_09_08_000016_enhance_productions_table.php
2026_09_08_000017_enhance_suppliers_table.php
2026_09_08_000018_enhance_ingredients_table.php
2026_09_08_000019_create_purchase_orders_table.php
2026_09_08_000020_create_purchase_order_items_table.php
2026_09_08_000021_create_stock_movements_table.php
2026_09_08_000022_enhance_orders_table.php

# Security, HR & Expiry Tracking (2026-09-10 to 2026-09-21)
2026_09_10_000001_create_notifications_table.php
2026_09_10_000002_enhance_users_table.php
2026_09_10_000003_create_audit_logs_table.php
2026_09_11_000001_add_production_time_to_recipes_table.php
2026_09_11_000002_add_shelf_life_and_expiry_to_products_table.php
2026_09_11_000003_create_attendances_table.php
2026_09_11_000004_create_work_schedules_table.php
2026_09_11_000005_create_salaries_table.php
2026_09_16_140000_drop_deliveries_table.php (Legacy drop)
2026_09_17_165105_add_unique_to_categories_name.php
2026_09_21_000001_add_performance_indexes.php

# Delivery Module Recreation & Integrity Safeguards (2026-10-28)
2026_10_28_000001_recreate_deliveries_table.php
2026_10_28_000002_add_delivery_safeguards_to_orders_and_sales.php
```

---

## 5. Historical Data Considerations & Pre-Deployment Invariants

1. **Duplicate Sales Invariant**:
   - Migration `2026_10_28_000002` applies a `UNIQUE` index on `sales.order_id`.
   - If historical production data contains multiple sales records pointing to the same order ID, the migration will intentionally abort.
   - The operator must execute the preflight diagnostic query during Step 8 of the deployment runbook:
     ```sql
     SELECT order_id, COUNT(*) AS duplicate_count
     FROM sales
     WHERE order_id IS NOT NULL
     GROUP BY order_id
     HAVING COUNT(*) > 1;
     ```
   - Any historical duplicate sales must be resolved manually with the accounting team prior to migration.
2. **Nullable Order Snapshots for Legacy Deliveries**:
   - Deliveries created historically prior to the snapshot update may have `order_number = NULL`.
   - The views and tests are explicitly verified to display fallback order details (`Order #ID` or `Historical Delivery`) when `order_number` is null.
3. **Financial Column Integrity**:
   - In both `sales` and `orders`, the monetary amount is stored in column `total` (`decimal(10,2)`). Neither table uses `total_amount`. All accounting queries must query `SUM(total)`.

