<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Render the comprehensive Admin Analytics & Dashboard command center with real database aggregations.
     */
    public function index(Request $request)
    {
        // 1. Parse & Validate Date Range Filter
        $period = $request->get('period', '7days');
        $startDate = null;
        $endDate = Carbon::now()->endOfDay();

        switch ($period) {
            case 'today':
                $startDate = Carbon::today()->startOfDay();
                $endDate = Carbon::today()->endOfDay();
                break;
            case 'yesterday':
                $startDate = Carbon::yesterday()->startOfDay();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->startOfDay();
                break;
            case 'custom':
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    try {
                        $parsedStart = Carbon::parse($request->start_date)->startOfDay();
                        $parsedEnd = Carbon::parse($request->end_date)->endOfDay();
                        if ($parsedStart->lte($parsedEnd)) {
                            $startDate = $parsedStart;
                            $endDate = $parsedEnd;
                        } else {
                            $startDate = Carbon::now()->subDays(6)->startOfDay();
                            $period = '7days';
                        }
                    } catch (\Exception $e) {
                        $startDate = Carbon::now()->subDays(6)->startOfDay();
                        $period = '7days';
                    }
                } else {
                    $startDate = Carbon::now()->subDays(6)->startOfDay();
                    $period = '7days';
                }
                break;
            case '7days':
            default:
                $period = '7days';
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
        }

        // =====================================================================
        // 2. CORE KPI CARDS (Real Database Metrics)
        // =====================================================================

        // KPI 1: Today's Sales (Revenue) & Yesterday Comparison
        $todayRevenueSum = (float) Sale::whereDate('sold_at', today())->sum('total');
        $yesterdayRevenue = (float) Sale::whereDate('sold_at', today()->subDay())->sum('total');
        $salesGrowthPercent = null;
        if ($yesterdayRevenue > 0) {
            $salesGrowthPercent = round((($todayRevenueSum - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1);
        }

        // KPI 2: Today's Orders & Yesterday Comparison
        $todayOrdersCount = Order::whereDate('created_at', today())->count();
        $yesterdayOrdersCount = Order::whereDate('created_at', today()->subDay())->count();
        $ordersGrowthPercent = null;
        if ($yesterdayOrdersCount > 0) {
            $ordersGrowthPercent = round((($todayOrdersCount - $yesterdayOrdersCount) / $yesterdayOrdersCount) * 100, 1);
        }

        // KPI 3: Total Customers & New this Month
        $totalCustomersCount = Customer::count();
        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // KPI 4: Low Stock Ingredients & Out of Stock
        $lowStockIngredientsCount = Ingredient::whereColumn('quantity', '<=', 'minimum_quantity')->count();
        $outOfStockIngredientsCount = Ingredient::where('quantity', '<=', 0)->count();
        $lowStockCount = $lowStockIngredientsCount; // Backward compatibility for views & tests

        // KPI 5: Products
        $activeProductsCount = Product::where('status', 'active')->count();
        $totalProductsCount = Product::count();

        // KPI 6: Pending & Preparing Orders
        $pendingOrdersCount = Order::where('order_status', 'pending')->count();
        $preparingOrdersCount = Order::whereIn('order_status', ['preparing', 'baking'])->count();
        $readyOrdersCount = Order::whereIn('order_status', ['ready_for_pickup', 'ready'])->count();

        // KPI 7: In Progress / Scheduled Productions
        $activeProductionsCount = Production::whereIn('status', ['scheduled', 'in_progress'])->count();
        $completedProductionsCount = Production::where('status', 'completed')->count();
        $totalUnitsProduced = (float) Production::where('status', 'completed')->sum('quantity');

        // KPI 8: Pending Purchase Orders
        $pendingPosCount = PurchaseOrder::whereIn('status', ['draft', 'ordered'])->count();
        $orderedPosCount = PurchaseOrder::where('status', 'ordered')->count();
        $receivedPosCount = PurchaseOrder::where('status', 'received')->count();
        $totalProcurementSpend = (float) PurchaseOrder::where('status', 'received')->sum('total_cost');

        // =====================================================================
        // 3. SALES & REVENUE TIMELINE ANALYTICS (Continuous Daily Aggregates)
        // =====================================================================
        $salesAggregates = Sale::whereBetween('sold_at', [$startDate, $endDate])
            ->selectRaw('DATE(sold_at) as sale_date, SUM(total) as daily_revenue, COUNT(*) as daily_orders')
            ->groupBy('sale_date')
            ->orderBy('sale_date', 'asc')
            ->get()
            ->keyBy('sale_date');

        $chartLabels = [];
        $chartRevenues = [];
        $chartOrderCounts = [];
        $periodTotalRevenue = 0.0;
        $periodTotalOrders = 0;

        $cursor = $startDate->copy();
        while ($cursor->lte($endDate)) {
            $dateKey = $cursor->format('Y-m-d');
            $label = $cursor->format('M d');
            $chartLabels[] = $label;

            if (isset($salesAggregates[$dateKey])) {
                $rev = (float) $salesAggregates[$dateKey]->daily_revenue;
                $cnt = (int) $salesAggregates[$dateKey]->daily_orders;
            } else {
                $rev = 0.0;
                $cnt = 0;
            }

            $chartRevenues[] = $rev;
            $chartOrderCounts[] = $cnt;
            $periodTotalRevenue += $rev;
            $periodTotalOrders += $cnt;

            $cursor->addDay();
        }

        // =====================================================================
        // 4. BEST SELLING PRODUCTS (Completed Orders Only)
        // =====================================================================
        $topProducts = OrderItem::join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.order_status', 'completed')
            ->selectRaw('products.id, products.name, products.price, SUM(order_items.quantity) as total_sold, SUM(order_items.subtotal) as total_revenue')
            ->groupBy('products.id', 'products.name', 'products.price')
            ->orderByDesc('total_sold')
            ->limit(5)
            ->get();

        // Calculate maximum sold for proportional visual progress bars
        $maxSold = $topProducts->max('total_sold') ?: 1;

        // =====================================================================
        // 5. ORDER STATUS DISTRIBUTION (Real Counts)
        // =====================================================================
        $rawStatusCounts = Order::selectRaw('order_status, COUNT(*) as count')
            ->groupBy('order_status')
            ->pluck('count', 'order_status')
            ->toArray();

        $statusPending = (int) ($rawStatusCounts['pending'] ?? 0);
        $statusPreparing = (int) (($rawStatusCounts['preparing'] ?? 0) + ($rawStatusCounts['baking'] ?? 0));
        $statusReady = (int) (($rawStatusCounts['ready_for_pickup'] ?? 0) + ($rawStatusCounts['ready'] ?? 0));
        $statusCompleted = (int) ($rawStatusCounts['completed'] ?? 0);
        $statusCancelled = (int) ($rawStatusCounts['cancelled'] ?? 0);
        $totalAllOrders = $statusPending + $statusPreparing + $statusReady + $statusCompleted + $statusCancelled;

        // =====================================================================
        // 6. INVENTORY: LOW STOCK & EXPIRING INGREDIENTS
        // =====================================================================
        $lowStockIngredients = Ingredient::whereColumn('quantity', '<=', 'minimum_quantity')
            ->orderBy('quantity', 'asc')
            ->limit(6)
            ->get();

        $expiredIngredientsCount = Ingredient::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', today())
            ->count();

        $expiringSoonIngredients = Ingredient::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(7))
            ->orderBy('expiry_date', 'asc')
            ->limit(5)
            ->get();

        $recentStockMovements = StockMovement::with(['ingredient', 'user'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // =====================================================================
        // 7. CUSTOMER ANALYTICS
        // =====================================================================
        $vipCustomersCount = Customer::where('loyalty_tier', 'vip')->count();
        $totalLoyaltyPoints = (int) Customer::sum('loyalty_points');

        $topCustomers = Customer::whereHas('orders', function ($q) {
                $q->where('order_status', 'completed');
            })
            ->withSum(['orders' => function ($q) {
                $q->where('order_status', 'completed');
            }], 'total')
            ->withCount(['orders' => function ($q) {
                $q->where('order_status', 'completed');
            }])
            ->orderByDesc('orders_sum_total')
            ->limit(5)
            ->get();

        // =====================================================================
        // 8. PROCUREMENT & SUPPLIER METRICS
        // =====================================================================
        $suppliersCount = Supplier::where('status', 'active')->count();
        $recentPurchaseOrders = PurchaseOrder::with('supplier')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        // =====================================================================
        // 9. RECENT ORDERS TABLE
        // =====================================================================
        $recentOrders = Order::with(['user', 'customer'])
            ->orderBy('id', 'desc')
            ->limit(6)
            ->get();

        // Settings
        $currency = Setting::get('currency', '$');
        $bakeryName = Setting::get('bakery_name', 'Sweet Delights Bakery');

        return view('admin.dashboard', compact(
            'period',
            'startDate',
            'endDate',
            'todayRevenueSum',
            'salesGrowthPercent',
            'todayOrdersCount',
            'ordersGrowthPercent',
            'totalCustomersCount',
            'newCustomersThisMonth',
            'lowStockIngredientsCount',
            'outOfStockIngredientsCount',
            'lowStockCount',
            'activeProductsCount',
            'totalProductsCount',
            'pendingOrdersCount',
            'preparingOrdersCount',
            'readyOrdersCount',
            'activeProductionsCount',
            'completedProductionsCount',
            'totalUnitsProduced',
            'pendingPosCount',
            'orderedPosCount',
            'receivedPosCount',
            'totalProcurementSpend',
            'chartLabels',
            'chartRevenues',
            'chartOrderCounts',
            'periodTotalRevenue',
            'periodTotalOrders',
            'topProducts',
            'maxSold',
            'statusPending',
            'statusPreparing',
            'statusReady',
            'statusCompleted',
            'statusCancelled',
            'totalAllOrders',
            'lowStockIngredients',
            'expiredIngredientsCount',
            'expiringSoonIngredients',
            'recentStockMovements',
            'vipCustomersCount',
            'totalLoyaltyPoints',
            'topCustomers',
            'suppliersCount',
            'recentPurchaseOrders',
            'recentOrders',
            'currency',
            'bakeryName'
        ));
    }
}
