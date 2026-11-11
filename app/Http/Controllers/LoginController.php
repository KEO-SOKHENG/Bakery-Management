<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoginApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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
        $isEmail = (bool) filter_var($loginInput, FILTER_VALIDATE_EMAIL);
        $fieldType = $isEmail ? 'email' : 'username';

        // Support case-insensitive lookup (e.g. 'Admin' vs 'admin') on databases like PostgreSQL
        $canonicalValue = $loginInput;
        try {
            $userLookup = \App\Models\User::query();
            if ($isEmail) {
                $matchedUser = $userLookup->whereRaw('LOWER(email) = ?', [strtolower($loginInput)])->first();
                if ($matchedUser) {
                    $canonicalValue = $matchedUser->email;
                }
            } else {
                $matchedUser = $userLookup->whereRaw('LOWER(username) = ?', [strtolower($loginInput)])->first();
                if ($matchedUser) {
                    $canonicalValue = $matchedUser->username;
                }
            }
        } catch (\Throwable $e) {
            // Fallback to raw input if lookup encounters an issue
            $canonicalValue = $loginInput;
        }

        try {
            // Check the password without signing in, so the approval gate can run first.
            $credentialsValid = Auth::validate([$fieldType => $canonicalValue, 'password' => $credentials['password']]);
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors([
                'username' => 'Authentication service is temporarily unavailable. Please try again later.',
            ])->onlyInput('username');
        }

        $user = $credentialsValid ? User::where($fieldType, $canonicalValue)->first() : null;

        if (!$user) {
            return back()->withErrors([
                'username' => 'Invalid login credentials. Please check your username/email and password.',
            ])->onlyInput('username');
        }

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

            return back()->withErrors(['username' => $statusError]);
        }

        // ── Login approval gate ──
        $approvals = app(LoginApprovalService::class);
        $gateReady = $approvals->enabled() && Schema::hasTable('login_approval_requests');
        $isOwner = $approvals->isOwner($user);
        $method = $isOwner ? 'owner' : 'no_active_user';

        if ($gateReady && !$isOwner && $approvals->otherUsersActive($user)) {
            if ($approvals->approverOnline($user)) {
                [$approval, $token] = $approvals->createRequest($user, $request);

                $request->session()->regenerate();
                $request->session()->put(LoginApprovalService::SESSION_PENDING, ['id' => $approval->id, 'token' => $token]);

                \App\Services\AuditLogService::log(
                    'login_approval_requested',
                    "Login for '{$user->username}' is waiting for approval",
                    $user,
                    ['request_id' => $approval->id, 'ip' => $request->ip()],
                    $user->id
                );
                $approvals->notifyApprovers($user, 'request', $approval);

                return redirect()->route('login.approval.wait');
            }

            // Other users are active, but none of them is allowed to approve.
            $method = 'no_approver_online';
        }

        // A remember-me cookie would skip the gate later, so only the owner gets one.
        $remember = $remember && ($isOwner || !$gateReady);

        return $this->completeLogin($request, $user, $method, null, $remember);
    }

    /**
     * Shared sign-in tail for direct and approved logins.
     */
    public function completeLogin(Request $request, User $user, string $method, ?string $approvalId = null, bool $remember = false)
    {
        Auth::login($user, $remember);

        $user->last_login_at = now();
        $user->save();

        \App\Services\AuditLogService::log(
            'login',
            "User '{$user->username}' logged in successfully",
            $user,
            ['role' => $user->role, 'ip' => $request->ip(), 'method' => $method]
        );

        $request->session()->regenerate();
        $request->session()->forget(LoginApprovalService::SESSION_PENDING);

        if (Schema::hasTable('user_devices')) {
            $approvals = app(LoginApprovalService::class);
            $approvals->registerDevice($user, $request, $method, $approvalId);
            $approvals->notifyApprovers($user, 'signin', null, $request);
        }

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

    public function logout(Request $request)
    {
        // GET logout is kept for links and typed URLs, but a cross-site GET only
        // gets a confirmation page (POST + CSRF), so other sites cannot sign users out.
        if ($request->isMethod('GET') && Auth::check()
            && !in_array($request->headers->get('Sec-Fetch-Site', 'same-origin'), ['same-origin', 'none'], true)) {
            return response()->view('auth.logout-confirm');
        }

        if (Auth::check()) {
            $user = Auth::user();
            \App\Services\AuditLogService::log(
                'logout',
                "User '{$user->username}' logged out",
                $user,
                ['role' => $user->role]
            );

            if (Schema::hasTable('user_devices')) {
                $approvals = app(LoginApprovalService::class);
                if ($device = $approvals->currentDevice($request)) {
                    $approvals->revokeDevice($device, $user, 'logout');
                }
            }
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('info', 'Logged out successfully.');
    }
}