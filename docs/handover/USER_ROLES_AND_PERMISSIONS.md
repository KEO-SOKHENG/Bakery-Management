# User Roles and Permissions Documentation
## Moon Cake Bakery Management System

---

## 1. System Roles Overview

The system implements a role-based access control (RBAC) architecture with five built-in roles verified in [User.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/User.php) and [CheckRole.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Middleware/CheckRole.php):

| Role Identifier | Display Name | Target Route on Login | Primary Responsibility |
| :--- | :--- | :--- | :--- |
| `admin` | Administrator | `/admin/dashboard` | Full system governance, user account management, role overrides, system backups, and order archiving. |
| `manager` | Bakery Manager | `/manager/dashboard` | Daily bakery operations, production planning, procurement POs, inventory restocking, and financial reports. |
| `cashier` | POS Cashier | `/cashier/dashboard` (or `/pos`) | Front-of-house register sales, order booking, customer registration, loyalty redemption, and delivery scheduling. |
| `baker` | Kitchen Baker | `/admin/production` | Recipe inspection, production batch execution (baking in progress, batch completion), and raw ingredient consumption. |
| `delivery_staff` / `delivery` | Delivery Driver | `/admin/deliveries` | Dispatch package routing, status updates (out for delivery, delivered, failed), and COD cash collection. |

---

## 2. Granular Permission Matrix (14 Modules, 42 Keys)

Permissions are defined in [PermissionService.php](file:///c:/wamp64/www/Bakery_Management_System/app/Services/PermissionService.php). Admins possess all permissions unconditionally.

| Functional Module | Permission Key | Admin | Manager | Cashier | Baker | Delivery Staff |
| :--- | :--- | :---: | :---: | :---: | :---: | :---: |
| **Users & Staff** | `users.view` | **Yes** | **Yes** | No | No | No |
| | `users.create` | **Yes** | No | No | No | No |
| | `users.edit` | **Yes** | No | No | No | No |
| | `users.status` | **Yes** | No | No | No | No |
| | `users.permissions` | **Yes** | No | No | No | No |
| | `users.reset_password` | **Yes** | No | No | No | No |
| **Catalog & Products** | `products.view` | **Yes** | **Yes** | **Yes** | No | No |
| | `products.create` | **Yes** | **Yes** | No | No | No |
| | `products.edit` | **Yes** | **Yes** | No | No | No |
| | `products.delete` | **Yes** | **Yes** | No | No | No |
| **Categories** | `categories.view` | **Yes** | **Yes** | No | No | No |
| | `categories.create` | **Yes** | **Yes** | No | No | No |
| | `categories.edit` | **Yes** | **Yes** | No | No | No |
| | `categories.delete` | **Yes** | **Yes** | No | No | No |
| **Ingredients** | `ingredients.view` | **Yes** | **Yes** | No | **Yes** | No |
| | `ingredients.manage` | **Yes** | **Yes** | No | No | No |
| **Recipes** | `recipes.view` | **Yes** | **Yes** | No | **Yes** | No |
| | `recipes.manage` | **Yes** | **Yes** | No | No | No |
| **Suppliers** | `suppliers.view` | **Yes** | **Yes** | No | No | No |
| | `suppliers.manage` | **Yes** | **Yes** | No | No | No |
| **Purchase Orders** | `purchase_orders.view` | **Yes** | **Yes** | No | No | No |
| | `purchase_orders.create`| **Yes** | **Yes** | No | No | No |
| | `purchase_orders.order` | **Yes** | **Yes** | No | No | No |
| | `purchase_orders.receive`| **Yes** | **Yes** | No | No | No |
| | `purchase_orders.cancel` | **Yes** | **Yes** | No | No | No |
| **Bakery Production** | `production.view` | **Yes** | **Yes** | No | **Yes** | No |
| | `production.create` | **Yes** | **Yes** | No | **Yes** | No |
| | `production.status` | **Yes** | **Yes** | No | **Yes** | No |
| | `production.delete` | **Yes** | No | No | No | No |
| **Orders Management** | `orders.view` | **Yes** | **Yes** | **Yes** | No | No |
| | `orders.create` | **Yes** | **Yes** | **Yes** | No | No |
| | `orders.edit` | **Yes** | **Yes** | **Yes** | No | No |
| | `orders.status` | **Yes** | **Yes** | **Yes** | No | No |
| | `orders.cancel` | **Yes** | **Yes** | No | No | No |
| | `orders.delete` | **Yes** | No | No | No | No |
| **HR & Schedules** | `hr.attendance` | **Yes** | **Yes** | No | No | No |
| | `hr.schedules` | **Yes** | **Yes** | No | No | No |
| | `hr.salaries` | **Yes** | No | No | No | No |
| **POS Terminal** | `pos.access` | **Yes** | **Yes** | **Yes** | No | No |
| | `pos.checkout` | **Yes** | **Yes** | **Yes** | No | No |
| **Customers** | `customers.view` | **Yes** | **Yes** | **Yes** | No | No |
| | `customers.create` | **Yes** | **Yes** | **Yes** | No | No |
| | `customers.edit` | **Yes** | **Yes** | **Yes** | No | No |
| | `customers.delete` | **Yes** | No | No | No | No |
| **Reports** | `reports.view` | **Yes** | **Yes** | No | No | No |
| | `reports.export` | **Yes** | **Yes** | No | No | No |
| **Notifications** | `notifications.view` | **Yes** | **Yes** | **Yes** | **Yes** | No |
| | `notifications.manage` | **Yes** | **Yes** | **Yes** | **Yes** | No |
| | `notifications.promote`| **Yes** | No | No | No | No |
| **Settings** | `settings.view` | **Yes** | **Yes** | No | No | No |
| | `settings.manage` | **Yes** | No | No | No | No |
| | `settings.backup` | **Yes** | No | No | No | No |

---

## 3. Backend Authorization Rules & Enforcement

### 3.1 HTTP Middleware Enforcement ([CheckRole.php](file:///c:/wamp64/www/Bakery_Management_System/app/Http/Middleware/CheckRole.php))
- Applied in route definitions as `middleware(['role:admin,manager,...'])`.
- If unauthenticated: redirects to `/login`.
- If account status is `inactive` or `suspended`: immediately logs the user out, invalidates session, and redirects to `/login` with an error message.
- If role is unauthorized:
  - JSON API requests receive `403 Forbidden` (`{"message": "Unauthorized action."}`).
  - Standard web requests redirect to the user's default role dashboard.

### 3.2 Granular Custom Permissions Override System
In [User.php](file:///c:/wamp64/www/Bakery_Management_System/app/Models/User.php):
```php
public function hasPermission(string $permission): bool
{
    return \App\Services\PermissionService::check($this, $permission);
}
```
**Evaluation Precedence**:
1. If user `isAdmin()`: Returns `true` unconditionally.
2. If user is deactivated or suspended: Returns `false` unconditionally.
3. If custom override exists in `users.custom_permissions` JSON array: Returns the explicit boolean value (`true` or `false`).
4. Fallback: Returns whether the permission is in the user's role default list (`getRoleDefaults($role)`).

---

## 4. Role-Specific Restrictions & Security Guarantees

### 4.1 Delivery Staff / Driver Permissions
- **Permitted Actions**:
  - Access delivery queue: `GET /admin/deliveries`
  - Update assigned delivery status: `POST /admin/deliveries/{delivery}/status` (`out_for_delivery`, `delivered`, `failed`)
  - Collect COD payments and settle outstanding balances upon successful delivery.
- **Strict Restrictions**:
  - **Account Isolation**: Drivers can see **only** deliveries where `deliveries.user_id` matches their own authenticated ID.
  - **Forbidden Operations**: Drivers cannot schedule new deliveries, assign drivers, delete orders, view kitchen recipes, or access financial reports.

### 4.2 Cashier Permissions & Restrictions
- **Permitted Actions**:
  - Access POS terminal and complete checkout.
  - Register new customers and redeem loyalty points.
  - Schedule deliveries from POS or order management.
  - Send delivery reminder notifications.
- **Strict Restrictions**:
  - **No Driver Assignment**: Cashiers are strictly forbidden from assigning drivers to deliveries (`POST /admin/deliveries/{delivery}/assign` is restricted to `admin` and `manager`).
  - **No Inventory Deletion**: Cashiers cannot alter ingredient stock levels, edit raw purchase orders, or delete catalog products.
  - **No User Management**: Cashiers cannot modify staff accounts or view payroll salaries.

### 4.3 Admin Superuser Permissions
- Full read and write access across all 14 modules.
- User management: create users, assign roles, reset passwords, and toggle activation status.
- System maintenance: trigger order archiving preview and order purges, configure global settings, and download database backups.

