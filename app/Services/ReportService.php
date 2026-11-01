<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Parse and validate report date range from request parameters.
     */
    public function parseDateRange(Request $request): array
    {
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
            case '7days':
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
            case '30days':
                $startDate = Carbon::now()->subDays(29)->startOfDay();
                break;
            case 'this_month':
                $startDate = Carbon::now()->startOfMonth()->startOfDay();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth()->startOfDay();
                $endDate = Carbon::now()->subMonth()->endOfMonth()->endOfDay();
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
                            // Start date after end date: safely fall back to 7 days
                            $period = '7days';
                            $startDate = Carbon::now()->subDays(6)->startOfDay();
                        }
                    } catch (\Exception $e) {
                        $period = '7days';
                        $startDate = Carbon::now()->subDays(6)->startOfDay();
                    }
                } else {
                    $period = '7days';
                    $startDate = Carbon::now()->subDays(6)->startOfDay();
                }
                break;
            default:
                $period = '7days';
                $startDate = Carbon::now()->subDays(6)->startOfDay();
                break;
        }

        return [
            'period'    => $period,
            'startDate' => $startDate,
            'endDate'   => $endDate,
        ];
    }

    /**
     * 1. SALES REPORT
     */
    public function getSalesReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        $baseQuery = Sale::with(['order.customer', 'order.items.product', 'user'])
            ->whereBetween('sold_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->whereDoesntHave('order')
                  ->orWhereHas('order', function ($oq) {
                      $oq->where('order_status', '!=', Order::STATUS_CANCELLED);
                  });
            });

        // Filter: Payment Method
        if ($request->filled('payment_method')) {
            $baseQuery->where('sales.payment_method', $request->payment_method);
        }

        // Filter: Payment Status
        if ($request->filled('payment_status')) {
            $baseQuery->where('sales.payment_status', $request->payment_status);
        }

        // Filter: Search (Sale ID, Order Number, Customer Name)
        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('sales.id', 'like', $term)
                  ->orWhereHas('order', function ($oq) use ($term) {
                      $oq->where('order_number', 'like', $term)
                         ->orWhere('customer_name', 'like', $term);
                  });
            });
        }

        // Real Aggregations
        $totalSalesRevenue = (float) (clone $baseQuery)->sum('sales.total');
        $totalSalesCount = (int) (clone $baseQuery)->count();
        $avgSaleValue = $totalSalesCount > 0 ? ($totalSalesRevenue / $totalSalesCount) : 0.0;

        // Payment Method Breakdown
        $paymentMethods = (clone $baseQuery)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total_amount')
            ->groupBy('payment_method')
            ->get();

        // Linked order totals for taxes and discounts
        $saleIds = (clone $baseQuery)->pluck('order_id');
        $totalDiscount = (float) Order::whereIn('id', $saleIds)->sum('discount');
        $totalTax = (float) Order::whereIn('id', $saleIds)->sum('tax');
        $totalSubtotal = (float) Order::whereIn('id', $saleIds)->sum('subtotal');
        $netSales = $totalSubtotal > 0 ? $totalSubtotal : ($totalSalesRevenue - $totalTax);

        $records = $paginate
            ? $baseQuery->orderByDesc('sold_at')->paginate(15)->withQueryString()
            : $baseQuery->orderByDesc('sold_at')->get();

        return [
            'totalSalesRevenue' => $totalSalesRevenue,
            'totalSalesCount'   => $totalSalesCount,
            'avgSaleValue'      => $avgSaleValue,
            'totalDiscount'     => $totalDiscount,
            'totalTax'          => $totalTax,
            'netSales'          => $netSales,
            'paymentMethods'    => $paymentMethods,
            'records'           => $records,
        ];
    }

    /**
     * 2. REVENUE REPORT
     */
    public function getRevenueReport(Carbon $startDate, Carbon $endDate): array
    {
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
            $chartLabels[] = $cursor->format('M d');

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

        $avgOrderValue = $periodTotalOrders > 0 ? ($periodTotalRevenue / $periodTotalOrders) : 0.0;

        // Payment Channel Breakdown in Selected Period (case-insensitive for SQLite/MySQL uniformity)
        $cashRevenue = (float) Sale::whereBetween('sold_at', [$startDate, $endDate])->whereRaw('LOWER(payment_method) = ?', ['cash'])->sum('total');
        $cardRevenue = (float) Sale::whereBetween('sold_at', [$startDate, $endDate])->whereRaw('LOWER(payment_method) = ?', ['card'])->sum('total');
        $qrRevenue   = (float) Sale::whereBetween('sold_at', [$startDate, $endDate])->whereRaw('LOWER(payment_method) = ?', ['qr_code'])->sum('total');

        // Revenue Growth vs Previous Period of Equal Length
        $dayCount = max(1, $startDate->diffInDays($endDate) + 1);
        $prevStart = $startDate->copy()->subDays($dayCount);
        $prevEnd = $startDate->copy()->subSecond();
        $prevRevenue = (float) Sale::whereBetween('sold_at', [$prevStart, $prevEnd])->sum('total');
        $revenueGrowthPercent = null;
        if ($prevRevenue > 0) {
            $revenueGrowthPercent = round((($periodTotalRevenue - $prevRevenue) / $prevRevenue) * 100, 1);
        }

        // Daily breakdown table records
        $dailyRecords = [];
        $cursor = $endDate->copy();
        while ($cursor->gte($startDate)) {
            $dateKey = $cursor->format('Y-m-d');
            $rev = isset($salesAggregates[$dateKey]) ? (float) $salesAggregates[$dateKey]->daily_revenue : 0.0;
            $cnt = isset($salesAggregates[$dateKey]) ? (int) $salesAggregates[$dateKey]->daily_orders : 0;
            $dailyRecords[] = [
                'date'        => $dateKey,
                'formatted'   => $cursor->format('D, M d, Y'),
                'orders'      => $cnt,
                'revenue'     => $rev,
                'avg_value'   => $cnt > 0 ? ($rev / $cnt) : 0.0,
            ];
            $cursor->subDay();
        }

        return [
            'totalRevenue'          => $periodTotalRevenue,
            'totalOrders'           => $periodTotalOrders,
            'avgOrderValue'         => $avgOrderValue,
            'cashRevenue'           => $cashRevenue,
            'cardRevenue'           => $cardRevenue,
            'qrRevenue'             => $qrRevenue,
            'revenueGrowthPercent'  => $revenueGrowthPercent,
            'prevRevenue'           => $prevRevenue,
            'chartLabels'           => $chartLabels,
            'chartRevenues'         => $chartRevenues,
            'chartOrderCounts'      => $chartOrderCounts,
            'dailyRecords'          => $dailyRecords,
        ];
    }

    /**
     * 3. ORDER REPORT
     */
    public function getOrderReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        $baseQuery = Order::with(['customer', 'user', 'items.product'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->filled('order_status')) {
            $baseQuery->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $baseQuery->where('payment_status', $request->payment_status);
        }

        if ($request->filled('is_custom')) {
            $baseQuery->where('is_custom', $request->is_custom === '1');
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('order_number', 'like', $term)
                  ->orWhere('customer_name', 'like', $term);
            });
        }

        $allPeriodOrders = Order::whereBetween('created_at', [$startDate, $endDate]);
        $totalOrders = (int) (clone $allPeriodOrders)->count();
        $completedOrders = (int) (clone $allPeriodOrders)->where('order_status', Order::STATUS_COMPLETED)->count();
        $pendingOrders = (int) (clone $allPeriodOrders)->where('order_status', Order::STATUS_PENDING)->count();
        $preparingOrders = (int) (clone $allPeriodOrders)->whereIn('order_status', [Order::STATUS_PREPARING, 'baking'])->count();
        $readyOrders = (int) (clone $allPeriodOrders)->whereIn('order_status', [Order::STATUS_READY_FOR_PICKUP, 'ready'])->count();
        $cancelledOrders = (int) (clone $allPeriodOrders)->where('order_status', Order::STATUS_CANCELLED)->count();
        $customOrders = (int) (clone $allPeriodOrders)->where('is_custom', true)->count();

        $completionRate = $totalOrders > 0 ? round(($completedOrders / $totalOrders) * 100, 1) : 0.0;
        $cancellationRate = $totalOrders > 0 ? round(($cancelledOrders / $totalOrders) * 100, 1) : 0.0;
        $totalOrderAmount = (float) (clone $allPeriodOrders)->where('order_status', '!=', Order::STATUS_CANCELLED)->sum('total');

        $records = $paginate
            ? $baseQuery->orderByDesc('created_at')->paginate(15)->withQueryString()
            : $baseQuery->orderByDesc('created_at')->get();

        return [
            'totalOrders'       => $totalOrders,
            'completedOrders'   => $completedOrders,
            'pendingOrders'     => $pendingOrders,
            'preparingOrders'   => $preparingOrders,
            'readyOrders'       => $readyOrders,
            'cancelledOrders'   => $cancelledOrders,
            'customOrders'      => $customOrders,
            'completionRate'    => $completionRate,
            'cancellationRate'  => $cancellationRate,
            'totalOrderAmount'  => $totalOrderAmount,
            'records'           => $records,
        ];
    }

    /**
     * 4. PRODUCT / BEST SELLER REPORT
     */
    public function getProductReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        // Completed orders in timeframe (prioritizing sale sold_at, fallback to created_at)
        $completedOrdersBase = Order::where('order_status', Order::STATUS_COMPLETED)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereHas('sale', function ($sq) use ($startDate, $endDate) {
                    $sq->whereBetween('sold_at', [$startDate, $endDate]);
                })->orWhere(function ($oq) use ($startDate, $endDate) {
                    $oq->doesntHave('sale')->whereBetween('created_at', [$startDate, $endDate]);
                });
            });

        $completedOrderIds = (clone $completedOrdersBase)->pluck('id');

        $query = OrderItem::whereIn('order_items.order_id', $completedOrderIds)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                products.id as product_id,
                products.name as product_name,
                products.sku,
                products.price as unit_price,
                products.cost as unit_cost,
                categories.name as category_name,
                SUM(order_items.quantity) as total_units_sold,
                SUM(order_items.subtotal) as total_revenue,
                COUNT(DISTINCT order_items.order_id) as total_orders_count
            ')
            ->groupBy(
                'products.id',
                'products.name',
                'products.sku',
                'products.price',
                'products.cost',
                'categories.name'
            );

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $query->where(function ($q) use ($term) {
                $q->where('products.name', 'like', $term)
                  ->orWhere('products.sku', 'like', $term);
            });
        }

        // Sorting
        $sort = $request->get('sort', 'units');
        if ($sort === 'revenue') {
            $query->orderByDesc('total_revenue');
        } else {
            $query->orderByDesc('total_units_sold');
        }

        $allCompletedItems = OrderItem::whereIn('order_items.order_id', $completedOrderIds);

        $totalUnitsSoldAll = (float) (clone $allCompletedItems)->sum('order_items.quantity');
        $totalRevenueAll = (float) (clone $allCompletedItems)->sum('order_items.subtotal');
        $totalDistinctProducts = (int) (clone $allCompletedItems)->distinct('order_items.product_id')->count('order_items.product_id');

        $top5 = (clone $query)->limit(5)->get();

        $records = $paginate
            ? $query->paginate(15)->withQueryString()
            : $query->get();

        return [
            'totalUnitsSold'        => $totalUnitsSoldAll,
            'totalRevenue'          => $totalRevenueAll,
            'totalDistinctProducts' => $totalDistinctProducts,
            'top5'                  => $top5,
            'records'               => $records,
        ];
    }

    /**
     * 5. INVENTORY REPORT
     */
    public function getInventoryReport(Request $request, bool $paginate = true): array
    {
        $baseQuery = Ingredient::with('supplier');

        if ($request->filled('status')) {
            if ($request->status === 'low_stock') {
                $baseQuery->whereColumn('quantity', '<=', 'minimum_quantity')->where('quantity', '>', 0);
            } elseif ($request->status === 'out_of_stock') {
                $baseQuery->where('quantity', '<=', 0);
            } elseif ($request->status === 'expired') {
                $baseQuery->whereNotNull('expiry_date')->whereDate('expiry_date', '<', today());
            } elseif ($request->status === 'expiring_soon') {
                $baseQuery->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', '>=', today())
                    ->whereDate('expiry_date', '<=', today()->addDays(7));
            }
        }

        if ($request->filled('supplier_id')) {
            $baseQuery->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where('name', 'like', $term);
        }

        $totalIngredients = Ingredient::count();
        $totalQuantitySum = (float) Ingredient::sum('quantity');
        $lowStockCount = Ingredient::whereColumn('quantity', '<=', 'minimum_quantity')->where('quantity', '>', 0)->count();
        $outOfStockCount = Ingredient::where('quantity', '<=', 0)->count();
        $expiredCount = Ingredient::whereNotNull('expiry_date')->whereDate('expiry_date', '<', today())->count();
        $expiringSoonCount = Ingredient::whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', today())
            ->whereDate('expiry_date', '<=', today()->addDays(7))
            ->count();

        // Accurate total inventory valuation: SUM(quantity * cost)
        $totalInventoryValue = (float) (Ingredient::selectRaw('SUM(quantity * cost) as val')->value('val') ?? 0.0);

        $records = $paginate
            ? $baseQuery->orderBy('name', 'asc')->paginate(15)->withQueryString()
            : $baseQuery->orderBy('name', 'asc')->get();

        return [
            'totalIngredients'    => $totalIngredients,
            'totalQuantitySum'    => $totalQuantitySum,
            'lowStockCount'       => $lowStockCount,
            'outOfStockCount'     => $outOfStockCount,
            'expiredCount'        => $expiredCount,
            'expiringSoonCount'   => $expiringSoonCount,
            'totalInventoryValue' => $totalInventoryValue,
            'records'             => $records,
        ];
    }

    /**
     * 6. LOW STOCK REPORT
     */
    public function getLowStockReport(Request $request, bool $paginate = true): array
    {
        $baseQuery = Ingredient::with('supplier')
            ->whereColumn('quantity', '<=', 'minimum_quantity');

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where('name', 'like', $term);
        }

        $outOfStockCount = Ingredient::where('quantity', '<=', 0)->count();
        $lowStockCount = Ingredient::whereColumn('quantity', '<=', 'minimum_quantity')->where('quantity', '>', 0)->count();
        $totalNeedingReorder = $outOfStockCount + $lowStockCount;

        // Estimated replenishment cost: SUM((minimum_quantity - quantity) * cost) where quantity < minimum_quantity
        $estimatedRestockCost = (float) (Ingredient::whereColumn('quantity', '<', 'minimum_quantity')
            ->selectRaw('SUM((minimum_quantity - quantity) * cost) as restock_cost')
            ->value('restock_cost') ?? 0.0);

        $records = $paginate
            ? $baseQuery->orderBy('quantity', 'asc')->paginate(15)->withQueryString()
            : $baseQuery->orderBy('quantity', 'asc')->get();

        return [
            'totalNeedingReorder'   => $totalNeedingReorder,
            'outOfStockCount'       => $outOfStockCount,
            'lowStockCount'         => $lowStockCount,
            'estimatedRestockCost'  => $estimatedRestockCost,
            'records'               => $records,
        ];
    }

    /**
     * 7. PRODUCTION REPORT
     */
    public function getProductionReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        $baseQuery = Production::with(['product', 'recipe', 'baker', 'creator'])
            ->whereBetween('production_date', [$startDate, $endDate]);

        if ($request->filled('status')) {
            $baseQuery->where('status', $request->status);
        }

        if ($request->filled('product_id')) {
            $baseQuery->where('product_id', $request->product_id);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('batch_number', 'like', $term)
                  ->orWhereHas('product', function ($pq) use ($term) {
                      $pq->where('name', 'like', $term);
                  });
            });
        }

        $allPeriodProductions = Production::whereBetween('production_date', [$startDate, $endDate]);
        $totalBatches = (int) (clone $allPeriodProductions)->count();
        $scheduledBatches = (int) (clone $allPeriodProductions)->where('status', 'scheduled')->count();
        $inProgressBatches = (int) (clone $allPeriodProductions)->where('status', 'in_progress')->count();
        $completedBatches = (int) (clone $allPeriodProductions)->where('status', 'completed')->count();
        $cancelledBatches = (int) (clone $allPeriodProductions)->where('status', 'cancelled')->count();
        $totalUnitsProduced = (float) (clone $allPeriodProductions)->where('status', 'completed')->sum('quantity');

        $completionRate = $totalBatches > 0 ? round(($completedBatches / $totalBatches) * 100, 1) : 0.0;

        $records = $paginate
            ? $baseQuery->orderByDesc('production_date')->paginate(15)->withQueryString()
            : $baseQuery->orderByDesc('production_date')->get();

        return [
            'totalBatches'        => $totalBatches,
            'scheduledBatches'    => $scheduledBatches,
            'inProgressBatches'   => $inProgressBatches,
            'completedBatches'    => $completedBatches,
            'cancelledBatches'    => $cancelledBatches,
            'totalUnitsProduced'  => $totalUnitsProduced,
            'completionRate'      => $completionRate,
            'records'             => $records,
        ];
    }

    /**
     * 8. CUSTOMER REPORT
     */
    public function getCustomerReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        $baseQuery = Customer::withCount(['orders' => function ($q) use ($startDate, $endDate) {
                $q->where('order_status', Order::STATUS_COMPLETED)
                  ->whereBetween('created_at', [$startDate, $endDate]);
            }])
            ->withSum(['orders' => function ($q) use ($startDate, $endDate) {
                $q->where('order_status', Order::STATUS_COMPLETED)
                  ->whereBetween('created_at', [$startDate, $endDate]);
            }], 'total');

        if ($request->filled('tier')) {
            $baseQuery->where('loyalty_tier', $request->tier);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                  ->orWhere('phone', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        }

        $totalCustomers = Customer::count();
        $newCustomersPeriod = Customer::whereBetween('created_at', [$startDate, $endDate])->count();
        
        // Customers with at least one completed order in period
        $activeCustomersCount = Customer::whereHas('orders', function ($q) use ($startDate, $endDate) {
            $q->where('order_status', Order::STATUS_COMPLETED)
              ->whereBetween('created_at', [$startDate, $endDate]);
        })->count();

        $totalCustomerSpend = (float) Order::where('order_status', Order::STATUS_COMPLETED)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('customer_id')
            ->sum('total');

        $avgCustomerSpend = $activeCustomersCount > 0 ? ($totalCustomerSpend / $activeCustomersCount) : 0.0;

        $topCustomers = (clone $baseQuery)->orderByDesc('orders_sum_total')->limit(5)->get();

        $records = $paginate
            ? $baseQuery->orderByDesc('orders_sum_total')->paginate(15)->withQueryString()
            : $baseQuery->orderByDesc('orders_sum_total')->get();

        return [
            'totalCustomers'       => $totalCustomers,
            'newCustomersPeriod'   => $newCustomersPeriod,
            'activeCustomersCount' => $activeCustomersCount,
            'totalCustomerSpend'   => $totalCustomerSpend,
            'avgCustomerSpend'     => $avgCustomerSpend,
            'topCustomers'         => $topCustomers,
            'records'              => $records,
        ];
    }

    /**
     * 9. SUPPLIER / PURCHASE REPORT
     */
    public function getSupplierPurchaseReport(Carbon $startDate, Carbon $endDate, Request $request, bool $paginate = true): array
    {
        $baseQuery = PurchaseOrder::with(['supplier', 'user'])
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($request->filled('status')) {
            $baseQuery->where('status', $request->status);
        }

        if ($request->filled('supplier_id')) {
            $baseQuery->where('supplier_id', $request->supplier_id);
        }

        if ($request->filled('search')) {
            $term = '%' . trim($request->search) . '%';
            $baseQuery->where(function ($q) use ($term) {
                $q->where('po_number', 'like', $term)
                  ->orWhereHas('supplier', function ($sq) use ($term) {
                      $sq->where('name', 'like', $term);
                  });
            });
        }

        $allPeriodPos = PurchaseOrder::whereBetween('order_date', [$startDate, $endDate]);
        $totalSuppliers = Supplier::count();
        $totalPos = (int) (clone $allPeriodPos)->count();
        $draftPos = (int) (clone $allPeriodPos)->where('status', 'draft')->count();
        $orderedPos = (int) (clone $allPeriodPos)->where('status', 'ordered')->count();
        $receivedPos = (int) (clone $allPeriodPos)->where('status', 'received')->count();
        $cancelledPos = (int) (clone $allPeriodPos)->where('status', 'cancelled')->count();

        // SPEND RULE: As defined by the project business rules, ONLY received purchase orders represent actual expenditure!
        $totalPurchaseSpend = (float) (clone $allPeriodPos)->where('status', 'received')->sum('total_cost');
        $avgPoValue = $receivedPos > 0 ? ($totalPurchaseSpend / $receivedPos) : 0.0;

        $records = $paginate
            ? $baseQuery->orderByDesc('order_date')->paginate(15)->withQueryString()
            : $baseQuery->orderByDesc('order_date')->get();

        return [
            'totalSuppliers'     => $totalSuppliers,
            'totalPos'           => $totalPos,
            'draftPos'           => $draftPos,
            'orderedPos'         => $orderedPos,
            'receivedPos'        => $receivedPos,
            'cancelledPos'       => $cancelledPos,
            'totalPurchaseSpend' => $totalPurchaseSpend,
            'avgPoValue'         => $avgPoValue,
            'records'            => $records,
        ];
    }

    /**
     * 10. PROFIT & LOSS / COST ANALYSIS
     */
    public function getProfitLossReport(Carbon $startDate, Carbon $endDate): array
    {
        // 1. Identify completed orders in the target timeframe:
        // Prioritizes sale transaction date (sold_at) when a Sale record exists, falling back to created_at
        $completedOrdersBase = Order::where('order_status', Order::STATUS_COMPLETED)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereHas('sale', function ($sq) use ($startDate, $endDate) {
                    $sq->whereBetween('sold_at', [$startDate, $endDate]);
                })->orWhere(function ($oq) use ($startDate, $endDate) {
                    $oq->doesntHave('sale')->whereBetween('created_at', [$startDate, $endDate]);
                });
            });

        $completedOrderIds = (clone $completedOrdersBase)->pluck('id');

        // Sales Revenue from Completed Orders
        $salesRevenue = (float) (clone $completedOrdersBase)->sum('total');

        // 2. Direct Product Cost of Goods Sold (COGS)
        // Calculated strictly from completed order items: SUM(order_items.quantity * products.cost)
        $productCogs = (float) (OrderItem::whereIn('order_items.order_id', $completedOrderIds)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->selectRaw('SUM(order_items.quantity * products.cost) as total_cogs')
            ->value('total_cogs') ?? 0.0);

        // 3. Procurement / Received Purchase Orders in Period (Raw Inventory Acquisition Cash Outflow)
        // Kept separate from COGS to strictly prevent double counting
        $procurementSpend = (float) PurchaseOrder::where('status', 'received')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->sum('total_cost');

        // 4. Gross Profit & Margins
        // Gross Profit = Sales Revenue - Cost of Goods Sold
        $grossProfit = $salesRevenue - $productCogs;
        $profitMargin = $salesRevenue > 0 ? round(($grossProfit / $salesRevenue) * 100, 1) : 0.0;

        // 5. Units Sold & Average Gross Profit per Unit
        $totalUnitsSold = (float) OrderItem::whereIn('order_items.order_id', $completedOrderIds)->sum('quantity');
        $avgProfitPerUnit = $totalUnitsSold > 0 ? ($grossProfit / $totalUnitsSold) : 0.0;

        // 6. Breakdown by Product Category
        $categoryBreakdown = OrderItem::whereIn('order_items.order_id', $completedOrderIds)
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->selectRaw('
                COALESCE(categories.name, \'Uncategorized\') as category_name,
                SUM(order_items.subtotal) as cat_revenue,
                SUM(order_items.quantity * products.cost) as cat_cogs,
                SUM(order_items.subtotal - (order_items.quantity * products.cost)) as cat_profit
            ')
            ->groupBy('categories.name')
            ->orderByDesc('cat_profit')
            ->get();

        return [
            'salesRevenue'       => $salesRevenue,
            'productCogs'        => $productCogs,
            'procurementSpend'   => $procurementSpend,
            'grossProfit'        => $grossProfit,
            'profitMargin'       => $profitMargin,
            'totalUnitsSold'     => $totalUnitsSold,
            'avgProfitPerUnit'   => $avgProfitPerUnit,
            'categoryBreakdown'  => $categoryBreakdown,
        ];
    }
}
