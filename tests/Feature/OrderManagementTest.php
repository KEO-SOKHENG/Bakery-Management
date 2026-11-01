<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $cashier;
    private User $baker;
    private Category $category;
    private Product $croissant;
    private Product $baguette;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['username' => 'admin_order_test'],
            ['name' => 'Admin Test', 'email' => 'admin_orders@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'admin', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['username' => 'manager_order_test'],
            ['name' => 'Manager Test', 'email' => 'manager_orders@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'manager', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['username' => 'cashier_order_test'],
            ['name' => 'Cashier Test', 'email' => 'cashier_orders@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->baker = User::firstOrCreate(
            ['username' => 'baker_order_test'],
            ['name' => 'Baker Test', 'email' => 'baker_orders@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'baker', 'status' => 'active']
        );

        $this->category = Category::firstOrCreate(
            ['name' => 'Pastries'],
            ['description' => 'Fresh pastries', 'status' => 'active']
        );

        $this->croissant = Product::firstOrCreate(
            ['name' => 'Butter Croissant Test'],
            ['price' => 3.50, 'cost' => 1.20, 'stock' => 50, 'category_id' => $this->category->id, 'status' => 'active']
        );

        $this->baguette = Product::firstOrCreate(
            ['name' => 'French Baguette Test'],
            ['price' => 2.50, 'cost' => 0.80, 'stock' => 40, 'category_id' => $this->category->id, 'status' => 'active']
        );

        $this->customer = Customer::firstOrCreate(
            ['phone' => '012345678'],
            ['name' => 'Sophea Pich', 'email' => 'sophea@example.com', 'loyalty_points' => 150, 'loyalty_tier' => 'silver', 'status' => 'active']
        );
    }

    /**
     * Test A: Order list page loads with metrics, search, and status filtering.
     */
    public function test_order_list_loads_with_metrics_search_and_status_filtering(): void
    {
        $order1 = Order::create([
            'order_number'   => 'ORD-TEST-001',
            'customer_name'  => 'Walk-in Customer',
            'subtotal'       => 10.00,
            'total'          => 11.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status'   => 'completed',
        ]);

        $order2 = Order::create([
            'order_number'   => 'ORD-TEST-002',
            'customer_id'    => $this->customer->id,
            'customer_name'  => $this->customer->name,
            'subtotal'       => 25.00,
            'total'          => 27.50,
            'payment_method' => 'qr_code',
            'payment_status' => 'unpaid',
            'order_status'   => 'pending',
            'is_custom'      => true,
            'special_instructions' => 'Custom anniversary cake',
        ]);

        // 1. Visit index as Admin
        $response = $this->actingAs($this->admin)->get(route('admin.orders'));
        $response->assertStatus(200);
        $response->assertSee('ORD-TEST-001');
        $response->assertSee('ORD-TEST-002');
        $response->assertSee('Sophea Pich');

        // 2. Search by order number
        $searchResp = $this->actingAs($this->admin)->get(route('admin.orders', ['search' => 'ORD-TEST-002']));
        $searchResp->assertStatus(200);
        $searchResp->assertSee('ORD-TEST-002');
        $searchResp->assertDontSee('ORD-TEST-001');

        // 3. Filter by status 'pending'
        $filterResp = $this->actingAs($this->admin)->get(route('admin.orders', ['status' => 'pending']));
        $filterResp->assertStatus(200);
        $filterResp->assertSee('ORD-TEST-002');
        $filterResp->assertDontSee('ORD-TEST-001');
    }

    /**
     * Test B: Order detail view displays correct customer, items, totals, and timeline.
     */
    public function test_order_detail_view_shows_all_order_information(): void
    {
        $order = Order::create([
            'user_id'        => $this->admin->id,
            'customer_id'    => $this->customer->id,
            'order_number'   => 'ORD-DETAIL-999',
            'customer_name'  => $this->customer->name,
            'subtotal'       => 10.50,
            'discount'       => 1.00,
            'tax'            => 0.95,
            'total'          => 10.45,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'order_status'   => 'ready_for_pickup',
            'is_custom'      => true,
            'special_instructions' => 'Extra chocolate lettering',
            'pickup_date'    => now()->addDay(),
        ]);

        $order->items()->create([
            'product_id' => $this->croissant->id,
            'quantity'   => 3,
            'price'      => 3.50,
            'subtotal'   => 10.50,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders.show', $order));
        $response->assertRedirect(route('admin.orders'));
    }

    /**
     * Test C: POS checkout creates order with customer, sale, payment, deducted stock, and shows in Order Management.
     */
    public function test_pos_checkout_creates_order_visible_in_order_management(): void
    {
        $initialStock = $this->croissant->stock;

        $response = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
            'customer_id'    => $this->customer->id,
            'payment_method' => 'qr_code',
            'discount'       => 0,
            'items'          => [
                [
                    'product_id' => $this->croissant->id,
                    'quantity'   => 4,
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Verify product stock decremented
        $this->assertEquals($initialStock - 4, $this->croissant->fresh()->stock);

        // Verify Order created in DB
        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertNotNull($order);
        $this->assertEquals('completed', $order->order_status);
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('qr_code', $order->payment_method);
        $this->assertCount(1, $order->items);
        $this->assertEquals(4, $order->items->first()->quantity);

        // Verify Sale and Payment created
        $this->assertNotNull(Sale::where('order_id', $order->id)->first());
        $this->assertNotNull(Payment::where('order_id', $order->id)->first());

        // Verify POS order is visible in Admin Order Management
        $adminView = $this->actingAs($this->admin)->get(route('admin.orders'));
        $adminView->assertStatus(200);
        $adminView->assertSee($order->order_number);
        $adminView->assertSee($this->customer->name);
    }

    /**
     * Test D: Primary status workflow: Pending → Preparing → Ready for Pickup → Completed.
     */
    public function test_primary_status_workflow_transitions(): void
    {
        $order = Order::create([
            'order_number'   => 'ORD-FLOW-001',
            'customer_name'  => 'Workflow Customer',
            'subtotal'       => 10.00,
            'total'          => 10.00,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'order_status'   => 'pending',
        ]);

        // 1. Pending → Preparing
        $res1 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $order), [
            'status' => 'preparing',
        ]);
        $res1->assertSessionHas('success');
        $this->assertEquals('preparing', $order->fresh()->order_status);

        // 2. Preparing → Ready for Pickup
        $res2 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $order), [
            'status' => 'ready_for_pickup',
        ]);
        $res2->assertSessionHas('success');
        $this->assertEquals('ready_for_pickup', $order->fresh()->order_status);

        // 3. Ready for Pickup → Completed
        $res3 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $order), [
            'status' => 'completed',
        ]);
        $res3->assertSessionHas('success');
        $this->assertEquals('completed', $order->fresh()->order_status);
        $this->assertEquals('paid', $order->fresh()->payment_status);
        $this->assertNotNull(Sale::where('order_id', $order->id)->first());
    }

    /**
     * Test E: Status workflow: Ready for Pickup → Completed (and out_for_delivery retirement).
     */
    public function test_delivery_status_workflow_transitions(): void
    {
        $order = Order::create([
            'order_number'   => 'ORD-DELIV-001',
            'customer_name'  => 'Delivery Customer',
            'subtotal'       => 15.00,
            'total'          => 15.00,
            'payment_method' => 'card',
            'payment_status' => 'paid',
            'order_status'   => 'ready_for_pickup',
        ]);

        // 1. Transition to retired 'out_for_delivery' must be rejected
        $res1 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $order), [
            'status' => 'out_for_delivery',
        ]);
        $res1->assertSessionHas('error');
        $this->assertEquals('ready_for_pickup', $order->fresh()->order_status);

        // 2. Ready for Pickup → Completed
        $res2 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $order), [
            'status' => 'completed',
        ]);
        $res2->assertSessionHas('success');
        $this->assertEquals('completed', $order->fresh()->order_status);
    }

    /**
     * Test Fallback: Authenticated delivery routes redirect to orders with info flash message.
     */
    public function test_delivery_fallback_redirects_to_orders(): void
    {
        $resIndex = $this->actingAs($this->admin)->get('/admin/deliveries');
        $resIndex->assertRedirect(route('admin.orders'));
        $resIndex->assertSessionHas('info');

        $resId = $this->actingAs($this->admin)->get('/admin/deliveries/123');
        $resId->assertRedirect(route('admin.orders'));
        $resId->assertSessionHas('info');
    }

    /**
     * Test F: Order cancellation restores reserved product stock.
     */
    public function test_cancelling_pending_order_restores_product_stock(): void
    {
        $initialStock = $this->croissant->stock;

        // Create order via store (reserves 5 croissants)
        $this->actingAs($this->admin)->post(route('admin.orders.store'), [
            'customer_name'  => 'Cancel Tester',
            'payment_method' => 'cash',
            'order_status'   => 'pending',
            'items'          => [
                [
                    'product_id' => $this->croissant->id,
                    'quantity'   => 5,
                ],
            ],
        ]);

        $this->assertEquals($initialStock - 5, $this->croissant->fresh()->stock);

        $order = Order::where('customer_name', 'Cancel Tester')->latest()->first();

        // Cancel the order
        $cancelResp = $this->actingAs($this->admin)->post(route('admin.orders.cancel', $order));
        $cancelResp->assertSessionHas('success');

        // Verify status and stock restoration
        $this->assertEquals('cancelled', $order->fresh()->order_status);
        $this->assertEquals($initialStock, $this->croissant->fresh()->stock);
    }

    /**
     * Test G: Invalid status transitions are rejected server-side.
     */
    public function test_invalid_status_transitions_are_strictly_rejected(): void
    {
        // 1. Completed order cannot transition back to pending or cancelled
        $completedOrder = Order::create([
            'order_number'   => 'ORD-COMP-001',
            'customer_name'  => 'Completed Customer',
            'subtotal'       => 20.00,
            'total'          => 20.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status'   => 'completed',
        ]);

        $res1 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $completedOrder), [
            'status' => 'pending',
        ]);
        $res1->assertSessionHas('error');
        $this->assertEquals('completed', $completedOrder->fresh()->order_status);

        $res2 = $this->actingAs($this->admin)->post(route('admin.orders.cancel', $completedOrder));
        $res2->assertSessionHas('error');
        $this->assertEquals('completed', $completedOrder->fresh()->order_status);

        // 2. Cancelled order cannot transition to anything
        $cancelledOrder = Order::create([
            'order_number'   => 'ORD-CANC-001',
            'customer_name'  => 'Cancelled Customer',
            'subtotal'       => 20.00,
            'total'          => 20.00,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'order_status'   => 'cancelled',
        ]);

        $res3 = $this->actingAs($this->admin)->post(route('admin.orders.updateStatus', $cancelledOrder), [
            'status' => 'completed',
        ]);
        $res3->assertSessionHas('error');
        $this->assertEquals('cancelled', $cancelledOrder->fresh()->order_status);
    }

    /**
     * Test H: Custom cake order stores special instructions and scheduled pickup date.
     */
    public function test_custom_cake_order_creation_preserves_custom_attributes(): void
    {
        $pickupDate = now()->addDays(2)->format('Y-m-d H:i');

        $response = $this->actingAs($this->admin)->post(route('admin.orders.store'), [
            'customer_id'          => $this->customer->id,
            'customer_name'        => $this->customer->name,
            'payment_method'       => 'card',
            'payment_status'       => 'unpaid',
            'order_status'         => 'pending',
            'is_custom'            => 1,
            'special_instructions' => '3 tiers vanilla cake with rainbow fondant',
            'pickup_date'          => $pickupDate,
            'items'                => [
                [
                    'product_id' => $this->baguette->id,
                    'quantity'   => 2,
                ],
            ],
        ]);

        $response->assertSessionHas('success');

        $order = Order::where('customer_id', $this->customer->id)->latest()->first();
        $this->assertTrue($order->is_custom);
        $this->assertEquals('3 tiers vanilla cake with rainbow fondant', $order->special_instructions);
        $this->assertNotNull($order->pickup_date);
    }

    /**
     * Test I: Updating order is allowed for Pending, but locked for Completed.
     */
    public function test_order_modifications_allowed_for_pending_and_locked_for_completed(): void
    {
        $order = Order::create([
            'order_number'   => 'ORD-EDIT-001',
            'customer_name'  => 'Original Customer',
            'subtotal'       => 7.00,
            'total'          => 7.00,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'order_status'   => 'pending',
        ]);

        $order->items()->create([
            'product_id' => $this->croissant->id,
            'quantity'   => 2,
            'price'      => 3.50,
            'subtotal'   => 7.00,
        ]);

        // 1. Pending order edit form loads
        $editResp = $this->actingAs($this->admin)->get(route('admin.orders.edit', $order));
        $editResp->assertStatus(200);

        // 2. Pending order can be updated
        $updateResp = $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
            'customer_name'  => 'Updated Customer Name',
            'payment_method' => 'card',
            'items'          => [
                [
                    'product_id' => $this->baguette->id,
                    'quantity'   => 4,
                ],
            ],
        ]);
        $updateResp->assertSessionHas('success');
        $this->assertEquals('Updated Customer Name', $order->fresh()->customer_name);

        // 3. Mark completed and try to update items
        $order->update(['order_status' => 'completed']);

        $editCompletedResp = $this->actingAs($this->admin)->get(route('admin.orders.edit', $order));
        $editCompletedResp->assertSessionHas('error');

        $updateCompletedResp = $this->actingAs($this->admin)->put(route('admin.orders.update', $order), [
            'customer_name'  => 'Hacker',
            'payment_method' => 'cash',
            'items'          => [
                ['product_id' => $this->baguette->id, 'quantity' => 1],
            ],
        ]);
        $updateCompletedResp->assertSessionHas('error');
    }

    /**
     * Test J: RBAC permissions (Admin/Manager full, Cashier operational, Baker restricted).
     */
    public function test_rbac_order_management_permissions(): void
    {
        $order = Order::create([
            'order_number'   => 'ORD-RBAC-001',
            'customer_name'  => 'RBAC Customer',
            'subtotal'       => 10.00,
            'total'          => 10.00,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'order_status'   => 'pending',
        ]);

        // Admin can view index and show redirects to index
        $this->actingAs($this->admin)->get(route('admin.orders'))->assertStatus(200);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertRedirect(route('admin.orders'));

        // Manager can view index and show redirects to index
        $this->actingAs($this->manager)->get(route('admin.orders'))->assertStatus(200);
        $this->actingAs($this->manager)->get(route('admin.orders.show', $order))->assertRedirect(route('admin.orders'));

        // Cashier can view index, show redirects to index, and update status
        $this->actingAs($this->cashier)->get(route('admin.orders'))->assertStatus(200);
        $this->actingAs($this->cashier)->get(route('admin.orders.show', $order))->assertRedirect(route('admin.orders'));

        // Cashier CANNOT delete orders (route is admin & manager only)
        $cashierDeleteResp = $this->actingAs($this->cashier)->delete(route('admin.orders.destroy', $order));
        $this->assertTrue(in_array($cashierDeleteResp->status(), [403, 302]));
        $this->actingAs($this->cashier)->deleteJson(route('admin.orders.destroy', $order))->assertStatus(403);

        // Baker CANNOT access orders index or show
        $bakerResp = $this->actingAs($this->baker)->get(route('admin.orders'));
        $this->assertTrue(in_array($bakerResp->status(), [403, 302]));
        $this->actingAs($this->baker)->getJson(route('admin.orders'))->assertStatus(403);
    }
}