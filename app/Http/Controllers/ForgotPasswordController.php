<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    // ── Password Reset Request (Forgot Password) ──

    public function showLinkRequestForm()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
        ], [
            'username.required' => 'Please enter your username or registered email address.',
        ]);

        $input = trim($request->input('username'));

        $user = User::whereRaw('LOWER(username) = ?', [strtolower($input)])
            ->orWhereRaw('LOWER(email) = ?', [strtolower($input)])
            ->first();

        if (!$user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'We could not find an account matching that username or email address.',
                ], 422);
            }

            return back()->withInput()->withErrors([
                'username' => 'We could not find an account matching that username or email address.',
            ]);
        }

        if ($user->status !== 'active') {
            $msg = 'This account is currently inactive. Please contact an administrator.';
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }

            return back()->withInput()->withErrors(['username' => $msg]);
        }

        $token = Password::broker()->createToken($user);

        try {
            $user->sendPasswordResetNotification($token);
        } catch (\Throwable $e) {
            Log::warning("Password reset email dispatch failure for {$user->email}: " . $e->getMessage());
        }

        AuditLogService::log(
            'password_reset_requested',
            "Password reset link requested for user '{$user->username}' ({$user->email})",
            $user,
            ['ip' => $request->ip()]
        );

        $resetUrl = route('password.reset', ['token' => $token, 'email' => $user->email]);
        $successMessage = "A password reset link has been prepared for your account ({$user->email}).";

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $successMessage,
                'reset_url' => $resetUrl,
                'email' => $user->email,
            ]);
        }

        return back()->with('status', $successMessage)->with('reset_url', $resetUrl);
    }

    // ── Password Reset Execution ──

    public function showResetForm(Request $request, string $token = null)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ], [
            'password.confirmed' => 'The password confirmation does not match.',
            'password.min'       => 'The password must be at least 6 characters.',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($request->email))])->first();

        if (!$user || !Password::broker()->tokenExists($user, $request->token)) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This password reset link is invalid or has expired. Please request a new one.',
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->save();

        Password::broker()->deleteToken($user);

        AuditLogService::log(
            'password_reset_completed',
            "Password was successfully reset for user '{$user->username}'",
            $user,
            ['ip' => $request->ip()]
        );

        return redirect()->route('login')->with('success', 'Your password has been reset successfully! You can now sign in with your new password.');
    }
}
