# Technical Documentation
## Moon Cake Bakery Management System

---

## 1. Technology Stack Overview

| Layer | Component | Version / Specification | Evidence Source |
| :--- | :--- | :--- | :--- |
| **Backend Framework** | Laravel Framework | `v13.25.0` | [composer.lock](file:///c:/wamp64/www/Bakery_Management_System/composer.lock) |
| **PHP Runtime** | PHP | `^8.3` (Runtime: `8.3.28`) | [composer.json](file:///c:/wamp64/www/Bakery_Management_System/composer.json) |
| **Frontend Tooling** | Vite | `v8.0.0` | [package.json](file:///c:/wamp64/www/Bakery_Management_System/package.json) |
| **CSS Styling** | Tailwind CSS / Vanilla CSS | `v4.0.0` & custom stylesheets | [package.json](file:///c:/wamp64/www/Bakery_Management_System/package.json), `public/css/` |
| **Template Engine** | Blade | Laravel 13 Native | `resources/views/` |
| **Document Generation** | DomPDF | `barryvdh/laravel-dompdf ^3.1` | [composer.json](file:///c:/wamp64/www/Bakery_Management_System/composer.json) |
| **Testing Framework** | PHPUnit | `v12.5.12` | [composer.lock](file:///c:/wamp64/www/Bakery_Management_System/composer.lock) |
| **Browser E2E Testing** | Playwright | `@playwright/test ^1.62.1` | [package.json](file:///c:/wamp64/www/Bakery_Management_System/package.json) |
| **Database Engines** | MySQL / PostgreSQL / SQLite | MySQL 8.0+, PostgreSQL 14+, SQLite (Test) | [database.php](file:///c:/wamp64/www/Bakery_Management_System/config/database.php) |

---

## 2. PHP and Laravel Requirements

### 2.1 PHP Version & Extensions
The application strictly enforces PHP 8.3 minimum compatibility. Deploying on PHP 8.2 will trigger a fatal Composer platform check failure.

- **Minimum PHP Version**: `PHP >= 8.3.0` (Local verified: `PHP 8.3.28`)
- **Required Core Extensions**:
  - `ext-pdo`: Database abstraction interface
  - `ext-pdo_mysql` OR `ext-pdo_pgsql`: Primary production database drivers
  - `ext-pdo_sqlite`: Used for isolated memory-based automated testing
  - `ext-mbstring`: Multi-byte string manipulation for multi-language support (English, Vietnamese, Chinese)
  - `ext-openssl`: Encrypted session tokens, password hashing, and HTTPS communication
  - `ext-tokenizer`: Laravel Blade and code compilation
  - `ext-xml`: Report exports and DOM document manipulation
  - `ext-ctype`: Character type checking
  - `ext-json`: JSON casting of user permissions and audit metadata
  - `ext-curl`: HTTP client requests and external integrations
  - `ext-session`: Web session management
  - `ext-fileinfo`: Secure file upload validation

### 2.2 Laravel 13 Framework Bootstrap
The application utilizes Laravel 13's single-file application builder pattern in [bootstrap/app.php](file:///c:/wamp64/www/Bakery_Management_System/bootstrap/app.php):
- **Routing**: Configured for web (`routes/web.php`), console commands (`routes/console.php`), and health check (`/up`).
- **Global Web Middleware**:
  - `\App\Http\Middleware\SetLocale`: Language switcher (English, Vietnamese, Chinese).
  - `\App\Http\Middleware\EnsurePasswordNotExpired`: Enforces 90-day password rotation and temporary password reset.
- **Middleware Aliases**:
  - `role`: Maps to `\App\Http\Middleware\CheckRole`.
  - `password.not_expired`: Maps to `\App\Http\Middleware\EnsurePasswordNotExpired`.
- **Proxy Trust**: Trust proxies set to `*` for reverse proxy compatibility.

---

## 3. Directory Structure

```
c:\wamp64\www\Bakery_Management_System\
├── app/
│   ├── Console/Commands/       # Custom artisan commands (e.g. SyncConditionAlertsCommand)
│   ├── Http/
│   │   ├── Controllers/        # Web controllers (Admin, Cashier, Manager, POS, Login, Profile)
│   │   └── Middleware/         # Security and localization middleware (CheckRole, SetLocale, Password)
│   ├── Models/                 # 23 Eloquent models with snapshot guards and relationships
│   └── Services/               # Domain services (Permissions, Notifications, Reports, Auditing)
├── bootstrap/
│   ├── app.php                 # Application builder, middleware stack, exception handlers
│   └── cache/                  # Compiled package discovery, route cache, config cache
├── config/                     # Core configurations (app, auth, database, queue, cache, session)
├── database/
│   ├── factories/              # Model factories for testing
│   ├── migrations/             # 38 schema migration files
│   └── seeders/                # Database seeders (User, Category, Product, Recipe, Delivery)
├── docs/
│   └── handover/               # Professional project handover package (10 documentation files)
├── e2e/                        # Playwright end-to-end browser test specifications (21 spec files)
├── public/
│   ├── css/                    # Modular feature stylesheets (pos.css, deliveries.css, orders.css)
│   ├── images/                 # Static branding, logo, and placeholder imagery
│   └── js/                     # Client-side UI enhancements
├── resources/
│   └── views/                  # Blade templates (layouts, admin, cashier, manager, profile, auth)
├── routes/
│   ├── console.php             # Scheduled tasks (hourly notifications:sync-alerts)
│   └── web.php                 # Authenticated and role-protected web routes
├── storage/                    # Application logs, framework file caches, session storage
├── tests/
│   ├── Feature/                # Feature integration tests (245 tests in SQLite :memory:)
│   └── Unit/                   # Unit test suite
├── composer.json               # PHP dependency specifications
├── package.json                # Frontend asset compilation and testing tooling
└── phpunit.xml                 # Isolated in-memory SQLite test configuration
```

---

## 4. Application Architecture & Service Layer

The application enforces a Clean MVC architecture complemented by a dedicated **Domain Service Layer**:

```
[HTTP Request] ---> [Web Server / PHP-FPM]
                           |
                           v
                   [CheckRole Middleware]
                           |
                           v
                [Controller Action Layer]
                           |
             +-------------+-------------+
             |                           |
             v                           v
     [Domain Services]           [Eloquent Models]
   (Business & Logic)          (Data & Persistence)
   - PermissionService         - Delivery (Snapshot Guard)
   - NotificationService       - Order, Sale, Payment
   - ReportService             - Product, Ingredient, Recipe
   - ReportExportService       - User (Role & Permissions)
   - AuditLogService
   - SettingsService
             |                           |
             +-------------+-------------+
                           |
                           v
             [Blade View Response (HTML)]
```

### 4.1 Key Domain Services

1. **[PermissionService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/PermissionService.php)**:
   - Manages 14 functional modules and 42 granular permission keys.
   - Calculates effective user permissions combining built-in role defaults with custom JSON permission overrides stored in `users.custom_permissions`.
   - Grants unconditional superuser access to users with `admin` role.
2. **[NotificationService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/NotificationService.php)**:
   - Scans live inventory, shelf-life dates, and custom orders to generate deduplicated condition alerts (`low_stock`, `out_of_stock`, `expiring`, `expired`, `pickup_soon`).
   - Automatically marks resolved condition alerts as inactive when inventory is restocked.
   - Broadcasts real-time delivery status notifications to managers and drivers.
3. **[ReportService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/ReportService.php)** & **[ReportExportService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/ReportExportService.php)**:
   - Compiles revenue, cost, net profit, and inventory valuation metrics across custom date ranges.
   - Streams formatted PDF downloads via DomPDF and CSV/Excel exports.
4. **[AuditLogService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/AuditLogService.php)**:
   - Records immutable audit events (user status changes, permission modifications, order purges, and security alerts) capturing user ID, IP address, user agent, and contextual JSON payloads.

---

## 5. Dependency Manifest

### 5.1 Backend Dependencies ([composer.json](file:///c:/wamp64/www/Bakery_Management_System/composer.json))

| Package | Version | Purpose |
| :--- | :--- | :--- |
| `php` | `^8.3` | Minimum language requirement |
| `laravel/framework` | `^13.17` (`v13.25.0` locked) | Core web application framework |
| `barryvdh/laravel-dompdf` | `^3.1` | PDF generation for receipts, invoices, and reports |
| `laravel/tinker` | `^3.0` | CLI REPL for internal inspection and maintenance |
| `phpunit/phpunit` (dev) | `^12.5.12` | Automated testing framework |
| `mockery/mockery` (dev) | `^1.6` | Mocking framework for unit testing |
| `fakerphp/faker` (dev) | `^1.23` | Realistic test data generation |
| `laravel/pint` (dev) | `^1.27` | Code style enforcement |

### 5.2 Frontend & Build Tooling ([package.json](file:///c:/wamp64/www/Bakery_Management_System/package.json))

| Package | Version | Purpose |
| :--- | :--- | :--- |
| `vite` | `^8.0.0` | Next-generation frontend build tool |
| `laravel-vite-plugin` | `^3.1` | Laravel integration for Vite asset bundling |
| `tailwindcss` | `^4.0.0` | Utility-first CSS framework |
| `@playwright/test` | `^1.62.1` | Browser end-to-end testing suite |
| `concurrently` | `^10.0.3` | Concurrent process execution for development |

