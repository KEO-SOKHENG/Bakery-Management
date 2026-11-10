<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name'     => 'System Admin',
            'email'    => 'admin@bakery.com',
            'username' => 'Admin',
            'role'     => 'admin',
            'status'   => 'active',
            'password' => Hash::make('oldpassword123'),
        ]);
    }

    /**
     * Create a secure password reset token for the given user.
     */
    protected function createPasswordToken(User $user): string
    {
        /** @var PasswordBroker $broker */
        $broker = Password::broker();

        return $broker->createToken($user);
    }

    /**
     * 1. Forgot password request form renders successfully.
     */
    public function test_forgot_password_page_renders_successfully(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertViewIs('auth.forgot-password');
        $response->assertSee('Forgot Password?');
        $response->assertSee('Send Reset Link');
    }

    /**
     * 2. User can request a reset link using their registered email.
     */
    public function test_user_can_request_reset_link_using_email(): void
    {
        $response = $this->post(route('password.email'), [
            'username' => 'admin@bakery.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin@bakery.com',
        ]);
    }

    /**
     * 3. User can request a reset link using their username (case-insensitive).
     */
    public function test_user_can_request_reset_link_using_username(): void
    {
        // Lowercase input matching 'Admin' username
        $response = $this->post(route('password.email'), [
            'username' => 'admin',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin@bakery.com',
        ]);
    }

    /**
     * 4. AJAX reset request returns JSON with reset URL.
     */
    public function test_ajax_request_returns_json_with_reset_url(): void
    {
        $response = $this->postJson(route('password.email'), [
            'username' => 'Admin',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'success',
            'message',
            'reset_url',
        ]);
    }

    /**
     * 5. Requesting reset link for nonexistent account returns validation error.
     */
    public function test_request_reset_link_for_nonexistent_account_fails(): void
    {
        $response = $this->post(route('password.email'), [
            'username' => 'nonexistent_user',
        ]);

        $response->assertSessionHasErrors('username');
    }

    /**
     * 6. Reset password screen renders with valid token.
     */
    public function test_reset_password_screen_renders_with_valid_token(): void
    {
        $token = $this->createPasswordToken($this->user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => $this->user->email,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
        $response->assertSee('Set New Password');
        $response->assertSee($token);
    }

    /**
     * 7. User can successfully reset password with valid credentials.
     */
    public function test_user_can_successfully_reset_password(): void
    {
        $token = $this->createPasswordToken($this->user);

        $response = $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => $this->user->email,
            'password'              => 'newadmin123',
            'password_confirmation' => 'newadmin123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success');

        // Fresh user check
        $this->user->refresh();
        $this->assertTrue(Hash::check('newadmin123', $this->user->password));

        // Token should be removed
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $this->user->email,
        ]);
    }

    /**
     * 8. Password reset fails when confirmation does not match.
     */
    public function test_password_reset_fails_when_confirmation_mismatches(): void
    {
        $token = $this->createPasswordToken($this->user);

        $response = $this->post(route('password.update'), [
            'token'                 => $token,
            'email'                 => $this->user->email,
            'password'              => 'newadmin123',
            'password_confirmation' => 'different_password',
        ]);

        $response->assertSessionHasErrors('password');
        $this->user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $this->user->password));
    }

    /**
     * 9. Password reset fails with invalid or expired token.
     */
    public function test_password_reset_fails_with_invalid_token(): void
    {
        $response = $this->post(route('password.update'), [
            'token'                 => 'invalid-token-123456',
            'email'                 => $this->user->email,
            'password'              => 'newadmin123',
            'password_confirmation' => 'newadmin123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->user->refresh();
        $this->assertTrue(Hash::check('oldpassword123', $this->user->password));
    }
}
