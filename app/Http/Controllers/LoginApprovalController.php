<?php

namespace App\Http\Controllers;

use App\Models\LoginApprovalRequest;
use App\Services\LoginApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginApprovalController extends Controller
{
    public function __construct(private readonly LoginApprovalService $approvals)
    {
    }

    // ── Waiting device (guest) ──

    public function wait(Request $request)
    {
        $approval = $this->pendingFromSession($request);

        if (!$approval) {
            return redirect()->route('login');
        }

        $approval = $this->approvals->refresh($approval);

        return view('auth.login-approval', [
            'approval' => $approval,
            'secondsLeft' => max(0, now()->diffInSeconds($approval->expires_at, false)),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $approval = $this->pendingFromSession($request);

        if (!$approval) {
            return response()->json(['status' => 'missing']);
        }

        $approval = $this->approvals->refresh($approval);

        return response()->json([
            'status' => $approval->status,
            'seconds_left' => max(0, (int) now()->diffInSeconds($approval->expires_at, false)),
        ]);
    }

    public function complete(Request $request, LoginController $login)
    {
        $pending = $request->session()->get(LoginApprovalService::SESSION_PENDING);
        $approval = is_array($pending)
            ? $this->approvals->consume((string) ($pending['id'] ?? ''), (string) ($pending['token'] ?? ''))
            : null;

        if (!$approval || $approval->user->status !== 'active') {
            $request->session()->forget(LoginApprovalService::SESSION_PENDING);

            return redirect()->route('login')->withErrors([
                'username' => 'This login request is no longer valid. Please sign in again.',
            ]);
        }

        return $login->completeLogin($request, $approval->user, 'approved', $approval->id);
    }

    public function cancel(Request $request)
    {
        $pending = $request->session()->pull(LoginApprovalService::SESSION_PENDING);

        if (is_array($pending)) {
            $this->approvals->cancel((string) ($pending['id'] ?? ''), (string) ($pending['token'] ?? ''));
        }

        return redirect()->route('login')->with('info', 'Login request cancelled.');
    }

    // ── Approving device (authenticated) ──

    public function pending(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$this->approvals->tablesReady() || !$this->approvals->isApprover($user)) {
            return response()->json(['requests' => []]);
        }

        return response()->json([
            'requests' => $this->approvals->pendingFor($user)->map(fn (LoginApprovalRequest $r) => [
                'id' => $r->id,
                'user' => $r->user?->name ?: $r->user?->username,
                'role' => ucfirst((string) $r->user?->role),
                'device' => $r->device_type ?: 'Unknown device',
                'browser' => $r->browser ?: 'Unknown browser',
                'platform' => $r->platform ?: 'Unknown OS',
                'ip' => $r->ip_address,
                'requested_at' => $r->created_at->toIso8601String(),
                'seconds_left' => max(0, (int) now()->diffInSeconds($r->expires_at, false)),
            ])->values(),
        ]);
    }

    public function approve(Request $request, string $id): JsonResponse
    {
        return $this->decide($id, true);
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        return $this->decide($id, false);
    }

    private function decide(string $id, bool $approve): JsonResponse
    {
        $error = $this->approvals->tablesReady()
            ? $this->approvals->decide($id, Auth::user(), $approve)
            : 'not_found';

        return match ($error) {
            null => response()->json(['ok' => true, 'status' => $approve ? 'approved' : 'rejected']),
            'forbidden' => response()->json(['ok' => false, 'message' => 'You are not allowed to decide this request.'], 403),
            'not_found' => response()->json(['ok' => false, 'message' => 'Request not found.'], 404),
            'expired' => response()->json(['ok' => false, 'message' => 'This request has expired.'], 409),
            default => response()->json(['ok' => false, 'message' => 'This request was already handled.'], 409),
        };
    }

    private function pendingFromSession(Request $request): ?LoginApprovalRequest
    {
        $pending = $request->session()->get(LoginApprovalService::SESSION_PENDING);

        return is_array($pending)
            ? $this->approvals->findWithToken((string) ($pending['id'] ?? ''), (string) ($pending['token'] ?? ''))
            : null;
    }
}
