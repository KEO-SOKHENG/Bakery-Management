<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class OrderController extends Controller
{
    /**
     * Display a paginated listing of orders with search, filters, and metrics.
     */
    public function index(Request $request)
    {
        $query = Order::with(['items.product', 'customer', 'user', 'sale', 'payments']);

        // 1. Search by order number, order ID, customer name, or customer phone
        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = ltrim($search, '#');
            $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($q) use ($search, $cleanSearch, $like) {
                $q->where('order_number', $like, "%{$search}%")
                  ->orWhere('customer_name', $like, "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search, $like) {
                      $cq->where('name', $like, "%{$search}%")
                         ->orWhere('phone', $like, "%{$search}%");
                  });

                if (is_numeric($cleanSearch)) {
                    $q->orWhere('id', (int) $cleanSearch);
                }
            });
        }

        // 2. Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $status = strtolower(trim($request->status));
            if ($status === 'preparing') {
                $query->whereIn('order_status', ['preparing', 'baking', 'in baking']);
            } elseif ($status === 'ready_for_pickup') {
                $query->whereIn('order_status', ['ready_for_pickup', 'ready']);
            } else {
                $query->where('order_status', $status);
            }
        }

        // 3. Filter by payment method
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        // 4. Filter by payment status
        if ($request->filled('payment_status') && $request->payment_status !== 'all') {
            $query->where('payment_status', $request->payment_status);
        }

        // 5. Filter by order type (Custom Cake vs Standard POS)
        if ($request->filled('type') && $request->type !== 'all') {
            if ($request->type === 'custom') {
                $query->where('is_custom', true);
            } elseif ($request->type === 'pos') {
                $query->where('is_custom', false);
            }
        }

        // 6. Filter by date range
        if ($request->filled('date')) {
            $dateFilter = $request->date;
            if ($dateFilter === 'today') {
                $query->whereDate('created_at', today());
            } elseif ($dateFilter === 'yesterday') {
                $query->whereDate('created_at', today()->subDay());
            } elseif ($dateFilter === 'this_week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($dateFilter === 'this_month') {
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            }
        }

        $orders = $query->orderBy('id', 'desc')->get();

        // Summary KPI Metrics (Consolidated single aggregation query)
        $summary = Order::query()
            ->selectRaw("
                COUNT(*) as total_orders_count,
                COUNT(CASE WHEN order_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN order_status IN ('preparing', 'baking') THEN 1 END) as preparing_count,
                COUNT(CASE WHEN order_status IN ('ready_for_pickup', 'ready') THEN 1 END) as ready_count,
                COUNT(CASE WHEN order_status = 'completed' THEN 1 END) as completed_count,
                COUNT(CASE WHEN order_status = 'cancelled' THEN 1 END) as cancelled_count,
                COALESCE(SUM(CASE WHEN order_status = 'completed' THEN total ELSE 0 END), 0) as total_revenue,
                COUNT(CASE WHEN is_custom = true THEN 1 END) as custom_orders_count
            ")
            ->first();

        $totalOrdersCount    = (int) ($summary->total_orders_count ?? 0);
        $pendingCount        = (int) ($summary->pending_count ?? 0);
        $preparingCount      = (int) ($summary->preparing_count ?? 0);
        $readyCount          = (int) ($summary->ready_count ?? 0);
        $completedCount      = (int) ($summary->completed_count ?? 0);
        $cancelledCount      = (int) ($summary->cancelled_count ?? 0);
        $totalRevenue        = (float) ($summary->total_revenue ?? 0.0);
        $customOrdersCount   = (int) ($summary->custom_orders_count ?? 0);

        $currency = Setting::get('currency', '$');
        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $products = Product::where('status', 'active')->where('stock', '>', 0)->orderBy('name')->get();

        return view('admin.orders.index', compact(
            'orders',
            'totalOrdersCount',
            'pendingCount',
            'preparingCount',
            'readyCount',
            'completedCount',
            'cancelledCount',
            'totalRevenue',
            'customOrdersCount',
            'currency',
            'taxPercentage',
            'products'
        ));
    }

    /**
     * Show the form for creating a new Customer / Custom Cake / Pre-Order.
     */
    public function create()
    {
        return redirect()->route('admin.orders');
    }

    /**
     * Store a newly created order with strict server calculation and stock safety.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'customer_name'        => 'nullable|string|max:255',
            'payment_method'       => 'required|string|in:cash,card,qr_code',
            'payment_status'       => 'nullable|string|in:paid,unpaid',
            'order_status'         => 'nullable|string|in:pending,preparing,ready_for_pickup,completed',
            'is_custom'            => 'nullable|boolean',
            'special_instructions' => 'nullable|string|max:2000',
            'pickup_date'          => 'nullable|date',
            'discount'             => 'nullable|numeric|min:0',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $taxRate = $taxPercentage / 100.0;
        $userEnteredDiscount = max(0, (float) ($validated['discount'] ?? 0));
        $initialStatus = $validated['order_status'] ?? 'pending';
        $paymentMethod = $validated['payment_method'];
        $isCustom = (bool) ($validated['is_custom'] ?? false);

        try {
            $order = DB::transaction(function () use (
                $validated,
                $taxRate,
                $userEnteredDiscount,
                $initialStatus,
                $paymentMethod,
                $isCustom
            ) {
                // Resolve customer
                $customer = null;
                if (!empty($validated['customer_id'])) {
                    $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                }

                $customerName = $customer
                    ? $customer->name
                    : (trim($validated['customer_name'] ?? '') ?: 'Walk-in Customer');

                $subtotal = 0.0;
                $itemsToCreate = [];

                foreach ($validated['items'] as $itemData) {
                    $productId = (int) $itemData['product_id'];
                    $qty = (int) $itemData['quantity'];

                    $product = Product::lockForUpdate()->find($productId);

                    if (!$product || $product->status !== 'active') {
                        throw new \Exception("Product '{$productId}' is inactive or unavailable.");
                    }

                    if ($product->stock < $qty) {
                        throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$product->stock}, Requested: {$qty}.");
                    }

                    $unitPrice = (float) $product->price;
                    $lineTotal = round($unitPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    // Reserve/deduct product stock
                    $product->decrement('stock', $qty);

                    $itemsToCreate[] = [
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'price'      => $unitPrice,
                        'subtotal'   => $lineTotal,
                    ];
                }

                $discount = min($userEnteredDiscount, $subtotal);
                $taxableAmount = max(0.0, $subtotal - $discount);
                $tax = round($taxableAmount * $taxRate, 2);
                $grandTotal = round($taxableAmount + $tax, 2);

                $orderNumber = 'ORD-' . strtoupper(uniqid());
                $paymentRef  = 'PAY-' . strtoupper(uniqid());

                $isPaid = ($initialStatus === 'completed') || (($validated['payment_status'] ?? '') === 'paid');
                $paymentStatus = $isPaid ? 'paid' : 'unpaid';

                // 1. Create Order
                $order = Order::create([
                    'user_id'              => Auth::id(),
                    'customer_id'          => $customer ? $customer->id : null,
                    'order_number'         => $orderNumber,
                    'customer_name'        => $customerName,
                    'subtotal'             => $subtotal,
                    'discount'             => $discount,
                    'tax'                  => $tax,
                    'total'                => $grandTotal,
                    'payment_method'       => $paymentMethod,
                    'payment_status'       => $paymentStatus,
                    'order_status'         => $initialStatus,
                    'is_custom'            => $isCustom,
                    'special_instructions' => $validated['special_instructions'] ?? null,
                    'pickup_date'          => !empty($validated['pickup_date']) ? $validated['pickup_date'] : null,
                ]);

                // 2. Create Order Items
                foreach ($itemsToCreate as $item) {
                    $order->items()->create($item);
                }

                // 3. Create Sale & Payment if completed/paid
                if ($isPaid) {
                    Sale::create([
                        'order_id'       => $order->id,
                        'user_id'        => Auth::id(),
                        'total'          => $grandTotal,
                        'payment_method' => $paymentMethod,
                        'payment_status' => 'paid',
                        'sold_at'        => now(),
                    ]);

                    Payment::create([
                        'order_id'          => $order->id,
                        'user_id'           => Auth::id(),
                        'payment_method'    => $paymentMethod,
                        'amount'            => $grandTotal,
                        'payment_status'    => 'completed',
                        'payment_reference' => $paymentRef,
                        'payment_date'      => now(),
                        'notes'             => "Order Payment completed by " . (Auth::user()->name ?? 'Staff'),
                    ]);

                    // Loyalty points
                    if ($customer) {
                        $pointsEarned = (int) floor($grandTotal);
                        if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'loyalty_points_awarded')) {
                            $order->loyalty_points_awarded = $pointsEarned;
                            $order->save();
                        }
                        $customer->addLoyaltyPoints($pointsEarned);
                    }
                } else {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'loyalty_points_awarded')) {
                        $order->loyalty_points_awarded = 0;
                        $order->save();
                    }
                }

                return $order;
            });

            // Dispatch notification for order creation
            try {
                app(\App\Services\NotificationService::class)->notifyOrderCreated($order);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch order notification: " . $e->getMessage());
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => "Order #{$order->order_number} created successfully.",
                    'order'   => $order->load('items.product'),
                ]);
            }

            return redirect()->route('admin.orders')
                ->with('success', "Order #{$order->order_number} created successfully!");
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Display the detailed order view (Redirects to orders index).
     */
    public function show($order = null)
    {
        return redirect()->route('admin.orders');
    }

    /**
     * Show form to edit a pending order.
     */
    public function edit(Order $order)
    {
        if (!$order->canBeEdited()) {
            return redirect()->route('admin.orders')
                ->with('error', "Only Pending orders can be modified. Current status is '{$order->order_status}'.");
        }

        $order->load(['items.product', 'customer']);
        $products = Product::where('status', 'active')->orderBy('name')->get();
        $customers = Customer::where('status', 'active')->orderBy('name')->get();
        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $currency = Setting::get('currency', '$');

        return view('admin.orders.edit', compact('order', 'products', 'customers', 'taxPercentage', 'currency'));
    }

    /**
     * Update order details safely with stock adjustment.
     */
    public function update(Request $request, Order $order)
    {
        // If order is preparing, allow updating instructions & pickup date only
        if ($order->isPreparing()) {
            $validated = $request->validate([
                'special_instructions' => 'nullable|string|max:2000',
                'pickup_date'          => 'nullable|date',
            ]);

            $order->update([
                'special_instructions' => $validated['special_instructions'] ?? $order->special_instructions,
                'pickup_date'          => !empty($validated['pickup_date']) ? $validated['pickup_date'] : $order->pickup_date,
            ]);

            return redirect()->route('admin.orders')
                ->with('success', "Order #{$order->order_number} notes and pickup schedule updated.");
        }

        // Only pending orders can have full modification
        if (!$order->isPending()) {
            return redirect()->route('admin.orders')
                ->with('error', "Cannot modify an order with status '{$order->order_status}'.");
        }

        $validated = $request->validate([
            'customer_id'          => 'nullable|exists:customers,id',
            'customer_name'        => 'nullable|string|max:255',
            'payment_method'       => 'required|string|in:cash,card,qr_code',
            'is_custom'            => 'nullable|boolean',
            'special_instructions' => 'nullable|string|max:2000',
            'pickup_date'          => 'nullable|date',
            'discount'             => 'nullable|numeric|min:0',
            'items'                => 'required|array|min:1',
            'items.*.product_id'   => 'required|exists:products,id',
            'items.*.quantity'     => 'required|integer|min:1',
        ]);

        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $taxRate = $taxPercentage / 100.0;
        $userEnteredDiscount = max(0, (float) ($validated['discount'] ?? 0));

        try {
            DB::transaction(function () use ($request, $order, $validated, $taxRate, $userEnteredDiscount) {
                $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

                if (!$lockedOrder->isPending()) {
                    throw new \Exception("Order status changed concurrently. Cannot modify non-pending order.");
                }

                // Map existing items: product_id => qty
                $oldItemsMap = [];
                foreach ($lockedOrder->items as $item) {
                    $oldItemsMap[$item->product_id] = ($oldItemsMap[$item->product_id] ?? 0) + $item->quantity;
                }

                // Map new items: product_id => qty
                $newItemsMap = [];
                foreach ($validated['items'] as $item) {
                    $pid = (int) $item['product_id'];
                    $newItemsMap[$pid] = ($newItemsMap[$pid] ?? 0) + (int) $item['quantity'];
                }

                // Collect all product IDs affected
                $allProductIds = array_unique(array_merge(array_keys($oldItemsMap), array_keys($newItemsMap)));

                foreach ($allProductIds as $pid) {
                    $oldQty = $oldItemsMap[$pid] ?? 0;
                    $newQty = $newItemsMap[$pid] ?? 0;
                    $diff = $newQty - $oldQty;

                    if ($diff !== 0) {
                        $product = Product::lockForUpdate()->find($pid);
                        if (!$product) {
                            throw new \Exception("Product #{$pid} not found.");
                        }

                        if ($diff > 0) {
                            // Needs more stock
                            if ($product->stock < $diff) {
                                throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$product->stock}, Additional needed: {$diff}.");
                            }
                            $product->decrement('stock', $diff);
                        } else {
                            // Return surplus stock
                            $product->increment('stock', abs($diff));
                        }
                    }
                }

                // Calculate fresh subtotal from database prices
                $subtotal = 0.0;
                $newOrderItems = [];
                foreach ($validated['items'] as $itemData) {
                    $product = Product::find($itemData['product_id']);
                    $qty = (int) $itemData['quantity'];
                    $unitPrice = (float) $product->price;
                    $lineTotal = round($unitPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    $newOrderItems[] = [
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'price'      => $unitPrice,
                        'subtotal'   => $lineTotal,
                    ];
                }

                $discount = min($userEnteredDiscount, $subtotal);
                $taxableAmount = max(0.0, $subtotal - $discount);
                $tax = round($taxableAmount * $taxRate, 2);
                $grandTotal = round($taxableAmount + $tax, 2);

                $customer = null;
                if (!empty($validated['customer_id'])) {
                    $customer = Customer::find($validated['customer_id']);
                }
                $customerName = $customer ? $customer->name : (trim($validated['customer_name'] ?? '') ?: 'Walk-in Customer');

                // Delete old items and insert updated items
                $lockedOrder->items()->delete();
                foreach ($newOrderItems as $item) {
                    $lockedOrder->items()->create($item);
                }

                // Update Order header
                $lockedOrder->update([
                    'customer_id'          => $customer ? $customer->id : null,
                    'customer_name'        => $customerName,
                    'subtotal'             => $subtotal,
                    'discount'             => $discount,
                    'tax'                  => $tax,
                    'total'                => $grandTotal,
                    'payment_method'       => $validated['payment_method'],
                    'is_custom'            => (bool) ($validated['is_custom'] ?? false),
                    'special_instructions' => $validated['special_instructions'] ?? null,
                    'pickup_date'          => !empty($validated['pickup_date']) ? $validated['pickup_date'] : null,
                ]);
            });

            return redirect()->route('admin.orders')
                ->with('success', "Order #{$order->order_number} updated successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Transition order status server-side strictly following the defined workflow.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|string',
        ]);

        $targetStatus = strtolower(trim($validated['status']));

        try {
            DB::transaction(function () use ($order, $targetStatus) {
                $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

                if (!$lockedOrder->canTransitionTo($targetStatus)) {
                    throw new \Exception("Invalid order status transition from '{$lockedOrder->order_status}' to '{$targetStatus}'.");
                }

                // If completing the order, ensure payment and sales records exist
                if ($targetStatus === Order::STATUS_COMPLETED) {
                    $lockedOrder->order_status = Order::STATUS_COMPLETED;
                    $lockedOrder->payment_status = 'paid';

                    // Ensure Sale record exists
                    if (!$lockedOrder->sale) {
                        Sale::create([
                            'order_id'       => $lockedOrder->id,
                            'user_id'        => Auth::id(),
                            'total'          => $lockedOrder->total,
                            'payment_method' => $lockedOrder->payment_method,
                            'payment_status' => 'paid',
                            'sold_at'        => now(),
                        ]);
                    }

                    // Ensure Payment record exists for outstanding balance
                    $alreadyPaidAmount = (float) $lockedOrder->payments()
                        ->where('payment_status', 'completed')
                        ->sum('amount');
                    $outstandingBalance = max(0.0, round((float) $lockedOrder->total - $alreadyPaidAmount, 2));

                    if ($outstandingBalance > 0) {
                        Payment::create([
                            'order_id'          => $lockedOrder->id,
                            'user_id'           => Auth::id(),
                            'payment_method'    => $lockedOrder->payment_method,
                            'amount'            => $outstandingBalance,
                            'payment_status'    => 'completed',
                            'payment_reference' => 'PAY-' . strtoupper(uniqid()),
                            'payment_date'      => now(),
                            'notes'             => "Order Completed by " . (Auth::user()->name ?? 'Staff'),
                        ]);
                    }

                    // Award loyalty points strictly if customer attached and not already awarded
                    // Note: Legacy orders with NULL are grandfathered and will not be re-awarded.
                    if ($lockedOrder->customer_id && $lockedOrder->loyalty_points_awarded === 0) {
                        $points = (int) floor($lockedOrder->total);
                        if ($points > 0) {
                            $lockedOrder->loyalty_points_awarded = $points;
                            $customer = Customer::lockForUpdate()->find($lockedOrder->customer_id);
                            if ($customer) {
                                $customer->addLoyaltyPoints($points);
                            }
                        }
                    }
                } else {
                    $lockedOrder->order_status = $targetStatus;
                }

                $lockedOrder->save();
            });

            // Dispatch notification for order status change
            try {
                $freshOrder = Order::find($order->id);
                if ($freshOrder) {
                    app(\App\Services\NotificationService::class)->notifyOrderStatusUpdated($freshOrder, $targetStatus);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch order status notification: " . $e->getMessage());
            }

            $humanStatus = ucwords(str_replace('_', ' ', $targetStatus));
            return redirect()->back()
                ->with('success', "Order #{$order->order_number} status updated to {$humanStatus}.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Cancel an active pending or preparing order and safely restore reserved stock.
     */
    public function cancel(Order $order)
    {
        if (!$order->canBeCancelled()) {
            return redirect()->back()
                ->with('error', "Order with status '{$order->order_status}' cannot be cancelled.");
        }

        try {
            DB::transaction(function () use ($order) {
                $lockedOrder = Order::lockForUpdate()->findOrFail($order->id);

                if (!$lockedOrder->canBeCancelled()) {
                    throw new \Exception("Order status changed concurrently. Cannot cancel '{$lockedOrder->order_status}' order.");
                }

                // Restore reserved inventory for each item
                foreach ($lockedOrder->items as $item) {
                    if ($item->product_id && $item->quantity > 0) {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->increment('stock', $item->quantity);
                        }
                    }
                }

                $lockedOrder->order_status = Order::STATUS_CANCELLED;
                $lockedOrder->save();
            });

            // Dispatch notification for order cancellation
            try {
                $freshOrder = Order::find($order->id);
                if ($freshOrder) {
                    app(\App\Services\NotificationService::class)->notifyOrderStatusUpdated($freshOrder, Order::STATUS_CANCELLED);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch order cancellation notification: " . $e->getMessage());
            }

            return redirect()->route('admin.orders')
                ->with('success', "Order #{$order->order_number} has been cancelled and reserved items restored to inventory.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Delete an order (Admin / Manager only, strictly non-completed orders).
     */
    public function destroy(Order $order, Request $request)
    {
        if ($order->isCompleted() || $order->sale) {
            $msg = "Cannot delete a completed order with financial sales records.";
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }


        try {
            DB::transaction(function () use ($order) {
                // If pending/preparing, restore inventory before deletion
                if ($order->canBeCancelled()) {
                    foreach ($order->items as $item) {
                        if ($item->product_id && $item->quantity > 0) {
                            $product = Product::lockForUpdate()->find($item->product_id);
                            if ($product) {
                                $product->increment('stock', $item->quantity);
                            }
                        }
                    }
                }

                $order->items()->delete();
                $order->payments()->delete();
                $order->delete();
            });

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => 'Order deleted successfully!']);
            }

            return redirect()->route('admin.orders')->with('success', 'Order deleted successfully!');
        } catch (\Exception $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    /**
     * Preview count of orders and associated records that match purge criteria.
     */
    public function purgePreview(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $scope = $request->get('scope', 'days');
        $days = (int) $request->get('days', 30);
        $status = $request->get('status', 'all');

        $query = Order::query();

        if ($scope === 'days') {
            $query->where('created_at', '<', now()->subDays($days));
        }

        if (!empty($status) && $status !== 'all') {
            $query->where('order_status', $status);
        }

        $orderIds = $query->pluck('id')->toArray();
        $orderCount = count($orderIds);

        $itemsCount = $orderCount > 0 ? OrderItem::whereIn('order_id', $orderIds)->count() : 0;
        $salesCount = $orderCount > 0 ? Sale::whereIn('order_id', $orderIds)->count() : 0;
        $paymentsCount = $orderCount > 0 ? Payment::whereIn('order_id', $orderIds)->count() : 0;
        $pendingCount = $orderCount > 0 ? Order::whereIn('id', $orderIds)->whereIn('order_status', ['pending', 'preparing', 'baking'])->count() : 0;

        return response()->json([
            'success' => true,
            'orders_count' => $orderCount,
            'items_count' => $itemsCount,
            'sales_count' => $salesCount,
            'payments_count' => $paymentsCount,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Bulk purge historical order data with data integrity, optional backup, and stock restoration.
     */
    public function purgeOld(Request $request)
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) {
            abort(403, 'Unauthorized. Only administrators can purge order records.');
        }

        $validated = $request->validate([
            'scope'          => 'required|in:all,days',
            'days'           => 'nullable|integer|min:0|max:3650',
            'status'         => 'nullable|string',
            'restore_stock'  => 'nullable|boolean',
            'backup'         => 'nullable|boolean',
            'confirm_phrase' => 'required|string',
        ]);

        if (strtoupper(trim($validated['confirm_phrase'])) !== 'CONFIRM') {
            return redirect()->back()->with('error', 'Confirmation failed. You must type "CONFIRM" to authorize order deletion.');
        }

        $all = $validated['scope'] === 'all';
        $days = $all ? null : (int) ($validated['days'] ?? 30);
        $status = $validated['status'] ?? 'all';
        $restoreStock = $request->boolean('restore_stock', true);
        $createBackup = $request->boolean('backup', true);

        $query = Order::query();

        if (!$all && $days !== null) {
            $query->where('created_at', '<', now()->subDays($days));
        }

        if (!empty($status) && $status !== 'all') {
            $query->where('order_status', $status);
        }

        $orderIds = $query->pluck('id')->toArray();
        $orderCount = count($orderIds);

        if ($orderCount === 0) {
            return redirect()->back()->with('error', 'No matching orders found to purge.');
        }

        // Backup to JSON if requested
        if ($createBackup) {
            try {
                $backupDir = storage_path('app/backups');
                File::ensureDirectoryExists($backupDir);
                $backupFile = $backupDir . DIRECTORY_SEPARATOR . 'orders_purge_backup_' . date('Y_m_d_His') . '.json';
                $backupData = Order::with(['items', 'sale', 'payments'])
                    ->whereIn('id', $orderIds)
                    ->get();
                File::put($backupFile, $backupData->toJson(JSON_PRETTY_PRINT));
            } catch (\Throwable $e) {
                // Log and continue
            }
        }

        $restoredItemsCount = 0;

        try {
            DB::transaction(function () use ($orderIds, $restoreStock, &$restoredItemsCount, $orderCount, $user, $all, $days, $status) {
                // Restore inventory if requested for active/uncompleted orders
                if ($restoreStock) {
                    $activeOrders = Order::with('items')
                        ->whereIn('id', $orderIds)
                        ->whereIn('order_status', ['pending', 'preparing', 'baking'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($activeOrders as $activeOrder) {
                        foreach ($activeOrder->items as $item) {
                            if ($item->product_id && $item->quantity > 0) {
                                $product = Product::lockForUpdate()->find($item->product_id);
                                if ($product) {
                                    $product->increment('stock', $item->quantity);
                                    $restoredItemsCount++;
                                }
                            }
                        }
                    }
                }

                // Explicit cascade deletion
                Payment::whereIn('order_id', $orderIds)->delete();
                Sale::whereIn('order_id', $orderIds)->delete();
                OrderItem::whereIn('order_id', $orderIds)->delete();
                Order::whereIn('id', $orderIds)->delete();

                // Audit log
                AuditLogService::log(
                    'orders_purged',
                    "Administrator {$user->name} purged {$orderCount} order records (Scope: " . ($all ? 'all' : ">{$days}d") . ", Status: {$status}).",
                    null,
                    [
                        'purged_by' => $user->id,
                        'orders_count' => $orderCount,
                        'restored_stock' => $restoreStock,
                        'restored_items_count' => $restoredItemsCount,
                        'scope' => $all ? 'all' : 'days',
                        'days' => $days,
                        'status_filter' => $status,
                    ]
                );
            });

            $msg = "Successfully purged {$orderCount} order(s) and associated sales/payment records.";
            if ($restoreStock && $restoredItemsCount > 0) {
                $msg .= " Restored inventory stock for {$restoredItemsCount} line item(s).";
            }

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => true, 'message' => $msg, 'purged_count' => $orderCount]);
            }

            return redirect()->route('admin.orders')->with('success', $msg);
        } catch (\Throwable $e) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Failed to purge orders: ' . $e->getMessage());
        }
    }
}

