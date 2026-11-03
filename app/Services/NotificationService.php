<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Production;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * In-memory cache for role-based users during the current service lifecycle.
     */
    protected array $cachedRoleUsers = [];

    /**
     * Fetch users by roles with memoization to prevent N+1 queries during batch processing.
     */
    public function getUsersByRoles(array $roles): \Illuminate\Database\Eloquent\Collection
    {
        $normalizedRoles = array_values(array_unique($roles));
        sort($normalizedRoles);
        $cacheKey = implode(',', $normalizedRoles);

        if (!isset($this->cachedRoleUsers[$cacheKey])) {
            $this->cachedRoleUsers[$cacheKey] = User::whereIn('role', $normalizedRoles)->get();
        }

        return $this->cachedRoleUsers[$cacheKey];
    }

    /**
     * Clear the memoized role users cache.
     */
    public function clearUserCache(): void
    {
        $this->cachedRoleUsers = [];
    }

    /**
     * Create a persistent notification for a specific user, with automatic deduplication.
     */
    public function createNotification(
        User|int $user,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null,
        ?string $dedupKey = null
    ): ?Notification {
        $userId = is_object($user) ? $user->id : (int) $user;

        // Deduplication safeguard: if unread notification with identical dedup_key exists, avoid duplicate
        if ($dedupKey) {
            $existing = Notification::where('user_id', $userId)
                ->where('dedup_key', $dedupKey)
                ->whereNull('read_at')
                ->first();

            if ($existing) {
                // Update timestamp so user sees it is still active without spamming rows
                $existing->touch();
                return $existing;
            }

            // For condition-based alerts, do not spam with repeated alerts on the same day if already read
            $isConditionAlert = str_starts_with($dedupKey, 'low_stock:') 
                             || str_starts_with($dedupKey, 'out_of_stock:')
                             || str_starts_with($dedupKey, 'expiring_')
                             || str_starts_with($dedupKey, 'expired:');

            if ($isConditionAlert) {
                $readToday = Notification::where('user_id', $userId)
                    ->where('dedup_key', $dedupKey)
                    ->where('created_at', '>=', now()->startOfDay())
                    ->first();

                if ($readToday) {
                    return $readToday;
                }
            }
        }

        return Notification::create([
            'user_id'    => $userId,
            'type'       => $type,
            'title'      => $title,
            'message'    => $message,
            'severity'   => $severity,
            'action_url' => $actionUrl,
            'dedup_key'  => $dedupKey,
        ]);
    }

    /**
     * Broadcast notification to all active users holding specific roles.
     */
    public function notifyRoles(
        array $roles,
        string $type,
        string $title,
        string $message,
        string $severity = 'info',
        ?string $actionUrl = null,
        ?string $dedupKey = null
    ): int {
        $users = $this->getUsersByRoles($roles);
        $count = 0;

        foreach ($users as $user) {
            $this->createNotification($user, $type, $title, $message, $severity, $actionUrl, $dedupKey);
            $count++;
        }

        return $count;
    }

    /**
     * Broadcast a promotional announcement or manual notification from Admin.
     */
    public function broadcastPromotion(
        string $title,
        string $message,
        ?string $targetRole = null,
        string $severity = 'info'
    ): int {
        $query = User::query();
        if ($targetRole && $targetRole !== 'all') {
            $query->where('role', $targetRole);
        }

        $users = $query->get();
        $count = 0;

        foreach ($users as $user) {
            $this->createNotification(
                $user,
                'promotion',
                $title,
                $message,
                $severity,
                route('admin.notifications'),
                null
            );
            $count++;
        }

        return $count;
    }

    // =========================================================================
    // EVENT-DRIVEN NOTIFICATIONS
    // =========================================================================

    /**
     * Trigger notification on new order placement (POS or web).
     */
    public function notifyOrderCreated(Order $order): void
    {
        $actionUrl = route('admin.orders');

        if ($order->is_custom) {
            // Custom cake orders alert kitchen staff (bakers) in addition to management
            $title = "New Custom Cake Order: #{$order->order_number}";
            $instructionSnippet = $order->special_instructions ? " — \"{$order->special_instructions}\"" : '';
            $message = "Customer {$order->customer_name} placed custom cake order ({$instructionSnippet}) totaling \${$order->total}.";
            
            $this->notifyRoles(['admin', 'manager', 'cashier', 'baker'], 'custom_order', $title, $message, 'warning', $actionUrl, "order_created:{$order->id}");
        } else {
            $title = "New Order: #{$order->order_number}";
            $message = "New order received from {$order->customer_name} totaling \${$order->total}.";

            $this->notifyRoles(['admin', 'manager', 'cashier'], 'order', $title, $message, 'info', $actionUrl, "order_created:{$order->id}");
        }
    }

    /**
     * Trigger notification on order status transition.
     */
    public function notifyOrderStatusUpdated(Order $order, string $newStatus): void
    {
        $actionUrl = route('admin.orders');

        if ($newStatus === Order::STATUS_READY_FOR_PICKUP || $newStatus === 'ready') {
            $title = "Order Ready for Pickup: #{$order->order_number}";
            $message = "Order for {$order->customer_name} is baked, packaged, and ready for pickup.";
            $this->notifyRoles(['admin', 'manager', 'cashier'], 'order', $title, $message, 'success', $actionUrl, "order_ready:{$order->id}");
        } elseif ($newStatus === Order::STATUS_COMPLETED) {
            $title = "Order Completed: #{$order->order_number}";
            $message = "Order for {$order->customer_name} completed. Revenue \${$order->total} confirmed.";
            $this->notifyRoles(['admin', 'manager', 'cashier'], 'order', $title, $message, 'success', $actionUrl, "order_completed:{$order->id}");
        } elseif ($newStatus === Order::STATUS_CANCELLED) {
            $title = "Order Cancelled: #{$order->order_number}";
            $message = "Order #{$order->order_number} has been cancelled and reserved items restored.";
            $this->notifyRoles(['admin', 'manager', 'cashier'], 'order', $title, $message, 'warning', $actionUrl, "order_cancelled:{$order->id}");
        }
    }

    /**
     * Trigger notification when a production batch is scheduled.
     */
    public function notifyProductionScheduled(Production $production): void
    {
        $productName = $production->product ? $production->product->name : 'Bakery Item';
        $actionUrl = route('admin.production', ['open_batch' => $production->id]);

        $title = "Production Scheduled: Batch #{$production->batch_number}";
        $message = "Batch for {$production->quantity} units of {$productName} scheduled.";

        $this->notifyRoles(['admin', 'manager', 'baker'], 'production', $title, $message, 'info', $actionUrl, "prod_scheduled:{$production->id}");
    }

    /**
     * Trigger notification when a production batch is completed.
     */
    public function notifyProductionCompleted(Production $production): void
    {
        $productName = $production->product ? $production->product->name : 'Bakery Item';
        $actionUrl = route('admin.production', ['open_batch' => $production->id]);

        $title = "Production Completed: Batch #{$production->batch_number}";
        $message = "Batch completed! {$production->quantity} units of {$productName} produced and added to available stock.";

        $this->notifyRoles(['admin', 'manager', 'baker'], 'production', $title, $message, 'success', $actionUrl, "prod_completed:{$production->id}");
    }

    /**
     * Trigger notification on Purchase Order events.
     */
    public function notifyPurchaseOrderUpdated(PurchaseOrder $po, string $action): void
    {
        $supplierName = $po->supplier ? $po->supplier->name : 'Supplier';
        $actionUrl = route('admin.purchase-orders.index', ['open_po' => $po->id]);

        if ($action === 'ordered') {
            $title = "Purchase Order Placed: #{$po->po_number}";
            $message = "Order placed with {$supplierName} totaling \${$po->total_cost}. Delivery expected soon.";
            $this->notifyRoles(['admin', 'manager'], 'purchase_order', $title, $message, 'info', $actionUrl, "po_ordered:{$po->id}");
        } elseif ($action === 'received') {
            $title = "Purchase Order Received: #{$po->po_number}";
            $message = "Shipment from {$supplierName} received! Ingredient stock levels have been replenished.";
            $this->notifyRoles(['admin', 'manager', 'baker'], 'purchase_order', $title, $message, 'success', $actionUrl, "po_received:{$po->id}");
        } elseif ($action === 'cancelled') {
            $title = "Purchase Order Cancelled: #{$po->po_number}";
            $message = "PO #{$po->po_number} with {$supplierName} was cancelled.";
            $this->notifyRoles(['admin', 'manager'], 'purchase_order', $title, $message, 'warning', $actionUrl, "po_cancelled:{$po->id}");
        }
    }

    // =========================================================================
    // CONDITION-BASED DEDUPLICATED ALERTS SCANNER
    // =========================================================================

    /**
     * Scan live inventory and scheduled orders to create deduplicated condition alerts.
     * Also self-heals: auto-resolves alerts when conditions normalize.
     */
    public function syncConditionAlerts(): array
    {
        $counts = [
            'low_stock'    => 0,
            'out_of_stock' => 0,
            'expiring'     => 0,
            'expired'      => 0,
            'pickup_soon'  => 0,
            'resolved'     => 0,
        ];

        $this->clearUserCache();

        // Check global notifications toggle
        if (!SettingsService::getBool('notifications_enabled', true)) {
            return $counts;
        }

        $lowStockAlertsEnabled = SettingsService::getBool('low_stock_alerts_enabled', true);
        $expiryAlertsEnabled   = SettingsService::getBool('expiry_alerts_enabled', true);
        $defaultMinStock       = SettingsService::getFloat('low_stock_threshold', 5.0);
        $expiryWarningDays     = SettingsService::getInt('expiry_warning_days', 3);

        // -------------------------------------------------------------
        // 1. INVENTORY: Low Stock and Out of Stock Alerts
        // -------------------------------------------------------------
        $ingredients = Ingredient::all();
        $targetRoles = ['admin', 'manager', 'baker'];
        $outOfStockToResolve = [];
        $keysToResolve = [];

        foreach ($ingredients as $ing) {
            $outOfStockKey = "out_of_stock:ingredient:{$ing->id}";
            $lowStockKey   = "low_stock:ingredient:{$ing->id}";
            $minThreshold  = $ing->minimum_quantity > 0 ? (float) $ing->minimum_quantity : $defaultMinStock;

            if ($lowStockAlertsEnabled) {
                if ($ing->quantity <= 0) {
                    // Critical Out of Stock
                    $title = "Critical: {$ing->name} Out of Stock";
                    $message = "{$ing->name} is depleted (0 {$ing->unit} remaining). Reorder required immediately.";
                    $this->notifyRoles($targetRoles, 'inventory', $title, $message, 'critical', route('admin.ingredients'), $outOfStockKey);
                    $counts['out_of_stock']++;
                } elseif ($ing->quantity <= $minThreshold) {
                    // Warning Low Stock
                    $title = "Low Stock Alert: {$ing->name}";
                    $message = "{$ing->name} is at {$ing->quantity} {$ing->unit} (Minimum: {$minThreshold} {$ing->unit}).";
                    $this->notifyRoles($targetRoles, 'inventory', $title, $message, 'warning', route('admin.ingredients'), $lowStockKey);
                    $counts['low_stock']++;

                    // Mark out of stock alert for batch resolution
                    $outOfStockToResolve[] = $outOfStockKey;
                } else {
                    // Self-Healing: Stock is above minimum threshold, queue for batch resolution
                    $keysToResolve[] = $lowStockKey;
                    $keysToResolve[] = $outOfStockKey;
                }
            }

            // ---------------------------------------------------------
            // 2. EXPIRING INGREDIENTS
            // ---------------------------------------------------------
            if ($expiryAlertsEnabled && $ing->expiry_date) {
                $expiry = Carbon::parse($ing->expiry_date)->startOfDay();
                $today = Carbon::today()->startOfDay();

                if ($expiry->lt($today)) {
                    // Expired
                    $title = "Expired Ingredient: {$ing->name}";
                    $message = "{$ing->name} expired on {$expiry->format('M d, Y')}. Remove from kitchen inventory immediately.";
                    $this->notifyRoles($targetRoles, 'expiring', $title, $message, 'critical', route('admin.ingredients'), "expired:ingredient:{$ing->id}");
                    $counts['expired']++;
                } elseif ($expiry->eq($today)) {
                    // Expiring today
                    $title = "Expiring Today: {$ing->name}";
                    $message = "{$ing->name} expires today ({$expiry->format('M d, Y')}). Use or plan batch.";
                    $this->notifyRoles($targetRoles, 'expiring', $title, $message, 'warning', route('admin.ingredients'), "expiring_today:ingredient:{$ing->id}");
                    $counts['expiring']++;
                } elseif ($expiry->lte($today->copy()->addDays($expiryWarningDays))) {
                    // Expiring within configured threshold
                    $title = "Expiring in {$expiryWarningDays} Days: {$ing->name}";
                    $message = "{$ing->name} will expire on {$expiry->format('M d, Y')} (within {$expiryWarningDays} days).";
                    $this->notifyRoles($targetRoles, 'expiring', $title, $message, 'warning', route('admin.ingredients'), "expiring_{$expiryWarningDays}days:ingredient:{$ing->id}");
                    $counts['expiring']++;
                } elseif ($expiryWarningDays < 7 && $expiry->lte($today->copy()->addDays(7))) {
                    // Secondary 7-day warning if configured threshold is less than 7
                    $title = "Expiring in 7 Days: {$ing->name}";
                    $message = "{$ing->name} will expire on {$expiry->format('M d, Y')}.";
                    $this->notifyRoles($targetRoles, 'expiring', $title, $message, 'info', route('admin.ingredients'), "expiring_7days:ingredient:{$ing->id}");
                }
            }
        }

        // Execute batched self-healing resolutions in 2 single queries instead of N loop queries
        if (!empty($outOfStockToResolve)) {
            Notification::whereIn('dedup_key', array_unique($outOfStockToResolve))
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
        }
        if (!empty($keysToResolve)) {
            $resolved = Notification::whereIn('dedup_key', array_unique($keysToResolve))
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $counts['resolved'] += $resolved;
        }

        // -------------------------------------------------------------
        // 3. CUSTOM ORDER PICKUP REMINDERS
        // -------------------------------------------------------------
        $urgentCustomOrders = Order::where('is_custom', true)
            ->whereIn('order_status', [Order::STATUS_PENDING, Order::STATUS_PREPARING, Order::STATUS_READY_FOR_PICKUP])
            ->whereNotNull('pickup_date')
            ->whereDate('pickup_date', '<=', Carbon::today()->addDay())
            ->get();

        foreach ($urgentCustomOrders as $cOrder) {
            $dedupKey = "custom_pickup_soon:{$cOrder->id}";
            $pickupTime = Carbon::parse($cOrder->pickup_date)->format('M d, H:i');
            $title = "Custom Cake Pickup Soon: #{$cOrder->order_number}";
            $message = "Order for {$cOrder->customer_name} is scheduled for pickup ({$pickupTime}). Status: " . ucfirst(str_replace('_', ' ', $cOrder->order_status));

            $this->notifyRoles(['admin', 'manager', 'cashier', 'baker'], 'custom_order', $title, $message, 'warning', route('admin.orders'), $dedupKey);
            $counts['pickup_soon']++;
        }

        return $counts;
    }

    /**
     * Throttled sync for web requests: runs at most once every 2 minutes per user.
     */
    public function syncThrottled(?User $user = null): void
    {
        $user = $user ?? Auth::user();
        if (!$user) {
            return;
        }

        $cacheKey = "notifications_synced_user_{$user->id}";
        if (!Cache::has($cacheKey)) {
            $this->syncConditionAlerts();
            Cache::put($cacheKey, true, 120); // 2 minutes throttle
        }
    }
}
