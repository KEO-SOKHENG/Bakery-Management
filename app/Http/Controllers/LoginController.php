<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function index()
    {
        try {
            if (Auth::check()) {
                $user = Auth::user();
                return match (strtolower($user->role)) {
                    'admin' => redirect()->route('admin.dashboard'),
                    'manager' => redirect()->route('manager.dashboard'),
                    'cashier' => redirect()->route('cashier.dashboard'),
                    'baker' => redirect()->route('admin.production'),
                    default => redirect()->route('login'),
                };
            }
        } catch (\Throwable $e) {
            report($e);
            try {
                Auth::logout();
                request()->session()->invalidate();
            } catch (\Throwable) {
                // Ignore secondary cleanup errors
            }
        }

        return view('login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim($credentials['username']);
        $remember = $request->boolean('remember');

        // Check if input is email or username
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        try {
            $authenticated = Auth::attempt([$fieldType => $loginInput, 'password' => $credentials['password']], $remember);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors([
                'username' => 'Authentication service is temporarily unavailable. Please try again later.',
            ])->onlyInput('username');
        }

        if ($authenticated) {
            $user = Auth::user();

            if ($user->status !== 'active') {
                $statusError = $user->status === 'suspended'
                    ? 'Your account has been suspended. Please contact an administrator.'
                    : 'Your account is deactivated. Please contact an administrator.';

                \App\Services\AuditLogService::log(
                    'login_blocked',
                    "Blocked login attempt for {$user->status} account '{$user->username}'",
                    $user,
                    ['status' => $user->status, 'ip' => $request->ip()],
                    $user->id
                );

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['username' => $statusError]);
            }

            $user->last_login_at = now();
            $user->save();

            \App\Services\AuditLogService::log(
                'login',
                "User '{$user->username}' logged in successfully",
                $user,
                ['role' => $user->role, 'ip' => $request->ip()]
            );

            $request->session()->regenerate();

            if ($user->must_change_password) {
                return redirect()->route('password.change')->with('warning', 'You are logged in with a temporary password. Please set a new password to continue.');
            }

            return match (strtolower($user->role)) {
                'admin' => redirect()->route('admin.dashboard')->with('success', 'Welcome back, Admin!'),
                'manager' => redirect()->route('manager.dashboard')->with('success', 'Welcome back, Manager!'),
                'cashier' => redirect()->route('cashier.dashboard')->with('success', 'Welcome back, Cashier!'),
                'baker' => redirect()->route('admin.production')->with('success', 'Welcome back, Baker!'),
                default => redirect()->route('login'),
            };
        }

        return back()->withErrors([
            'username' => 'Invalid login credentials. Please check your username/email and password.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            \App\Services\AuditLogService::log(
                'logout',
                "User '{$user->username}' logged out",
                $user,
                ['role' => $user->role]
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Logged out successfully.');
    }
}