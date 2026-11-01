@extends('layouts.app')

@section('title', $user->name . ' - User Profile - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/users.css') }}">
    <style>
        .user-profile-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .user-profile-grid {
                grid-template-columns: 1fr;
            }
        }
        .profile-card {
            background: var(--user-card-bg);
            border: 1px solid var(--user-border);
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
        }
        .profile-hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--user-border);
            margin-bottom: 1.25rem;
        }
        .profile-avatar-lg {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #563020 0%, #3b2118 100%);
            color: #ffffff;
            font-size: 1.6rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 20px rgba(86, 48, 32, 0.25);
            margin-bottom: 0.85rem;
        }
        .profile-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.65rem 0;
            border-bottom: 1px dashed var(--user-border);
            font-size: 0.85rem;
        }
        .profile-info-row:last-child {
            border-bottom: none;
        }
        .profile-info-label {
            color: var(--user-muted);
            font-weight: 600;
        }
        .profile-info-value {
            color: var(--user-text);
            font-weight: 700;
            text-align: right;
        }
        .perm-grid-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.65rem;
            background: rgba(0, 0, 0, 0.015);
            border-radius: 8px;
            font-size: 0.825rem;
        }
        .perm-status-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 800;
        }
        .perm-status-icon.granted {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }
        .perm-status-icon.denied {
            background: rgba(148, 163, 184, 0.15);
            color: #94a3b8;
        }
    </style>
@endpush

@section('content')
<!-- Back & Breadcrumb Bar -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <a href="{{ route('admin.users.index') }}" class="btn-icon-action" style="width: 38px; height: 38px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center;" title="Back to Users List">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0; color: var(--user-text); display: flex; align-items: center; gap: 0.5rem;">
                {{ $user->name }}
                @if($user->id === auth()->id())
                    <span style="font-size: 0.675rem; background: rgba(86, 48, 32, 0.1); color: #563020; padding: 0.15rem 0.45rem; border-radius: 9999px; font-weight: 800;">YOU</span>
                @endif
            </h2>
            <div style="font-size: 0.825rem; color: var(--user-muted); margin-top: 0.2rem;">
                @<span>{{ $user->username }}</span> • Joined {{ $user->created_at ? $user->created_at->format('M d, Y') : '—' }}
            </div>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.users.index') }}" class="btn-modal-cancel" style="text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem;">
            &larr; <span data-lang-key="users_management">{{ __('messages.users_management') }}</span>
        </a>
    </div>
</div>

<!-- Operational Metric Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Orders Handled</span>
            <div class="stat-value">{{ number_format($user->orders_count ?? 0) }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Sales Recorded</span>
            <div class="stat-value">{{ number_format($user->sales_count ?? 0) }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Baking Batches</span>
            <div class="stat-value">{{ number_format(($user->productions_count ?? 0) + ($user->assigned_productions_count ?? 0)) }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Purchase Orders</span>
            <div class="stat-value">{{ number_format($user->purchase_orders_count ?? 0) }}</div>
        </div>
    </div>
</div>

<!-- Main Two-Column Profile Grid -->
<div class="user-profile-grid">
    <!-- Left Column: User Profile Card -->
    <div class="profile-card">
        @php
            $initials = strtoupper(substr($user->name, 0, 2));
            $roleClass = 'role-' . strtolower($user->role);
        @endphp

        <div class="profile-hero">
            <div class="profile-avatar-lg">{{ $initials }}</div>
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--user-text);">
                {{ $user->name }}
            </h3>
            <div style="font-size: 0.85rem; color: var(--user-muted); margin-top: 0.2rem;">
                {{ '@' . $user->username }}
            </div>

            <div style="display: flex; gap: 0.5rem; margin-top: 0.75rem;">
                <span class="role-pill {{ $roleClass }}">
                    {{ ucfirst($user->role) }}
                </span>
                <x-badge :variant="$user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'danger' : 'warning')">
                    {{ ucfirst($user->status) }}
                </x-badge>
            </div>
        </div>

        <div>
            <div class="profile-info-row">
                <span class="profile-info-label">Email</span>
                <span class="profile-info-value">{{ $user->email }}</span>
            </div>
            <div class="profile-info-row">
                <span class="profile-info-label">Phone</span>
                <span class="profile-info-value">{{ $user->phone ?? '—' }}</span>
            </div>
            <div class="profile-info-row">
                <span class="profile-info-label">Last Login</span>
                <span class="profile-info-value">
                    {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                </span>
            </div>
            <div class="profile-info-row">
                <span class="profile-info-label">Created At</span>
                <span class="profile-info-value">
                    {{ $user->created_at ? $user->created_at->format('M d, Y h:i A') : '—' }}
                </span>
            </div>
            <div class="profile-info-row">
                <span class="profile-info-label">Password Policy</span>
                <span class="profile-info-value">
                    <span style="color: #16a34a; font-weight: 700;">Active & Verified</span>
                </span>
            </div>
            @if(!empty($user->custom_permissions))
                <div class="profile-info-row">
                    <span class="profile-info-label">Custom Overrides</span>
                    <span class="profile-info-value" style="color: #5b21b6;">
                        {{ count($user->custom_permissions) }} rules configured
                    </span>
                </div>
            @endif
        </div>
    </div>

    <!-- Right Column: Effective Permissions & Audit Trail -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Permissions Card -->
        <x-card class="users-card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--user-border); padding-bottom: 0.75rem;">
                <div>
                    <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--user-text);" data-lang-key="manage_permissions">
                        Effective Role Permissions
                    </h3>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--user-muted);">
                        Based on <strong>{{ ucfirst($user->role) }}</strong> role defaults
                        @if(!empty($customOverrides))
                            plus active custom overrides
                        @endif
                    </p>
                </div>
            </div>

            @if($user->isAdmin())
                <div style="padding: 0.85rem 1rem; background: rgba(91, 33, 182, 0.08); border: 1px solid rgba(91, 33, 182, 0.2); border-radius: 12px; font-size: 0.85rem; font-weight: 700; color: #5b21b6;">
                    👑 <strong>Administrator Account:</strong> Inherently holds all system module permissions unconditionally.
                </div>
            @endif

            <div class="permission-modules-list" style="margin-top: 1rem;">
                @foreach($modules as $moduleKey => $module)
                    <div class="permission-module-card">
                        <div class="permission-module-header">
                            <div>
                                <div class="permission-module-title">{{ $module['label'] }}</div>
                                <div class="permission-module-desc">{{ $module['description'] }}</div>
                            </div>
                        </div>
                        <div class="permission-checkboxes-grid">
                            @foreach($module['permissions'] as $permKey => $permLabel)
                                @php
                                    $isGranted = in_array($permKey, $effectivePermissions);
                                    $isDefault = in_array($permKey, $roleDefaults);
                                    $hasOverride = array_key_exists($permKey, $customOverrides);
                                @endphp
                                <div class="perm-grid-item">
                                    <div style="display: flex; align-items: center; gap: 0.45rem;">
                                        <span class="perm-status-icon {{ $isGranted ? 'granted' : 'denied' }}">
                                            {!! $isGranted ? '✓' : '✕' !!}
                                        </span>
                                        <span style="font-weight: 600; color: var(--user-text);">{{ $permLabel }}</span>
                                    </div>
                                    <div>
                                        @if($hasOverride)
                                            <span style="font-size: 0.65rem; background: rgba(91, 33, 182, 0.12); color: #5b21b6; padding: 0.15rem 0.4rem; border-radius: 4px; font-weight: 800;">Custom</span>
                                        @elseif($isDefault)
                                            <span class="perm-role-default-tag">Default</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <!-- Audit Trail Card -->
        <x-card class="users-card" style="padding: 1.5rem;">
            <div style="margin-bottom: 1rem; border-bottom: 1px solid var(--user-border); padding-bottom: 0.75rem;">
                <h3 style="margin: 0; font-size: 1.05rem; font-weight: 800; color: var(--user-text);" data-lang-key="audit_trail">
                    {{ __('messages.audit_trail') }}
                </h3>
                <p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: var(--user-muted);">
                    Recent activity events and changes associated with {{ $user->name }}
                </p>
            </div>

            @if($recentAudits->isEmpty())
                <div style="text-align: center; padding: 2rem 1rem; color: var(--user-muted); font-size: 0.85rem;">
                    No recorded activity logs for this user account yet.
                </div>
            @else
                <div class="audit-timeline">
                    @foreach($recentAudits as $audit)
                        <div class="audit-timeline-item">
                            <div>
                                <div class="audit-action-title">
                                    {{ $audit->description ?? $audit->action }}
                                </div>
                                <div class="audit-meta-time">
                                    {{ $audit->created_at ? $audit->created_at->format('M d, Y h:i A') : '—' }}
                                    • IP: {{ $audit->ip_address ?? '—' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
</div>
@endsection
