<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $cashier;
    protected User $baker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'System Admin',
            'username' => 'sysadmin',
            'email' => 'admin@bakery.com',
            'role' => 'admin',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Bakery Manager',
            'username' => 'bakerymanager',
            'email' => 'manager@bakery.com',
            'role' => 'manager',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $this->cashier = User::factory()->create([
            'name' => 'Bakery Cashier',
            'username' => 'bakerycashier',
            'email' => 'cashier@bakery.com',
            'role' => 'cashier',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);

        $this->baker = User::factory()->create([
            'name' => 'Head Baker',
            'username' => 'headbaker',
            'email' => 'baker@bakery.com',
            'role' => 'baker',
            'status' => 'active',
            'password' => Hash::make('secret123'),
        ]);
    }

    /** 1. Admin can create user. */
    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.users.store'), [
            'name' => 'New Cashier',
            'username' => 'newcashier',
            'email' => 'newcashier@bakery.com',
            'phone' => '012999888',
            'password' => 'password123',
            'role' => 'cashier',
            'status' => 'active',
            'must_change_password' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'username' => 'newcashier',
            'email' => 'newcashier@bakery.com',
            'role' => 'cashier',
            'status' => 'active',
            'must_change_password' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_created',
        ]);
    }

    /** 2. Manager cannot create Admin or access user management. */
    public function test_manager_cannot_create_admin(): void
    {
        $response = $this->actingAs($this->manager)->post(route('admin.users.store'), [
            'name' => 'Rogue Admin',
            'username' => 'rogueadmin',
            'email' => 'rogue@bakery.com',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Manager is blocked by role:admin middleware
        $this->assertDatabaseMissing('users', ['username' => 'rogueadmin']);
    }

    /** 3. Cashier cannot create users. */
    public function test_cashier_cannot_create_users(): void
    {
        $response = $this->actingAs($this->cashier)->post(route('admin.users.store'), [
            'name' => 'Test User',
            'username' => 'testuser',
            'email' => 'test@bakery.com',
            'password' => 'password123',
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('users', ['username' => 'testuser']);
    }

    /** 4. Baker cannot create users. */
    public function test_baker_cannot_create_users(): void
    {
        $response = $this->actingAs($this->baker)->post(route('admin.users.store'), [
            'name' => 'Test User',
            'username' => 'testuser2',
            'email' => 'test2@bakery.com',
            'password' => 'password123',
            'role' => 'baker',
            'status' => 'active',
        ]);

        $this->assertDatabaseMissing('users', ['username' => 'testuser2']);
    }

    /** 5. Admin can assign role. */
    public function test_admin_can_assign_role(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEquals('manager', $user->fresh()->role);
    }

    /** 6. Unauthorized user cannot modify role. */
    public function test_unauthorized_user_cannot_modify_role(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->cashier)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->assertEquals('cashier', $user->fresh()->role);
    }

    /** 7. Admin can deactivate user. */
    public function test_admin_can_deactivate_user(): void
    {
        $user = User::factory()->create([
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.users.updateStatus', $user), [
            'status' => 'inactive',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEquals('inactive', $user->fresh()->status);
    }

    /** 8. User cannot login after deactivation or suspension. */
    public function test_user_cannot_login_after_deactivation(): void
    {
        $deactivated = User::factory()->create([
            'username' => 'inactiveuser',
            'password' => Hash::make('password123'),
            'status' => 'inactive',
            'role' => 'cashier',
        ]);

        $response = $this->post(route('login.post'), [
            'username' => 'inactiveuser',
            'password' => 'password123',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertGuest();

        // Also test suspended
        $suspended = User::factory()->create([
            'username' => 'suspendeduser',
            'password' => Hash::make('password123'),
            'status' => 'suspended',
            'role' => 'cashier',
        ]);

        $response2 = $this->post(route('login.post'), [
            'username' => 'suspendeduser',
            'password' => 'password123',
        ]);

        $response2->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    /** 9. Admin cannot deactivate last active Admin. */
    public function test_admin_cannot_deactivate_last_active_admin(): void
    {
        // Only 1 admin exists in setup ($this->admin)
        $this->assertEquals(1, User::where('role', 'admin')->where('status', 'active')->count());

        $response = $this->actingAs($this->admin)->post(route('admin.users.updateStatus', $this->admin), [
            'status' => 'inactive',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals('active', $this->admin->fresh()->status);

        // Also cannot demote role of last active admin
        $response2 = $this->actingAs($this->admin)->put(route('admin.users.update', $this->admin), [
            'name' => $this->admin->name,
            'username' => $this->admin->username,
            'email' => $this->admin->email,
            'role' => 'manager',
            'status' => 'active',
        ]);

        $response2->assertSessionHas('error');
        $this->assertEquals('admin', $this->admin->fresh()->role);
    }

    /** 10. Admin can reset password. */
    public function test_admin_can_reset_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('oldpassword'),
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.users.resetPassword', $user), [
            'password' => 'newSecretPass789',
            'must_change_password' => '1',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue(Hash::check('newSecretPass789', $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
    }

    /** 11. Temporary password forces password change on next login. */
    public function test_temporary_password_forces_password_change(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'username' => 'tempuser',
            'password' => Hash::make('temp123'),
            'must_change_password' => true,
            'status' => 'active',
            'role' => 'cashier',
        ]);

        // Login with temporary credentials
        $loginResponse = $this->post(route('login.post'), [
            'username' => 'tempuser',
            'password' => 'temp123',
        ]);

        $loginResponse->assertRedirect(route('password.change'));

        // Attempt to access dashboard should be blocked and redirected to change password
        $dashboardResponse = $this->actingAs($user)->get(route('cashier.dashboard'));
        $dashboardResponse->assertRedirect(route('password.change'));

        // User updates password
        $changeResponse = $this->actingAs($user)->post(route('password.change.update'), [
            'current_password' => 'temp123',
            'password' => 'newPermPass456',
            'password_confirmation' => 'newPermPass456',
        ]);

        $changeResponse->assertRedirect(route('cashier.dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('newPermPass456', $user->fresh()->password));
    }

    /** 12. Search works. */
    public function test_search_works(): void
    {
        User::factory()->create([
            'name' => 'Searchable Staff',
            'username' => 'findme123',
            'email' => 'findme@bakery.com',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['search' => 'findme123']));
        $response->assertStatus(200);
        $response->assertSee('findme123');
        $response->assertSee('Searchable Staff');
    }

    /** 13. Role filter works. */
    public function test_role_filter_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['role' => 'baker']));
        $response->assertStatus(200);
        $response->assertSee('headbaker');
    }

    /** 14. Status filter works. */
    public function test_status_filter_works(): void
    {
        $inactive = User::factory()->create([
            'name' => 'Inactive Guy',
            'username' => 'inactiveguy',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.users.index', ['status' => 'inactive']));
        $response->assertStatus(200);
        $response->assertSee('inactiveguy');
    }

    /** 15. Permissions are enforced server-side. */
    public function test_permissions_are_enforced_server_side(): void
    {
        // Admin has all permissions
        $this->assertTrue($this->admin->hasPermission('users.create'));
        $this->assertTrue($this->admin->hasPermission('orders.delete'));

        // Cashier role default does not have users.create or products.delete
        $this->assertFalse($this->cashier->hasPermission('users.create'));
        $this->assertFalse($this->cashier->hasPermission('products.delete'));

        // Cashier has pos.access by default
        $this->assertTrue($this->cashier->hasPermission('pos.access'));

        // Custom override: grant Cashier reports.view
        $this->cashier->custom_permissions = ['reports.view' => true];
        $this->cashier->save();
        $this->assertTrue($this->cashier->fresh()->hasPermission('reports.view'));

        // Custom override: explicitly deny pos.access
        $this->cashier->custom_permissions = ['pos.access' => false];
        $this->cashier->save();
        $this->assertFalse($this->cashier->fresh()->hasPermission('pos.access'));
    }

    /** 16. Historical transactions remain intact. */
    public function test_historical_transactions_remain_intact(): void
    {
        $customer = Customer::create([
            'name' => 'Historical Customer',
            'phone' => '012888999',
            'email' => 'historical@example.com',
            'loyalty_points' => 10,
            'loyalty_tier' => 'standard',
            'status' => 'active',
        ]);

        $order = Order::create([
            'order_number' => 'ORD-TEST-999',
            'user_id' => $this->cashier->id,
            'customer_id' => $customer->id,
            'total_amount' => 50.00,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);

        // Deleting cashier succeeds and preserves linked historical order transactions with user_id null
        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $this->cashier));
        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');

        // Verify user is deleted from users table
        $this->assertDatabaseMissing('users', ['id' => $this->cashier->id]);

        // Historical order still exists accurately in database with null user reference
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'user_id' => null]);
    }

    /** 17. Existing authentication still works. */
    public function test_existing_authentication_still_works(): void
    {
        $response = $this->post(route('login.post'), [
            'username' => 'sysadmin',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    /** 18. Existing RBAC still works. */
    public function test_existing_rbac_still_works(): void
    {
        // Admin can access admin dashboard
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertStatus(200);

        // Manager can access manager dashboard
        $this->actingAs($this->manager)->get(route('manager.dashboard'))->assertStatus(200);

        // Cashier can access cashier dashboard
        $this->actingAs($this->cashier)->get(route('cashier.dashboard'))->assertStatus(200);

        // Cashier blocked from admin dashboard
        $this->actingAs($this->cashier)->get(route('admin.dashboard'))->assertRedirect(route('cashier.dashboard'));
    }

    /** 19. Admin can delete user without historical records (no deliveries() bug). */
    public function test_admin_can_delete_user_without_historical_records(): void
    {
        $userToDelete = User::factory()->create([
            'name' => 'Temporary Staff',
            'username' => 'tempstaff20',
            'email' => 'tempstaff20@bakery.com',
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('admin.users.destroy', $userToDelete));

        $response->assertRedirect(route('admin.users.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('users', ['id' => $userToDelete->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_deleted',
        ]);
    }

    /** 20. Admin can view user show profile page. */
    public function test_admin_can_view_user_profile_page(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.users.show', $this->cashier));

        $response->assertStatus(200);
        $response->assertViewIs('admin.users.show');
        $response->assertSee($this->cashier->name);
        $response->assertSee('@' . $this->cashier->username);
        $response->assertSee('Orders Handled');
        $response->assertSee('Effective Role Permissions');
    }

    /** 21. Admin can uncheck must_change_password on update. */
    public function test_admin_can_uncheck_must_change_password_on_update(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'role' => 'cashier',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            // must_change_password omitted (unchecked)
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    /** 22. Admin can revoke default permission via form submission. */
    public function test_admin_can_revoke_default_permission_via_form(): void
    {
        // Cashier has pos.access by default
        $this->assertTrue($this->cashier->hasPermission('pos.access'));

        // Form submission for Cashier with only 'orders.view' checked (pos.access unchecked/omitted)
        $response = $this->actingAs($this->admin)->put(route('admin.users.updatePermissions', $this->cashier), [
            'permissions' => [
                'orders.view' => '1',
            ],
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $freshCashier = $this->cashier->fresh();
        // pos.access was a default for Cashier, but was omitted from the form, so it was revoked
        $this->assertFalse($freshCashier->hasPermission('pos.access'));
        $this->assertTrue($freshCashier->hasPermission('orders.view'));
    }
}
