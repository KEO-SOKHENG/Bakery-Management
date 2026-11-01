<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Cashier Som',
            'username' => 'som_cashier',
            'email' => 'som@bakery.com',
            'phone' => '+85512345678',
            'role' => 'cashier',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $this->admin = User::factory()->create([
            'name' => 'Admin Manager',
            'username' => 'admin_main',
            'email' => 'admin_main@bakery.com',
            'role' => 'admin',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);
    }

    public function test_guest_is_redirected_to_login_when_accessing_profile()
    {
        $response = $this->get(route('profile'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile_page()
    {
        $response = $this->actingAs($this->user)->get(route('profile'));

        $response->assertStatus(200);
        $response->assertSee('Cashier Som');
        $response->assertSee('som_cashier');
        $response->assertSee('som@bakery.com');
        $response->assertSee('+85512345678');
        $response->assertSee('Cashier');
    }

    public function test_user_can_update_personal_details()
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Cashier Som Updated',
            'email' => 'som.updated@bakery.com',
            'phone' => '+85598765432',
        ]);

        $response->assertRedirect(route('profile'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertEquals('Cashier Som Updated', $this->user->name);
        $this->assertEquals('som.updated@bakery.com', $this->user->email);
        $this->assertEquals('+85598765432', $this->user->phone);
    }

    public function test_user_cannot_update_email_to_another_users_email()
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Cashier Som',
            'email' => $this->admin->email, // Duplicate email
            'phone' => '+85512345678',
        ]);

        $response->assertSessionHasErrors('email');
        $this->user->refresh();
        $this->assertEquals('som@bakery.com', $this->user->email);
    }

    public function test_user_can_update_password_with_valid_current_password()
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Cashier Som',
            'email' => 'som@bakery.com',
            'current_password' => 'secret123',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ]);

        $response->assertRedirect(route('profile'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertTrue(Hash::check('newsecret123', $this->user->password));
    }

    public function test_user_cannot_update_password_with_invalid_current_password()
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Cashier Som',
            'email' => 'som@bakery.com',
            'current_password' => 'wrong_password',
            'password' => 'newsecret123',
            'password_confirmation' => 'newsecret123',
        ]);

        $response->assertSessionHasErrors('current_password');
        $this->user->refresh();
        $this->assertFalse(Hash::check('newsecret123', $this->user->password));
    }

    public function test_profile_update_ignores_unauthorized_role_modification()
    {
        $response = $this->actingAs($this->user)->put(route('profile.update'), [
            'name' => 'Cashier Som',
            'email' => 'som@bakery.com',
            'role' => 'admin', // Malicious payload
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('profile'));
        $this->user->refresh();
        $this->assertEquals('cashier', $this->user->role);
        $this->assertEquals('active', $this->user->status);
    }

    public function test_layout_renders_profile_pill_and_dropdown_navigation()
    {
        // Admin profile navigation links directly to Settings Profile card
        $responseAdmin = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('id="user_profile_pill"', false);
        $responseAdmin->assertSee('id="nav_my_profile"', false);
        $responseAdmin->assertSee(route('admin.settings') . '#profile_info', false);

        // Staff without settings management links to standard Profile route
        $responseStaff = $this->actingAs($this->user)->get(route('pos'));
        $responseStaff->assertStatus(200);
        $responseStaff->assertSee('id="nav_my_profile"', false);
        $responseStaff->assertSee(route('profile'), false);
    }

    public function test_order_management_renders_receipt_reprint_elements()
    {
        $product = Product::create([
            'name' => 'Moon Cake Classic',
            'sku' => 'MC-CLS-01',
            'price' => 5.00,
            'stock' => 50,
            'status' => 'active',
        ]);

        $order = Order::create([
            'user_id' => $this->admin->id,
            'order_number' => 'ORD-PRINT-01',
            'customer_name' => 'Vannak Heng',
            'subtotal' => 10.00,
            'total' => 10.00,
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'cash',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 5.00,
            'subtotal' => 10.00,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.orders'));

        $response->assertStatus(200);
        $response->assertSee('id="btn_print_order_receipt"', false);
        $response->assertSee('id="thermal_slip_print_area"', false);
        $response->assertSee('printOrderReceipt()', false);
        $response->assertSee('currentModalOrder = order;', false);
        $response->assertSee('data-order-code="ORD-PRINT-01"', false);
        $response->assertSee('Moon Cake Classic', false);
        $response->assertSee('id="thermal_items_body"', false);
        $response->assertSee('id="thermal_total"', false);
        $response->assertSee('id="thermal_subtotal"', false);
        $response->assertSee('id="thermal_tax"', false);
    }

    public function test_profile_update_requires_auth_and_csrf_protection()
    {
        $response = $this->put(route('profile.update'), [
            'name' => 'Attacker',
            'email' => 'attacker@evil.com',
        ]);

        $response->assertRedirect(route('login'));
    }
}

