# Project Overview
## Moon Cake Bakery Management System

---

## 1. Project Purpose & Executive Summary
The **Moon Cake Bakery Management System** is a production-grade web application tailored for commercial, retail, and artisanal bakeries specializing in seasonal pastries (moon cakes, egg tarts, and baked goods) as well as everyday bakery operations.

The system unifies the entire bakery operational lifecycle into an integrated platform:
- **Front-of-House**: Fast, touch-friendly Point of Sale (POS) terminal, walk-in sales, customer loyalty program, and instant payment settlement.
- **Production & Kitchen**: Formula and recipe management, batch scheduling, automated raw material/ingredient depletion, baking status tracking, and baker assignments.
- **Logistics & Delivery**: Customer delivery dispatch, driver routing, cash-on-delivery (COD) collection, automated delivery status notifications, and immutable snapshot retention.
- **Back-of-House & Supply Chain**: Supplier catalog management, purchase order procurement, automated low-stock and expiry alerts, staff attendance, and multi-tier role-based access control.
- **Executive Governance**: Real-time sales dashboards, product profitability analysis, and financial reporting with PDF/Excel export.

---

## 2. System Scope & Core Capabilities

| Functional Domain | Key Capabilities | Primary Users |
| :--- | :--- | :--- |
| **Front-of-House (POS)** | Rapid category-filtered product selection, multi-item cart management, customer lookup & loyalty tier application, payment processing (Cash, Card, Transfer, Split, COD), receipt generation. | Cashier, Manager, Admin |
| **Order Management** | Standard and custom bakery orders, pickup scheduling, custom cake special instructions, order cancellation, and archived order purge preview. | Cashier, Manager, Admin |
| **Delivery Logistics** | Scheduling deliveries from POS or order management, driver assignment, dispatch tracking (Pending $\rightarrow$ Assigned $\rightarrow$ Out for Delivery $\rightarrow$ Delivered / Failed / Cancelled), COD payment integration, and immutable history retention. | Delivery Staff / Driver, Cashier, Manager, Admin |
| **Kitchen & Production** | Batch production planning, recipe yield scaling, automatic raw ingredient stock deduction, baker task assignment, batch completion logging, and inventory replenishment. | Baker, Manager, Admin |
| **Inventory & Procurement** | Raw ingredient tracking, unit conversions, shelf-life and expiration tracking, minimum stock thresholds, supplier management, and purchase order workflow (Draft $\rightarrow$ Ordered $\rightarrow$ Received / Restocked $\rightarrow$ Cancelled). | Manager, Admin |
| **Staff & HR** | Staff profile directory, daily attendance check-in/check-out, work shift scheduling, and salary disbursement tracking. | Manager, Admin |
| **Executive Reports** | Daily, weekly, monthly, and yearly sales summaries, category revenue analysis, best-selling products, raw material cost tracking, and PDF/Excel export. | Manager, Admin |
| **Security & Auditing** | Password expiration enforcement, forced password reset on first login, granular permission override engine, and immutable audit logging for security-sensitive operations. | Admin |

---

## 3. Main System Modules

```
+-----------------------------------------------------------------------------------+
|                        MOON CAKE BAKERY MANAGEMENT SYSTEM                         |
+-----------------------------------------------------------------------------------+
  |-- [1. Authentication & Profile]      : Session security, password expiry, language switch
  |-- [2. User & Access Control]         : 5 roles, custom permissions JSON overrides, audit logs
  |-- [3. Products & Categories]         : Catalog, pricing, shelf life, expiry days
  |-- [4. Ingredients & Inventory]       : Raw stock, reorder levels, unit measurements
  |-- [5. Recipes & Formulations]        : Multi-ingredient formulas, yields, production times
  |-- [6. Production & Baking Batches]   : Batch lifecycle, baker assignment, stock depletion
  |-- [7. Procurement & Suppliers]       : Vendor profiles, purchase orders, receiving & restocking
  |-- [8. Point of Sale (POS)]           : Rapid register, customer loyalty points, order creation
  |-- [9. Orders & Custom Orders]        : Order tracking, custom cake specs, pickup dates
  |-- [10. Delivery Management]          : Driver assignment, routing, COD settlements, immutable history
  |-- [11. Customers & Loyalty]          : Customer profiles, loyalty point accrual & redemptions
  |-- [12. Human Resources (HR)]         : Attendance logs, shift schedules, salary records
  |-- [13. Financial Reports]            : P&L overview, sales analytics, PDF/Excel export
  |-- [14. Notifications & Alerts]       : Automated condition engine (stock, expiry, reminders)
  |-- [15. System Settings]              : Bakery branding, tax rates, currency, backup metadata
```

---

## 4. Key Business Workflows

### 4.1 POS Sale to Delivery Workflow
```
[Customer at Register / Phone]
        |
        v
[Cashier Opens POS Interface (/pos)]
        |
        +---> Select Products (Filtered by Category)
        +---> Attach Customer (Applies Loyalty Discount / Points)
        +---> Choose Fulfillment: "Standard" OR "Delivery"
        |
        +-- (If Delivery Selected) --> Enter Delivery Address, Phone, & Fee
        |
        v
[Process Payment] ---> (Cash / Card / Bank Transfer / Cash-on-Delivery)
        |
        v
[System Creates Atomic Records]
        |---> Order Created (orders table)
        |---> Order Items Stored (order_items table)
        |---> Delivery Scheduled (deliveries table: status = 'pending', order_number snapshot captured)
        |---> Initial Sale/Payment Created (or marked pending for COD)
        |---> Notification Dispatched to Managers & Delivery Team
```

### 4.2 Delivery Dispatch & Settlement Workflow
```
[Admin/Manager Assigns Driver]
        |
        v
[Delivery Status: 'assigned'] ---> Driver Sees Order on Delivery Panel (/admin/deliveries)
        |
        v
[Driver Dispatches Package] ---> Delivery Status Updated to 'out_for_delivery'
        |
        +-----------------------------------+-----------------------------------+
        |                                                                       |
        v                                                                       v
[Successful Delivery ('delivered')]                                   [Failed Delivery ('failed')]
        |                                                                       |
        +-- If COD: Collects Cash, creates Payment/Sale, awards Loyalty        +-- Order reverted to 'ready_for_pickup'
        +-- Sets delivered_at timestamp                                         +-- No financial charge created
        +-- Marks Order as 'completed'                                          +-- Delivery can be rescheduled
```

### 4.3 Batch Production & Automated Depletion Workflow
```
[Manager/Admin Creates Production Batch]
        |
        v
[Select Recipe & Batch Quantity] ---> System Calculates Required Raw Ingredients
        |
        v
[Assign Baker & Scheduled Date] ---> Status: 'planned'
        |
        v
[Baker Updates Status to 'in_progress'] ---> Actual Baking Commences
        |
        v
[Baker Marks Batch 'completed']
        |
        +---> Deducts exact ingredient quantities from `ingredients` table
        +---> Records audit trail in `stock_movements` table
        +---> Increments finished goods stock in `products` table
        +---> Automatically triggers low-stock alerts if ingredient threshold breached
```

---

## 5. System Architecture Overview

```
+-----------------------------------------------------------------------------------+
|                                CLIENT BROWSER                                     |
|         Desktop / POS Touch Monitor / Tablet / Mobile (Responsive UI)             |
+-----------------------------------------------------------------------------------+
                                         |
                                         v  (HTTPS / Reverse Proxy)
+-----------------------------------------------------------------------------------+
|                        WEB SERVER & RUNTIME LAYER                                 |
|                   Nginx or Apache (mod_proxy_fcgi)                                |
|                        PHP 8.3 (PHP-FPM) + OPcache                                |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                           LARAVEL 13 APPLICATION LAYER                            |
|                                                                                   |
|  [HTTP Middleware]                                                                |
|    - SetLocale (en, vi, zh)                                                       |
|    - EnsurePasswordNotExpired                                                     |
|    - CheckRole (admin, manager, cashier, baker, delivery_staff)                   |
|                                                                                   |
|  [Controllers & Handlers]                                                         |
|    - Admin, Cashier, Manager Controllers                                          |
|    - PosController & OrderController                                              |
|    - DeliveryController (Snapshot auto-capture & Driver state machine)            |
|                                                                                   |
|  [Service Layer Architecture]                                                     |
|    - PermissionService: Role defaults & custom permission JSON evaluation         |
|    - NotificationService: Condition scanner (low-stock, expiry, custom orders)    |
|    - ReportService & ReportExportService: Financial aggregation & PDF/Excel       |
|    - AuditLogService: Security and operational event logging                      |
|    - SettingsService: Bakery configurations, tax, currency                        |
|                                                                                   |
|  [Data Access Layer (Eloquent ORM)]                                               |
|    - 23 Active Models with relationships, cast attributes, and model events       |
+-----------------------------------------------------------------------------------+
                                         |
                                         v
+-----------------------------------------------------------------------------------+
|                               DATABASE STORAGE                                    |
|   Production: MySQL 8.0+ (InnoDB)  OR  PostgreSQL 14+ (Full MVCC)                 |
|   Testing:    SQLite (:memory: isolated in-memory test environment)               |
+-----------------------------------------------------------------------------------+
```

---

## 6. Document Metadata & Verification
- **Application Name**: Moon Cake Bakery Management System
- **Framework Version**: Laravel Framework 13.25.0
- **Language Runtime**: PHP 8.3.28 (Minimum Requirement: PHP ^8.3)
- **Technical Audit Assessment**: READY WITH CONDITIONS (Phase 10.13)
- **Verification Basis**: Verified against live project repository files on October 31, 2026.

