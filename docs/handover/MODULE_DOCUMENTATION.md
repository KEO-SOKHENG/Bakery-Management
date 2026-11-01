# Module Documentation
## Moon Cake Bakery Management System

---

## 1. Authentication & User Profile Management

- **Primary Controllers**:
  - [LoginController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/LoginController.php)
  - [PasswordChangeController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/PasswordChangeController.php)
  - [ProfileController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/ProfileController.php)
  - [LanguageController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/LanguageController.php)
- **Primary Middleware**:
  - [EnsurePasswordNotExpired.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Middleware/EnsurePasswordNotExpired.php)
  - [SetLocale.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Middleware/SetLocale.php)
- **Key Features Verified in Code**:
  1. **Role-Based Login Redirection**:
     - `admin` $\rightarrow$ `/admin/dashboard`
     - `manager` $\rightarrow$ `/manager/dashboard`
     - `cashier` $\rightarrow$ `/cashier/dashboard` (or direct POS access)
     - `baker` $\rightarrow$ `/admin/production`
     - `delivery_staff` / `delivery` $\rightarrow$ `/admin/deliveries`
  2. **Account Status Enforcement**: Accounts marked `suspended` or `inactive` are immediately logged out with descriptive session flash error messages.
  3. **Forced Password Rotation**: Users flagged with `must_change_password = true` or whose passwords exceed 90 days are intercepted by middleware and redirected to `/change-password`.
  4. **Multi-Language Support**: Supports English (`en`), Vietnamese (`vi`), and Traditional/Simplified Chinese (`zh`) with session persistence.
  5. **Self-Service Profile**: Authenticated users can view and update their contact phone number, email address, and personal password.

---

## 2. Role & Permission Management

- **Primary Controller**: [UserController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/UserController.php)
- **Primary Service**: [PermissionService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/PermissionService.php)
- **Primary Middleware**: [CheckRole.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Middleware/CheckRole.php)
- **Key Features Verified in Code**:
  1. **Built-in System Roles**: Five distinct roles (`admin`, `manager`, `cashier`, `baker`, `delivery_staff`).
  2. **Hybrid RBAC + Custom Permission Overrides**:
     - 42 granular permission keys grouped across 14 modules.
     - Role defaults provide baseline capabilities.
     - Admins can assign custom granular overrides per user via JSON column `users.custom_permissions` (e.g., granting a specific Cashier access to view inventory or reports without altering the global role).
  3. **Audit Logging**: All permission changes and status updates (activate, deactivate, suspend) are recorded in `audit_logs` with actor and target IDs.

---

## 3. Product & Inventory Management

- **Primary Controllers**:
  - [ProductController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/ProductController.php)
  - [CategoryController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/CategoryController.php)
  - [IngredientController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/IngredientController.php)
- **Primary Models**: [Product.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Product.php), [Category.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Category.php), [Ingredient.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Ingredient.php)
- **Key Features Verified in Code**:
  1. **Finished Goods Catalog**: Product SKU, barcode, retail price, cost price, current stock, and category relationships.
  2. **Shelf-Life & Expiry Management**: Products track `shelf_life_days` and calculate dynamic `expiry_date`.
  3. **Raw Ingredients Tracking**: Raw inventory tracks unit quantities (kg, g, L, pieces), minimum reorder alert levels, unit costs, and expiration dates.
  4. **Category Protection**: Protected categories cannot be deleted if associated with active products. Unique name constraints are enforced.
- **Unverified / Missing Features**:
  - Automated barcode scanner hardware SDK: *NOT VERIFIED* (Standard text barcode inputs supported).

---

## 4. Recipe & Production Management

- **Primary Controllers**:
  - [RecipeController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/RecipeController.php)
  - [ProductionController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/ProductionController.php)
- **Primary Models**: [Recipe.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Recipe.php), [Production.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Production.php), [RecipeIngredient.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/RecipeIngredient.php)
- **Key Features Verified in Code**:
  1. **Baking Formulations**: Multi-ingredient recipe specifications, yield calculations, preparation instructions, and estimated production time in minutes.
  2. **Production Batch Lifecycle**: Batches transition through `planned` $\rightarrow$ `in_progress` $\rightarrow$ `completed` (or `cancelled`).
  3. **Automated Stock Deduction**: Marking a batch as `completed` automatically:
     - Deducts proportional ingredient quantities from `ingredients`.
     - Logs audit movements in `stock_movements`.
     - Increments product stock in `products`.
  4. **Baker Task Assignment**: Batches can be assigned to specific staff members with role `baker`.

---

## 5. Supplier Management & Procurement (Purchase Orders)

- **Primary Controllers**:
  - [SupplierController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/SupplierController.php)
  - [PurchaseOrderController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/PurchaseOrderController.php)
- **Primary Models**: [Supplier.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Supplier.php), [PurchaseOrder.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/PurchaseOrder.php), [PurchaseOrderItem.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/PurchaseOrderItem.php)
- **Key Features Verified in Code**:
  1. **Supplier Directory**: Vendor company name, contact person, email, phone, address, and supplied ingredient catalog.
  2. **Purchase Order Workflow**: POs progress through `draft` $\rightarrow$ `ordered` $\rightarrow$ `received` (or `cancelled`).
  3. **Stock Replenishment on Receiving**: Marking a PO as `received` automatically increments raw ingredient stock levels and records replenishment timestamps.

---

## 6. Point of Sale (POS) & Order Management

- **Primary Controllers**:
  - [PosController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/PosController.php)
  - [OrderController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/OrderController.php)
- **Primary Models**: [Order.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Order.php), [OrderItem.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/OrderItem.php), [Sale.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Sale.php)
- **Key Features Verified in Code**:
  1. **POS Register Interface**: Rapid category tabs, item search, quantity adjustments, discount inputs, and tax calculation.
  2. **Fulfillment Modes**: Direct in-store checkout or delivery scheduling with address and delivery fee.
  3. **Custom Orders**: Special instructions, pickup dates, and custom cake specifications.
  4. **Order Safeguards & Purge Preview**:
     - Orders cannot be deleted if active in-flight deliveries exist.
     - Admin order purge tool includes a non-destructive preview (`/admin/orders/purge-preview`) displaying eligible orders and associated delivery records.
     - When old orders are archived/purged, the Delivery history remains fully intact through immutable order snapshot fields (`deliveries.order_number`).

---

## 7. Customer Management & Loyalty Program

- **Primary Controller**: [CustomerController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/CustomerController.php)
- **Primary Model**: [Customer.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Customer.php)
- **Key Features Verified in Code**:
  1. **Customer Directory**: Phone number, full name, email, address, and purchase statistics.
  2. **Loyalty Accrual & Tiers**:
     - Calculates points based on order spend.
     - Tiers: Bronze, Silver, Gold, Platinum based on historical spend thresholds.
     - Points can be redeemed at POS for order discounts.
  3. **Duplicate Loyalty Protection**: Pre-paid orders award points at checkout; COD orders award points only upon successful delivery completion; legacy null-point orders never duplicate awards.

---

## 8. Payment Handling & Financial Settlement

- **Primary Models**: [Payment.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Payment.php), [Sale.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Sale.php)
- **Key Features Verified in Code**:
  1. **Supported Payment Methods**: Cash, Credit/Debit Card, Bank Transfer, Split Payment, Cash on Delivery (COD).
  2. **Payment Status Lifecycle**: `pending`, `partially_paid`, `paid`, `refunded`.
  3. **Outstanding Balance Handling**: Partial payments track remaining balance; COD deliveries collect outstanding balance upon delivery.
  4. **Unique Sales Receipt Constraint**: Migration `2026_10_28_000002` enforces a 1:1 unique constraint on `sales.order_id` to prevent duplicate ledger revenue entries.

---

## 9. Delivery Management (Core Module)

- **Primary Controller**: [DeliveryController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/DeliveryController.php)
- **Primary Model**: [Delivery.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Delivery.php)
- **Key Features Verified in Code**:
  1. **Model Snapshot Auto-Capture**: The `creating` hook in [Delivery.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Delivery.php) automatically pulls and stores `orders.order_number` into `deliveries.order_number`.
  2. **Snapshot Immutability Guard**: The `updating` hook in [Delivery.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/Delivery.php) strictly prevents alteration of `order_number` once set, protecting historical records even if the parent order is purged.
  3. **Delivery State Machine**:
     ```
     [pending] ---> [assigned] ---> [out_for_delivery] ---> [delivered]
                                                     ---> [failed] ---> [cancelled]
     ```
  4. **Driver Role Isolation**: Delivery drivers can view only deliveries assigned to their account (`user_id`) and are strictly forbidden from assigning drivers or viewing other drivers' deliveries.
  5. **COD Settlement on Delivery**: Marking a COD delivery as `delivered` automatically creates the corresponding payment record, logs the sale receipt, and awards customer loyalty points.
  6. **Rescheduling & Retry**: Failed deliveries revert the order to `ready_for_pickup` without financial charges, enabling drivers or dispatchers to reschedule delivery.

---

## 10. Reports & Executive Dashboard

- **Primary Controllers**:
  - [DashboardController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/DashboardController.php)
  - [ReportController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/ReportController.php)
- **Primary Services**: [ReportService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/ReportService.php), [ReportExportService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/ReportExportService.php)
- **Key Features Verified in Code**:
  1. **Dashboard KPIs**: Today's revenue, active orders count, low-stock ingredient alerts, pending delivery count, and sales trend graphs.
  2. **Financial Aggregations**: Revenue, raw material cost, gross margin, and net profit calculated from `sales`, `order_items`, and `ingredients`.
  3. **Export Formats**: PDF export via DomPDF and CSV/Excel data streaming.

---

## 11. System Notifications & Automated Alerts

- **Primary Controller**: [NotificationController.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Controllers/Admin/NotificationController.php)
- **Primary Service**: [NotificationService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/NotificationService.php)
- **Artisan Command**: [SyncConditionAlertsCommand.php](file:///c:/wamp64/www/Bakery_Management_System/app/Console/Commands/SyncConditionAlertsCommand.php)
- **Key Features Verified in Code**:
  1. **Automated Condition Scanner**: Hourly cron execution (`notifications:sync-alerts`) scans for:
     - Low stock ingredients (`current_stock <= reorder_level`)
     - Out of stock ingredients (`current_stock <= 0`)
     - Expiring ingredients/products (within warning threshold)
     - Expired items
     - Custom order reminders (approaching pickup date)
  2. **Deduplication Engine**: Automatically resolves/deactivates alerts when stock is replenished; never creates duplicate active alerts for the same condition.
  3. **Broadcast & Read Receipts**: In-app notification bell icon, mark-as-read, and role-targeted announcements.

