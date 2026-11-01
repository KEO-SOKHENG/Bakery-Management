<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Ingredient;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Production;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Recipe;
use App\Models\Supplier;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationManagementTest extends TestCase
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
            ['username' => 'admin_notif_test'],
            ['name' => 'Admin Notif', 'email' => 'admin_notif@bakery.com', 'password' => bcrypt('password'), 'role' => 'admin', 'status' => 'active']
        );

        $this->manager = User::firstOrCreate(
            ['username' => 'mgr_notif_test'],
            ['name' => 'Manager Notif', 'email' => 'mgr_notif@bakery.com', 'password' => bcrypt('password'), 'role' => 'manager', 'status' => 'active']
        );

        $this->cashier = User::firstOrCreate(
            ['username' => 'csh_notif_test'],
            ['name' => 'Cashier Notif', 'email' => 'cashier_notif@bakery.com', 'password' => bcrypt('password'), 'role' => 'cashier', 'status' => 'active']
        );

        $this->baker = User::firstOrCreate(
            ['username' => 'bkr_notif_test'],
            ['name' => 'Baker Notif', 'email' => 'baker_notif@bakery.com', 'password' => bcrypt('password'), 'role' => 'baker', 'status' => 'active']
        );
    }

    /**
     * Test guest redirection from Notification Center.
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.notifications'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test authenticated users can access the Notification Center.
     */
    public function test_authenticated_users_can_access_notifications_center(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.notifications'));
        $response->assertStatus(200);
        $response->assertSee('Notification Center');

        $responseMgr = $this->actingAs($this->manager)->get(route('admin.notifications'));
        $responseMgr->assertStatus(200);

        $responseCashier = $this->actingAs($this->cashier)->get(route('admin.notifications'));
        $responseCashier->assertStatus(200);

        $responseBaker = $this->actingAs($this->baker)->get(route('admin.notifications'));
        $responseBaker->assertStatus(200);
    }

    /**
     * Test low-stock and out-of-stock condition alerts are generated for Admin and Manager.
     */
    public function test_low_and_out_of_stock_alerts_generation(): void
    {
        $supplier = Supplier::create([
            'name' => 'Sugar Co', 'contact_person' => 'Bob', 'phone' => '123', 'email' => 'bob@sugar.com', 'status' => 'active'
        ]);

        // Out of stock
        Ingredient::create([
            'name' => 'Flour Type 00', 'quantity' => 0, 'unit' => 'kg', 'cost' => 1.5, 'minimum_quantity' => 10, 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        // Low stock
        Ingredient::create([
            'name' => 'Raw Cane Sugar', 'quantity' => 4, 'unit' => 'kg', 'cost' => 2.0, 'minimum_quantity' => 10, 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        $service = app(NotificationService::class);
        $summary = $service->syncConditionAlerts();

        $this->assertEquals(1, $summary['out_of_stock']);
        $this->assertEquals(1, $summary['low_stock']);

        // Check Admin received both alerts
        $adminAlerts = Notification::forUser($this->admin->id)->unread()->get();
        $this->assertCount(2, $adminAlerts);

        // Check Manager received both alerts
        $mgrAlerts = Notification::forUser($this->manager->id)->unread()->get();
        $this->assertCount(2, $mgrAlerts);

        // Check Cashier and Baker did not receive inventory procurement alerts
        $cashierAlerts = Notification::forUser($this->cashier->id)->unread()->get();
        $this->assertCount(0, $cashierAlerts);
    }

    /**
     * Test deduplication: repeated condition syncs do not produce duplicate rows.
     */
    public function test_deduplication_engine_prevents_duplicate_notifications(): void
    {
        $supplier = Supplier::create([
            'name' => 'Flour Ltd', 'contact_person' => 'Sam', 'phone' => '456', 'email' => 'sam@flour.com', 'status' => 'active'
        ]);

        Ingredient::create([
            'name' => 'Yeast Fresh', 'quantity' => 2, 'unit' => 'kg', 'cost' => 3.0, 'minimum_quantity' => 5, 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        $service = app(NotificationService::class);

        // First sync
        $service->syncConditionAlerts();
        $initialCount = Notification::where('type', 'inventory')->count();
        $this->assertGreaterThan(0, $initialCount);

        // Second sync immediately after
        $service->syncConditionAlerts();
        $secondCount = Notification::where('type', 'inventory')->count();

        $this->assertEquals($initialCount, $secondCount, "Deduplication failed: Duplicate notification rows were created.");
    }

    /**
     * Test self-healing: restocking an ingredient auto-resolves active unread low-stock notifications.
     */
    public function test_self_healing_auto_resolves_when_stock_normalizes(): void
    {
        $supplier = Supplier::create([
            'name' => 'Butter Dairy', 'contact_person' => 'Alice', 'phone' => '789', 'email' => 'alice@dairy.com', 'status' => 'active'
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Organic Butter', 'quantity' => 2, 'unit' => 'kg', 'cost' => 5.0, 'minimum_quantity' => 10, 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        $service = app(NotificationService::class);
        $service->syncConditionAlerts();

        // Verify unread alert exists
        $dedupKey = "low_stock:ingredient:{$ingredient->id}";
        $this->assertTrue(Notification::where('dedup_key', $dedupKey)->whereNull('read_at')->exists());

        // Restock ingredient above minimum quantity
        $ingredient->update(['quantity' => 25]);

        // Run sync again
        $summary = $service->syncConditionAlerts();
        $this->assertGreaterThan(0, $summary['resolved']);

        // Verify alert was automatically marked as read
        $this->assertFalse(Notification::where('dedup_key', $dedupKey)->whereNull('read_at')->exists());
        $this->assertTrue(Notification::where('dedup_key', $dedupKey)->whereNotNull('read_at')->exists());
    }

    /**
     * Test expiring ingredient notifications.
     */
    public function test_expiring_and_expired_ingredient_alerts(): void
    {
        $supplier = Supplier::create([
            'name' => 'Berry Farm', 'contact_person' => 'Jane', 'phone' => '111', 'email' => 'jane@berry.com', 'status' => 'active'
        ]);

        // Expired yesterday
        Ingredient::create([
            'name' => 'Fresh Strawberries', 'quantity' => 10, 'unit' => 'kg', 'cost' => 4.0, 'minimum_quantity' => 2, 'expiry_date' => Carbon::yesterday(), 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        // Expiring in 2 days
        Ingredient::create([
            'name' => 'Blueberries', 'quantity' => 8, 'unit' => 'kg', 'cost' => 6.0, 'minimum_quantity' => 2, 'expiry_date' => Carbon::today()->addDays(2), 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        $service = app(NotificationService::class);
        $summary = $service->syncConditionAlerts();

        $this->assertEquals(1, $summary['expired']);
        $this->assertEquals(1, $summary['expiring']);

        $alerts = Notification::forUser($this->admin->id)->where('type', 'expiring')->get();
        $this->assertCount(2, $alerts);
    }

    /**
     * Test custom cake order pickup reminder within 24 hours.
     */
    public function test_custom_order_pickup_reminder_alerts(): void
    {
        $customer = Customer::create([
            'name' => 'Birthday Customer', 'phone' => '012345678', 'status' => 'active'
        ]);

        Order::create([
            'order_number' => 'ORD-CAKE-REMINDER-01',
            'user_id' => $this->admin->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'subtotal' => 50,
            'discount' => 0,
            'tax' => 5,
            'total' => 55,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => 'preparing',
            'is_custom' => true,
            'pickup_date' => Carbon::tomorrow()->subHours(2), // within 24 hours
        ]);

        $service = app(NotificationService::class);
        $summary = $service->syncConditionAlerts();

        $this->assertEquals(1, $summary['pickup_soon']);

        $alert = Notification::forUser($this->admin->id)->where('type', 'custom_order')->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('Birthday Customer', $alert->message);
    }

    /**
     * Test order creation triggers event notifications.
     */
    public function test_order_creation_triggers_notifications(): void
    {
        $category = Category::create(['name' => 'Pastries', 'status' => 'active']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Almond Croissant',
            'sku' => 'CROISSANT-01',
            'price' => 3.50,
            'cost' => 1.20,
            'stock' => 50,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->cashier)->post(route('admin.orders.store'), [
            'customer_name' => 'Walk-in Alice',
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status' => 'completed',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2]
            ]
        ]);

        $response->assertSessionHasNoErrors();

        // Both Admin and Manager should have received the order notification
        $adminNotifs = Notification::forUser($this->admin->id)->where('type', 'order')->get();
        $this->assertGreaterThan(0, $adminNotifs->count());
        $this->assertStringContainsString('New Order', $adminNotifs->first()->title);
    }

    /**
     * Test production batch scheduling and completion event notifications.
     */
    public function test_production_events_trigger_notifications(): void
    {
        $category = Category::create(['name' => 'Breads', 'status' => 'active']);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Sourdough Loaf',
            'sku' => 'SOURDOUGH-01',
            'price' => 5.00,
            'cost' => 2.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        // Schedule production batch
        $response = $this->actingAs($this->manager)->post(route('admin.production.store'), [
            'product_id' => $product->id,
            'quantity' => 20,
            'baker_id' => $this->baker->id,
            'scheduled_at' => now()->addHours(2)->toDateTimeString(),
            'status' => 'scheduled',
        ]);

        $response->assertSessionHasNoErrors();

        // The assigned Baker should receive the notification
        $bakerNotifs = Notification::forUser($this->baker->id)->where('type', 'production')->get();
        $this->assertGreaterThan(0, $bakerNotifs->count());
        $this->assertStringContainsString('Production Scheduled', $bakerNotifs->first()->title);
    }

    /**
     * Test Purchase Order ordering and receiving triggers notifications.
     */
    public function test_purchase_order_events_trigger_notifications(): void
    {
        $supplier = Supplier::create([
            'name' => 'PO Test Supplier', 'contact_person' => 'Paul', 'phone' => '999', 'email' => 'paul@sup.com', 'status' => 'active'
        ]);

        $ingredient = Ingredient::create([
            'name' => 'Cocoa Powder', 'quantity' => 5, 'unit' => 'kg', 'cost' => 8.0, 'minimum_quantity' => 10, 'supplier_id' => $supplier->id, 'status' => 'active'
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $supplier->id,
            'user_id' => $this->admin->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'total_cost' => 80.00,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'ingredient_id' => $ingredient->id,
            'quantity' => 10,
            'unit' => 'kg',
            'purchase_price' => 8.00,
            'subtotal' => 80.00,
        ]);

        // Transition Draft -> Ordered
        $response = $this->actingAs($this->manager)->post(route('admin.purchase-orders.order', $po));
        $response->assertSessionHasNoErrors();

        $adminOrderedAlerts = Notification::forUser($this->admin->id)->where('type', 'purchase_order')->get();
        $this->assertGreaterThan(0, $adminOrderedAlerts->count());

        // Transition Ordered -> Received
        $responseRecv = $this->actingAs($this->manager)->post(route('admin.purchase-orders.receive', $po));
        $responseRecv->assertSessionHasNoErrors();

        $adminRecvAlerts = Notification::forUser($this->admin->id)->where('type', 'purchase_order')->where('title', 'like', '%Received%')->get();
        $this->assertGreaterThan(0, $adminRecvAlerts->count());
    }

    /**
     * Test user isolation: user cannot mark read or delete another user's notification.
     */
    public function test_user_isolation_blocks_unauthorized_access(): void
    {
        $adminNotif = Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'system',
            'title' => 'Admin Secret Alert',
            'message' => 'Confidential message',
            'severity' => 'info',
        ]);

        // Manager attempts to mark Admin's notification as read
        $response = $this->actingAs($this->manager)->post(route('notifications.read', $adminNotif));
        $response->assertStatus(403);

        // Manager attempts to delete Admin's notification
        $responseDel = $this->actingAs($this->manager)->delete(route('notifications.destroy', $adminNotif));
        $responseDel->assertStatus(403);
    }

    /**
     * Test AJAX endpoints: unread count, feed, mark as read, mark all as read.
     */
    public function test_ajax_notification_endpoints(): void
    {
        $n1 = Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'system',
            'title' => 'Notice 1',
            'message' => 'Message 1',
            'severity' => 'info',
        ]);

        $n2 = Notification::create([
            'user_id' => $this->admin->id,
            'type' => 'system',
            'title' => 'Notice 2',
            'message' => 'Message 2',
            'severity' => 'warning',
        ]);

        // 1. Unread count
        $respCount = $this->actingAs($this->admin)->getJson(route('notifications.unreadCount'));
        $respCount->assertStatus(200);
        $respCount->assertJson(['count' => 2]);

        // 2. Feed
        $respFeed = $this->actingAs($this->admin)->getJson(route('notifications.feed'));
        $respFeed->assertStatus(200);
        $respFeed->assertJsonPath('unread_count', 2);
        $this->assertCount(2, $respFeed->json('notifications'));

        // 3. Mark single notification as read
        $respRead = $this->actingAs($this->admin)->postJson(route('notifications.read', $n1));
        $respRead->assertStatus(200);
        $respRead->assertJson(['success' => true, 'unread_count' => 1]);

        $this->assertNotNull($n1->fresh()->read_at);

        // 4. Mark all as read
        $respReadAll = $this->actingAs($this->admin)->postJson(route('notifications.readAll'));
        $respReadAll->assertStatus(200);
        $respReadAll->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertNotNull($n2->fresh()->read_at);

        // 5. Delete notification
        $respDelete = $this->actingAs($this->admin)->deleteJson(route('notifications.destroy', $n1));
        $respDelete->assertStatus(200);
        $respDelete->assertJson(['success' => true]);
        $this->assertDatabaseMissing('notifications', ['id' => $n1->id]);
    }

    /**
     * Test mark notification as unread works via AJAX.
     */
    public function test_mark_as_unread_toggles_status(): void
    {
        $notif = Notification::create([
            'user_id'  => $this->admin->id,
            'type'     => 'system',
            'title'    => 'Read Notification',
            'message'  => 'Already read item',
            'severity' => 'info',
            'read_at'  => now(),
        ]);

        $this->assertTrue($notif->isRead());

        $response = $this->actingAs($this->admin)->postJson(route('notifications.unread', $notif));
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertNull($notif->fresh()->read_at);
        $this->assertFalse($notif->fresh()->isRead());
    }

    /**
     * Test Admin promotion broadcast and audit logging.
     */
    public function test_admin_promotion_notification_broadcast_and_audit_logging(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.notifications.promotions'), [
            'title'       => 'Grand Holiday Pastry Sale',
            'message'     => 'Special 25% discount on all artisan breads and cakes this weekend!',
            'target_role' => 'cashier',
            'severity'    => 'warning',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.notifications'));

        // Verify Cashier received the promotion
        $cashierNotifs = Notification::forUser($this->cashier->id)->where('type', 'promotion')->get();
        $this->assertCount(1, $cashierNotifs);
        $this->assertEquals('Grand Holiday Pastry Sale', $cashierNotifs->first()->title);

        // Verify Baker did not receive cashier-targeted promotion
        $bakerNotifs = Notification::forUser($this->baker->id)->where('type', 'promotion')->get();
        $this->assertCount(0, $bakerNotifs);

        // Verify Audit Log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'notification.promotion_sent',
            'user_id' => $this->admin->id,
        ]);
    }

    /**
     * Test unauthorized roles cannot broadcast promotions.
     */
    public function test_unauthorized_role_cannot_broadcast_promotions(): void
    {
        // Cashier attempt (JSON API returns 403)
        $responseCashier = $this->actingAs($this->cashier)->postJson(route('admin.notifications.promotions'), [
            'title'       => 'Unauthorized Cashier Broadcast',
            'message'     => 'Should fail',
            'target_role' => 'all',
        ]);
        $responseCashier->assertStatus(403);

        // Manager attempt (JSON API returns 403)
        $responseManager = $this->actingAs($this->manager)->postJson(route('admin.notifications.promotions'), [
            'title'       => 'Unauthorized Manager Broadcast',
            'message'     => 'Should fail',
            'target_role' => 'all',
        ]);
        $responseManager->assertStatus(403);

        // Standard web request redirects unauthorized role to dashboard
        $responseWeb = $this->actingAs($this->cashier)->post(route('admin.notifications.promotions'), [
            'title'       => 'Unauthorized Web Broadcast',
            'message'     => 'Should fail',
            'target_role' => 'all',
        ]);
        $responseWeb->assertRedirect(route('cashier.dashboard'));
    }

    /**
     * Test Order Ready for pickup status transition triggers notifications.
     */
    public function test_order_ready_for_pickup_generates_notification(): void
    {
        $order = Order::create([
            'order_number'   => 'ORD-READY-001',
            'user_id'        => $this->cashier->id,
            'customer_name'  => 'Ready Customer',
            'subtotal'       => 20.00,
            'tax'            => 2.00,
            'total'          => 22.00,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'order_status'   => Order::STATUS_PREPARING,
        ]);

        $response = $this->actingAs($this->manager)->post(route('admin.orders.updateStatus', $order), [
            'status' => Order::STATUS_READY_FOR_PICKUP,
        ]);

        $response->assertSessionHasNoErrors();

        // Verify Admin received the Order Ready notification
        $adminReadyNotifs = Notification::forUser($this->admin->id)
            ->where('type', 'order')
            ->where('title', 'like', '%Ready for Pickup%')
            ->get();

        $this->assertGreaterThan(0, $adminReadyNotifs->count());
        $this->assertEquals('success', $adminReadyNotifs->first()->severity);
    }

    /**
     * Test rolled-back database transaction does NOT dispatch notification.
     */
    public function test_rolled_back_transaction_does_not_generate_notification(): void
    {
        $initialCount = Notification::where('type', 'order')->count();

        try {
            DB::transaction(function () {
                $order = Order::create([
                    'order_number'   => 'ORD-FAIL-ROLLBACK',
                    'user_id'        => $this->cashier->id,
                    'customer_name'  => 'Rollback Customer',
                    'subtotal'       => 15.00,
                    'tax'            => 1.50,
                    'total'          => 16.50,
                    'payment_method' => 'cash',
                    'payment_status' => 'paid',
                    'order_status'   => 'pending',
                ]);

                // Simulate unexpected failure inside transaction
                throw new \Exception("Simulated transaction rollback error.");

                // Would only reach here if transaction did not throw
                app(NotificationService::class)->notifyOrderCreated($order);
            });
        } catch (\Exception $e) {
            // Transaction rolled back safely
        }

        $afterCount = Notification::where('type', 'order')->count();
        $this->assertEquals($initialCount, $afterCount, "Rolled-back transaction must NOT generate notifications.");
    }

    /**
     * Test English and Khmer translation strings load correctly.
     */
    public function test_english_and_khmer_translations_load_correctly(): void
    {
        // English
        App::setLocale('en');
        $this->assertEquals('Notifications', __('messages.notifications'));
        $this->assertEquals('Notification Center', __('messages.notification_center'));
        $this->assertEquals('Low Stock', __('messages.low_stock'));
        $this->assertEquals('Out of Stock', __('messages.out_of_stock'));
        $this->assertEquals('Expiring Soon', __('messages.expiring_soon'));
        $this->assertEquals('Expired', __('messages.expired'));
        $this->assertEquals('Order Ready', __('messages.order_ready'));
        $this->assertEquals('Production Completed', __('messages.production_completed'));
        $this->assertEquals('Purchase Order Received', __('messages.purchase_order_received'));
        $this->assertEquals('Promotion', __('messages.promotion'));
        $this->assertEquals('Mark as unread', __('messages.mark_as_unread'));

        // Khmer
        App::setLocale('km');
        $this->assertEquals('ការជូនដំណឹង', __('messages.notifications'));
        $this->assertEquals('មជ្ឈមណ្ឌលជូនដំណឹង', __('messages.notification_center'));
        $this->assertEquals('ស្តុកទាប', __('messages.low_stock'));
        $this->assertEquals('អស់ពីស្តុក', __('messages.out_of_stock'));
        $this->assertEquals('ជិតផុតកំណត់', __('messages.expiring_soon'));
        $this->assertEquals('បានផុតកំណត់', __('messages.expired'));
        $this->assertEquals('ការបញ្ជាទិញរួចរាល់', __('messages.order_ready'));
        $this->assertEquals('ការផលិតបានបញ្ចប់', __('messages.production_completed'));
        $this->assertEquals('បានទទួលការបញ្ជាទិញទំនិញចូល', __('messages.purchase_order_received'));
        $this->assertEquals('ការផ្សព្វផ្សាយ', __('messages.promotion'));
        $this->assertEquals('កំណត់ថាមិនទាន់អាន', __('messages.mark_as_unread'));
    }

    /**
     * Test bulk notification actions: bulkRead, bulkUnread, bulkDelete.
     */
    public function test_bulk_notification_operations(): void
    {
        $n1 = Notification::create([
            'user_id'  => $this->admin->id,
            'type'     => 'order',
            'title'    => 'Bulk Order 1',
            'message'  => 'Test order 1',
            'severity' => 'info',
        ]);

        $n2 = Notification::create([
            'user_id'  => $this->admin->id,
            'type'     => 'production',
            'title'    => 'Bulk Prod 2',
            'message'  => 'Test prod 2',
            'severity' => 'warning',
        ]);

        $this->assertTrue(!$n1->isRead());
        $this->assertTrue(!$n2->isRead());

        // 1. Bulk Read
        $responseRead = $this->actingAs($this->admin)->postJson(route('notifications.bulkRead'), [
            'ids' => [$n1->id, $n2->id],
        ]);
        $responseRead->assertStatus(200);
        $responseRead->assertJson(['success' => true]);
        $this->assertTrue($n1->fresh()->isRead());
        $this->assertTrue($n2->fresh()->isRead());

        // 2. Bulk Unread
        $responseUnread = $this->actingAs($this->admin)->postJson(route('notifications.bulkUnread'), [
            'ids' => [$n1->id],
        ]);
        $responseUnread->assertStatus(200);
        $responseUnread->assertJson(['success' => true]);
        $this->assertTrue(!$n1->fresh()->isRead());
        $this->assertTrue($n2->fresh()->isRead());

        // 3. Bulk Delete
        $responseDelete = $this->actingAs($this->admin)->postJson(route('notifications.bulkDelete'), [
            'ids' => [$n1->id, $n2->id],
        ]);
        $responseDelete->assertStatus(200);
        $responseDelete->assertJson(['success' => true]);
        $this->assertDatabaseMissing('notifications', ['id' => $n1->id]);
        $this->assertDatabaseMissing('notifications', ['id' => $n2->id]);
    }
}

