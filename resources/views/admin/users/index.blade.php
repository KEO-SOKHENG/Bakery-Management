@extends('layouts.app')

@section('title', 'Staff & Accounts - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <link rel="stylesheet" href="{{ asset('css/users.css') }}?v={{ @filemtime(public_path('css/users.css')) ?: '1.0' }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.users.index') }}" class="period-pill active">
            <span data-lang-key="tab_staff_accounts">{{ __('messages.tab_staff_accounts') }}</span>
        </a>
        <a href="{{ route('admin.hr.attendance') }}" class="period-pill">
            <span data-lang-key="tab_attendance">{{ __('messages.tab_attendance') }}</span>
        </a>
        <a href="{{ route('admin.hr.schedules') }}" class="period-pill">
            <span data-lang-key="tab_schedules">{{ __('messages.tab_schedules') }}</span>
        </a>
        <a href="{{ route('admin.hr.salaries') }}" class="period-pill">
            <span data-lang-key="tab_salaries">{{ __('messages.tab_salaries') }}</span>
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container">

    <h1 class="sr-only">User & Staff Management</h1>

<!-- Flash Notifications -->
@if(session('success'))
    <x-alert type="success" :message="session('success')" />
@endif

@if(session('error'))
    <x-alert type="danger" :message="session('error')" />
@endif

@if(session('info'))
    <x-alert type="info" :message="session('info')" />
@endif

@if($errors->any())
    <x-alert type="danger">
        <ul style="margin: 0; padding-left: 1.2rem;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

<!-- Summary Metric Cards (Exact Orders / Dashboard Page Style) -->
<div class="m-stat-row dash-stat-row">
    <div class="stat-card user-stat-card">
        <div class="stat-icon-box blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="total_staff">{{ __('messages.total_staff') }}</span>
            <div class="stat-value">{{ number_format($totalUsers) }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Team Base
            </span>
        </div>
    </div>

    <div class="stat-card user-stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="active_accounts">{{ __('messages.active_accounts') }}</span>
            <div class="stat-value">{{ number_format($activeUsers) }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Active Staff
            </span>
        </div>
    </div>

    <div class="stat-card user-stat-card">
        <div class="stat-icon-box yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="inactive_accounts">{{ __('messages.inactive_accounts') }}</span>
            <div class="stat-value">{{ number_format($inactiveUsers) }}</div>
            <span class="stat-trend {{ $inactiveUsers > 0 ? 'warning' : 'up' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                {{ $inactiveUsers > 0 ? 'Dormant' : 'All Active' }}
            </span>
        </div>
    </div>

    <div class="stat-card user-stat-card">
        <div class="stat-icon-box red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="suspended_accounts">{{ __('messages.suspended_accounts') }}</span>
            <div class="stat-value">{{ number_format($suspendedUsers) }}</div>
            <span class="stat-trend {{ $suspendedUsers > 0 ? 'warning' : 'up' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                {{ $suspendedUsers > 0 ? 'Locked' : 'Zero Issues' }}
            </span>
        </div>
    </div>
</div>

<!-- 2. Action & Filter Toolbar Row (Exact Orders & Ingredients Style) -->
<div class="ingredients-toolbar-row m-toolbar" id="users_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
    <!-- Left: Filters without toolbar search input -->
    <form action="{{ route('admin.users.index') }}" method="GET" id="users_filter_form" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin: 0;" data-mfilter>
        @if(request('search'))
            <input type="hidden" name="search" id="user_search_input" value="{{ request('search') }}">
        @endif

        <div class="supplier-filter-pill-card">
            <select name="role" id="user_role_select" onchange="this.form.submit()">
                <option value="" data-lang-key="all_roles">{{ __('messages.all_roles') }}</option>
                @foreach($roles as $key => $label)
                    <option value="{{ $key }}" {{ request('role') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select name="status" id="user_status_select" onchange="this.form.submit()">
                <option value="" data-lang-key="all_statuses">{{ __('messages.all_statuses') }}</option>
                @foreach($statuses as $key => $label)
                    <option value="{{ $key }}" {{ request('status') == $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>


            @if(request()->hasAny(['search', 'role', 'status']) && (request('search') || request('role') || request('status')))
                <a href="{{ route('admin.users.index') }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.35rem 0.85rem; font-weight: 600; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem;">Clear</a>
            @endif
        </div>
    </form>

    <!-- Right: Add Staff Button -->
    <div style="display: flex; align-items: center; gap: 0.5rem;">
        <button type="button" class="btn m-icon-btn" id="btn_open_create_user_modal" title="{{ __('messages.add_staff') }}" aria-label="{{ __('messages.add_staff') }}" style="border-radius: 50px; height: 36px; padding: 0 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span data-lang-key="add_staff">{{ __('messages.add_staff') }}</span>
        </button>
    </div>
</div>

<!-- 3. Users Table Card (Full Viewport Height with internal scrolling) -->
<div class="orders-card" id="users_table_card">
    <div class="orders-table-wrapper" id="users_table_wrapper">
        <table class="orders-table" id="users_table">
            <thead>
                <tr>
                    <th style="width: 25%;">Staff</th>
                    <th style="width: 21%;">Contact</th>
                    <th style="width: 13%;">Role</th>
                    <th style="width: 13%;">Status</th>
                    <th style="width: 14%;">Last Active</th>
                    <th style="width: 14%; min-width: 160px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    @php
                        $initials = strtoupper(substr($user->name, 0, 2));
                        $roleClass = 'role-' . strtolower($user->role);
                    @endphp
                    <tr id="user_row_{{ $user->id }}" class="us-row {{ $user->id === auth()->id() ? 'us-self' : '' }}">
                        <td class="us-id">
                            <div class="user-identity">
                                <div class="user-avatar">{{ $initials }}</div>
                                <div>
                                    <div class="user-name">
                                        {{ $user->name }}
                                        @if($user->id === auth()->id())
                                            <span class="user-tag-you">YOU</span>
                                        @endif
                                    </div>
                                    <div class="user-username">{{ '@' . $user->username }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="us-contact">
                            <div class="user-email">{{ $user->email }}</div>
                            <div class="user-phone {{ $user->phone ? '' : 'is-empty' }}">{{ $user->phone ?? '—' }}</div>
                        </td>
                        <td class="us-role">
                            <span class="role-pill {{ $roleClass }}">
                                {{ ucfirst($user->role) }}
                            </span>
                        </td>
                        <td class="us-status">
                            <x-badge :variant="$user->status === 'active' ? 'success' : ($user->status === 'suspended' ? 'danger' : 'warning')">
                                {{ ucfirst($user->status) }}
                            </x-badge>
                        </td>
                        <td class="us-last">
                            <div class="user-last-login">
                                {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'Never' }}
                            </div>
                            <div class="user-joined-date">
                                Joined {{ $user->created_at ? $user->created_at->format('M d, Y') : '—' }}
                            </div>
                        </td>
                        <td class="us-actions user-actions-cell" style="text-align: right;">
                            <div class="user-action-group">
                                <!-- View Details & Audit Button -->
                                <button
                                    type="button"
                                    class="btn-action-btn btn-view-user"
                                    title="View Profile & Activity"
                                    data-id="{{ $user->id }}"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span>View</span>
                                </button>

                                <!-- Edit Profile Button (Central Hub for Role, Status, Permissions, Password, Delete) -->
                                <button
                                    type="button"
                                    class="btn-action-btn btn-edit-user"
                                    title="Edit Staff Member"
                                    data-id="{{ $user->id }}"
                                    data-name="{{ $user->name }}"
                                    data-username="{{ $user->username }}"
                                    data-email="{{ $user->email }}"
                                    data-phone="{{ $user->phone }}"
                                    data-role="{{ $user->role }}"
                                    data-status="{{ $user->status }}"
                                    data-mustchange="{{ $user->must_change_password ? '1' : '0' }}"
                                    data-isself="{{ $user->id === auth()->id() ? '1' : '0' }}"
                                >
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                    <span>Edit</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem; color: var(--user-muted);">
                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">👥</div>
                            <div style="font-size: 1rem; font-weight: 700; color: var(--user-text);" data-lang-key="no_users_found">{{ __('messages.no_users_found') }}</div>
                            <p style="font-size: 0.85rem; margin-top: 0.25rem;">Try adjusting your search criteria or register a new staff account above.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- ==========================================================================
     CREATE USER MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="create_user_modal">
    <div class="user-modal-card">
        <div class="user-modal-header">
            <h3 data-lang-key="create_user">{{ __('messages.create_user') }}</h3>
        </div>
        <form action="{{ route('admin.users.store') }}" method="POST" autocomplete="off">
            @csrf
            <div class="user-modal-body">
                <div class="form-field-group">
                    <label for="create_user_name">Full Name *</label>
                    <input type="text" id="create_user_name" name="name" required placeholder="e.g. Sareth Chan">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="create_user_username">Username *</label>
                        <input type="text" id="create_user_username" name="username" required placeholder="e.g. sareth.c">
                    </div>
                    <div class="form-field-group">
                        <label for="create_user_phone">Phone Number</label>
                        <input type="text" id="create_user_phone" name="phone" placeholder="e.g. 012 345 678">
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="create_user_email">Email Address *</label>
                    <input type="email" id="create_user_email" name="email" required placeholder="e.g. staff@bakery.com">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="create_user_role">Staff Role *</label>
                        <select id="create_user_role" name="role" required>
                            <option value="cashier" selected>Cashier</option>
                            <option value="baker">Baker</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label for="create_user_status">Initial Status *</label>
                        <select id="create_user_status" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="create_user_password">Password * (min 6 chars)</label>
                    <input type="password" id="create_user_password" name="password" required minlength="6" autocomplete="new-password" placeholder="••••••••••••">
                </div>
            </div>
            <div class="user-modal-footer">
                <x-button variant="secondary" id="btn_cancel_create_user" class="btn-modal-cancel" data-lang-key="cancel">{{ __('messages.cancel') }}</x-button>
                <x-button variant="primary" type="submit" class="btn-user-primary" data-lang-key="save">{{ __('messages.save') }}</x-button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     EDIT USER MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="edit_user_modal">
    <div class="user-modal-card">
        <div class="user-modal-header">
            <h3 data-lang-key="edit_user">{{ __('messages.edit_user') }}</h3>
        </div>
        <form id="form_edit_user" method="POST" autocomplete="off">
            @csrf
            @method('PUT')
            <div class="user-modal-body">
                <div class="form-field-group">
                    <label for="edit_user_name">Full Name *</label>
                    <input type="text" id="edit_user_name" name="name" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="edit_user_username">Username *</label>
                        <input type="text" id="edit_user_username" name="username" required>
                    </div>
                    <div class="form-field-group">
                        <label for="edit_user_phone">Phone Number</label>
                        <input type="text" id="edit_user_phone" name="phone">
                    </div>
                </div>

                <div class="form-field-group">
                    <label for="edit_user_email">Email Address *</label>
                    <input type="email" id="edit_user_email" name="email" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="edit_user_role">Staff Role *</label>
                        <select id="edit_user_role" name="role" required>
                            <option value="cashier">Cashier</option>
                            <option value="baker">Baker</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-field-group">
                        <label for="edit_user_status">Account Status *</label>
                        <select id="edit_user_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>

                <!-- 1. PERMISSIONS SECTION -->
                <div style="margin-top: 1rem; border-top: 1px solid var(--user-border); padding-top: 1rem;">
                    <div class="user-action-card user-action-card-permissions">
                        <div>
                            <div class="user-action-card-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                <span>Role & Module Permissions</span>
                            </div>
                            <div class="user-action-card-desc">
                                Manage granular module access and custom permission overrides
                            </div>
                        </div>
                        <button type="button" class="btn btn-modal-cancel btn-action-card-btn btn-permissions-action" id="edit_btn_manage_permissions">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                            <span>Permissions</span>
                        </button>
                    </div>
                </div>

                <!-- 2. CHANGE PASSWORD SECTION -->
                <div style="margin-top: 0.85rem;">
                    <div class="user-action-card user-action-card-password">
                        <div>
                            <div class="user-action-card-title">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                                <span>Staff Password</span>
                            </div>
                            <div class="user-action-card-desc">
                                Reset account password
                            </div>
                        </div>
                        <button type="button" class="btn btn-modal-cancel btn-action-card-btn btn-password-action" id="edit_btn_open_reset_pwd">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                            <span>Change Password</span>
                        </button>
                    </div>
                    <!-- Hidden inputs for script & test backwards-compatibility -->
                    <input type="hidden" id="edit_user_password" name="password" value="">
                    <input type="checkbox" id="edit_must_change_password" name="must_change_password" value="1" style="display: none;">
                </div>

                <!-- 3. DANGER ZONE / DELETE ACCOUNT SECTION (Hidden if editing self) -->
                <div id="edit_danger_zone" style="margin-top: 0.85rem;">
                    <div class="user-action-card user-action-card-danger">
                        <div>
                            <div class="user-action-card-title" style="color: #ef4444;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                <span>Delete Staff Account</span>
                            </div>
                            <div class="user-action-card-desc">
                                Permanently delete account while preserving all historical business records
                            </div>
                        </div>
                        <button type="button" class="btn btn-modal-cancel btn-action-card-btn btn-danger-action" id="btn_delete_from_edit">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            <span>Delete Account</span>
                        </button>
                    </div>
                </div>
            </div>
            <div class="user-modal-footer">
                <x-button variant="secondary" id="btn_cancel_edit_user" class="btn-modal-cancel" data-lang-key="cancel">{{ __('messages.cancel') }}</x-button>
                <x-button variant="primary" type="submit" class="btn-user-primary" data-lang-key="save">{{ __('messages.save') }}</x-button>
            </div>
        </form>
        <form id="form_delete_user_from_modal" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>
    </div>
</div>

<!-- ==========================================================================
     DELETE ACCOUNT CONFIRMATION MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="delete_confirm_modal">
    <div class="user-modal-card" style="max-width: 440px;">
        <div class="user-modal-header" style="border-bottom: 1px solid rgba(220, 38, 38, 0.2);">
            <h3 style="color: #ef4444; display: flex; align-items: center; gap: 0.5rem; font-size: 1.05rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Delete Staff Account</span>
            </h3>
        </div>
        <div class="user-modal-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
            <p style="font-size: 0.925rem; margin: 0; color: var(--user-text); line-height: 1.5;">
                Are you sure you want to delete staff account <strong id="delete_modal_username" style="color: #f59e0b;"></strong>?
            </p>
            <div style="background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--user-muted); line-height: 1.45;">
                <div style="font-weight: 700; color: #ef4444; margin-bottom: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Historical Business Records Protected</span>
                </div>
                All sales, orders, production logs, and audit entries linked to this staff member remain safely preserved.
            </div>
        </div>
        <div class="user-modal-footer" style="padding: 1rem 1.5rem; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-modal-cancel" id="btn_cancel_delete_confirm" data-lang-key="cancel">{{ __('messages.cancel') }}</button>
            <button type="button" class="btn btn-action-card-btn btn-danger-action" id="btn_confirm_delete_execute" style="height: 40px; padding: 0 1.25rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span>Delete Account</span>
            </button>
        </div>
    </div>
</div>

<!-- ==========================================================================
     RESET PASSWORD MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="reset_password_modal">
    <div class="user-modal-card">
        <div class="user-modal-header" style="border-bottom: 1px solid rgba(245, 158, 11, 0.2);">
            <h3 style="display: flex; align-items: center; gap: 0.5rem;" data-lang-key="reset_password">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                <span>{{ __('messages.reset_password') }}</span>
            </h3>
        </div>
        <form id="form_reset_password" method="POST" autocomplete="off">
            @csrf
            <div class="user-modal-body">
                <div style="padding: 0.75rem 1rem; background: rgba(245, 158, 11, 0.08); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 12px; font-size: 0.85rem; font-weight: 600; color: #f59e0b; display: flex; align-items: center; gap: 0.5rem;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    <span>Resetting password for: <strong id="reset_pwd_user_title" style="color: var(--user-text);">User</strong></span>
                </div>

                <div class="form-field-group">
                    <label for="reset_pwd_input">New Password * (min 6 characters)</label>
                    <input type="password" id="reset_pwd_input" name="password" required minlength="6" autocomplete="new-password" placeholder="Enter new password">
                </div>
            </div>
            <div class="user-modal-footer">
                <x-button variant="secondary" id="btn_cancel_reset_pwd" class="btn-modal-cancel" data-lang-key="cancel">{{ __('messages.cancel') }}</x-button>
                <button type="submit" class="btn btn-user-primary" id="btn_submit_reset_pwd" style="height: 40px; padding: 0 1.25rem; font-size: 0.875rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Save New Password</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     STATUS QUICK-UPDATE MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="status_user_modal">
    <div class="user-modal-card">
        <div class="user-modal-header">
            <h3>Update Account Status</h3>
        </div>
        <form id="form_status_user" method="POST">
            @csrf
            <div class="user-modal-body">
                <div style="padding: 0.75rem; background: rgba(86, 48, 32, 0.05); border: 1px solid var(--user-border); border-radius: 12px; font-size: 0.85rem; font-weight: 600;">
                    Target User: <strong id="status_user_name"></strong>
                </div>

                <div class="form-field-group" style="margin-top: 1rem;">
                    <label for="modal_status_select">Set Account Status *</label>
                    <select id="modal_status_select" name="status" required>
                        <option value="active">Active (Full operational access)</option>
                        <option value="inactive">Inactive (Deactivated, blocked from logging in)</option>
                        <option value="suspended">Suspended (Temporarily locked due to policy)</option>
                    </select>
                </div>

                <div style="font-size: 0.8rem; color: var(--user-muted); line-height: 1.4;">
                    <strong>Historical Integrity Rule:</strong> Deactivating or suspending a staff member preserves all historical orders, sales, payments, and batch productions created by them.
                </div>
            </div>
            <div class="user-modal-footer">
                <x-button variant="secondary" id="btn_cancel_status_modal" class="btn-modal-cancel" data-lang-key="cancel">{{ __('messages.cancel') }}</x-button>
                <x-button variant="primary" type="submit" class="btn-user-primary" data-lang-key="save">{{ __('messages.save') }}</x-button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     PERMISSIONS MANAGEMENT MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="permissions_user_modal">
    <div class="user-modal-card wide-modal">
        <div class="user-modal-header">
            <div>
                <h3 data-lang-key="manage_permissions">{{ __('messages.manage_permissions') }}</h3>
                <div style="font-size: 0.8rem; color: var(--user-muted);" id="perm_user_subtitle">
                    Configuring permissions for Staff Member
                </div>
            </div>
        </div>
        <form id="form_update_permissions" method="POST">
            @csrf
            @method('PUT')
            <div class="user-modal-body" style="max-height: 65vh; overflow-y: auto;">
                <div id="perm_admin_notice" style="display: none; padding: 0.85rem 1rem; background: rgba(147, 51, 234, 0.12); border: 1px solid rgba(147, 51, 234, 0.35); border-radius: 12px; font-size: 0.85rem; font-weight: 700; color: #a855f7; margin-bottom: 0.75rem;">
                    👑 <strong>Administrator Account:</strong> Admins inherently possess all module permissions unconditionally. Overrides are not required.
                </div>

                <div class="permission-modules-list" id="permission_modules_container">
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
                                    <label class="perm-checkbox-item">
                                        <input
                                            type="checkbox"
                                            name="permissions[{{ $permKey }}]"
                                            value="1"
                                            id="perm_check_{{ str_replace('.', '_', $permKey) }}"
                                            class="perm-checkbox"
                                            data-perm-key="{{ $permKey }}"
                                        >
                                        <div>
                                            <span>{{ $permLabel }}</span>
                                            <span class="perm-role-default-tag" id="tag_default_{{ str_replace('.', '_', $permKey) }}" style="display: none;">Default</span>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="user-modal-footer">
                <button type="button" class="btn-modal-cancel" id="btn_cancel_perm_modal" data-lang-key="cancel">{{ __('messages.cancel') }}</button>
                <button type="submit" class="btn-user-primary" id="btn_save_permissions" data-lang-key="save_permissions">{{ __('messages.save_permissions') }}</button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     USER DETAILS & AUDIT TRAIL MODAL
     ========================================================================== -->
<div class="user-modal-backdrop" id="view_user_modal">
    <div class="user-modal-card">
        <div class="user-modal-header">
            <h3 data-lang-key="user_details">{{ __('messages.user_details') }}</h3>
        </div>
        <div class="user-modal-body">
            <div style="display: flex; align-items: center; gap: 1rem; border-bottom: 1px solid var(--user-border); padding-bottom: 1rem;">
                <div class="user-avatar" id="view_user_avatar" style="width: 52px; height: 52px; font-size: 1.25rem;">SC</div>
                <div>
                    <div style="font-weight: 800; font-size: 1.15rem; color: var(--user-text);" id="view_user_name">—</div>
                    <div style="font-size: 0.825rem; color: var(--user-muted);" id="view_user_username">@username</div>
                    <div style="display: flex; gap: 0.5rem; margin-top: 0.35rem;">
                        <span class="role-pill" id="view_user_role_pill">Role</span>
                        <span class="status-badge" id="view_user_status_badge">Status</span>
                    </div>
                </div>
            </div>

            <!-- Activity / Operational Stats -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem; text-align: center; margin: 0.5rem 0;">
                <div style="background: var(--bg-primary, #f8fafc); border: 1px solid var(--user-border); border-radius: 12px; padding: 0.65rem;">
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--user-muted);">ORDERS</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--user-text);" id="view_orders_count">0</div>
                </div>
                <div style="background: var(--bg-primary, #f8fafc); border: 1px solid var(--user-border); border-radius: 12px; padding: 0.65rem;">
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--user-muted);">SALES</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--user-text);" id="view_sales_count">0</div>
                </div>
                <div style="background: var(--bg-primary, #f8fafc); border: 1px solid var(--user-border); border-radius: 12px; padding: 0.65rem;">
                    <div style="font-size: 0.7rem; font-weight: 700; color: var(--user-muted);">BAKING</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--user-text);" id="view_prod_count">0</div>
                </div>
            </div>

            <div>
                <h4 style="font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: var(--user-muted); margin: 0 0 0.5rem 0;" data-lang-key="audit_trail">
                    {{ __('messages.audit_trail') }}
                </h4>
                <div class="audit-timeline" id="view_user_audit_timeline">
                    <div style="font-size: 0.8rem; color: var(--user-muted); padding: 0.5rem 0;">Loading activity trail...</div>
                </div>
            </div>
        </div>
        <div class="user-modal-footer">
            <button type="button" class="btn-modal-cancel" id="btn_close_view_footer" style="width: 100%; text-align: center;">Close</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Helpers
    function openModal(modal) {
        if (modal) modal.classList.add('is-active');
    }
    function closeModal(modal) {
        if (modal) modal.classList.remove('is-active');
    }

    // 0. Actions "More" Dropdown Toggle
    document.querySelectorAll('.btn-more-toggle').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const wrapper = this.closest('.user-more-wrapper');
            const row = this.closest('tr');
            const isOpen = wrapper ? wrapper.classList.contains('is-open') : false;

            // Close other open dropdowns
            document.querySelectorAll('.user-more-wrapper.is-open').forEach(w => {
                if (w !== wrapper) {
                    w.classList.remove('is-open');
                    const r = w.closest('tr');
                    if (r) r.classList.remove('has-open-dropdown');
                    const t = w.querySelector('.btn-more-toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                }
            });

            if (wrapper) {
                if (isOpen) {
                    wrapper.classList.remove('is-open');
                    if (row) row.classList.remove('has-open-dropdown');
                    this.setAttribute('aria-expanded', 'false');
                } else {
                    wrapper.classList.add('is-open');
                    if (row) row.classList.add('has-open-dropdown');
                    this.setAttribute('aria-expanded', 'true');
                }
            }
        });
    });

    // Close dropdown when clicking an action item
    document.querySelectorAll('.user-more-menu .dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
            const wrapper = this.closest('.user-more-wrapper');
            if (wrapper) {
                wrapper.classList.remove('is-open');
                const r = wrapper.closest('tr');
                if (r) r.classList.remove('has-open-dropdown');
                const t = wrapper.querySelector('.btn-more-toggle');
                if (t) t.setAttribute('aria-expanded', 'false');
            }
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.user-more-wrapper')) {
            document.querySelectorAll('.user-more-wrapper.is-open').forEach(w => {
                w.classList.remove('is-open');
                const r = w.closest('tr');
                if (r) r.classList.remove('has-open-dropdown');
                const t = w.querySelector('.btn-more-toggle');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
        }
    });

    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.user-more-wrapper.is-open').forEach(w => {
                w.classList.remove('is-open');
                const r = w.closest('tr');
                if (r) r.classList.remove('has-open-dropdown');
                const t = w.querySelector('.btn-more-toggle');
                if (t) t.setAttribute('aria-expanded', 'false');
            });
            document.querySelectorAll('.user-modal-backdrop.is-active').forEach(modal => {
                closeModal(modal);
            });
        }
    });

    // 1. Create User Modal
    const createModal = document.getElementById('create_user_modal');
    const btnOpenCreate = document.getElementById('btn_open_create_user_modal');
    const btnCloseCreate = document.getElementById('btn_close_create_user');
    const btnCancelCreate = document.getElementById('btn_cancel_create_user');

    if (btnOpenCreate) btnOpenCreate.addEventListener('click', () => openModal(createModal));
    if (btnCloseCreate) btnCloseCreate.addEventListener('click', () => closeModal(createModal));
    if (btnCancelCreate) btnCancelCreate.addEventListener('click', () => closeModal(createModal));

    // 2. Edit User Modal & Integrated Features (Permissions, Password, Delete)
    const editModal = document.getElementById('edit_user_modal');
    const btnCloseEdit = document.getElementById('btn_close_edit_user');
    const btnCancelEdit = document.getElementById('btn_cancel_edit_user');
    const formEdit = document.getElementById('form_edit_user');
    const btnManagePermFromEdit = document.getElementById('edit_btn_manage_permissions');
    const btnOpenResetPwdFromEdit = document.getElementById('edit_btn_open_reset_pwd');
    const btnDeleteFromEdit = document.getElementById('btn_delete_from_edit');
    const formDeleteFromModal = document.getElementById('form_delete_user_from_modal');

    let currentEditUserId = null;
    let currentEditUserName = '';
    let currentEditUserUsername = '';
    let currentEditUserRole = '';

    function populateAndOpenEdit(btn) {
        currentEditUserId = btn.dataset.id;
        currentEditUserName = btn.dataset.name || '';
        currentEditUserUsername = btn.dataset.username || '';
        currentEditUserRole = btn.dataset.role || 'cashier';
        const isSelf = btn.dataset.isself === '1';

        if (formEdit) formEdit.action = `/admin/users/${currentEditUserId}`;
        const nameInput = document.getElementById('edit_user_name');
        if (nameInput) nameInput.value = currentEditUserName;
        const usernameInput = document.getElementById('edit_user_username');
        if (usernameInput) usernameInput.value = currentEditUserUsername;
        const emailInput = document.getElementById('edit_user_email');
        if (emailInput) emailInput.value = btn.dataset.email || '';
        const phoneInput = document.getElementById('edit_user_phone');
        if (phoneInput) phoneInput.value = btn.dataset.phone || '';
        const roleInput = document.getElementById('edit_user_role');
        if (roleInput) roleInput.value = currentEditUserRole;
        const statusInput = document.getElementById('edit_user_status');
        if (statusInput) statusInput.value = btn.dataset.status || 'active';
        if (document.getElementById('edit_must_change_password')) {
            document.getElementById('edit_must_change_password').checked = false;
        }
        const pwdInput = document.getElementById('edit_user_password');
        if (pwdInput) pwdInput.value = '';

        const dangerZone = document.getElementById('edit_danger_zone');
        if (dangerZone) {
            dangerZone.style.display = isSelf ? 'none' : 'block';
        }

        openModal(editModal);
    }

    document.querySelectorAll('.btn-edit-user').forEach(btn => {
        btn.addEventListener('click', function() {
            populateAndOpenEdit(this);
        });
    });

    if (btnCloseEdit) btnCloseEdit.addEventListener('click', () => closeModal(editModal));
    if (btnCancelEdit) btnCancelEdit.addEventListener('click', () => closeModal(editModal));

    // Open Permissions from inside Edit Modal
    if (btnManagePermFromEdit) {
        btnManagePermFromEdit.addEventListener('click', function(e) {
            e.preventDefault();
            if (!currentEditUserId) return;
            const selectedRole = document.getElementById('edit_user_role') ? document.getElementById('edit_user_role').value : currentEditUserRole;
            openPermissionsForUser(currentEditUserId, currentEditUserName, selectedRole);
        });
    }

    // Open Reset Password Modal from inside Edit Modal
    if (btnOpenResetPwdFromEdit) {
        btnOpenResetPwdFromEdit.addEventListener('click', function(e) {
            e.preventDefault();
            if (!currentEditUserId) return;
            if (formReset) formReset.action = `/admin/users/${currentEditUserId}/reset-password`;
            if (resetTitle) resetTitle.textContent = `${currentEditUserName} (@${currentEditUserUsername})`;
            const resetPwdInput = document.getElementById('reset_pwd_input');
            if (resetPwdInput) resetPwdInput.value = '';
            openModal(resetModal);
        });
    }

    // Delete Account with Custom Confirmation Modal
    const deleteConfirmModal = document.getElementById('delete_confirm_modal');
    const btnCloseDeleteConfirm = document.getElementById('btn_close_delete_confirm');
    const btnCancelDeleteConfirm = document.getElementById('btn_cancel_delete_confirm');
    const btnConfirmDeleteExecute = document.getElementById('btn_confirm_delete_execute');
    const deleteModalUsername = document.getElementById('delete_modal_username');

    if (btnDeleteFromEdit) {
        btnDeleteFromEdit.addEventListener('click', function(e) {
            e.preventDefault();
            if (!currentEditUserId) return;
            if (deleteModalUsername) {
                deleteModalUsername.textContent = `@${currentEditUserUsername}`;
            }
            openModal(deleteConfirmModal);
        });
    }

    if (btnCloseDeleteConfirm) {
        btnCloseDeleteConfirm.addEventListener('click', () => closeModal(deleteConfirmModal));
    }
    if (btnCancelDeleteConfirm) {
        btnCancelDeleteConfirm.addEventListener('click', () => closeModal(deleteConfirmModal));
    }
    if (btnConfirmDeleteExecute) {
        btnConfirmDeleteExecute.addEventListener('click', function() {
            if (!currentEditUserId) return;
            btnConfirmDeleteExecute.disabled = true;
            btnConfirmDeleteExecute.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <span>Deleting...</span>';
            if (formDeleteFromModal) {
                formDeleteFromModal.action = `/admin/users/${currentEditUserId}`;
                formDeleteFromModal.submit();
            }
        });
    }

    // 3. Reset Password Modal
    const resetModal = document.getElementById('reset_password_modal');
    const btnCloseResetPwd = document.getElementById('btn_close_reset_pwd');
    const btnCancelReset = document.getElementById('btn_cancel_reset_pwd');
    const formReset = document.getElementById('form_reset_password');
    const resetTitle = document.getElementById('reset_pwd_user_title');

    document.querySelectorAll('.btn-reset-pwd-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id || currentEditUserId;
            const name = this.dataset.name || currentEditUserName;
            const username = this.dataset.username || currentEditUserUsername;
            if (id) {
                if (formReset) formReset.action = `/admin/users/${id}/reset-password`;
                if (resetTitle) resetTitle.textContent = `${name} (@${username})`;
                const resetPwdInput = document.getElementById('reset_pwd_input');
                if (resetPwdInput) resetPwdInput.value = '';
                openModal(resetModal);
            }
        });
    });

    if (btnCloseResetPwd) btnCloseResetPwd.addEventListener('click', () => closeModal(resetModal));
    if (btnCancelReset) btnCancelReset.addEventListener('click', () => closeModal(resetModal));

    // 4. Status Quick Update Modal
    const statusModal = document.getElementById('status_user_modal');
    const btnCloseStatusModal = document.getElementById('btn_close_status_modal');
    const btnCancelStatus = document.getElementById('btn_cancel_status_modal');
    const formStatus = document.getElementById('form_status_user');
    const statusUserName = document.getElementById('status_user_name');
    const statusSelect = document.getElementById('modal_status_select');

    document.querySelectorAll('.btn-status-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            const name = this.dataset.name;
            const currentStatus = this.dataset.status;
            if (formStatus) formStatus.action = `/admin/users/${id}/status`;
            if (statusUserName) statusUserName.textContent = name;
            if (statusSelect) statusSelect.value = currentStatus;
            openModal(statusModal);
        });
    });

    if (btnCloseStatusModal) btnCloseStatusModal.addEventListener('click', () => closeModal(statusModal));
    if (btnCancelStatus) btnCancelStatus.addEventListener('click', () => closeModal(statusModal));

    // 5. Permissions Management Modal
    const permModal = document.getElementById('permissions_user_modal');
    const btnClosePermModal = document.getElementById('btn_close_perm_modal');
    const btnCancelPerm = document.getElementById('btn_cancel_perm_modal');
    const formPerm = document.getElementById('form_update_permissions');
    const permSubtitle = document.getElementById('perm_user_subtitle');
    const permAdminNotice = document.getElementById('perm_admin_notice');
    const btnSavePerm = document.getElementById('btn_save_permissions');

    async function openPermissionsForUser(userId, userName, userRole) {
        if (!userId) return;
        if (formPerm) formPerm.action = `/admin/users/${userId}/permissions`;
        if (permSubtitle) permSubtitle.textContent = `Configuring permissions for ${userName} (${(userRole || '').toUpperCase()})`;

        const permBtn = document.getElementById('edit_btn_manage_permissions');
        let originalHtml = '';
        if (permBtn) {
            originalHtml = permBtn.innerHTML;
            permBtn.disabled = true;
            permBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <span>Loading...</span>';
        }

        try {
            const response = await fetch(`/admin/users/${userId}/permissions`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();

            const isAdmin = (userRole && userRole.toLowerCase() === 'admin');
            if (permAdminNotice) {
                permAdminNotice.style.display = isAdmin ? 'block' : 'none';
            }
            if (btnSavePerm) {
                btnSavePerm.disabled = isAdmin;
            }

            const defaults = data.role_defaults || [];
            const effective = data.effective_permissions || [];

            document.querySelectorAll('.perm-checkbox').forEach(cb => {
                const key = cb.dataset.permKey;
                const safeKey = key.replace(/\./g, '_');
                const defaultTag = document.getElementById(`tag_default_${safeKey}`);

                if (defaultTag) {
                    defaultTag.style.display = defaults.includes(key) ? 'inline-block' : 'none';
                }

                if (isAdmin) {
                    cb.checked = true;
                    cb.disabled = true;
                } else {
                    cb.disabled = false;
                    cb.checked = effective.includes(key);
                }
            });

            openModal(permModal);
        } catch (err) {
            console.error('Error fetching permissions:', err);
            alert('Could not load user permissions. Please try again.');
        } finally {
            if (permBtn) {
                permBtn.disabled = false;
                permBtn.innerHTML = originalHtml;
            }
        }
    }

    document.querySelectorAll('.btn-permissions-user').forEach(btn => {
        btn.addEventListener('click', function() {
            const userId = this.dataset.id || currentEditUserId;
            const userName = this.dataset.name || currentEditUserName;
            const userRole = this.dataset.role || currentEditUserRole;
            if (userId) {
                openPermissionsForUser(userId, userName, userRole);
            }
        });
    });

    if (btnClosePermModal) btnClosePermModal.addEventListener('click', () => closeModal(permModal));
    if (btnCancelPerm) btnCancelPerm.addEventListener('click', () => closeModal(permModal));

    // 6. View User Details & Audit Trail Modal
    const viewModal = document.getElementById('view_user_modal');
    const btnCloseViewUser = document.getElementById('btn_close_view_user');
    const btnCloseViewFooter = document.getElementById('btn_close_view_footer');
    const viewName = document.getElementById('view_user_name');
    const viewUsername = document.getElementById('view_user_username');
    const viewAvatar = document.getElementById('view_user_avatar');
    const viewRolePill = document.getElementById('view_user_role_pill');
    const viewStatusBadge = document.getElementById('view_user_status_badge');
    const viewOrders = document.getElementById('view_orders_count');
    const viewSales = document.getElementById('view_sales_count');
    const viewProd = document.getElementById('view_prod_count');
    const viewTimeline = document.getElementById('view_user_audit_timeline');

    async function openUserDetails(userId) {
        if (!userId) return;
        if (viewTimeline) {
            viewTimeline.innerHTML = '<div style="font-size: 0.8rem; color: var(--user-muted); padding: 0.5rem 0;">Loading activity history...</div>';
        }
        openModal(viewModal);

        try {
            const response = await fetch(`/admin/users/${userId}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            const u = data.user;

            if (viewName) viewName.textContent = u.name;
            if (viewUsername) viewUsername.textContent = `@${u.username}`;
            if (viewAvatar) viewAvatar.textContent = (u.name || '').substring(0, 2).toUpperCase();

            if (viewRolePill) {
                viewRolePill.className = `role-pill role-${u.role}`;
                viewRolePill.textContent = (u.role || '').toUpperCase();
            }

            if (viewStatusBadge) {
                viewStatusBadge.className = `status-badge status-${u.status}`;
                viewStatusBadge.innerHTML = `<span class="status-dot"></span> ${(u.status || '').toUpperCase()}`;
            }

            if (viewOrders) viewOrders.textContent = u.orders_count || 0;
            if (viewSales) viewSales.textContent = u.sales_count || 0;
            if (viewProd) viewProd.textContent = u.productions_count || 0;

            const audits = data.recent_audits || [];
            if (viewTimeline) {
                if (audits.length === 0) {
                    viewTimeline.innerHTML = '<div style="font-size: 0.8rem; color: var(--user-muted); padding: 0.5rem 0;">No recorded audit events yet.</div>';
                } else {
                    viewTimeline.innerHTML = audits.map(a => {
                        const dateStr = new Date(a.created_at).toLocaleString();
                        return `
                            <div class="audit-timeline-item">
                                <div>
                                    <div class="audit-action-title">${escapeHtml(a.description || a.action)}</div>
                                    <div class="audit-meta-time">${dateStr} • IP: ${a.ip_address || '—'}</div>
                                </div>
                            </div>
                        `;
                    }).join('');
                }
            }
        } catch (err) {
            console.error('Error fetching user details:', err);
            if (viewTimeline) {
                viewTimeline.innerHTML = '<div style="font-size: 0.8rem; color: #ef4444; padding: 0.5rem 0;">Failed to load user details.</div>';
            }
        }
    }

    document.querySelectorAll('.btn-view-user').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openUserDetails(this.dataset.id);
        });
    });

    // Delegated click listener for table action buttons (both View and Edit)
    document.addEventListener('click', function(e) {
        const viewBtn = e.target.closest('.btn-view-user');
        if (viewBtn && viewBtn.dataset.id) {
            e.preventDefault();
            openUserDetails(viewBtn.dataset.id);
            return;
        }
        const editBtn = e.target.closest('.btn-edit-user');
        if (editBtn && editBtn.dataset.id) {
            e.preventDefault();
            populateAndOpenEdit(editBtn);
            return;
        }
    });

    if (btnCloseViewUser) btnCloseViewUser.addEventListener('click', () => closeModal(viewModal));
    if (btnCloseViewFooter) btnCloseViewFooter.addEventListener('click', () => closeModal(viewModal));

    // Close on backdrop click
    document.querySelectorAll('.user-modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', function(e) {
            if (e.target === this) closeModal(this);
        });
    });

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
@endpush
@endsection
