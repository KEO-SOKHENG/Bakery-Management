# Database Setup Guide
## Moon Cake Bakery Management System

This document provides step-by-step instructions to configure and initialize the PostgreSQL database for the Moon Cake Bakery Management System.

---

## 1. Prerequisites

- **Database Engine:** PostgreSQL 16+ (tested on PostgreSQL 16.14 64-bit)
- **Database Client:** `psql` command-line utility or pgAdmin 4
- **Encoding:** `UTF8`
- **PHP Version:** PHP 8.2+ or 8.3+ with `ext-pgsql` and `ext-pdo_pgsql` enabled

---

## 2. Automated Schema Initialization

### Option A: Using `psql` (Recommended)

1. Open a terminal / command prompt.
2. Create the empty PostgreSQL database:
   ```bash
   createdb -h localhost -p 5432 -U postgres -E UTF8 bakery_management_system
   ```
   *(Or in `psql`: `CREATE DATABASE bakery_management_system WITH ENCODING = 'UTF8';`)*

3. Import the clean schema file:
   ```bash
   psql -h localhost -p 5432 -U postgres -d bakery_management_system -f schema.sql
   ```

4. Confirm that exactly 30 public tables were created:
   ```bash
   psql -h localhost -p 5432 -U postgres -d bakery_management_system -c "\dt public.*"
   ```

---

## 3. Laravel Migration Alternative

If setting up via Laravel's native migration engine instead of the raw SQL schema:

1. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```

2. Configure database credentials in `.env`:
   ```dotenv
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=bakery_management_system
   DB_USERNAME=postgres
   DB_PASSWORD=your_secure_password
   ```

3. Generate application encryption key:
   ```bash
   php artisan key:generate
   ```

4. Execute database migrations:
   ```bash
   php artisan migrate
   ```

---

## 4. Verification Checklist

After database initialization:

- [x] Total public tables: **30 tables** (all standard tables: users, orders, order_items, sales, payments, products, categories, ingredients, recipes, productions, suppliers, purchase_orders, notifications, audit_logs, settings, etc.)
- [x] Deliveries table: **Retired** (the historical customer deliveries table has been retired; order fulfillment is pickup-only)
- [x] Supplier procurement: `purchase_orders.expected_delivery_date` intact for vendor supply chain management.

