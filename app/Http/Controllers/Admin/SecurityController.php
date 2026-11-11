<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginApprovalRequest;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use App\Services\LoginApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SecurityController extends Controller
{
    public function __construct(private readonly LoginApprovalService $approvals)
    {
    }

    public function index(Request $request)
    {
        if (!$this->approvals->tablesReady()) {
            return view('admin.security', [
                'notReady' => true,
                'devices' => collect(), 'history' => collect(),
                'pending' => collect(), 'activeSince' => now(), 'current' => null,
                'activeCount' => 0, 'recentSignIns' => 0, 'view' => $this->view($request, false),
            ]);
        }

        $activeSince = now()->subSeconds((int) config('login_approval.active_window', 300));
        $current = $this->approvals->currentDevice($request);

        $devices = UserDevice::with('user:id,name,username,role')
            ->whereNull('revoked_at')
            ->orderByDesc('last_seen_at')
            ->limit(100)
            ->get();

        $history = LoginApprovalRequest::with(['user:id,name,username', 'decider:id,name,username'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($r) => $this->approvals->refresh($r));

        // Summary + the prominent pending list (same query the approval cards poll).
        $pending = $this->approvals->pendingFor($request->user());
        $activeCount = $devices->filter(fn ($d) => $d->last_seen_at && $d->last_seen_at->gte($activeSince))->count();
        $recentSignIns = UserDevice::where('created_at', '>=', now()->subDay())->count();
        $notReady = false;
        $view = $this->view($request, $pending->isNotEmpty());

        return view('admin.security', compact(
            'devices', 'history', 'activeSince', 'current', 'notReady',
            'pending', 'activeCount', 'recentSignIns', 'view'
        ));
    }

    /**
     * Which table the page shows (?view=…); defaults to pending requests when any are waiting.
     */
    private function view(Request $request, bool $hasPending): string
    {
        $view = (string) $request->query('view', '');

        return in_array($view, ['pending', 'devices', 'approvals'], true)
            ? $view
            : ($hasPending ? 'pending' : 'devices');
    }

    public function revoke(Request $request, UserDevice $device)
    {
        $current = $this->approvals->currentDevice($request);

        if ($current && $current->id === $device->id) {
            return back()->with('error', 'You cannot revoke the device you are using. Log out instead.');
        }

        $this->approvals->revokeDevice($device, Auth::user(), 'revoked');

        AuditLogService::log(
            'device_revoked',
            "Revoked device access for '{$device->user?->username}' ({$device->label()})",
            $device->user,
            ['device_id' => $device->id, 'device_ip' => $device->ip_address]
        );

        return back()->with('success', 'Device access revoked. That session is signed out on its next request.');
    }
}
