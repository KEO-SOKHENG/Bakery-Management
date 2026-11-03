<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Ingredient;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockMovement;

use Illuminate\Foundation\Testing\RefreshDatabase;

class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Supplier $supplier;
    private Ingredient $flour;
    private Ingredient $butter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::firstOrCreate(
            ['username' => 'admin_test'],
            ['name' => 'Admin Test', 'email' => 'admintest@bakery.com', 'password' => bcrypt('pass123'), 'role' => 'admin', 'status' => 'active']
        );

        $this->supplier = Supplier::firstOrCreate(
            ['name' => 'Test Supplier PO'],
            ['status' => 'active', 'contact_person' => 'Supplier Rep']
        );

        $this->flour = Ingredient::firstOrCreate(
            ['name' => 'PO Test Flour'],
            ['unit' => 'kg', 'quantity' => 50.00, 'cost' => 1.20, 'minimum_quantity' => 10.00, 'supplier_id' => $this->supplier->id]
        );

        $this->butter = Ingredient::firstOrCreate(
            ['name' => 'PO Test Butter'],
            ['unit' => 'kg', 'quantity' => 20.00, 'cost' => 4.50, 'minimum_quantity' => 5.00, 'supplier_id' => $this->supplier->id]
        );
    }

    public function test_can_create_draft_purchase_order_with_server_calculated_totals(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'order_date'  => today()->toDateString(),
            'notes'       => 'Test multi-item procurement',
            'items'       => [
                [
                    'ingredient_id'  => $this->flour->id,
                    'quantity'       => 15,
                    'purchase_price' => 1.30, // 15 * 1.30 = 19.50
                ],
                [
                    'ingredient_id'  => $this->butter->id,
                    'quantity'       => 10,
                    'purchase_price' => 5.00, // 10 * 5.00 = 50.00
                ],
            ],
        ]);

        $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->latest()->first();
        $this->assertNotNull($po);
        $response->assertRedirect(route('admin.purchase-orders.index', ['open_po' => $po->id]));

        $this->assertEquals('draft', $po->status);
        $this->assertEquals(69.50, (float) $po->total_cost); // 19.50 + 50.00 = 69.50
        $this->assertCount(2, $po->items);
    }

    public function test_can_transition_from_draft_to_ordered(): void
    {
        $po = PurchaseOrder::create([
            'po_number'   => 'PO-TEST-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'draft',
            'total_cost'  => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.order', $po->id));
        $response->assertRedirect(route('admin.purchase-orders.index', ['open_po' => $po->id]));

        $po->refresh();
        $this->assertEquals('ordered', $po->status);
    }

    public function test_receiving_ordered_po_increases_stock_updates_cost_and_creates_audit_movement(): void
    {
        // Record initial values
        $initialStock = (float) $this->flour->quantity;

        $po = PurchaseOrder::create([
            'po_number'   => 'PO-TEST-RCV-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'ordered',
            'total_cost'  => 52.00,
        ]);

        $orderItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'ingredient_id'     => $this->flour->id,
            'quantity'          => 40.00,
            'unit'              => 'kg',
            'purchase_price'    => 1.35,
            'subtotal'          => 54.00,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.receive', $po->id));
        $response->assertRedirect(route('admin.purchase-orders.index', ['open_po' => $po->id]));
        $response->assertSessionHas('success');

        $po->refresh();
        $this->flour->refresh();

        // 1. PO Status is Received
        $this->assertEquals('received', $po->status);
        $this->assertNotNull($po->received_date);

        // 2. Ingredient stock increased by exactly 40 kg
        $this->assertEquals($initialStock + 40.00, (float) $this->flour->quantity);

        // 3. Purchase cost updated to 1.35
        $this->assertEquals(1.35, (float) $this->flour->cost);

        // 4. Audit StockMovement created
        $this->assertDatabaseHas('stock_movements', [
            'ingredient_id'    => $this->flour->id,
            'type'             => 'in',
            'reference_type'   => 'purchase_order',
            'reference_id'     => $po->id,
            'reference_number' => $po->po_number,
        ]);
    }

    public function test_duplicate_receiving_is_strictly_prevented(): void
    {
        $po = PurchaseOrder::create([
            'po_number'     => 'PO-TEST-DUP-' . rand(1000, 9999),
            'supplier_id'   => $this->supplier->id,
            'order_date'    => today(),
            'received_date' => now(),
            'status'        => 'received', // Already received!
            'total_cost'    => 20.00,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'ingredient_id'     => $this->flour->id,
            'quantity'          => 20.00,
            'unit'              => 'kg',
            'purchase_price'    => 1.00,
            'subtotal'          => 20.00,
        ]);

        $this->flour->refresh();
        $stockBefore = (float) $this->flour->quantity;

        // Attempt second receive
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.receive', $po->id));
        $response->assertSessionHas('error');

        $this->flour->refresh();
        // Stock must NOT have increased!
        $this->assertEquals($stockBefore, (float) $this->flour->quantity);
    }

    public function test_invalid_state_transitions_are_rejected(): void
    {
        // 1. Cannot transition Draft directly to Received without ordering
        $poDraft = PurchaseOrder::create([
            'po_number'   => 'PO-TEST-INV1-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'draft',
            'total_cost'  => 10.00,
        ]);
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.receive', $poDraft->id));
        $response->assertSessionHas('error');
        $this->assertEquals('draft', $poDraft->fresh()->status);

        // 2. Cannot receive a Cancelled PO
        $poCancelled = PurchaseOrder::create([
            'po_number'   => 'PO-TEST-INV2-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'cancelled',
            'total_cost'  => 10.00,
        ]);
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.receive', $poCancelled->id));
        $response->assertSessionHas('error');
        $this->assertEquals('cancelled', $poCancelled->fresh()->status);
    }

    public function test_can_cancel_draft_or_ordered_po(): void
    {
        $po = PurchaseOrder::create([
            'po_number'   => 'PO-TEST-CAN-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'ordered',
            'total_cost'  => 30.00,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.cancel', $po->id));
        $response->assertRedirect(route('admin.purchase-orders.index', ['open_po' => $po->id]));

        $this->assertEquals('cancelled', $po->fresh()->status);
    }

    public function test_purchase_order_show_redirects_to_index_with_open_po_for_browser_requests(): void
    {
        $po = PurchaseOrder::create([
            'po_number'   => 'PO-SHOW-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'ordered',
            'total_cost'  => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.show', $po->id));
        $response->assertRedirect(route('admin.purchase-orders.index', ['open_po' => $po->id]));
    }

    public function test_purchase_order_edit_redirects_to_index_with_edit_po_for_browser_requests(): void
    {
        $po = PurchaseOrder::create([
            'po_number'   => 'PO-EDIT-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'draft',
            'total_cost'  => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.edit', $po->id));
        $response->assertRedirect(route('admin.purchase-orders.index', ['edit_po' => $po->id]));
    }

    public function test_purchase_order_show_returns_json_when_requested(): void
    {
        $po = PurchaseOrder::create([
            'po_number'   => 'PO-JSON-' . rand(1000, 9999),
            'supplier_id' => $this->supplier->id,
            'order_date'  => today(),
            'status'      => 'ordered',
            'total_cost'  => 50.00,
        ]);

        $response = $this->actingAs($this->admin)->getJson(route('admin.purchase-orders.show', $po->id));
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'purchase_order' => [
                'id'        => $po->id,
                'po_number' => $po->po_number,
                'status'    => 'ordered',
            ],
        ]);
    }

    public function test_purchase_orders_index_provides_ingredients_catalog_for_create_modal(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.index'));
        $response->assertStatus(200);
        $response->assertViewHas('ingredients');
        $response->assertViewHas('ingredientsJson');
        $response->assertSee('createPurchaseOrderModal');
        $response->assertSee('openCreatePurchaseOrderModal');
    }

    public function test_can_create_draft_purchase_order_from_modal_and_redirect_to_index(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.purchase-orders.store'), [
            'redirect_to' => 'index',
            'supplier_id' => $this->supplier->id,
            'order_date'  => today()->toDateString(),
            'notes'       => 'Modal draft procurement',
            'items'       => [
                [
                    'ingredient_id'  => $this->flour->id,
                    'quantity'       => 20,
                    'purchase_price' => 1.50,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.purchase-orders.index'));
        $po = PurchaseOrder::where('notes', 'Modal draft procurement')->first();
        $this->assertNotNull($po);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals(30.00, (float) $po->total_cost);
    }

    public function test_create_route_redirects_to_index_with_action_create_and_params(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.purchase-orders.create', ['ingredient_id' => $this->flour->id]));
        $response->assertRedirect(route('admin.purchase-orders.index', ['ingredient_id' => $this->flour->id, 'action' => 'create']));
    }
}
