<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    /**
     * Display the POS Terminal interface.
     */
    public function index()
    {
        $categories = Category::where('status', 'active')->orderBy('name')->get();
        $products = Product::with('category')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $currency = \App\Services\SettingsService::getCurrencySymbol();
        $bakeryName = Setting::get('bakery_name', Setting::get('shop_name', 'Sweet Delights Bakery'));
        $bakeryPhone = Setting::get('bakery_phone', Setting::get('shop_phone', '+855 23 123 456'));
        $bakeryAddress = Setting::get('bakery_address', Setting::get('shop_address', 'Monivong Blvd, Phnom Penh, Cambodia'));
        $receiptHeader = Setting::get('receipt_header', "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads");
        $receiptFooter = Setting::get('receipt_footer', "Thank you for visiting!\nFollow us @sweetdelights.bakery");

        return view('pos.index', compact(
            'categories',
            'products',
            'taxPercentage',
            'currency',
            'bakeryName',
            'bakeryPhone',
            'bakeryAddress',
            'receiptHeader',
            'receiptFooter'
        ));
    }

    /**
     * Search customers for POS live lookup.
     */
    public function searchCustomers(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (empty($q)) {
            $customers = Customer::where('status', 'active')->orderBy('name')->limit(10)->get();
        } else {
            $customers = Customer::where('status', 'active')
                ->where(function ($query) use ($q) {
                    $query->where('name', 'ilike', "%{$q}%")
                          ->orWhere('phone', 'ilike', "%{$q}%")
                          ->orWhere('email', 'ilike', "%{$q}%");
                })
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return response()->json([
            'success' => true,
            'customers' => $customers->map(function ($c) {
                return [
                    'id'             => $c->id,
                    'name'           => $c->name,
                    'phone'          => $c->phone ?? '',
                    'email'          => $c->email ?? '',
                    'loyalty_points' => (int) $c->loyalty_points,
                    'loyalty_tier'   => ucfirst($c->loyalty_tier),
                ];
            }),
        ]);
    }

    /**
     * Quick register customer from POS terminal.
     */
    public function quickStoreCustomer(Request $request)
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255|unique:customers,email',
        ]);

        $customer = Customer::create([
            'name'           => $validated['name'],
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'loyalty_points' => 0,
            'loyalty_tier'   => 'standard',
            'status'         => 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Customer '{$customer->name}' registered successfully.",
            'customer' => [
                'id'             => $customer->id,
                'name'           => $customer->name,
                'phone'          => $customer->phone ?? '',
                'email'          => $customer->email ?? '',
                'loyalty_points' => (int) $customer->loyalty_points,
                'loyalty_tier'   => ucfirst($customer->loyalty_tier),
            ],
        ]);
    }

    /**
     * Process POS Checkout / Complete Sale with Customer & Loyalty points.
     */
    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'customer_id'    => 'nullable|exists:customers,id',
            'customer_name'  => 'nullable|string|max:255',
            'payment_method' => 'required|string|in:cash,card,qr_code',
            'discount'       => 'nullable|numeric|min:0',
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        $userEnteredDiscount = max(0, (float) ($validated['discount'] ?? 0));
        $taxPercentage = (float) Setting::get('tax_percentage', Setting::get('tax_rate', 10.0));
        $taxRate = $taxPercentage / 100.0;
        $paymentMethod = $validated['payment_method'];

        try {
            $result = DB::transaction(function () use ($validated, $userEnteredDiscount, $taxRate, $paymentMethod) {
                // Fetch customer if customer_id provided
                $customer = null;
                if (!empty($validated['customer_id'])) {
                    $customer = Customer::lockForUpdate()->find($validated['customer_id']);
                }

                $customerName = $customer
                    ? $customer->name
                    : (trim($validated['customer_name'] ?? '') ?: 'Walk-in Customer');

                $subtotal = 0.0;
                $itemsToSave = [];
                $receiptItems = [];

                foreach ($validated['items'] as $itemData) {
                    $productId = (int) $itemData['product_id'];
                    $qty = (int) $itemData['quantity'];

                    // Lock row for pessimistic concurrency control
                    $product = Product::lockForUpdate()->find($productId);

                    if (!$product || $product->status !== 'active') {
                        throw new \Exception("Product '{$itemData['product_id']}' is unavailable or inactive.");
                    }

                    if ($product->stock < $qty) {
                        throw new \Exception("Insufficient stock for '{$product->name}'. Available: {$product->stock}, Requested: {$qty}.");
                    }

                    $unitPrice = (float) $product->price;
                    $lineTotal = round($unitPrice * $qty, 2);
                    $subtotal += $lineTotal;

                    // Reduce stock
                    $product->decrement('stock', $qty);

                    $itemsToSave[] = [
                        'product_id' => $product->id,
                        'quantity'   => $qty,
                        'price'      => $unitPrice,
                        'subtotal'   => $lineTotal,
                    ];

                    $receiptItems[] = [
                        'name'            => $product->name,
                        'quantity'        => $qty,
                        'price'           => $unitPrice,
                        'subtotal'        => $lineTotal,
                        'remaining_stock' => $product->stock,
                    ];
                }

                // Ensure discount does not exceed subtotal
                $discount = min($userEnteredDiscount, $subtotal);
                $taxableAmount = max(0.0, $subtotal - $discount);
                $tax = round($taxableAmount * $taxRate, 2);
                $grandTotal = round($taxableAmount + $tax, 2);

                $orderNumber = 'ORD-' . strtoupper(uniqid());
                $paymentRef  = 'PAY-' . strtoupper(uniqid());

                $pointsEarned = $customer ? (int) floor($grandTotal) : 0;

                // 1. Create Order with customer association
                $orderPayload = [
                    'user_id'        => Auth::id(),
                    'customer_id'    => $customer ? $customer->id : null,
                    'order_number'   => $orderNumber,
                    'customer_name'  => $customerName,
                    'subtotal'       => $subtotal,
                    'discount'       => $discount,
                    'tax'            => $tax,
                    'total'          => $grandTotal,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'order_status'   => 'completed',
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('orders', 'loyalty_points_awarded')) {
                    $orderPayload['loyalty_points_awarded'] = $pointsEarned;
                }
                $order = Order::create($orderPayload);

                // 2. Create Order Items
                foreach ($itemsToSave as $item) {
                    $order->items()->create($item);
                }

                // 3. Create Sale record
                $sale = Sale::create([
                    'order_id'       => $order->id,
                    'user_id'        => Auth::id(),
                    'total'          => $grandTotal,
                    'payment_method' => $paymentMethod,
                    'payment_status' => 'paid',
                    'sold_at'        => now(),
                ]);

                // 4. Create Payment record
                $payment = Payment::create([
                    'order_id'          => $order->id,
                    'user_id'           => Auth::id(),
                    'payment_method'    => $paymentMethod,
                    'amount'            => $grandTotal,
                    'payment_status'    => 'completed',
                    'payment_reference' => $paymentRef,
                    'payment_date'      => now(),
                    'notes'             => "POS In-Store Sale completed by " . (Auth::user()->name ?? 'Cashier'),
                ]);

                // 5. Award Customer Loyalty Points if customer attached
                $pointsEarned = 0;
                $newPointsBalance = 0;
                $loyaltyTier = 'standard';

                if ($customer) {
                    $pointsEarned = (int) floor($grandTotal);
                    $customer->addLoyaltyPoints($pointsEarned);
                    $newPointsBalance = $customer->loyalty_points;
                    $loyaltyTier = $customer->loyalty_tier;
                }

                return [
                    'order'              => $order,
                    'sale'               => $sale,
                    'payment'            => $payment,
                    'customer'           => $customer,
                    'customer_name'      => $customerName,
                    'points_earned'      => $pointsEarned,
                    'new_points_balance' => $newPointsBalance,
                    'loyalty_tier'       => $loyaltyTier,
                    'subtotal'           => $subtotal,
                    'discount'           => $discount,
                    'tax'                => $tax,
                    'grand_total'        => $grandTotal,
                    'receipt_items'      => $receiptItems,
                ];
            });

            // Dispatch notification for new POS order
            try {
                app(\App\Services\NotificationService::class)->notifyOrderCreated($result['order']);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to dispatch POS order notification: " . $e->getMessage());
            }

            $user = Auth::user();
            $receiptData = [
                'order_number'        => $result['order']->order_number,
                'sale_id'             => $result['sale']->id,
                'payment_ref'         => $result['payment']->payment_reference,
                'cashier_name'        => $user ? $user->name : 'Cashier',
                'customer_id'         => $result['customer'] ? $result['customer']->id : null,
                'customer_name'       => $result['customer_name'],
                'customer_phone'      => $result['customer'] ? ($result['customer']->phone ?? '') : null,
                'points_earned'       => $result['points_earned'],
                'loyalty_balance'     => $result['new_points_balance'],
                'loyalty_tier'        => ucfirst($result['loyalty_tier']),
                'date_time'           => now()->format('Y-m-d H:i:s'),
                'payment_method'      => strtoupper($paymentMethod === 'qr_code' ? 'KHQR' : $paymentMethod),
                'subtotal'            => number_format($result['subtotal'], 2),
                'discount'            => number_format($result['discount'], 2),
                'tax_percentage'      => $taxPercentage,
                'tax'                 => number_format($result['tax'], 2),
                'grand_total'         => number_format($result['grand_total'], 2),
                'currency'            => \App\Services\SettingsService::getCurrencySymbol(),
                'bakery_name'         => Setting::get('bakery_name', Setting::get('shop_name', 'Sweet Delights Bakery')),
                'bakery_phone'        => Setting::get('bakery_phone', Setting::get('shop_phone', '+855 23 123 456')),
                'bakery_address'      => Setting::get('bakery_address', Setting::get('shop_address', 'Monivong Blvd, Phnom Penh, Cambodia')),
                'receipt_header'      => Setting::get('receipt_header', "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads"),
                'receipt_footer'      => Setting::get('receipt_footer', "Thank you for visiting!\nFollow us @sweetdelights.bakery"),
                'items'               => $result['receipt_items'],
            ];

            return response()->json([
                'success' => true,
                'message' => "Sale #{$result['order']->order_number} completed successfully!",
                'receipt' => $receiptData,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
