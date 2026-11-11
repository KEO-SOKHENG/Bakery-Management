<?php

namespace App\Services;

use App\Models\User;

class PermissionService
{
    /**
     * Complete list of permissions grouped by module.
     */
    public static function getAllModules(): array
    {
        return [
            'users' => [
                'label' => 'Users & Staff',
                'description' => 'User accounts, roles, statuses and permissions',
                'permissions' => [
                    'users.view' => 'View Users & Staff List',
                    'users.create' => 'Create New User Accounts',
                    'users.edit' => 'Edit User Profiles & Details',
                    'users.status' => 'Activate / Deactivate / Suspend Accounts',
                    'users.permissions' => 'Manage Custom Roles & Permissions',
                    'users.reset_password' => 'Reset Passwords & Issue Temporary Credentials',
                ]
            ],
            'products' => [
                'label' => 'Products & Catalog',
                'description' => 'Baked goods catalog, pricing, and stock thresholds',
                'permissions' => [
                    'products.view' => 'View Products Catalog',
                    'products.create' => 'Create New Products',
                    'products.edit' => 'Edit Product Details & Prices',
                    'products.delete' => 'Delete Products',
                ]
            ],
            'categories' => [
                'label' => 'Product Categories',
                'description' => 'Product classification and tax categories',
                'permissions' => [
                    'categories.view' => 'View Categories',
                    'categories.create' => 'Create Categories',
                    'categories.edit' => 'Edit Categories',
                    'categories.delete' => 'Delete Categories',
                ]
            ],
            'ingredients' => [
                'label' => 'Inventory & Ingredients',
                'description' => 'Raw materials, unit stock levels, and alert thresholds',
                'permissions' => [
                    'ingredients.view' => 'View Ingredient Stocks',
                    'ingredients.manage' => 'Manage, Restock & Adjust Ingredients',
                ]
            ],
            'recipes' => [
                'label' => 'Recipes & Formulations',
                'description' => 'Baking formulas, ingredient ratios, and batch yields',
                'permissions' => [
                    'recipes.view' => 'View Bakery Recipes',
                    'recipes.manage' => 'Create, Edit & Delete Recipes',
                ]
            ],
            'suppliers' => [
                'label' => 'Suppliers & Vendors',
                'description' => 'Ingredient vendor contacts and catalog terms',
                'permissions' => [
                    'suppliers.view' => 'View Supplier Directory',
                    'suppliers.manage' => 'Create, Edit & Delete Suppliers',
                ]
            ],
            'purchase_orders' => [
                'label' => 'Purchase Orders (Procurement)',
                'description' => 'Stock replenishment orders and supplier receiving',
                'permissions' => [
                    'purchase_orders.view' => 'View Purchase Orders',
                    'purchase_orders.create' => 'Create Draft Purchase Orders',
                    'purchase_orders.order' => 'Send Orders to Suppliers',
                    'purchase_orders.receive' => 'Receive & Restock Purchase Deliveries',
                    'purchase_orders.cancel' => 'Cancel Purchase Orders',
                ]
            ],
            'production' => [
                'label' => 'Bakery Production',
                'description' => 'Baking batches, ingredient consumption, and status',
                'permissions' => [
                    'production.view' => 'View Production Batches',
                    'production.create' => 'Start New Production Batch',
                    'production.status' => 'Update Baking Status (Baking, Completed)',
                    'production.delete' => 'Cancel / Delete Production Batch',
                ]
            ],
            'orders' => [
                'label' => 'Orders Management',
                'description' => 'Bakery orders, fulfillment, and customer invoices',
                'permissions' => [
                    'orders.view' => 'View Orders List & Details',
                    'orders.create' => 'Create Orders',
                    'orders.edit' => 'Edit Orders',
                    'orders.status' => 'Update Order Status',
                    'orders.cancel' => 'Cancel Orders',
                    'orders.delete' => 'Delete Orders',
                ]
            ],
            'pos' => [
                'label' => 'POS Terminal',
                'description' => 'Front-of-house point of sale and rapid checkout',
                'permissions' => [
                    'pos.access' => 'Access POS Terminal Interface',
                    'pos.checkout' => 'Process POS Sales & Payments',
                ]
            ],
            'customers' => [
                'label' => 'Customer Management',
                'description' => 'Customer records, loyalty tiers, and purchase records',
                'permissions' => [
                    'customers.view' => 'View Customers & Loyalty Data',
                    'customers.create' => 'Register New Customers',
                    'customers.edit' => 'Edit Customer Information',
                    'customers.delete' => 'Delete / Archive Customers',
                ]
            ],
            'reports' => [
                'label' => 'Financial & Operational Reports',
                'description' => 'Sales, P&L, inventory valuation, and cost reports',
                'permissions' => [
                    'reports.view' => 'View Reports & Analytics',
                    'reports.export' => 'Export PDF & Excel Financial Reports',
                ]
            ],
            'notifications' => [
                'label' => 'System Notifications',
                'description' => 'Stock alerts, expiry alerts, and system notices',
                'permissions' => [
                    'notifications.view' => 'View System Notifications',
                    'notifications.manage' => 'Acknowledge, Read & Clear Notifications',
                    'notifications.promote' => 'Broadcast Manual & Promotional Announcements',
                ]
            ],
            'settings' => [
                'label' => 'System Settings',
                'description' => 'Bakery profile, tax rates, currency, and configurations',
                'permissions' => [
                    'settings.view' => 'View System Settings',
                    'settings.manage' => 'Modify System Configurations',
                    'settings.backup' => 'Generate and Download System Backups',
                ]
            ],
        ];
    }

    /**
     * Flat list of all permission strings.
     */
    public static function getAllPermissionKeys(): array
    {
        $keys = [];
        foreach (self::getAllModules() as $module) {
            foreach (array_keys($module['permissions']) as $key) {
                $keys[] = $key;
            }
        }
        return $keys;
    }

    /**
     * Sensible default permissions for each built-in role.
     */
    public static function getRoleDefaults(string $role): array
    {
        $role = strtolower(trim($role));

        if ($role === 'admin') {
            return self::getAllPermissionKeys();
        }

        if ($role === 'manager') {
            // Operational bakery management: everything except managing admin accounts/users
            return [
                'users.view',
                'products.view', 'products.create', 'products.edit', 'products.delete',
                'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
                'ingredients.view', 'ingredients.manage',
                'recipes.view', 'recipes.manage',
                'suppliers.view', 'suppliers.manage',
                'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.order', 'purchase_orders.receive', 'purchase_orders.cancel',
                'production.view', 'production.create', 'production.status',
                'orders.view', 'orders.create', 'orders.edit', 'orders.status', 'orders.cancel',
                'pos.access', 'pos.checkout',
                'customers.view', 'customers.create', 'customers.edit',
                'reports.view', 'reports.export',
                'notifications.view', 'notifications.manage',
                'settings.view',
            ];
        }

        if ($role === 'cashier') {
            return [
                'pos.access', 'pos.checkout',
                'orders.view', 'orders.create', 'orders.edit', 'orders.status',
                'customers.view', 'customers.create', 'customers.edit',
                'products.view',
                'notifications.view', 'notifications.manage',
            ];
        }

        if ($role === 'baker') {
            return [
                'production.view', 'production.create', 'production.status',
                'ingredients.view',
                'recipes.view',
                'notifications.view', 'notifications.manage',
            ];
        }

        return [];
    }

    /**
     * Compute effective permissions for a user taking into account role defaults + custom overrides.
     */
    public static function getEffectivePermissions(User $user): array
    {
        if ($user->isAdmin()) {
            return self::getAllPermissionKeys();
        }

        $defaults = self::getRoleDefaults($user->role);
        $overrides = $user->custom_permissions ?? [];

        $effective = [];
        foreach (self::getAllPermissionKeys() as $perm) {
            if (array_key_exists($perm, $overrides)) {
                if ((bool)$overrides[$perm]) {
                    $effective[] = $perm;
                }
            } elseif (in_array($perm, $defaults)) {
                $effective[] = $perm;
            }
        }

        return $effective;
    }

    /**
     * Check if user has permission.
     */
    public static function check(User $user, string $permission): bool
    {
        // Admins have all permissions unconditionally
        if ($user->isAdmin()) {
            return true;
        }

        // Deactivated or suspended users have no permissions
        if ($user->status !== 'active') {
            return false;
        }

        // Check custom overrides first
        $overrides = $user->custom_permissions;
        if (is_array($overrides) && array_key_exists($permission, $overrides)) {
            return (bool)$overrides[$permission];
        }

        // Fall back to role defaults
        $roleDefaults = self::getRoleDefaults($user->role);
        return in_array($permission, $roleDefaults);
    }
}
