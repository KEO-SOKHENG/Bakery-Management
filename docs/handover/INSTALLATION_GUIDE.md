# Installation & Setup Guide
## Moon Cake Bakery Management System

---

## 1. System Requirements

### 1.1 Core Software
- **Operating System**: Linux (Ubuntu 22.04 / 24.04 LTS, Debian 12, RHEL 9) or Windows (Windows Server / WAMP 3.x)
- **PHP**: `PHP >= 8.3.0` (Recommended: PHP 8.3.28 or later)
- **Composer**: Composer 2.7+
- **Node.js**: Node.js 18.x or 20.x LTS with npm
- **Database**:
  - MySQL 8.0+ / 8.4 LTS (with `InnoDB` default engine), OR
  - PostgreSQL 14+, OR
  - SQLite 3.35+ (For local development and automated testing)
- **Web Server**: Nginx 1.22+ or Apache 2.4+ (with `mod_rewrite` enabled)

### 1.2 Required PHP Extensions
Confirm that the following extensions are enabled in your `php.ini`:
```ini
extension=pdo
extension=pdo_mysql       ; Or pdo_pgsql for PostgreSQL
extension=pdo_sqlite      ; Required for running automated PHPUnit tests
extension=mbstring
extension=openssl
extension=tokenizer
extension=xml
extension=ctype
extension=json
extension=curl
extension=fileinfo
extension=session
```

---

## 2. Environment Configuration

### 2.1 Clone Repository & Prepare Directory
```bash
git clone <repository_url> Bakery_Management_System
cd Bakery_Management_System
```

### 2.2 Configure Environment File
Copy the example environment template:
```bash
cp .env.example .env
```
Open `.env` and configure your local or staging database connection settings:
```dotenv
APP_NAME="Moon Cake Bakery Management System"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

# Database Configuration (MySQL Example)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bakery_management_system
DB_USERNAME=your_db_username
DB_PASSWORD=your_db_password

# Or PostgreSQL Example
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=bakery_management_system
# DB_USERNAME=your_db_username
# DB_PASSWORD=your_db_password

# Background Queue & Cache
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

> [!CAUTION]
> **Credential Security**: Never commit real database passwords, production app keys, or secret tokens to source control. Ensure `.env` is listed in `.gitignore`.

---

## 3. Dependency Installation & Application Initialization

### 3.1 Install PHP Dependencies via Composer
```bash
# For local development:
composer install

# For production staging (optimized autoloader without dev packages):
# composer install --no-dev --optimize-autoloader
```

### 3.2 Generate Application Encryption Key
```bash
php artisan key:generate
```

### 3.3 Link Storage Directory
Create the public symlink for uploaded product images and logos:
```bash
php artisan storage:link
```

### 3.4 Install Frontend Dependencies & Compile Assets
```bash
npm install
npm run build
```

---

## 4. Database Setup & Migrations

### 4.1 Run Schema Migrations
Execute the full migration suite (all 38 migration files):
```bash
php artisan migrate
```

### 4.2 Database Seeding (Local Development Only)
To populate baseline demo users (Admin, Manager, Cashier, Baker, Driver), initial product categories, and sample ingredients:
```bash
php artisan db:seed
```

> [!WARNING]
> **Production Notice**: Never run `php artisan db:seed` or `migrate:fresh` against a live production database. Production deployments must run `php artisan migrate --force` following the approved deployment runbook.

---

## 5. Local Development Startup

### 5.1 Starting the Local Web Server
```bash
php artisan serve
```
The application will be accessible at: `http://127.0.0.1:8000`

### 5.2 Starting Background Queue Workers (Local)
In a separate terminal, start the queue worker to process asynchronous notifications and condition scans:
```bash
php artisan queue:work
```

### 5.3 Compiling Assets with Live Reload (Vite)
```bash
npm run dev
```

---

## 6. Safe Automated Testing Instructions

The project test suite is pre-configured to execute in **complete isolation** using an in-memory SQLite database (`:memory:`). Running tests will **never connect to or modify your local MySQL/PostgreSQL database or live WAMP data**.

### 6.1 Running the Full PHPUnit Suite
```bash
php artisan test
```
*Expected Result*: 245 tests, 1,200 assertions, 0 failures.

### 6.2 Running Targeted Delivery Management Tests
```bash
php artisan test --filter=DeliveryManagementTest
```
*Expected Result*: 27 passed, 150 assertions, 0 failures.

### 6.3 Verifying Test Environment Isolation
Inspect [phpunit.xml](file:///c:/wamp64/www/Bakery_Management_System/phpunit.xml#L26):
```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```
This guarantees that all unit and feature tests run in temporary memory space and discard data immediately upon test completion.

