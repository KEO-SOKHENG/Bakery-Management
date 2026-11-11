<?php

namespace Tests\Feature;

use App\Models\LoginApprovalRequest;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\LoginApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginApprovalTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret-pass-123';
    private const OWNER = 'owner@bakery.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['login_approval.owner_email' => self::OWNER]);
    }

    // ── Helpers ──

    private function user(string $role, array $attrs = []): User
    {
        $name = $attrs['username'] ?? $role . '_' . Str::lower(Str::random(5));

        return User::create(array_merge([
            'name' => ucfirst($name),
            'username' => $name,
            'email' => $name . '@bakery.test',
            'password' => Hash::make(self::PASSWORD),
            'role' => $role,
            'status' => 'active',
        ], $attrs));
    }

    private function activeDevice(User $user, array $attrs = []): UserDevice
    {
        return UserDevice::create(array_merge([
            'user_id' => $user->id,
            'session_key' => hash('sha256', Str::random(40)),
            'browser' => 'Chrome',
            'platform' => 'Windows',
            'device_type' => 'Desktop',
            'login_method' => 'no_active_user',
            'last_seen_at' => now(),
        ], $attrs));
    }

    private function login(User|string $who, string $password = self::PASSWORD, array $extra = [])
    {
        $username = $who instanceof User ? $who->username : $who;

        return $this->withHeader('User-Agent', 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1')
            ->post(route('login.post'), array_merge(['username' => $username, 'password' => $password], $extra));
    }

    private function pendingRequest(): LoginApprovalRequest
    {
        return LoginApprovalRequest::where('status', 'pending')->latest()->firstOrFail();
    }

    private function asGuest(): void
    {
        // One test client stands in for several browsers: drop the previous browser's session.
        Auth::logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    // ── Gate decisions ──

    public function test_login_is_immediate_when_nobody_else_is_active(): void
    {
        $cashier = $this->user('cashier');

        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));

        $this->assertAuthenticatedAs($cashier);
        $this->assertDatabaseHas('user_devices', ['user_id' => $cashier->id, 'login_method' => 'no_active_user', 'revoked_at' => null]);
        $this->assertSame(0, LoginApprovalRequest::count());
    }

    public function test_login_waits_for_approval_when_an_approver_is_active(): void
    {
        $this->activeDevice($this->user('admin'));
        $cashier = $this->user('cashier');

        $this->login($cashier)->assertRedirect(route('login.approval.wait'));

        $this->assertGuest();
        $request = $this->pendingRequest();
        $this->assertSame($cashier->id, $request->user_id);
        $this->assertSame('Safari', $request->browser);
        $this->assertSame('iPadOS', $request->platform);
        $this->assertEqualsWithDelta(now()->addSeconds(120)->timestamp, $request->expires_at->timestamp, 2);
    }

    public function test_wrong_password_never_creates_a_request(): void
    {
        $this->activeDevice($this->user('admin'));
        $cashier = $this->user('cashier');

        $this->login($cashier, 'wrong-password')->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame(0, LoginApprovalRequest::count());
    }

    public function test_stale_revoked_or_inactive_sessions_do_not_count_as_active(): void
    {
        $this->activeDevice($this->user('admin'), ['last_seen_at' => now()->subMinutes(6)]);
        $this->activeDevice($this->user('admin'), ['revoked_at' => now()]);
        $this->activeDevice($this->user('admin', ['status' => 'suspended']));
        $cashier = $this->user('cashier');

        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));
        $this->assertAuthenticatedAs($cashier);
    }

    public function test_the_users_own_other_session_does_not_require_approval(): void
    {
        $cashier = $this->user('cashier');
        $this->activeDevice($cashier);

        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));
        $this->assertAuthenticatedAs($cashier);
    }

    public function test_login_proceeds_when_active_users_cannot_approve(): void
    {
        $this->activeDevice($this->user('cashier'));
        $baker = $this->user('baker');

        $this->login($baker)->assertRedirect(route('admin.production'));

        $this->assertAuthenticatedAs($baker);
        $this->assertDatabaseHas('user_devices', ['user_id' => $baker->id, 'login_method' => 'no_approver_online']);
    }

    public function test_owner_bypasses_approval_but_still_needs_the_password(): void
    {
        $this->activeDevice($this->user('admin'));
        $owner = $this->user('manager', ['username' => 'owner', 'email' => 'Owner@Bakery.test']);

        $this->login('owner', 'wrong-password')->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->login('owner')->assertRedirect(route('manager.dashboard'));
        $this->assertAuthenticatedAs($owner);
        $this->assertDatabaseHas('user_devices', ['user_id' => $owner->id, 'login_method' => 'owner']);
        $this->assertSame(0, LoginApprovalRequest::count());
    }

    public function test_without_owner_email_configured_nobody_bypasses(): void
    {
        config(['login_approval.owner_email' => null]);
        $this->activeDevice($this->user('admin'));
        $this->user('manager', ['username' => 'owner', 'email' => self::OWNER]);

        $this->login('owner')->assertRedirect(route('login.approval.wait'));
        $this->assertGuest();
    }

    public function test_suspended_user_is_blocked_before_any_request(): void
    {
        $this->activeDevice($this->user('admin'));
        $user = $this->user('cashier', ['status' => 'suspended']);

        $this->login($user)->assertSessionHasErrors(['username' => 'Your account has been suspended. Please contact an administrator.']);

        $this->assertGuest();
        $this->assertSame(0, LoginApprovalRequest::count());
    }

    public function test_feature_switch_off_restores_plain_login(): void
    {
        config(['login_approval.enabled' => false]);
        $this->activeDevice($this->user('admin'));
        $cashier = $this->user('cashier');

        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));
        $this->assertAuthenticatedAs($cashier);
    }

    // ── Approver endpoints ──

    public function test_approver_sees_card_details_and_non_approver_sees_nothing(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $cashier = $this->user('cashier', ['name' => 'Dara Cashier']);
        $this->login($cashier);
        $this->asGuest();

        $this->actingAs($admin)->getJson(route('login-approvals.pending'))
            ->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0.user', 'Dara Cashier')
            ->assertJsonPath('requests.0.browser', 'Safari')
            ->assertJsonPath('requests.0.device', 'Tablet')
            ->assertJsonPath('requests.0.ip', '127.0.0.1')
            ->assertJsonMissingPath('requests.0.token_hash');

        $this->asGuest();
        $this->actingAs($this->user('cashier'))->getJson(route('login-approvals.pending'))
            ->assertOk()->assertJsonCount(0, 'requests');
    }

    public function test_non_approver_cannot_approve(): void
    {
        $this->activeDevice($this->user('admin'));
        $this->login($this->user('cashier'));
        $id = $this->pendingRequest()->id;
        $this->asGuest();

        $this->actingAs($this->user('baker'))->postJson(route('login-approvals.approve', $id))->assertForbidden();

        $this->assertSame('pending', LoginApprovalRequest::find($id)->status);
    }

    public function test_approve_requires_csrf_token(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier'));
        $id = $this->pendingRequest()->id;
        $this->asGuest();

        $this->withMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->app->instance('env', 'production');

        $this->actingAs($admin)->post(route('login-approvals.approve', $id))->assertStatus(419);
        $this->assertSame('pending', LoginApprovalRequest::find($id)->status);
    }

    public function test_a_user_cannot_approve_their_own_request(): void
    {
        $manager = $this->user('manager');
        $this->activeDevice($this->user('admin'));
        $this->login($manager);
        $id = $this->pendingRequest()->id;
        $this->asGuest();

        $this->actingAs($manager)->postJson(route('login-approvals.approve', $id))->assertForbidden();
    }

    // ── Full flow ──

    public function test_approved_login_signs_in_exactly_once(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $cashier = $this->user('cashier');

        $this->login($cashier)->assertRedirect(route('login.approval.wait'));
        $pending = session(LoginApprovalService::SESSION_PENDING);
        $this->get(route('login.approval.wait'))->assertOk()->assertSee('Login approval required')->assertDontSee(self::OWNER);
        $this->getJson(route('login.approval.status'))->assertJsonPath('status', 'pending');

        $this->asGuest();
        $this->actingAs($admin)->postJson(route('login-approvals.approve', $pending['id']))->assertOk()->assertJsonPath('status', 'approved');
        $this->asGuest();

        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->getJson(route('login.approval.status'))->assertJsonPath('status', 'approved');

        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->post(route('login.approval.complete'))
            ->assertRedirect(route('cashier.dashboard'));

        $this->assertAuthenticatedAs($cashier);
        $this->assertDatabaseHas('user_devices', ['user_id' => $cashier->id, 'login_method' => 'approved', 'approval_request_id' => $pending['id']]);
        $this->assertSame('consumed', LoginApprovalRequest::find($pending['id'])->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_approval_approved', 'user_id' => $admin->id]);

        // Replaying the same token is refused.
        $this->asGuest();
        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->post(route('login.approval.complete'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_approval_cannot_be_redeemed_without_the_exact_token(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier'));
        $pending = session(LoginApprovalService::SESSION_PENDING);
        $this->asGuest();
        app(LoginApprovalService::class)->decide($pending['id'], $admin, true);

        // Another browser knows the request ID but not the token.
        $this->flushSession();
        $this->withSession([LoginApprovalService::SESSION_PENDING => ['id' => $pending['id'], 'token' => Str::random(64)]])
            ->post(route('login.approval.complete'))
            ->assertRedirect(route('login'));
        $this->assertGuest();

        // A browser with no pending request at all.
        $this->flushSession();
        $this->post(route('login.approval.complete'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertSame('approved', LoginApprovalRequest::find($pending['id'])->status);
    }

    public function test_rejected_login_cannot_sign_in(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier'));
        $pending = session(LoginApprovalService::SESSION_PENDING);
        $this->asGuest();

        $this->actingAs($admin)->postJson(route('login-approvals.reject', $pending['id']))->assertOk()->assertJsonPath('status', 'rejected');
        $this->asGuest();

        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->getJson(route('login.approval.status'))->assertJsonPath('status', 'rejected');
        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->post(route('login.approval.complete'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'login_approval_rejected']);
    }

    public function test_request_expires_after_two_minutes_and_cannot_be_approved(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier'));
        $pending = session(LoginApprovalService::SESSION_PENDING);

        $this->travel(121)->seconds();
        $this->activeDevice($admin, ['last_seen_at' => now()]);

        $this->getJson(route('login.approval.status'))->assertJsonPath('status', 'expired');
        $this->asGuest();

        $this->actingAs($admin)->getJson(route('login-approvals.pending'))->assertJsonCount(0, 'requests');
        $this->actingAs($admin)->postJson(route('login-approvals.approve', $pending['id']))->assertStatus(409);
        $this->assertSame('expired', LoginApprovalRequest::find($pending['id'])->status);
    }

    public function test_approval_must_be_redeemed_promptly(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier'));
        $pending = session(LoginApprovalService::SESSION_PENDING);
        $this->asGuest();
        app(LoginApprovalService::class)->decide($pending['id'], $admin, true);

        $this->travel(121)->seconds();

        $this->withSession([LoginApprovalService::SESSION_PENDING => $pending])
            ->post(route('login.approval.complete'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_cancel_ends_the_pending_request(): void
    {
        $this->activeDevice($this->user('admin'));
        $this->login($this->user('cashier'));
        $id = $this->pendingRequest()->id;

        $this->post(route('login.approval.cancel'))->assertRedirect(route('login'));

        $this->assertSame('cancelled', LoginApprovalRequest::find($id)->status);
    }

    public function test_a_new_attempt_supersedes_the_previous_pending_request(): void
    {
        $this->activeDevice($this->user('admin'));
        $cashier = $this->user('cashier');

        $this->login($cashier);
        $this->login($cashier);

        $this->assertSame(1, LoginApprovalRequest::where('status', 'pending')->count());
        $this->assertSame(1, LoginApprovalRequest::where('status', 'cancelled')->count());
    }

    // ── Devices ──

    public function test_revoked_device_is_signed_out_on_its_next_request(): void
    {
        $cashier = $this->user('cashier');
        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));
        $device = UserDevice::where('user_id', $cashier->id)->firstOrFail();

        $admin = $this->user('admin');
        $device->update(['revoked_at' => now(), 'revoked_by' => $admin->id, 'revoke_reason' => 'revoked']);

        $this->get(route('cashier.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_admin_can_revoke_another_device_but_not_their_own(): void
    {
        $admin = $this->user('admin');
        $this->login($admin)->assertRedirect(route('admin.dashboard'));
        $own = UserDevice::where('user_id', $admin->id)->firstOrFail();
        $other = $this->activeDevice($this->user('cashier'));

        $this->get(route('admin.security'))->assertOk()->assertSee('Active Devices')->assertSee('This device');

        $this->post(route('admin.security.revoke', $own))->assertSessionHas('error');
        $this->assertNull($own->fresh()->revoked_at);

        $this->post(route('admin.security.revoke', $other))->assertSessionHas('success');
        $this->assertNotNull($other->fresh()->revoked_at);
        $this->assertSame($admin->id, $other->fresh()->revoked_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'device_revoked']);
    }

    public function test_security_page_is_admin_only(): void
    {
        $this->actingAs($this->user('manager'))->get(route('admin.security'))->assertRedirect();
        $this->actingAs($this->user('cashier'))->get(route('admin.security'))->assertRedirect();
    }

    public function test_logout_ends_the_device_record(): void
    {
        $cashier = $this->user('cashier');
        $this->login($cashier);

        $this->get(route('logout'))->assertRedirect(route('login'));

        $this->assertDatabaseHas('user_devices', ['user_id' => $cashier->id, 'revoke_reason' => 'logout']);
        $this->assertFalse(app(LoginApprovalService::class)->otherUsersActive($this->user('admin')));
    }

    public function test_session_from_before_this_feature_is_registered_not_logged_out(): void
    {
        $manager = $this->user('manager');

        // A real pre-existing session: login key present, no device key.
        $this->withSession([Auth::guard()->getName() => $manager->id])
            ->get(route('manager.dashboard'))->assertOk();

        $this->assertDatabaseHas('user_devices', ['user_id' => $manager->id, 'login_method' => 'existing_session']);
    }

    public function test_remember_me_is_only_issued_to_the_owner(): void
    {
        $recaller = Auth::guard()->getRecallerName();

        $cashier = $this->user('cashier');
        $this->login($cashier, self::PASSWORD, ['remember' => 'on'])->assertCookieMissing($recaller);
        $this->asGuest();
        $this->flushSession();

        $this->user('admin', ['username' => 'owner', 'email' => self::OWNER]);
        $this->login('owner', self::PASSWORD, ['remember' => 'on'])->assertCookie($recaller);
    }

    // ── UI ──

    public function test_approval_script_loads_only_for_approvers_and_owner_email_never_renders(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('js/login-approval.js', false)->assertDontSee(self::OWNER);

        $this->asGuest();
        $this->actingAs($this->user('cashier'))->get(route('cashier.dashboard'))
            ->assertOk()->assertDontSee('js/login-approval.js', false);

        $this->asGuest();
        $this->get(route('login'))->assertOk()->assertDontSee(self::OWNER);
    }

    public function test_user_agent_parsing_is_display_only(): void
    {
        $info = LoginApprovalService::parseUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36 Edg/130.0');
        $this->assertSame(['browser' => 'Edge', 'platform' => 'Windows', 'device_type' => 'Desktop'], $info);

        $info = LoginApprovalService::parseUserAgent('Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 Chrome/130.0 Mobile Safari/537.36');
        $this->assertSame(['browser' => 'Chrome', 'platform' => 'Android', 'device_type' => 'Mobile'], $info);

        $this->assertSame(['browser' => null, 'platform' => null, 'device_type' => null], LoginApprovalService::parseUserAgent(''));
    }

    // ── Hardening (local verification phase) ──

    public function test_nobody_can_take_the_owner_email_in_any_letter_case(): void
    {
        $owner = $this->user('admin', ['username' => 'owner', 'email' => self::OWNER]);
        $cashier = $this->user('cashier');

        // Self-service profile change.
        $this->actingAs($cashier)->put(route('profile.update'), ['name' => 'X', 'email' => 'OWNER@bakery.TEST'])
            ->assertSessionHasErrors('email');
        $this->assertNotSame('owner@bakery.test', strtolower($cashier->fresh()->email));

        // Admin user management, create and update.
        $this->asGuest();
        $this->actingAs($owner)->post(route('admin.users.store'), [
            'name' => 'Y', 'username' => 'yy', 'email' => ' Owner@Bakery.Test', 'password' => 'secret12',
            'role' => 'cashier', 'status' => 'active',
        ])->assertSessionHasErrors('email');
        $this->actingAs($owner)->put(route('admin.users.update', $cashier), [
            'name' => 'Z', 'username' => $cashier->username, 'email' => 'owner@BAKERY.test',
            'role' => 'cashier', 'status' => 'active',
        ])->assertSessionHasErrors('email');

        // The owner may keep (or re-case) their own address.
        $this->actingAs($owner)->put(route('profile.update'), ['name' => 'Owner', 'email' => 'Owner@Bakery.test'])
            ->assertSessionHasNoErrors();
    }

    public function test_bypass_is_disabled_when_two_accounts_share_the_owner_email_by_case(): void
    {
        $this->activeDevice($this->user('admin'));
        $this->user('manager', ['username' => 'owner', 'email' => self::OWNER]);
        $this->user('cashier', ['username' => 'impostor', 'email' => 'OWNER@bakery.test']);

        $this->login('impostor')->assertRedirect(route('login.approval.wait'));
        $this->asGuest();
        $this->login('owner')->assertRedirect(route('login.approval.wait'));
    }

    public function test_background_polling_does_not_keep_a_session_active(): void
    {
        $admin = $this->user('admin');
        $this->login($admin);
        $device = UserDevice::where('user_id', $admin->id)->firstOrFail();

        // Six minutes of nothing but background polls.
        foreach ([2, 4, 6] as $minutes) {
            $this->travelTo($device->created_at->copy()->addMinutes($minutes));
            $this->withHeader('X-Background-Poll', '1')->getJson(route('login-approvals.pending'))->assertOk();
        }

        $this->assertTrue($device->fresh()->last_seen_at->lte($device->created_at->copy()->addSecond()));
        $this->assertFalse(app(LoginApprovalService::class)->otherUsersActive($this->user('cashier')));

        // A real page view counts again.
        $this->flushHeaders();
        $this->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue(app(LoginApprovalService::class)->otherUsersActive($this->user('cashier')));
    }

    public function test_revoking_a_device_invalidates_remember_me_cookies(): void
    {
        $owner = $this->user('admin', ['username' => 'owner', 'email' => self::OWNER]);
        $before = $owner->fresh()->remember_token;
        $device = $this->activeDevice($owner);

        app(LoginApprovalService::class)->revokeDevice($device, $this->user('admin'), 'revoked');

        $this->assertNotSame($before, $owner->fresh()->remember_token);
    }

    public function test_post_logout_requires_csrf_and_signs_out(): void
    {
        $cashier = $this->user('cashier');
        $this->login($cashier);

        $this->post(route('logout.post'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('user_devices', ['user_id' => $cashier->id, 'revoke_reason' => 'logout']);
    }

    public function test_cross_site_get_logout_only_shows_a_confirmation(): void
    {
        $cashier = $this->user('cashier');
        $this->login($cashier);

        $this->withHeader('Sec-Fetch-Site', 'cross-site')->get(route('logout'))
            ->assertOk()->assertSee('Sign out?')->assertSee(route('logout.post'), false);
        $this->assertAuthenticatedAs($cashier);

        // Same-origin links and typed URLs keep working as before.
        $this->withHeader('Sec-Fetch-Site', 'same-origin')->get(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_everything_degrades_gracefully_before_the_migration_runs(): void
    {
        \Illuminate\Support\Facades\Schema::drop('user_devices');
        \Illuminate\Support\Facades\Schema::drop('login_approval_requests');
        $admin = $this->user('admin');

        $this->login($admin)->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.security'))->assertOk()->assertDontSee('Login security is not set up yet');
        $this->getJson(route('login-approvals.pending'))->assertOk()->assertJsonCount(0, 'requests');
        $this->get(route('admin.dashboard'))->assertOk();
        $this->post(route('logout.post'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_security_is_a_staff_and_accounts_tab(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('id="tab_login_security"', false)
            ->assertSee(route('admin.security'), false)
            ->assertDontSee('id="nav_security"', false);

        // Direct load / refresh of the tab URL keeps the tab bar, the active tab and the sidebar highlight.
        $this->get(route('admin.security'))
            ->assertOk()
            ->assertSee('Active Devices')
            ->assertSee('Approval History')
            ->assertSee('Pending Approvals')
            ->assertSee('class="period-pill active" id="tab_login_security"', false)
            ->assertSee(route('admin.users.index'), false)
            ->assertDontSee('id="nav_security"', false);
    }

    public function test_security_tab_shows_summary_and_pending_requests_with_actions(): void
    {
        $admin = $this->user('admin');
        $this->activeDevice($admin);
        $this->login($this->user('cashier', ['name' => 'Waiting Cashier']));
        $id = $this->pendingRequest()->id;
        $this->asGuest();

        $this->actingAs($admin)->get(route('admin.security'))
            ->assertOk()
            ->assertSeeInOrder(['Active Devices', 'Pending Requests', 'Recent Sign-ins', 'Pending Login Approvals'])
            ->assertSee('data-request-id="' . $id . '"', false)
            ->assertSee('Waiting Cashier')
            ->assertSee('data-action="approve"', false)
            ->assertSee('data-action="reject"', false)
            ->assertSee('id="sec_view_approvals"', false)->assertDontSee('id="sec_view_signins"', false);

        // The buttons use the existing endpoint.
        $this->postJson(route('login-approvals.approve', $id))->assertOk();
        $this->get(route('admin.security', ['view' => 'pending']))->assertOk()->assertSee('No login requests are waiting');
    }

    public function test_view_switches_open_their_view_even_before_the_migration(): void
    {
        $this->actingAs($this->user('admin'));

        foreach (['pending' => 'No login requests are waiting', 'devices' => 'No devices are signed in', 'approvals' => 'No login approval requests yet'] as $view => $text) {
            $this->get(route('admin.security', ['view' => $view]))->assertOk()->assertSee($text);
        }

        \Illuminate\Support\Facades\Schema::drop('user_devices');
        \Illuminate\Support\Facades\Schema::drop('login_approval_requests');

        foreach (['pending' => 'No login requests are waiting', 'approvals' => 'No login approval requests yet'] as $view => $text) {
            $this->get(route('admin.security', ['view' => $view]))->assertOk()->assertSee($text);
        }
    }

    public function test_approvers_get_a_bell_notification_for_a_pending_login(): void
    {
        $admin = $this->user('admin');
        $manager = $this->user('manager');
        $otherCashier = $this->user('cashier');
        $this->activeDevice($admin);
        $cashier = $this->user('cashier', ['name' => 'Dara']);

        $this->login($cashier)->assertRedirect(route('login.approval.wait'));
        $id = $this->pendingRequest()->id;

        $note = \App\Models\Notification::where('user_id', $admin->id)->where('type', 'security')->firstOrFail();
        $this->assertSame('Login approval needed', $note->title);
        $this->assertStringContainsString('Dara (Cashier)', $note->message);
        $this->assertStringContainsString('Safari on iPadOS', $note->message);
        $this->assertSame('warning', $note->severity);
        $this->assertSame(route('admin.security', ['view' => 'pending']), $note->action_url);
        $this->assertSame('login_request:' . $id, $note->dedup_key);

        // Managers are told too (no admin-only link); non-approvers and the requester are not.
        $this->assertNull(\App\Models\Notification::where('user_id', $manager->id)->where('type', 'security')->value('action_url'));
        $this->assertSame(0, \App\Models\Notification::where('user_id', $otherCashier->id)->count());
        $this->assertSame(0, \App\Models\Notification::where('user_id', $cashier->id)->count());

        // It shows on the approver's bell.
        $this->asGuest();
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('Login approval needed');
    }

    public function test_approvers_get_a_bell_notification_when_someone_signs_in(): void
    {
        $admin = $this->user('admin');
        $cashier = $this->user('cashier', ['name' => 'Sokha']);

        $this->login($cashier)->assertRedirect(route('cashier.dashboard'));

        $note = \App\Models\Notification::where('user_id', $admin->id)->where('type', 'security')->firstOrFail();
        $this->assertSame('New device sign-in', $note->title);
        $this->assertStringContainsString('Sokha (Cashier) signed in from Safari on iPadOS', $note->message);
        $this->assertSame(route('admin.security', ['view' => 'devices']), $note->action_url);

        // An admin's own sign-in does not notify themselves.
        $this->asGuest();
        $this->login($admin);
        $this->assertSame(0, \App\Models\Notification::where('user_id', $admin->id)->where('title', 'New device sign-in')->where('message', 'like', $admin->name . '%')->count());
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        $cashier = $this->user('cashier');

        for ($i = 0; $i < 10; $i++) {
            $this->login($cashier, 'wrong-password')->assertSessionHasErrors('username');
        }

        $this->login($cashier)->assertStatus(429);
        $this->assertGuest();
    }

    public function test_layout_logout_confirm_button_posts_with_csrf(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('action="' . route('logout.post') . '"', false)
            ->assertSee('id="btn_confirm_logout"', false);
    }
}
