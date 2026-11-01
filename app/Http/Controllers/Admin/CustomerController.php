<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with metrics, search, and filtering.
     */
    public function index(Request $request)
    {
        $query = Customer::withCount('orders');

        $like = \Illuminate\Support\Facades\DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        // Search by name, phone, or email
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search, $like) {
                $q->where('name', $like, "%{$search}%")
                  ->orWhere('phone', $like, "%{$search}%")
                  ->orWhere('email', $like, "%{$search}%");
            });
        }

        // Filter by loyalty tier
        if ($request->filled('tier') && $request->tier !== 'all') {
            $query->where('loyalty_tier', $request->tier);
        }

        // Filter by status
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $customers = $query->orderBy('id', 'desc')->get();

        // Calculate summary metrics
        $totalCustomers = Customer::count();
        $activeCustomers = Customer::where('status', 'active')->count();
        $loyaltyMembers = Customer::where('loyalty_points', '>', 0)->count();
        $totalCustomerRevenue = Order::whereNotNull('customer_id')
            ->where('order_status', 'completed')
            ->sum('total');

        return view('admin.customers.index', compact(
            'customers',
            'totalCustomers',
            'activeCustomers',
            'loyaltyMembers',
            'totalCustomerRevenue'
        ));
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255|unique:customers,email',
            'address'        => 'nullable|string|max:500',
            'loyalty_points' => 'nullable|integer|min:0',
            'status'         => 'required|in:active,inactive',
        ]);

        $points = (int) ($validated['loyalty_points'] ?? 0);
        $tier = Customer::calculateTier($points);

        $customer = Customer::create([
            'name'           => $validated['name'],
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'loyalty_points' => $points,
            'loyalty_tier'   => $tier,
            'status'         => $validated['status'],
        ]);

        return redirect()->route('admin.customers.index')
            ->with('success', "Customer '{$customer->name}' created successfully.");
    }

    /**
     * Display customer detail with full purchase history.
     */
    public function show(Request $request, Customer $customer)
    {
        $orders = $customer->orders()
            ->with(['items.product', 'sale', 'payments'])
            ->orderBy('id', 'desc')
            ->paginate(10);

        $totalSpent = $customer->total_spent;
        $totalOrders = $customer->total_orders_count;
        $avgOrderValue = $customer->average_order_value;
        $lastVisit = $customer->last_visit;

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'customer' => $customer,
                'totalSpent' => number_format($totalSpent, 2),
                'totalOrders' => $totalOrders,
                'avgOrderValue' => number_format($avgOrderValue, 2),
                'lastVisit' => $lastVisit ? $lastVisit->format('M d, Y') : 'Never',
                'orders' => $orders->map(function ($order) {
                    return [
                        'order_number' => $order->order_number,
                        'date' => $order->created_at->format('M d, Y h:i A'),
                        'total' => number_format($order->total, 2),
                        'status' => ucfirst($order->order_status ?? 'completed'),
                        'payment_method' => ucfirst($order->payment_method ?? 'cash'),
                        'items_count' => $order->items->count(),
                    ];
                }),
            ]);
        }

        // Dedicated page is removed; redirect directly to customer index
        return redirect()->route('admin.customers.index');
    }

    /**
     * Update customer profile.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'phone'          => 'nullable|string|max:50',
            'email'          => 'nullable|email|max:255|unique:customers,email,' . $customer->id,
            'address'        => 'nullable|string|max:500',
            'loyalty_points' => 'nullable|integer|min:0',
            'status'         => 'required|in:active,inactive',
        ]);

        $points = isset($validated['loyalty_points']) ? (int) $validated['loyalty_points'] : $customer->loyalty_points;
        $tier = Customer::calculateTier($points);

        $customer->update([
            'name'           => $validated['name'],
            'phone'          => $validated['phone'] ?? null,
            'email'          => $validated['email'] ?? null,
            'address'        => $validated['address'] ?? null,
            'loyalty_points' => $points,
            'loyalty_tier'   => $tier,
            'status'         => $validated['status'],
        ]);

        return redirect()->back()
            ->with('success', "Customer '{$customer->name}' updated successfully.");
    }

    /**
     * Delete customer. Orders will safely retain customer_id = null.
     */
    public function destroy(Customer $customer)
    {
        $name = $customer->name;
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('success', "Customer '{$name}' deleted successfully.");
    }
}
