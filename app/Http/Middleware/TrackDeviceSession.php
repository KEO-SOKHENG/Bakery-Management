<?php

namespace App\Http\Middleware;

use App\Services\LoginApprovalService;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds each authenticated session to a user_devices row: refreshes its
 * last_seen_at (the "active user" signal) and ends sessions whose device was revoked.
 */
class TrackDeviceSession
{
    public function __construct(private readonly LoginApprovalService $approvals)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard();

        // Guests carry neither a login session key nor a remember-me cookie: skip without resolving a user.
        if (!$guard instanceof SessionGuard || !$request->hasSession()
            || (!$request->session()->has($guard->getName()) && !$request->cookies->has($guard->getRecallerName()))) {
            return $next($request);
        }

        try {
            $signedIn = $guard->check() && $this->approvals->tablesReady();
        } catch (\Throwable $e) {
            // Auth/DB outage: let the route's own error handling respond (e.g. the login page).
            report($e);
            $signedIn = false;
        }

        if (!$signedIn) {
            return $next($request);
        }

        $user = Auth::user();
        $device = $this->approvals->currentDevice($request);

        // A key left over from a different account in this session is stale, not a revocation.
        if ($device && (int) $device->user_id !== (int) $user->id) {
            $device = null;
        }

        if ($device && $device->revoked_at !== null) {
            return $this->endSession($request, 'This device was signed out. Please sign in again.');
        }

        if (!$device) {
            // Remember-me logins skip the approval gate, so only the owner may use them.
            if (Auth::viaRemember() && $this->approvals->enabled() && !$this->approvals->isOwner($user)) {
                return $this->endSession($request, 'Your saved sign-in has expired. Please sign in again.');
            }

            // Sessions that predate this feature (or remember-me for the owner) are registered as-is.
            $this->approvals->registerDevice($user, $request, Auth::viaRemember() ? 'remember' : 'existing_session');

            return $next($request);
        }

        // Background polls are not user activity: an open but abandoned tab must age out.
        if ($request->headers->has('X-Background-Poll')) {
            return $next($request);
        }

        if (!$device->last_seen_at || $device->last_seen_at->lt(now()->subSeconds((int) config('login_approval.touch_interval', 30)))) {
            $device->forceFill(['last_seen_at' => now(), 'ip_address' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }

    private function endSession(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'signed_out' => true], 401);
        }

        return redirect()->route('login')->with('warning', $message);
    }
}
