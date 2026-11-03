<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAdminPasswordTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearResetEnv();
        Artisan::call('migrate');
    }

    protected function tearDown(): void
    {
        $this->clearResetEnv();
        parent::tearDown();
    }

    protected function setResetEnv(array $vars): void
    {
        foreach ($vars as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    protected function clearResetEnv(): void
    {
        $keys = [
            'RESET_ADMIN_PASSWORD',
            'RESET_ADMIN_USERNAME',
            'RESET_ADMIN_EMAIL',
            'RESET_ADMIN_NEW_PASSWORD',
            'RESET_ADMIN_PASSWORD_VALUE',
            'RESET_ADMIN_MUST_CHANGE_PASSWORD',
            'BOOTSTRAP_ADMIN',
            'BOOTSTRAP_ADMIN_NAME',
            'BOOTSTRAP_ADMIN_USERNAME',
            'BOOTSTRAP_ADMIN_EMAIL',
            'BOOTSTRAP_ADMIN_PASSWORD',
        ];

        foreach ($keys as $key) {
            putenv("{$key}=");
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    /**
     * Test reset fails when RESET_ADMIN_PASSWORD is not set or false and --force is not provided.
     */
    public function test_reset_fails_when_disabled(): void
    {
        $this->setResetEnv(['RESET_ADMIN_PASSWORD' => 'false']);

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Admin password reset is disabled', $output);
    }

    /**
     * Test validation fails when required inputs are missing or too short.
     */
    public function test_reset_fails_when_required_inputs_are_invalid(): void
    {
        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'ad', // too short (<3)
            'RESET_ADMIN_EMAIL'        => 'not-an-email',
            'RESET_ADMIN_NEW_PASSWORD' => 'short', // too short (<8)
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Admin password reset validation failed', $output);
    }

    /**
     * Test reset fails safely when the specified account does not exist.
     */
    public function test_reset_fails_when_account_does_not_exist(): void
    {
        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'nonexistent_admin',
            'RESET_ADMIN_EMAIL'        => 'nonexistent@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD' => 'SecurePass#2026',
        ]);

        $initialUserCount = User::count();

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Account not found', $output);
        $this->assertStringContainsString('will not create new accounts', $output);
        $this->assertSame($initialUserCount, User::count());
    }

    /**
     * Test reset fails safely when username and email match different accounts.
     */
    public function test_reset_fails_safely_when_username_and_email_mismatch(): void
    {
        $user1 = User::create([
            'name'                 => 'Admin One',
            'username'             => 'admin_one',
            'email'                => 'admin_one@bakery.com',
            'password'             => Hash::make('PasswordOne#123'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $user2 = User::create([
            'name'                 => 'Admin Two',
            'username'             => 'admin_two',
            'email'                => 'admin_two@bakery.com',
            'password'             => Hash::make('PasswordTwo#123'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'admin_one',
            'RESET_ADMIN_EMAIL'        => 'admin_two@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD' => 'AttemptedNewPass#999',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Account mismatch', $output);

        // Verify neither user was modified
        $this->assertTrue(Hash::check('PasswordOne#123', $user1->fresh()->password));
        $this->assertTrue(Hash::check('PasswordTwo#123', $user2->fresh()->password));
    }

    /**
     * Test reset fails safely when the account is not an admin (cannot promote or modify non-admins).
     */
    public function test_reset_fails_safely_on_non_admin_account(): void
    {
        $cashier = User::create([
            'name'                 => 'Store Cashier',
            'username'             => 'store_cashier',
            'email'                => 'cashier@bakery.com',
            'password'             => Hash::make('OriginalCashierPass#123'),
            'role'                 => 'cashier',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'store_cashier',
            'RESET_ADMIN_EMAIL'        => 'cashier@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD' => 'AttemptedCashierReset#999',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Access denied', $output);
        $this->assertStringContainsString('strictly restricted to administrator accounts', $output);

        $reloaded = $cashier->fresh();
        $this->assertSame('cashier', $reloaded->role);
        $this->assertTrue(Hash::check('OriginalCashierPass#123', $reloaded->password));
    }

    /**
     * Test successful administrator password reset with must_change_password enabled by default.
     */
    public function test_successful_admin_password_reset_preserves_forced_change(): void
    {
        $admin = User::create([
            'name'                 => 'System Administrator',
            'username'             => 'sys_admin',
            'email'                => 'sys_admin@bakery.com',
            'password'             => Hash::make('OldExpiredPassword#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'sys_admin',
            'RESET_ADMIN_EMAIL'        => 'sys_admin@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD' => 'NewTemporaryPassword#123',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('password reset successfully', $output);
        $this->assertStringContainsString('sys_admin', $output);
        $this->assertStringContainsString('sys_admin@bakery.com', $output);

        $reloaded = $admin->fresh();
        $this->assertTrue(Hash::check('NewTemporaryPassword#123', $reloaded->password));
        $this->assertTrue($reloaded->must_change_password);
        $this->assertSame('active', $reloaded->status);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action'  => 'admin_password_reset',
            'user_id' => $admin->id,
        ]);
    }

    /**
     * Test fallback to BOOTSTRAP_ADMIN_* environment variables when RESET_ADMIN_* are omitted.
     */
    public function test_reset_falls_back_to_bootstrap_env_variables(): void
    {
        $admin = User::create([
            'name'                 => 'Production Admin',
            'username'             => 'prod_admin',
            'email'                => 'prod_admin@bakery.com',
            'password'             => Hash::make('OldUnknownPass#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => true,
        ]);

        // Simulate user setting only RESET_ADMIN_PASSWORD=true with existing BOOTSTRAP_ADMIN_* vars in Render
        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'BOOTSTRAP_ADMIN_USERNAME' => 'prod_admin',
            'BOOTSTRAP_ADMIN_EMAIL'    => 'prod_admin@bakery.com',
            'BOOTSTRAP_ADMIN_PASSWORD' => 'RenderBootstrapSecret#2026',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $this->assertSame(0, $exitCode);

        $reloaded = $admin->fresh();
        $this->assertTrue(Hash::check('RenderBootstrapSecret#2026', $reloaded->password));
        $this->assertTrue($reloaded->must_change_password);
    }

    /**
     * Test that RESET_ADMIN_MUST_CHANGE_PASSWORD=false sets must_change_password to false.
     */
    public function test_reset_with_must_change_password_false(): void
    {
        $admin = User::create([
            'name'                 => 'Direct Admin',
            'username'             => 'direct_admin',
            'email'                => 'direct@bakery.com',
            'password'             => Hash::make('OldPass#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => true,
        ]);

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'              => 'true',
            'RESET_ADMIN_USERNAME'              => 'direct_admin',
            'RESET_ADMIN_EMAIL'                 => 'direct@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD'          => 'PermanentDirectPass#2026',
            'RESET_ADMIN_MUST_CHANGE_PASSWORD' => 'false',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $this->assertSame(0, $exitCode);

        $reloaded = $admin->fresh();
        $this->assertTrue(Hash::check('PermanentDirectPass#2026', $reloaded->password));
        $this->assertFalse($reloaded->must_change_password);
    }

    /**
     * Test complete lifecycle: password reset via env -> login with temp pass -> forced change -> dashboard.
     */
    public function test_complete_login_and_forced_password_change_flow_after_reset(): void
    {
        $admin = User::create([
            'name'                 => 'Flow Administrator',
            'username'             => 'flow_admin',
            'email'                => 'flow_admin@bakery.com',
            'password'             => Hash::make('MismatchedOldPass#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => true,
        ]);

        // 1. Reset temporary password via environment variable command
        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'              => 'true',
            'RESET_ADMIN_USERNAME'              => 'flow_admin',
            'RESET_ADMIN_EMAIL'                 => 'flow_admin@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD'          => 'TemporaryResetPassword#123',
            'RESET_ADMIN_MUST_CHANGE_PASSWORD' => 'true',
        ]);

        $this->assertSame(0, Artisan::call('admin:reset-password'));

        // 2. Admin logs in with the newly reset temporary password
        $loginResponse = $this->post(route('login.post'), [
            'username' => 'flow_admin',
            'password' => 'TemporaryResetPassword#123',
        ]);

        $loginResponse->assertRedirect(route('password.change'));
        $loginResponse->assertSessionHas('warning');

        // 3. Navigation to protected admin routes is blocked by EnsurePasswordNotExpired middleware
        $blockedResponse = $this->actingAs($admin->fresh())->get(route('admin.dashboard'));
        $blockedResponse->assertRedirect(route('password.change'));

        // 4. Entering the same password triggers validation error
        $samePassResponse = $this->actingAs($admin->fresh())->post(route('password.change.update'), [
            'current_password'      => 'TemporaryResetPassword#123',
            'password'              => 'TemporaryResetPassword#123',
            'password_confirmation' => 'TemporaryResetPassword#123',
        ]);
        $samePassResponse->assertSessionHasErrors(['password']);

        // 5. Admin enters correct temporary password and a new permanent password
        $changeResponse = $this->actingAs($admin->fresh())->post(route('password.change.update'), [
            'current_password'      => 'TemporaryResetPassword#123',
            'password'              => 'MyNewPermanentPassword#2026',
            'password_confirmation' => 'MyNewPermanentPassword#2026',
        ]);

        $changeResponse->assertRedirect(route('admin.dashboard'));
        $changeResponse->assertSessionHas('success');

        // 6. Verify database state
        $finalUser = $admin->fresh();
        $this->assertFalse($finalUser->must_change_password);
        $this->assertTrue(Hash::check('MyNewPermanentPassword#2026', $finalUser->password));

        // 7. Protected admin dashboard is now fully accessible
        $dashboardResponse = $this->actingAs($finalUser)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
    }

    /**
     * Test docker/entrypoint.sh contains the admin:reset-password integration.
     */
    public function test_entrypoint_script_contains_reset_integration(): void
    {
        $entrypointPath = base_path('docker/entrypoint.sh');
        $this->assertFileExists($entrypointPath);
        $content = file_get_contents($entrypointPath);

        $this->assertStringContainsString('php artisan admin:reset-password', $content);
        $this->assertStringContainsString('RESET_ADMIN_PASSWORD', $content);
    }

    /**
     * Test passwords are never printed to console or logs during reset.
     */
    public function test_password_is_never_printed_to_console(): void
    {
        User::create([
            'name'                 => 'Secret Admin',
            'username'             => 'secret_admin',
            'email'                => 'secret_admin@bakery.com',
            'password'             => Hash::make('PriorSecret#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $plainPassword = 'UltraTopSecretPassword#2026';

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'     => 'true',
            'RESET_ADMIN_USERNAME'     => 'secret_admin',
            'RESET_ADMIN_EMAIL'        => 'secret_admin@bakery.com',
            'RESET_ADMIN_NEW_PASSWORD' => $plainPassword,
        ]);

        Artisan::call('admin:reset-password');
        $output = Artisan::output();

        $this->assertStringNotContainsString($plainPassword, $output);
    }

    /**
     * Test reset succeeds when username and email differ only in casing (PostgreSQL case-insensitive compatibility).
     */
    public function test_reset_is_case_insensitive_for_username_and_email(): void
    {
        $admin = User::create([
            'name'                 => 'Cased Admin',
            'username'             => 'cased_admin',
            'email'                => 'cased_admin@bakery.com',
            'password'             => Hash::make('PriorSecret#000'),
            'role'                 => 'admin',
            'status'               => 'active',
            'must_change_password' => false,
        ]);

        $this->setResetEnv([
            'RESET_ADMIN_PASSWORD'              => 'true',
            'RESET_ADMIN_USERNAME'              => 'CASED_ADMIN',
            'RESET_ADMIN_EMAIL'                 => 'Cased_Admin@Bakery.com',
            'RESET_ADMIN_NEW_PASSWORD'          => 'NewCasedPassword#2026',
            'RESET_ADMIN_MUST_CHANGE_PASSWORD' => 'false',
        ]);

        $exitCode = Artisan::call('admin:reset-password');
        $this->assertSame(0, $exitCode);

        $reloaded = $admin->fresh();
        $this->assertTrue(Hash::check('NewCasedPassword#2026', $reloaded->password));
        $this->assertFalse($reloaded->must_change_password);
    }
}
