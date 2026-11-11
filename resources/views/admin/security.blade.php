@extends('layouts.app')

@section('title', 'Login Security - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users.css') }}?v={{ @filemtime(public_path('css/users.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/login-approval.css') }}?v={{ @filemtime(public_path('css/login-approval.css')) ?: '1.0' }}">
@endpush

{{-- Same tab bar as Staff & Accounts; Login Security is its admin-only tab. --}}
@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.users.index') }}" class="period-pill">
            <span data-lang-key="tab_staff_accounts">{{ __('messages.tab_staff_accounts') }}</span>
        </a>
        <a href="{{ route('admin.security') }}" class="period-pill active" id="tab_login_security" aria-current="page">
            <span>Login Security</span>
        </a>
    </div>
@endsection

@php
    $views = [
        'pending' => ['Pending Approvals', $pending->count()],
        'devices' => ['Active Devices', $devices->count()],
        'approvals' => ['Approval History', null],
    ];
    $initials = fn ($u) => strtoupper(substr($u?->name ?? '?', 0, 2));
@endphp

@section('content')
<div class="orders-container">

    <h1 class="sr-only">Login Security</h1>

<!-- Flash Notifications -->
@if(session('success'))
    <x-alert type="success" :message="session('success')" />
@endif
@if(session('error'))
    <x-alert type="danger" :message="session('error')" />
@endif

<!-- 1. Summary Metric Cards (Staff & Accounts style) -->
<div class="m-stat-row dash-stat-row">
    <div class="stat-card user-stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Active Devices</span>
            <div class="stat-value">{{ number_format($activeCount) }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Last {{ (int) round(config('login_approval.active_window', 300) / 60) }} min
            </span>
        </div>
    </div>

    <div class="stat-card user-stat-card">
        <div class="stat-icon-box yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Pending Requests</span>
            <div class="stat-value">{{ number_format($pending->count()) }}</div>
            <span class="stat-trend {{ $pending->isNotEmpty() ? 'warning' : 'up' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                {{ $pending->isNotEmpty() ? 'Needs Review' : 'All Clear' }}
            </span>
        </div>
    </div>

    <div class="stat-card user-stat-card">
        <div class="stat-icon-box blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Recent Sign-ins</span>
            <div class="stat-value">{{ number_format($recentSignIns) }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Last 24 Hours
            </span>
        </div>
    </div>
</div>

<!-- 2. View Toolbar Row (Staff & Accounts style) -->
<div class="ingredients-toolbar-row" id="security_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <nav class="period-pill-group sec-view-pills" aria-label="Login security views">
        @foreach($views as $key => [$label, $count])
            <a href="{{ route('admin.security', ['view' => $key]) }}" id="sec_view_{{ $key }}"
                class="period-pill {{ $view === $key ? 'active' : '' }}" @if($view === $key) aria-current="page" @endif>
                <span>{{ $label }}</span>
                @if($count)
                    <span class="sec-pill-count {{ $key === 'pending' ? 'is-alert' : '' }}">{{ $count }}</span>
                @endif
            </a>
        @endforeach
    </nav>
    <span class="sec-toolbar-hint">
        @switch($view)
            @case('pending') Approve only people and devices you recognise. Requests expire after 2 minutes. @break
            @case('devices') Revoking signs that device out on its next request. @break
            @default The 50 most recent login approval requests.
        @endswitch
    </span>
</div>

<!-- 3. Table Card (Staff & Accounts style) -->
<div class="orders-card" id="security_table_card">
    <div class="orders-table-wrapper" id="security_table_wrapper">

        @if($view === 'pending')
            <h2 class="sr-only">Pending Login Approvals</h2>
            <table class="orders-table" id="security_pending_table">
                <thead>
                    <tr>
                        <th style="width: 26%;">Staff</th>
                        <th style="width: 24%;">Device</th>
                        <th style="width: 14%;">IP Address</th>
                        <th style="width: 16%;">Requested</th>
                        <th style="width: 20%; min-width: 190px; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pending as $req)
                        <tr class="sec-request" data-request-id="{{ $req->id }}">
                            <td data-label="Staff">
                                <div class="user-identity">
                                    <div class="user-avatar">{{ $initials($req->user) }}</div>
                                    <div>
                                        <div class="user-name">{{ $req->user?->name ?? 'Unknown user' }}</div>
                                        <div class="user-username">{{ ucfirst((string) $req->user?->role) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Device">
                                <div class="user-last-login">{{ ($req->browser ?? 'Unknown browser') . ' on ' . ($req->platform ?? 'unknown') }}</div>
                                <div class="user-joined-date">{{ $req->device_type ?? 'Unknown device' }}</div>
                            </td>
                            <td data-label="IP Address">{{ $req->ip_address ?? '—' }}</td>
                            <td data-label="Requested">
                                <div class="user-last-login">{{ $req->created_at->format('H:i:s') }}</div>
                                <div class="user-joined-date sec-expiry">Expires {{ $req->expires_at->diffForHumans() }}</div>
                            </td>
                            <td data-label="Actions" style="text-align: right;">
                                <div class="user-action-group">
                                    <button type="button" class="btn-action-btn sec-action-reject" data-action="reject">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        <span>Reject</span>
                                    </button>
                                    <button type="button" class="btn-action-btn sec-action-approve" data-action="approve">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                                        <span>Approve</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="sec-empty-cell">No login requests are waiting. New requests appear here and as a pop-up card.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($view === 'devices')
            <table class="orders-table" id="security_devices_table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Staff</th>
                        <th style="width: 30%;">Device</th>
                        <th style="width: 22%;">Last Seen</th>
                        <th style="width: 18%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($devices as $device)
                        @php $isActive = $device->last_seen_at && $device->last_seen_at->gte($activeSince); @endphp
                        <tr>
                            <td data-label="Staff">
                                <div class="user-identity">
                                    <div class="user-avatar">{{ $initials($device->user) }}</div>
                                    <div>
                                        <div class="user-name">{{ $device->user?->name ?? 'Deleted user' }}</div>
                                        <div class="user-username">{{ ucfirst((string) $device->user?->role) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Device">
                                <div class="user-last-login">{{ $device->label() }}</div>
                                <div class="user-joined-date">{{ $device->ip_address ?? '—' }}</div>
                            </td>
                            <td data-label="Last Seen">
                                <x-badge :variant="$isActive ? 'success' : 'neutral'">{{ $isActive ? 'Active' : 'Idle' }}</x-badge>
                                <div class="user-joined-date">{{ $device->last_seen_at?->diffForHumans() ?? '—' }}</div>
                            </td>
                            <td data-label="Actions" style="text-align: right;">
                                @if($current && $current->id === $device->id)
                                    <span class="user-tag-you">This device</span>
                                @else
                                    <form method="POST" action="{{ route('admin.security.revoke', $device) }}" style="display: inline;"
                                        onsubmit="return confirm('Revoke access for this device? It will be signed out.');">
                                        @csrf
                                        <button type="submit" class="btn-action-btn sec-action-reject">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                            <span>Revoke</span>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="sec-empty-cell">No devices are signed in.</td></tr>
                    @endforelse
                </tbody>
            </table>

        @elseif($view === 'approvals')
            <h2 class="sr-only">Security History</h2>
            <table class="orders-table" id="security_approvals_table">
                <thead>
                    <tr>
                        <th style="width: 18%;">Requested</th>
                        <th style="width: 24%;">Staff</th>
                        <th style="width: 26%;">Device</th>
                        <th style="width: 14%;">Result</th>
                        <th style="width: 18%;">Decided By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($history as $req)
                        @php
                            $variant = match ($req->status) {
                                'approved', 'consumed' => 'success',
                                'rejected' => 'danger',
                                'pending' => 'warning',
                                default => 'neutral',
                            };
                        @endphp
                        <tr>
                            <td data-label="Requested">{{ $req->created_at->format('M d, H:i') }}</td>
                            <td data-label="Staff">{{ $req->user?->name ?? 'Deleted user' }}</td>
                            <td data-label="Device">
                                <div class="user-last-login">{{ ($req->browser ?? 'Unknown') . ' on ' . ($req->platform ?? 'unknown') }}</div>
                                <div class="user-joined-date">{{ $req->ip_address ?? '—' }}</div>
                            </td>
                            <td data-label="Result"><x-badge :variant="$variant">{{ $req->status === 'consumed' ? 'Approved' : ucfirst($req->status) }}</x-badge></td>
                            <td data-label="Decided By">{{ $req->decider?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="sec-empty-cell">No login approval requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        // ── Pending approvals: same endpoints as the pop-up cards ──
        var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        var decideUrl = @json(url('/security/login-approvals/__ID__/__ACTION__'));
        document.querySelectorAll('.sec-request').forEach(function (row) {
            row.querySelectorAll('[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var action = btn.dataset.action;
                    if (action === 'approve' && !confirm('Approve this login? Only approve if you recognise this person and device.')) return;
                    row.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
                    fetch(decideUrl.replace('__ID__', encodeURIComponent(row.dataset.requestId)).replace('__ACTION__', action), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }
                    }).then(function () { location.reload(); })
                      .catch(function () { row.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); });
                });
            });
        });

        // ── New requests: refresh while open (background poll, not counted as activity) ──
        if (!document.querySelector('.sec-request')) {
            var pendingUrl = @json(route('login-approvals.pending'));
            setInterval(function () {
                if (document.visibilityState !== 'visible') return;
                fetch(pendingUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Background-Poll': '1' } })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (d) { if (d && d.requests && d.requests.length) location.reload(); })
                    .catch(function () {});
            }, 15000);
        }
    })();
</script>
@endpush
