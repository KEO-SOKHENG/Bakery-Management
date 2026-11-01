# Moon Cake Bakery Management System
## Academic Submission Package & Project Documentation

> **Academic Submission Note:**  
> This file serves as the master submission documentation for university course evaluation. The working repository maintains `README.md` as an owner-approved unstaged Git deletion; this document represents the comprehensive, authoritative guide for academic review and grading.

---

## 1. Project Overview & Objectives

The **Moon Cake Bakery Management System** is a full-featured enterprise web application developed with **Laravel 12 / 13** and **PostgreSQL 16**. The system automates end-to-end bakery retail, manufacturing, inventory control, and accounting operations.

### Key Objectives:
- **POS & Checkout Automation:** High-speed barcode/search checkout terminal supporting split tenders, cash, card, and QR payments with automated invoice and receipt printing.
- **Recipe & Batch Production Tracking:** Bill of Materials (BOM) management, auto-deduction of ingredient inventory upon baking batch completion, and real-time recipe unit cost calculations.
- **Inventory & Stock Movements:** Multi-ingredient stock tracking, minimum stock thresholds, automatic low-stock alerts, and auditable stock movement logs.
- **Supplier Procurement:** Purchase order generation, vendor management, and tracking of ingredient procurement orders with expected delivery dates.
- **Financial & Accounting Ledgers:** Double-entry-aligned sales, orders, and payment records with immutable financial auditing.
- **Fulfillment Model:** Streamlined **Pickup-Only** order lifecycle (`Pending` → `Preparing / Baking` → `Ready for Pickup` → `Completed` / `Cancelled`) with automated notification handling and historical URL redirection.

---

## 2. Technology Stack & Prerequisites

| Layer | Component | Specification |
| :--- | :--- | :--- |
| **Backend Framework** | Laravel | Framework 13.25 / PHP 8.3 / 8.2 |
| **Database Engine** | PostgreSQL | Version 16.14 (UTF-8 encoding) |
| **Frontend Architecture** | Blade / Vite / TailwindCSS | Vite 8.2.1, TailwindCSS 4.0 |
| **Testing Framework** | PHPUnit | 22 test suites, 219 tests (1,039 assertions) |
| **Server Environment** | Windows WAMP / Linux CLI | Apache / Nginx, PHP-FPM |

### Prerequisites:
- PHP 8.2 or 8.3 with extensions: `pdo_pgsql`, `pgsql`, `sodium`, `mbstring`, `xml`, `bcmath`, `curl`
- PostgreSQL 16.0+
- Composer 2.7+
- Node.js 20+ and NPM 10+

---

## 3. Installation & Local Development Setup

### Step 1: Clone and Environment Configuration
```bash
# Clone the repository
git clone <repository_url>
cd Bakery_Management_System

# Copy the environment template
cp .env.example .env
```

### Step 2: Configure Database Credentials
Edit `.env` to match your local PostgreSQL server:
```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=bakery_management_system
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

### Step 3: Install Dependencies
```bash
# Install PHP dependencies
composer install --optimize-autoloader

# Generate application key
php artisan key:generate

# Install Node dependencies and build assets
npm install
npm run build
```

### Step 4: Database Setup
Import the clean PostgreSQL schema from the submission package:
```bash
psql -h 127.0.0.1 -p 5432 -U postgres -d bakery_management_system -f submission/database/schema.sql
```
*(Or run native migrations: `php artisan migrate`)*

### Step 5: Start Local Development Server
```bash
php artisan serve
```
Access the application at `http://localhost:8000`.

---

## 4. User Roles & Access Control

Role authorization is strictly enforced via the `role` middleware:

| Role | Capabilities | Primary Entry Point |
| :--- | :--- | :--- |
| **Admin (`admin`)** | Unrestricted access to POS, products, production, inventory, suppliers, reports, users, settings, and audit logs. | `/admin/dashboard` |
| **Manager (`manager`)** | Operational access to POS, products, stock, recipes, suppliers, purchase orders, and business reports. | `/pos` |
| **Cashier (`cashier`)** | Front-of-house operations: POS terminal, fast checkout, order creation, receipt printing. | `/pos` |
| **Baker (`baker`)** | Kitchen production tracking, ingredient availability, and baking batch status updates. | `/admin/production` |

---

## 5. Main Functional Modules

1. **POS Terminal (`/pos`):** Interactive fast-search terminal, customer assignment, product grid, discounts, tax calculations, and instant receipt generation.
2. **Order Management (`/admin/orders`):** Order lifecycle tracking from placement to pickup and completion.
3. **Product & Category Catalog (`/admin/products`, `/admin/categories`):** Price, SKU, shelf life, expiration warning days, category management.
4. **Ingredients & Inventory (`/admin/ingredients`):** Stock levels, reorder thresholds, unit measurements, and historical movement ledgers.
5. **Recipes & Formulations (`/admin/recipes`):** Multi-ingredient recipe mapping, ingredient portion costs, total batch production cost.
6. **Supplier Procurement (`/admin/suppliers`, `/admin/purchase-orders`):** Purchase order workflows, supplier profiles, delivery tracking (`expected_delivery_date`).
7. **Production Tracking (`/admin/production`):** Daily baking schedules, baker assignment, batch progress, and automatic inventory depletion.
8. **Financial Reports (`/admin/reports`):** Daily sales summaries, revenue totals, tax reports, payment method breakdown, and inventory valuation.
9. **Notification System (`/admin/notifications`):** In-app notifications with graceful fallback handling for historical delivery URLs.

---

## 6. Testing & Quality Verification

Run the test suite using PHPUnit:
```bash
# Execute complete test suite
php artisan test

# Execute specific feature suites
php artisan test --filter NotificationManagementTest
php artisan test --filter OrderManagementTest
php artisan test --filter ReportManagementTest
```
- **Test Pass Rate:** 20 of 22 feature test suites passed with 100% success (215 of 219 tests passed, 1,039 assertions).
- **Known Test Limitations:** 4 tests in `DashboardTest` and `UserManagementTest` reference deleted auxiliary controllers (`Cashier\DashboardController`, `Manager\DashboardController`), preserved as documented user working-tree modifications.

---

## 7. Academic Submission Deliverables

The accompanying `submission/` package contains:
- `submission/docs/`: Full project overview, architecture specifications, database documentation, and user guides.
- `submission/database/`: Clean PostgreSQL `schema.sql` (30 public tables, credential-free) and setup runbook.
- `submission/reports/`: Full test execution report, audit report, and final submission checklist.
