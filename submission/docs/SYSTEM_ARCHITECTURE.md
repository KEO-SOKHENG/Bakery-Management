# System Architecture & Technical Specifications
## Moon Cake Bakery Management System

---

## 1. Architectural Overview

The **Moon Cake Bakery Management System** is architected using the standard **Model-View-Controller (MVC)** design pattern, implemented on **Laravel 12 / 13** with **PostgreSQL 16**. The architecture emphasizes high modularity, transactional consistency, and defense-in-depth security.

```mermaid
flowchart TD
    Client["Client Web Browser<br>(Desktop / POS Tablet)"]
    
    subgraph WEB["Web Presentation Layer"]
        Routing["Laravel Routing (`routes/web.php`)"]
        Middleware["Security Middleware<br>(Auth, RoleCheck, CSRF)"]
        BladeViews["Blade View Templates & Assets<br>(Vite + TailwindCSS)"]
    end
    
    subgraph APP["Application Logic Layer"]
        Controllers["Controllers<br>(Pos, Order, Product, Production, etc.)"]
        Services["Domain Services<br>(ReportService, NotificationService)"]
        Requests["Form Requests & Validation"]
    end
    
    subgraph DATA["Data Persistence Layer"]
        Eloquent["Eloquent ORM & Query Builder"]
        Postgres["PostgreSQL 16.14 Database<br>(30 Tables, Foreign Keys, MVCC)"]
    end
    
    Client -->|HTTP / HTTPS| Routing
    Routing --> Middleware
    Middleware --> Controllers
    Controllers --> Requests
    Controllers --> Services
    Controllers --> Eloquent
    Eloquent --> Postgres
    Controllers --> BladeViews
    BladeViews --> Client
```

---

## 2. Directory Structure & Key Components

```
Bakery_Management_System/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/           # Admin management controllers
│   │   │   │   ├── CategoryController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── IngredientController.php
│   │   │   │   ├── NotificationController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── ProductionController.php
│   │   │   │   ├── ProductController.php
│   │   │   │   ├── PurchaseOrderController.php
│   │   │   │   ├── RecipeController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   ├── SettingController.php
│   │   │   │   ├── SupplierController.php
│   │   │   │   └── UserController.php
│   │   │   ├── Auth/            # Authentication & Session controllers
│   │   │   ├── PosController.php # POS terminal and checkout controller
│   │   │   └── ProfileController.php # Staff profile management
│   │   └── Middleware/          # Role, password expiry, and security middleware
│   ├── Models/                  # Eloquent models (Order, Sale, Product, etc.)
│   └── Services/                # Business logic services (ReportService)
├── config/                      # Laravel environment configuration
├── database/
│   ├── migrations/              # Database DDL migration files
│   └── seeders/                 # Database seeders
├── resources/
│   └── views/                   # Blade template views
│       ├── admin/               # Administrative views
│       ├── layouts/             # App shell, navigation, header, sidebar
│       └── pos/                 # POS terminal interface
├── routes/
│   ├── web.php                  # Web and application routes
│   └── console.php              # Artisan console command routes
├── storage/                     # Application logs, sessions, and backups
└── tests/                       # Automated PHPUnit feature & unit test suites
```

---

## 3. Core Domain Workflows

### 3.1 Point of Sale & Checkout Workflow
```mermaid
sequenceDiagram
    autonumber
    actor Cashier as Cashier / Manager
    participant POS as POS Controller
    participant DB as PostgreSQL Database
    
    Cashier->>POS: Scan / Select Products into Cart
    Cashier->>POS: Submit Checkout (Payment: Cash/Card/QR)
    POS->>DB: BEGIN Transaction
    POS->>DB: INSERT into `orders` (Status: completed)
    POS->>DB: INSERT into `order_items` (Quantities, Prices)
    POS->>DB: INSERT into `sales` (Revenue, Sold At)
    POS->>DB: INSERT into `payments` (Collected Amount, Method)
    POS->>DB: COMMIT Transaction
    POS-->>Cashier: Generate Printable Invoice / Receipt
```

### 3.2 Recipe Formulation & Baking Production Batch Workflow
```mermaid
sequenceDiagram
    autonumber
    actor Baker as Kitchen Baker
    participant Prod as Production Controller
    participant DB as PostgreSQL Database
    
    Baker->>Prod: Schedule Baking Batch (Product, Quantity, Date)
    Prod->>DB: INSERT into `productions` (Status: scheduled)
    Baker->>Prod: Mark Batch as "Completed"
    Prod->>DB: BEGIN Transaction
    Prod->>DB: UPDATE `productions` (Status: completed)
    Prod->>DB: Query `recipe_ingredients` for Product
    loop For Each Ingredient in Recipe
        Prod->>DB: UPDATE `ingredients` (Deduct Quantity * Batch Size)
        Prod->>DB: INSERT into `stock_movements` (Type: production_usage)
    end
    Prod->>DB: COMMIT Transaction
    Prod-->>Baker: Batch Complete & Warehouse Stock Updated
```

---

## 4. Security Architecture

1. **Authentication:** Native Laravel session guard utilizing bcrypt password hashing with automatic password expiration enforcement (`EnsurePasswordNotExpired`).
2. **Role Authorization (`CheckRole`):** Strictly intercepts requests based on role declarations (`admin`, `manager`, `cashier`, `baker`).
3. **Cross-Site Request Forgery (CSRF):** Protected via encrypted tokens generated on every state-changing POST/PUT/DELETE request.
4. **SQL Injection Prevention:** 100% parameterized queries via Eloquent ORM and PDO binding; zero raw unescaped input interpolation.
5. **Historical URL Fallback Interceptor:** Route handler intercepts obsolete `/admin/deliveries/{id}` URLs and redirects authenticated requests gracefully to `/admin/orders` with an informational flash alert.
