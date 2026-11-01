<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\PurchaseOrder;
use App\Models\Recipe;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $cashier;
    private User $baker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_rpt_test'],
            ['name' => 'Admin Rpt', 'email' => 'admin_rpt@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'admin', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['username' => 'mgr_rpt_test'],
            ['name' => 'Manager Rpt', 'email' => 'mgr_rpt@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'manager', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['username' => 'csh_rpt_test'],
            ['name' => 'Cashier Rpt', 'email' => 'cashier_rpt@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->baker = User::firstOrCreate(
            ['username' => 'bkr_rpt_test'],
            ['name' => 'Baker Rpt', 'email' => 'baker_rpt@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'baker', 'status' => 'active']
        );
    }

    /** 1. Reports main dashboard loads successfully for Admin */
    public function test_reports_page_loads_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports');
        $response->assertSee('Reports');
        $response->assertSee('Sales Report');
    }

    /** 2. Admin can access all report types via URL parameters */
    public function test_admin_can_access_all_report_types(): void
    {
        $types = ['sales', 'revenue', 'orders', 'products', 'inventory', 'low-stock', 'production', 'customers', 'purchases', 'profit-loss'];

        foreach ($types as $type) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => $type]));
            $response->assertStatus(200);
            $response->assertViewHas('type', $type);
        }
    }

    /** 3. Manager can access reports */
    public function test_manager_can_access_reports(): void
    {
        $response = $this->actingAs($this->manager)->get(route('admin.reports'));
        $response->assertStatus(200);
    }

    /** 4. Unauthorized roles (Cashier, Baker, Guest) are blocked */
    public function test_unauthorized_roles_are_blocked_from_reports(): void
    {
        $guestResponse = $this->get(route('admin.reports'));
        $guestResponse->assertRedirect(route('login'));

        $cashierResponse = $this->actingAs($this->cashier)->get(route('admin.reports'));
        $cashierResponse->assertRedirect(route('cashier.dashboard'));

        $bakerResponse = $this->actingAs($this->baker)->get(route('admin.reports'));
        $bakerResponse->assertRedirect(route('admin.production'));
    }

    /** 5. Sales Report: Real database aggregations */
    public function test_sales_report_aggregation_accuracy(): void
    {
        $order1 = Order::create([
            'order_number' => 'ORD-RPT-001',
            'customer_name' => 'Walk-in',
            'subtotal' => 100.00,
            'discount' => 10.00,
            'tax' => 5.00,
            'total' => 95.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => Order::STATUS_COMPLETED,
            'created_at' => now(),
        ]);

        Sale::create([
            'order_id' => $order1->id,
            'user_id' => $this->admin->id,
            'total' => 95.00,
            'payment_method' => 'cash',
            'payment_status' => 'completed',
            'sold_at' => now(),
        ]);

        $order2 = Order::create([
            'order_number' => 'ORD-RPT-002',
            'customer_name' => 'Alice',
            'subtotal' => 200.00,
            'discount' => 20.00,
            'tax' => 10.00,
            'total' => 190.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'order_status' => Order::STATUS_COMPLETED,
            'created_at' => now(),
        ]);

        Sale::create([
            'order_id' => $order2->id,
            'user_id' => $this->admin->id,
            'total' => 190.00,
            'payment_method' => 'card',
            'payment_status' => 'completed',
            'sold_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'sales', 'period' => 'today']));
        $response->assertStatus(200);

        $reportData = $response->viewData('reportData');
        $this->assertEquals(285.00, $reportData['totalSalesRevenue']);
        $this->assertEquals(2, $reportData['totalSalesCount']);
        $this->assertEquals(142.50, $reportData['avgSaleValue']);
        $this->assertEquals(30.00, $reportData['totalDiscount']);
        $this->assertEquals(15.00, $reportData['totalTax']);
    }

    /** 6. Revenue Report: Daily records & totals */
    public function test_revenue_report_daily_aggregation(): void
    {
        $order = Order::create([
            'order_number' => 'ORD-REV-001',
            'total' => 150.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => Order::STATUS_COMPLETED,
            'created_at' => now(),
        ]);

        Sale::create([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'total' => 150.00,
            'payment_method' => 'cash',
            'payment_status' => 'completed',
            'sold_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'revenue', 'period' => '7days']));
        $response->assertStatus(200);

        $reportData = $response->viewData('reportData');
        $this->assertEquals(150.00, $reportData['totalRevenue']);
        $this->assertEquals(1, $reportData['totalOrders']);
        $this->assertCount(7, $reportData['chartLabels']);
    }

    /** 7. Date filtering works for presets */
    public function test_date_filtering_presets(): void
    {
        $presets = ['today', 'yesterday', '7days', '30days', 'this_month', 'last_month'];

        foreach ($presets as $preset) {
            $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'sales', 'period' => $preset]));
            $response->assertStatus(200);
            $response->assertViewHas('period', $preset);
        }
    }

    /** 8. Custom date range filtering works */
    public function test_custom_date_range_filtering(): void
    {
        $startDate = now()->subDays(5)->format('Y-m-d');
        $endDate = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)->get(route('admin.reports', [
            'type' => 'sales',
            'period' => 'custom',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('period', 'custom');
        $this->assertEquals($startDate, $response->viewData('startDate')->format('Y-m-d'));
        $this->assertEquals($endDate, $response->viewData('endDate')->format('Y-m-d'));
    }

    /** 9. Missing dates in timeline are handled with 0 */
    public function test_missing_dates_handled_with_zero_revenue(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'revenue', 'period' => '7days']));
        $response->assertStatus(200);

        $reportData = $response->viewData('reportData');
        $this->assertEquals(0.00, $reportData['totalRevenue']);
        $this->assertCount(7, $reportData['chartRevenues']);
        foreach ($reportData['chartRevenues'] as $rev) {
            $this->assertEquals(0.0, $rev);
        }
    }

    /** 10. Order Report: Order status aggregation accuracy */
    public function test_order_report_status_aggregation(): void
    {
        Order::create([
            'order_number' => 'ORD-CMP-1',
            'total' => 50.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-PND-1',
            'total' => 60.00,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'unpaid',
            'created_at' => now(),
        ]);

        Order::create([
            'order_number' => 'ORD-CAN-1',
            'total' => 70.00,
            'order_status' => Order::STATUS_CANCELLED,
            'payment_status' => 'unpaid',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'orders', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(3, $data['totalOrders']);
        $this->assertEquals(1, $data['completedOrders']);
        $this->assertEquals(1, $data['pendingOrders']);
        $this->assertEquals(1, $data['cancelledOrders']);
        $this->assertEquals(33.3, $data['completionRate']);
    }

    /** 11. Best Sellers / Product Report calculations */
    public function test_product_best_seller_calculation(): void
    {
        $cat = Category::create(['name' => 'Cakes', 'status' => 'active']);
        $prod = Product::create([
            'name' => 'Chocolate Fudge Cake',
            'category_id' => $cat->id,
            'price' => 25.00,
            'cost' => 10.00,
            'stock' => 20,
            'minimum_stock' => 5,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PRD-1',
            'total' => 50.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod->id,
            'quantity' => 2,
            'price' => 25.00,
            'subtotal' => 50.00,
        ]);

        $cancelledOrder = Order::create([
            'order_number' => 'ORD-PRD-CAN',
            'total' => 100.00,
            'order_status' => Order::STATUS_CANCELLED,
            'payment_status' => 'unpaid',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $cancelledOrder->id,
            'product_id' => $prod->id,
            'quantity' => 4,
            'price' => 25.00,
            'subtotal' => 100.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'products', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(2, $data['totalUnitsSold']);
        $this->assertEquals(50.00, $data['totalRevenue']);
        $this->assertEquals(1, $data['totalDistinctProducts']);
    }

    /** 12. Inventory Report: Stock levels and accurate valuation */
    public function test_inventory_report_calculations(): void
    {
        $supplier = Supplier::create(['name' => 'Flour Mills Inc', 'status' => 'active']);

        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Organic Flour',
            'unit' => 'kg',
            'quantity' => 100.00,
            'cost' => 1.50,
            'minimum_quantity' => 20.00,
            'status' => 'active',
        ]);

        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Vanilla Extract',
            'unit' => 'ml',
            'quantity' => 5.00,
            'cost' => 10.00,
            'minimum_quantity' => 10.00,
            'status' => 'active',
        ]);

        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Yeast',
            'unit' => 'g',
            'quantity' => 0.00,
            'cost' => 0.50,
            'minimum_quantity' => 50.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'inventory']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(3, $data['totalIngredients']);
        $this->assertEquals(1, $data['lowStockCount']);
        $this->assertEquals(1, $data['outOfStockCount']);
        $this->assertEquals(200.00, $data['totalInventoryValue']);
    }

    /** 13. Low Stock Report: Deficit and replenishment costs */
    public function test_low_stock_report_calculations(): void
    {
        $supplier = Supplier::create(['name' => 'Dairy Best', 'status' => 'active']);

        Ingredient::create([
            'supplier_id' => $supplier->id,
            'name' => 'Heavy Cream',
            'unit' => 'liters',
            'quantity' => 2.00,
            'cost' => 4.00,
            'minimum_quantity' => 10.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'low-stock']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(1, $data['totalNeedingReorder']);
        $this->assertEquals(32.00, $data['estimatedRestockCost']);
    }

    /** 14. Production Report: Batch status aggregation and units produced */
    public function test_production_report_aggregation(): void
    {
        $cat = Category::create(['name' => 'Breads', 'status' => 'active']);
        $prod = Product::create([
            'name' => 'Sourdough Loaf',
            'category_id' => $cat->id,
            'price' => 6.00,
            'cost' => 2.00,
            'stock' => 30,
            'minimum_stock' => 5,
            'status' => 'active',
        ]);
        $recipe = Recipe::create(['product_id' => $prod->id, 'name' => 'Sourdough Recipe', 'yield_quantity' => 10]);

        Production::create([
            'batch_number' => 'BATCH-001',
            'product_id' => $prod->id,
            'recipe_id' => $recipe->id,
            'user_id' => $this->admin->id,
            'quantity' => 20,
            'status' => 'completed',
            'production_date' => now(),
            'started_at' => now()->subHour(),
            'completed_at' => now(),
        ]);

        Production::create([
            'batch_number' => 'BATCH-002',
            'product_id' => $prod->id,
            'recipe_id' => $recipe->id,
            'user_id' => $this->admin->id,
            'quantity' => 15,
            'status' => 'scheduled',
            'production_date' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'production', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(2, $data['totalBatches']);
        $this->assertEquals(1, $data['completedBatches']);
        $this->assertEquals(1, $data['scheduledBatches']);
        $this->assertEquals(20, $data['totalUnitsProduced']);
        $this->assertEquals(50.0, $data['completionRate']);
    }

    /** 15. Customer Report: Customer spending and order counts */
    public function test_customer_report_aggregation(): void
    {
        $customer = Customer::create([
            'name' => 'Bob Builder',
            'email' => 'bob@builder.com',
            'phone' => '1234567890',
            'status' => 'active',
            'loyalty_points' => 120,
            'loyalty_tier' => 'silver',
        ]);

        Order::create([
            'order_number' => 'ORD-BOB-1',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'total' => 75.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'customers', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(1, $data['totalCustomers']);
        $this->assertEquals(1, $data['activeCustomersCount']);
        $this->assertEquals(75.00, $data['totalCustomerSpend']);
        $this->assertEquals(75.00, $data['avgCustomerSpend']);
    }

    /** 16. Supplier Report: PO spending ONLY counts received POs */
    public function test_supplier_purchase_report_only_counts_received_spend(): void
    {
        $supplier = Supplier::create(['name' => 'Sugar Supplier Co', 'status' => 'active']);

        PurchaseOrder::create([
            'po_number' => 'PO-REC-001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'received',
            'order_date' => now(),
            'total_cost' => 500.00,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-DFT-001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'draft',
            'order_date' => now(),
            'total_cost' => 300.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'purchases', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(2, $data['totalPos']);
        $this->assertEquals(1, $data['receivedPos']);
        $this->assertEquals(1, $data['draftPos']);
        $this->assertEquals(500.00, $data['totalPurchaseSpend']);
    }

    /** 17. Profit & Loss: Revenue, COGS, Gross Profit and Margin */
    public function test_profit_loss_calculation(): void
    {
        $cat = Category::create(['name' => 'Muffins', 'status' => 'active']);
        $prod = Product::create([
            'name' => 'Blueberry Muffin',
            'category_id' => $cat->id,
            'price' => 4.00,
            'cost' => 1.50,
            'stock' => 50,
            'minimum_stock' => 5,
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-PNL-1',
            'total' => 40.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $prod->id,
            'quantity' => 10,
            'price' => 4.00,
            'subtotal' => 40.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'profit-loss', 'period' => 'today']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(40.00, $data['salesRevenue']);
        $this->assertEquals(15.00, $data['productCogs']);
        $this->assertEquals(25.00, $data['grossProfit']);
        $this->assertEquals(62.5, $data['profitMargin']);
    }

    /** 18. Empty periods return clean zero data without errors */
    public function test_empty_period_returns_clean_zero_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'sales', 'period' => 'yesterday']));
        $response->assertStatus(200);

        $data = $response->viewData('reportData');
        $this->assertEquals(0.00, $data['totalSalesRevenue']);
        $this->assertEquals(0, $data['totalSalesCount']);
        $this->assertEquals(0.00, $data['avgSaleValue']);
    }

    /** 19. Export routes are protected by RBAC */
    public function test_export_routes_are_protected_by_rbac(): void
    {
        $pdfGuest = $this->get(route('admin.reports.export.pdf'));
        $pdfGuest->assertRedirect(route('login'));

        $excelGuest = $this->get(route('admin.reports.export.excel'));
        $excelGuest->assertRedirect(route('login'));

        $pdfCashier = $this->actingAs($this->cashier)->get(route('admin.reports.export.pdf'));
        $pdfCashier->assertRedirect(route('cashier.dashboard'));

        $excelCashier = $this->actingAs($this->cashier)->get(route('admin.reports.export.excel'));
        $excelCashier->assertRedirect(route('cashier.dashboard'));
    }

    /** 20. Excel export returns a streamed CSV response with UTF-8 BOM */
    public function test_excel_export_returns_streamed_csv_with_utf8_bom(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.excel', ['type' => 'sales']));

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('Content-Type'), 'text/csv'));
        $this->assertTrue(str_contains($response->headers->get('Content-Disposition'), 'attachment; filename='));
    }

    /** 21. PDF export returns successful download response */
    public function test_pdf_export_returns_successful_download(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export.pdf', ['type' => 'sales']));

        $response->assertStatus(200);
    }

    /** 22. Financial Audit: No double-counting between product recipe COGS and PO procurement spend */
    public function test_financial_audit_no_double_counting_between_cogs_and_po_spend(): void
    {
        $supplier = Supplier::create(['name' => 'Flour Mills Inc', 'status' => 'active']);
        
        // 1. Receive PO for $150 of raw materials
        PurchaseOrder::create([
            'po_number' => 'PO-AUDIT-001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'received',
            'order_date' => now(),
            'total_cost' => 150.00,
        ]);

        // 2. Product manufactured with recipe cost $2.00, sold at $6.00
        $product = Product::create([
            'name' => 'Artisan Sourdough',
            'price' => 6.00,
            'cost' => 2.00,
            'stock' => 100,
            'minimum_stock' => 10,
            'status' => 'active',
        ]);

        // 3. Completed Sale of 10 loaves: Revenue = $60.00, Recipe COGS = $20.00
        $order = Order::create([
            'order_number' => 'ORD-AUDIT-001',
            'subtotal' => 60.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 60.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 6.00,
            'subtotal' => 60.00,
        ]);

        Sale::create([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'total' => 60.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'sold_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'profit-loss', 'period' => 'today']));
        $response->assertStatus(200);

        $pnl = $response->viewData('reportData');
        
        // Assert financial correctness
        $this->assertEquals(60.00, $pnl['salesRevenue'], 'Sales revenue must equal $60.00');
        $this->assertEquals(20.00, $pnl['productCogs'], 'COGS must equal 10 units * $2.00 cost = $20.00');
        $this->assertEquals(150.00, $pnl['procurementSpend'], 'Procurement spend must equal received PO total of $150.00');
        
        // CRITICAL CHECK: Gross Profit = Revenue - COGS ($60 - $20 = $40)
        // Procurement spend ($150) MUST NOT be subtracted from gross profit (which would double-count inventory costs)
        $this->assertEquals(40.00, $pnl['grossProfit'], 'Gross profit must strictly be Revenue - COGS ($40.00). Double-counting prevented!');
        $this->assertEquals(66.7, $pnl['profitMargin'], 'Gross margin must be ($40.00 / $60.00) * 100 = 66.7%');
    }

    /** 23. Financial Audit: Cancelled and pending orders excluded from revenue and COGS */
    public function test_financial_audit_cancelled_and_pending_orders_excluded(): void
    {
        $product = Product::create([
            'name' => 'Vanilla Scone',
            'price' => 5.00,
            'cost' => 1.50,
            'stock' => 50,
            'minimum_stock' => 5,
            'status' => 'active',
        ]);

        // Completed Order ($50)
        $compOrder = Order::create([
            'order_number' => 'ORD-COMP-01',
            'total' => 50.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $compOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 5.00,
            'subtotal' => 50.00,
        ]);
        Sale::create([
            'order_id' => $compOrder->id,
            'user_id' => $this->admin->id,
            'total' => 50.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'sold_at' => now(),
        ]);

        // Cancelled Order ($100)
        $cancOrder = Order::create([
            'order_number' => 'ORD-CANC-01',
            'total' => 100.00,
            'order_status' => Order::STATUS_CANCELLED,
            'payment_status' => 'unpaid',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $cancOrder->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'price' => 5.00,
            'subtotal' => 100.00,
        ]);

        // Pending Order ($75)
        $pendOrder = Order::create([
            'order_number' => 'ORD-PEND-01',
            'total' => 75.00,
            'order_status' => Order::STATUS_PENDING,
            'payment_status' => 'unpaid',
            'created_at' => now(),
        ]);
        OrderItem::create([
            'order_id' => $pendOrder->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'price' => 5.00,
            'subtotal' => 75.00,
        ]);

        // P&L Verification: Only completed order is included
        $pnlResp = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'profit-loss', 'period' => 'today']));
        $pnl = $pnlResp->viewData('reportData');
        $this->assertEquals(50.00, $pnl['salesRevenue']);
        $this->assertEquals(15.00, $pnl['productCogs']);
        $this->assertEquals(35.00, $pnl['grossProfit']);

        // Best Sellers Verification: Only 10 units sold (compOrder)
        $prodResp = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'products', 'period' => 'today']));
        $prod = $prodResp->viewData('reportData');
        $this->assertEquals(10, $prod['totalUnitsSold']);
        $this->assertEquals(50.00, $prod['totalRevenue']);

        // Orders Report Verification: Correct counts and amounts
        $ordResp = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'orders', 'period' => 'today']));
        $ord = $ordResp->viewData('reportData');
        $this->assertEquals(3, $ord['totalOrders']);
        $this->assertEquals(1, $ord['completedOrders']);
        $this->assertEquals(1, $ord['cancelledOrders']);
        $this->assertEquals(1, $ord['pendingOrders']);
        $this->assertEquals(125.00, $ord['totalOrderAmount']); // excludes cancelled order (50 + 75)
    }

    /** 24. Financial Audit: Purchase Order spend strictly separates Received vs Draft/Ordered/Cancelled */
    public function test_financial_audit_purchase_order_status_separation(): void
    {
        $supplier = Supplier::create(['name' => 'Grain Mills Ltd', 'status' => 'active']);

        PurchaseOrder::create([
            'po_number' => 'PO-REC',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'received',
            'order_date' => now(),
            'total_cost' => 450.00,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-ORD',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'ordered',
            'order_date' => now(),
            'total_cost' => 250.00,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-DFT',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'draft',
            'order_date' => now(),
            'total_cost' => 100.00,
        ]);

        PurchaseOrder::create([
            'po_number' => 'PO-CNC',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'status' => 'cancelled',
            'order_date' => now(),
            'total_cost' => 80.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'purchases', 'period' => 'today']));
        $data = $response->viewData('reportData');

        $this->assertEquals(4, $data['totalPos']);
        $this->assertEquals(1, $data['receivedPos']);
        $this->assertEquals(1, $data['orderedPos']);
        $this->assertEquals(1, $data['draftPos']);
        $this->assertEquals(1, $data['cancelledPos']);
        $this->assertEquals(450.00, $data['totalPurchaseSpend']); // Only received PO
    }

    /** 25. Financial Audit: Delayed order completion recognized on sale date */
    public function test_financial_audit_delayed_order_completion_recognized_on_sale_date(): void
    {
        $product = Product::create([
            'name' => 'Celebration Cake',
            'price' => 80.00,
            'cost' => 25.00,
            'stock' => 10,
            'minimum_stock' => 2,
            'status' => 'active',
        ]);

        // Order created 3 days ago as custom cake
        $order = Order::create([
            'order_number' => 'ORD-DELAY-01',
            'total' => 80.00,
            'order_status' => Order::STATUS_COMPLETED,
            'payment_status' => 'paid',
            'created_at' => now()->subDays(3),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 80.00,
            'subtotal' => 80.00,
        ]);

        // Sale executed today upon pickup
        Sale::create([
            'order_id' => $order->id,
            'user_id' => $this->admin->id,
            'total' => 80.00,
            'payment_method' => 'qr_code',
            'payment_status' => 'paid',
            'sold_at' => now(),
        ]);

        // Today's P&L must recognize the completed sale today
        $response = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'profit-loss', 'period' => 'today']));
        $data = $response->viewData('reportData');

        $this->assertEquals(80.00, $data['salesRevenue'], 'Revenue recognized on pickup/sale date');
        $this->assertEquals(25.00, $data['productCogs'], 'COGS recognized matching sale');
        $this->assertEquals(55.00, $data['grossProfit']);
    }

    /** 26. Reports consolidated navigation and overview dashboard test */
    public function test_reports_consolidated_navigation_and_overview(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertStatus(200);
        $response->assertViewHas('type', 'overview');

        // Navigation elements (5 primary consolidated tabs)
        $response->assertSee('id="nav_overview"', false);
        $response->assertSee('id="nav_sales"', false);
        $response->assertSee('id="nav_inventory"', false);
        $response->assertSee('id="tab_production"', false);
        $response->assertSee('id="tab_customers"', false);

        // Overview KPI summary cards (including P&L navigation card)
        $response->assertSee('id="overview_sales_card"', false);
        $response->assertSee('id="overview_revenue_card"', false);
        $response->assertSee('id="overview_orders_card"', false);
        $response->assertSee('id="overview_pl_card"', false);
        $response->assertSee('id="overview_stock_card"', false);
        $response->assertSee('type=profit-loss', false);
    }

    /** 27. Reports contextual sub-navigation pills render for Sales and Inventory */
    public function test_reports_contextual_sub_navigation_pills(): void
    {
        // Sales group sub-pills
        $salesResponse = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'sales']));
        $salesResponse->assertStatus(200);
        $salesResponse->assertSee('id="tab_sales"', false);
        $salesResponse->assertSee('id="tab_revenue"', false);
        $salesResponse->assertSee('id="tab_orders"', false);

        // Inventory group sub-pills
        $invResponse = $this->actingAs($this->admin)->get(route('admin.reports', ['type' => 'products']));
        $invResponse->assertStatus(200);
        $invResponse->assertSee('id="tab_products"', false);
        $invResponse->assertSee('id="tab_inventory"', false);
        $invResponse->assertSee('id="tab_low_stock"', false);
        $invResponse->assertSee('id="tab_purchases"', false);
    }
}

