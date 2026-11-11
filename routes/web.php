<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Cashier\DashboardController as CashierDashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\IngredientController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\ProductionController;
use App\Http\Controllers\Admin\RecipeController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\HrController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PosController;

// Health Check Endpoint (Render, Kubernetes, monitoring services)
Route::get('/healthz', function () {
    return response('OK', 200)->header('Content-Type', 'text/plain');
});

// Redirect root and /home to Login Page
Route::get('/', function () {
    return redirect()->route('login');
});
Route::get('/home', function () {
    return redirect()->route('login');
});

// Authentication Routes
Route::get('/login', [LoginController::class, 'index'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.post');
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

// Password Reset Routes (Guest)
Route::middleware(['guest'])->group(function () {
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');
});

// Language Switcher
Route::get('/lang/{lang}', [LanguageController::class, 'switchLanguage'])->name('lang.switch');

// Protected Routes (Must be Authenticated)
Route::middleware(['auth'])->group(function () {

    // Mandatory Password Change
    Route::get('/change-password', [PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    // Self-Service User Profile
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // -------------------------------------------------------------
    // ADMIN EXCLUSIVE ROUTES (Admin Only)
    // -------------------------------------------------------------
    Route::middleware(['role:admin'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
        
        // Users & Staff Management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.updateStatus');
        Route::post('/users/{user}/toggle', [UserController::class, 'toggleStatus'])->name('users.toggleStatus');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword');
        Route::get('/users/{user}/permissions', [UserController::class, 'permissions'])->name('users.permissions');
        Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->name('users.updatePermissions');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        // System Settings - Admin Exclusive Backup & Restore & Storage Diagnostics
        Route::get('/settings/storage-status', [SettingController::class, 'storageStatus'])->name('settings.storageStatus');
        Route::post('/settings/backup', [SettingController::class, 'createBackup'])->name('settings.backup');
        Route::post('/settings/restore', [SettingController::class, 'restoreBackup'])->name('settings.restore');

        // Full Backup & Restore (database + media + settings). Production restore is disabled.
        Route::get('/backups', [BackupController::class, 'index'])->middleware('throttle:30,1,backups-index')->name('backups.index');
        Route::post('/backups', [BackupController::class, 'store'])->middleware('throttle:3,1,backups-store')->name('backups.store');
        Route::get('/backups/production-gate', [BackupController::class, 'productionGate'])->middleware('throttle:30,1,backups-gate')->name('backups.productionGate');
        Route::post('/backups/verify', [BackupController::class, 'verify'])->middleware('throttle:6,1,backups-verify')->name('backups.verify');
        Route::post('/backups/production-restore', [BackupController::class, 'productionRestore'])->middleware('throttle:6,1,backups-production-restore')->name('backups.productionRestore');
        Route::get('/backups/{id}/status', [BackupController::class, 'status'])->whereUuid('id')->middleware('throttle:120,1,backups-status')->name('backups.status');
        Route::get('/backups/{id}/download', [BackupController::class, 'download'])->whereUuid('id')->middleware('signed')->name('backups.download');
        Route::post('/backups/{id}/sandbox-restore', [BackupController::class, 'sandboxRestore'])->whereUuid('id')->middleware('throttle:3,1,backups-sandbox')->name('backups.sandboxRestore');

        // Orders Purge & Data Maintenance (Admin Exclusive)
        Route::get('/orders/purge-preview', [OrderController::class, 'purgePreview'])->name('orders.purgePreview');
        Route::post('/orders/purge-old', [OrderController::class, 'purgeOld'])->name('orders.purgeOld');
    });

    // -------------------------------------------------------------
    // OPERATIONAL ROUTES (Admin & Manager)
    // -------------------------------------------------------------
    Route::middleware(['role:admin,manager'])->prefix('admin')->name('admin.')->group(function () {
        // System Settings (Admin & Authorized Manager)
        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::post('/settings/update', [SettingController::class, 'update'])->name('settings.update');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/ingredients', [IngredientController::class, 'index'])->name('ingredients');
        Route::post('/ingredients', [IngredientController::class, 'store'])->name('ingredients.store');
        Route::put('/ingredients/{ingredient}', [IngredientController::class, 'update'])->name('ingredients.update');
        Route::delete('/ingredients/{ingredient}', [IngredientController::class, 'destroy'])->name('ingredients.destroy');

        Route::get('/recipes', [RecipeController::class, 'index'])->name('recipes');
        Route::post('/recipes', [RecipeController::class, 'store'])->name('recipes.store');
        Route::put('/recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
        Route::delete('/recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');

        // Suppliers Management
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers');
        Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

        // Purchase Orders Management & Stock Replenishment
        Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/purchase-orders/create', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/purchase-orders/{purchase_order}/edit', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/purchase-orders/{purchase_order}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::post('/purchase-orders/{purchase_order}/order', [PurchaseOrderController::class, 'order'])->name('purchase-orders.order');
        Route::post('/purchase-orders/{purchase_order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('/purchase-orders/{purchase_order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');

        Route::post('/production', [ProductionController::class, 'store'])->name('production.store');
        Route::delete('/production/{production}', [ProductionController::class, 'destroy'])->name('production.destroy');

        Route::get('/reports', [ReportController::class, 'index'])->name('reports');
        Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
        Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');

        // Customer Management & Purchase History
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        // Order Management (Admin & Manager Safe Deletion)
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');

        // HR & Staff Extensions (Attendance, Work Schedules, Salaries)
        Route::get('/hr/attendance', [HrController::class, 'attendance'])->name('hr.attendance');
        Route::post('/hr/attendance', [HrController::class, 'storeAttendance'])->name('hr.attendance.store');
        Route::put('/hr/attendance/{attendance}', [HrController::class, 'updateAttendance'])->name('hr.attendance.update');
        Route::delete('/hr/attendance/{attendance}', [HrController::class, 'destroyAttendance'])->name('hr.attendance.destroy');

        Route::get('/hr/schedules', [HrController::class, 'schedules'])->name('hr.schedules');
        Route::post('/hr/schedules', [HrController::class, 'storeSchedule'])->name('hr.schedules.store');
        Route::put('/hr/schedules/{workSchedule}', [HrController::class, 'updateSchedule'])->name('hr.schedules.update');
        Route::delete('/hr/schedules/{workSchedule}', [HrController::class, 'destroySchedule'])->name('hr.schedules.destroy');

        Route::get('/hr/salaries', [HrController::class, 'salaries'])->name('hr.salaries');
        Route::post('/hr/salaries', [HrController::class, 'storeSalary'])->name('hr.salaries.store');
        Route::post('/hr/salaries/{salary}/pay', [HrController::class, 'markSalaryPaid'])->name('hr.salaries.markPaid');
        Route::delete('/hr/salaries/{salary}', [HrController::class, 'destroySalary'])->name('hr.salaries.destroy');
    });

    // -------------------------------------------------------------
    // PRODUCTION EXECUTION ROUTES (Admin, Manager, Baker)
    // -------------------------------------------------------------
    Route::middleware(['role:admin,manager,baker'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/production', [ProductionController::class, 'index'])->name('production');
        Route::get('/production/{production}', [ProductionController::class, 'show'])->name('production.show');
        Route::post('/production/{production}/status', [ProductionController::class, 'updateStatus'])->name('production.updateStatus');
    });

    // -------------------------------------------------------------
    // SHARED OPERATIONAL ROUTES (Admin, Manager, Cashier)
    // -------------------------------------------------------------
    Route::middleware(['role:admin,manager,cashier'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('/products', [ProductController::class, 'index'])->name('products');
        Route::get('/products/ingredients-list', [ProductController::class, 'ingredientsList'])->name('products.ingredients-list');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

        // Order Management Workflow & Views
        Route::get('/orders', [OrderController::class, 'index'])->name('orders');
        Route::get('/orders/create', [OrderController::class, 'create'])->name('orders.create');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/edit', [OrderController::class, 'edit'])->name('orders.edit');
        Route::put('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
        Route::post('/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    });

    // -------------------------------------------------------------
    // RETIRED DELIVERY ROUTE FALLBACK (Project Owner Decision 1)
    // -------------------------------------------------------------
    // Gracefully handles legacy notifications and bookmarked URLs pointing to /admin/deliveries/*
    Route::middleware(['auth'])->prefix('admin')->group(function () {
        Route::get('/deliveries/{id?}', function () {
            return redirect()->route('admin.orders')
                ->with('info', 'Customer delivery tracking has been retired. Order fulfillment records remain accessible in Orders.');
        })->where('id', '[0-9]+')->name('admin.deliveries.fallback');
    });

    // MANAGER DASHBOARD
    Route::middleware(['role:admin,manager'])->prefix('manager')->name('manager.')->group(function () {
        Route::get('/dashboard', [ManagerDashboardController::class, 'index'])->name('dashboard');
    });

    // CASHIER DASHBOARD
    Route::middleware(['role:admin,manager,cashier'])->prefix('cashier')->name('cashier.')->group(function () {
        Route::get('/dashboard', [CashierDashboardController::class, 'index'])->name('dashboard');
    });

    // POS TERMINAL & CHECKOUT (Admin, Manager, Cashier)
    Route::middleware(['role:admin,manager,cashier'])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos');
        Route::get('/pos/customers/search', [PosController::class, 'searchCustomers'])->name('pos.customers.search');
        Route::post('/pos/customers/quick-store', [PosController::class, 'quickStoreCustomer'])->name('pos.customers.quickStore');
        Route::post('/pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
    });

    // -------------------------------------------------------------
    // NOTIFICATION MANAGEMENT ROUTES
    // -------------------------------------------------------------
    Route::get('/admin/notifications', [NotificationController::class, 'index'])->name('admin.notifications');
    Route::post('/admin/notifications/promotions', [NotificationController::class, 'sendPromotion'])->name('admin.notifications.promotions')->middleware(['role:admin']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unreadCount');
    Route::get('/notifications/feed', [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'markAsUnread'])->name('notifications.unread');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
    Route::post('/notifications/bulk-read', [NotificationController::class, 'bulkRead'])->name('notifications.bulkRead');
    Route::post('/notifications/bulk-unread', [NotificationController::class, 'bulkUnread'])->name('notifications.bulkUnread');
    Route::post('/notifications/bulk-delete', [NotificationController::class, 'bulkDelete'])->name('notifications.bulkDelete');

});