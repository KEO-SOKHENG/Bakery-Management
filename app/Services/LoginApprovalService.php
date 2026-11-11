<?php

namespace App\Services;

use App\Models\LoginApprovalRequest;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Login approval gate. Security rests on the random per-request token held
 * only in the waiting browser's session; device/browser labels are display-only.
 */
class LoginApprovalService
{
    public const SESSION_PENDING = 'login_approval';
    public const SESSION_DEVICE = 'device_key';

    // ── Policy ──

    public function enabled(): bool
    {
        return (bool) config('login_approval.enabled', true);
    }

    /**
     * False until the login-security migration has run; callers then fall back to plain login.
     */
    public function tablesReady(): bool
    {
        try {
            return Schema::hasTable('login_approval_requests') && Schema::hasTable('user_devices');
        } catch (\Throwable) {
            return false;
        }
    }

    public function ownerEmail(): string
    {
        return strtolower(trim((string) config('login_approval.owner_email')));
    }

    public function isOwner(User $user): bool
    {
        $owner = $this->ownerEmail();

        if ($owner === '' || !hash_equals($owner, strtolower(trim((string) $user->email)))) {
            return false;
        }

        // Ambiguity (two accounts differing only by case) disables the bypass.
        return User::whereRaw('lower(trim(email)) = ?', [$owner])->count() === 1;
    }

    public function isApprover(User $user): bool
    {
        return $user->status === 'active'
            && ($this->isOwner($user) || in_array(strtolower((string) $user->role), $this->approverRoles(), true));
    }

    public function approverRoles(): array
    {
        return config('login_approval.approver_roles', ['admin', 'manager']);
    }

    /**
     * Devices of other, active users seen within the active window and not revoked.
     */
    public function activeDevicesQuery(User $except): Builder
    {
        return UserDevice::query()
            ->whereNull('revoked_at')
            ->where('last_seen_at', '>=', now()->subSeconds((int) config('login_approval.active_window', 300)))
            ->where('user_id', '!=', $except->id)
            ->whereHas('user', fn ($q) => $q->where('status', 'active'));
    }

    public function otherUsersActive(User $user): bool
    {
        return $this->activeDevicesQuery($user)->exists();
    }

    public function approverOnline(User $user): bool
    {
        $owner = $this->ownerEmail();
        $roles = $this->approverRoles();

        return $this->activeDevicesQuery($user)
            ->whereHas('user', function ($q) use ($owner, $roles) {
                $q->where(function ($q) use ($owner, $roles) {
                    $q->whereIn(DB::raw('lower(role)'), $roles);
                    if ($owner !== '') {
                        $q->orWhereRaw('lower(email) = ?', [$owner]);
                    }
                });
            })
            ->exists();
    }

    // ── Requests ──

    /**
     * @return array{0: LoginApprovalRequest, 1: string} request and its plain token
     */
    public function createRequest(User $user, Request $request): array
    {
        $token = Str::random(64);
        $info = self::parseUserAgent((string) $request->userAgent());

        $approval = DB::transaction(function () use ($user, $request, $token, $info) {
            // One pending request per user: older ones are superseded.
            LoginApprovalRequest::where('user_id', $user->id)
                ->where('status', LoginApprovalRequest::PENDING)
                ->update(['status' => LoginApprovalRequest::CANCELLED, 'decided_at' => now()]);

            return LoginApprovalRequest::create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $token),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                'browser' => $info['browser'],
                'platform' => $info['platform'],
                'device_type' => $info['device_type'],
                'status' => LoginApprovalRequest::PENDING,
                'expires_at' => now()->addSeconds((int) config('login_approval.request_ttl', 120)),
            ]);
        });

        return [$approval, $token];
    }

    /**
     * Find a request only when the caller holds its token.
     */
    public function findWithToken(string $requestId, string $token): ?LoginApprovalRequest
    {
        if (!Str::isUuid($requestId) || $token === '') {
            return null;
        }

        $approval = LoginApprovalRequest::find($requestId);

        return $approval && hash_equals($approval->token_hash, hash('sha256', $token)) ? $approval : null;
    }

    /**
     * Current status, marking an overdue pending request as expired.
     */
    public function refresh(LoginApprovalRequest $approval): LoginApprovalRequest
    {
        if ($approval->status === LoginApprovalRequest::PENDING && $approval->isExpired()) {
            $updated = LoginApprovalRequest::whereKey($approval->id)
                ->where('status', LoginApprovalRequest::PENDING)
                ->update(['status' => LoginApprovalRequest::EXPIRED, 'decided_at' => now()]);

            if ($updated) {
                AuditLogService::log('login_approval_expired', "Login approval request for '{$approval->user->username}' expired", $approval->user, [
                    'request_id' => $approval->id,
                ], $approval->user_id);
            }

            $approval->refresh();
        }

        return $approval;
    }

    public function pendingFor(User $approver): Collection
    {
        if (!$this->isApprover($approver)) {
            return collect();
        }

        return LoginApprovalRequest::with('user:id,name,username,role')
            ->where('status', LoginApprovalRequest::PENDING)
            ->where('expires_at', '>', now())
            ->where('user_id', '!=', $approver->id)
            ->orderBy('created_at')
            ->limit(10)
            ->get();
    }

    /**
     * Approve or reject. Returns null on success, or a refusal reason.
     */
    public function decide(string $requestId, User $approver, bool $approve): ?string
    {
        if (!$this->isApprover($approver)) {
            return 'forbidden';
        }
        if (!Str::isUuid($requestId)) {
            return 'not_found';
        }

        return DB::transaction(function () use ($requestId, $approver, $approve) {
            $approval = LoginApprovalRequest::whereKey($requestId)->lockForUpdate()->first();

            if (!$approval) {
                return 'not_found';
            }
            if ((int) $approval->user_id === (int) $approver->id) {
                return 'forbidden';
            }
            if ($approval->status !== LoginApprovalRequest::PENDING) {
                return 'already_decided';
            }
            if ($approval->isExpired()) {
                $approval->update(['status' => LoginApprovalRequest::EXPIRED, 'decided_at' => now()]);
                return 'expired';
            }

            $approval->update([
                'status' => $approve ? LoginApprovalRequest::APPROVED : LoginApprovalRequest::REJECTED,
                'decided_by' => $approver->id,
                'decided_at' => now(),
            ]);

            AuditLogService::log(
                $approve ? 'login_approval_approved' : 'login_approval_rejected',
                ($approve ? 'Approved' : 'Rejected') . " login request for '{$approval->user->username}'",
                $approval->user,
                ['request_id' => $approval->id, 'request_ip' => $approval->ip_address],
                $approver->id
            );

            return null;
        });
    }

    /**
     * Single-use redemption of an approved request by the browser holding its token.
     */
    public function consume(string $requestId, string $token): ?LoginApprovalRequest
    {
        $approval = $this->findWithToken($requestId, $token);

        if (!$approval) {
            return null;
        }

        // An approval must be redeemed within one TTL of the decision.
        $deadline = now()->subSeconds((int) config('login_approval.request_ttl', 120));

        $claimed = LoginApprovalRequest::whereKey($approval->id)
            ->where('status', LoginApprovalRequest::APPROVED)
            ->whereNull('consumed_at')
            ->where('decided_at', '>=', $deadline)
            ->update(['status' => LoginApprovalRequest::CONSUMED, 'consumed_at' => now()]);

        return $claimed ? $approval->fresh() : null;
    }

    public function cancel(string $requestId, string $token): void
    {
        $approval = $this->findWithToken($requestId, $token);

        if ($approval) {
            LoginApprovalRequest::whereKey($approval->id)
                ->where('status', LoginApprovalRequest::PENDING)
                ->update(['status' => LoginApprovalRequest::CANCELLED, 'decided_at' => now()]);
        }
    }

    // ── Bell notifications ──

    /**
     * Notify every active approver (except $about) through the header bell.
     * Never throws: a notification problem must not block a login.
     */
    public function notifyApprovers(User $about, string $event, ?LoginApprovalRequest $approval = null, ?Request $request = null): void
    {
        try {
            $owner = $this->ownerEmail();
            $roles = $this->approverRoles();
            $recipients = User::where('status', 'active')
                ->where('id', '!=', $about->id)
                ->where(function ($q) use ($roles, $owner) {
                    $q->whereIn(DB::raw('lower(role)'), $roles);
                    if ($owner !== '') {
                        $q->orWhereRaw('lower(email) = ?', [$owner]);
                    }
                })
                ->get();

            if ($recipients->isEmpty()) {
                return;
            }

            $info = $approval
                ? ['browser' => $approval->browser, 'platform' => $approval->platform]
                : self::parseUserAgent((string) $request?->userAgent());
            $device = ($info['browser'] ?? 'Unknown browser') . ' on ' . ($info['platform'] ?? 'unknown device');
            $ip = $approval?->ip_address ?? $request?->ip() ?? 'unknown IP';
            $who = $about->name . ' (' . ucfirst((string) $about->role) . ')';

            [$title, $message, $severity, $view, $dedup] = $event === 'request'
                ? ['Login approval needed', "{$who} is trying to sign in from {$device}, IP {$ip}. Approve or reject within 2 minutes.", 'warning', 'pending', 'login_request:' . $approval?->id]
                : ['New device sign-in', "{$who} signed in from {$device}, IP {$ip}.", 'info', 'devices', null];

            $notifications = app(NotificationService::class);
            foreach ($recipients as $recipient) {
                // The Login Security page is admin-only; others act from the pop-up card.
                $url = strtolower((string) $recipient->role) === 'admin' ? route('admin.security', ['view' => $view]) : null;
                $notifications->createNotification($recipient, 'security', $title, $message, $severity, $url, $dedup);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ── Devices ──

    public function registerDevice(User $user, Request $request, string $method, ?string $approvalId = null): UserDevice
    {
        $info = self::parseUserAgent((string) $request->userAgent());
        $key = Str::random(64);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'session_key' => hash('sha256', $key),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'browser' => $info['browser'],
            'platform' => $info['platform'],
            'device_type' => $info['device_type'],
            'login_method' => $method,
            'approval_request_id' => $approvalId,
            'last_seen_at' => now(),
        ]);

        $request->session()->put(self::SESSION_DEVICE, $key);

        return $device;
    }

    public function currentDevice(Request $request): ?UserDevice
    {
        $key = $request->session()->get(self::SESSION_DEVICE);

        return is_string($key) && $key !== ''
            ? UserDevice::where('session_key', hash('sha256', $key))->first()
            : null;
    }

    public function revokeDevice(UserDevice $device, ?User $by, string $reason): void
    {
        if ($device->revoked_at === null) {
            $device->update(['revoked_at' => now(), 'revoked_by' => $by?->id, 'revoke_reason' => $reason]);
        }

        // A remember-me cookie would silently sign a revoked owner device back in.
        if ($reason === 'revoked' && $device->user) {
            $device->user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
        }
    }

    // ── User-agent parsing (display only, never trusted) ──

    public static function parseUserAgent(string $ua): array
    {
        if ($ua === '') {
            return ['browser' => null, 'platform' => null, 'device_type' => null];
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS') => 'Firefox',
            str_contains($ua, 'CriOS') || str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Other browser',
        };

        $platform = match (true) {
            str_contains($ua, 'iPad') => 'iPadOS',
            str_contains($ua, 'iPhone') => 'iOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        $type = match (true) {
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile')) => 'Tablet',
            str_contains($ua, 'Mobile') || str_contains($ua, 'iPhone') => 'Mobile',
            default => 'Desktop',
        };

        return ['browser' => $browser, 'platform' => $platform, 'device_type' => $type];
    }
}
