@extends('layouts.app')

@section('title', 'Customer Management - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/customers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.customers.index', array_merge(request()->except('status'), ['status' => 'all'])) }}"
           class="period-pill {{ (!request()->filled('status') || request('status') === 'all') ? 'active' : '' }}">
            <span>All Customers ({{ number_format($totalCustomers) }})</span>
        </a>
        <a href="{{ route('admin.customers.index', array_merge(request()->except('status'), ['status' => 'active'])) }}"
           class="period-pill {{ request('status') === 'active' ? 'active' : '' }}">
            <span>Active Accounts ({{ number_format($activeCustomers) }})</span>
        </a>
        <a href="{{ route('admin.customers.index', array_merge(request()->except('status'), ['status' => 'inactive'])) }}"
           class="period-pill {{ request('status') === 'inactive' ? 'active' : '' }}">
            <span>Inactive ({{ number_format(max(0, $totalCustomers - $activeCustomers)) }})</span>
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container">

    <h1 class="sr-only">Customer Management</h1>

    <!-- Flash Notifications -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
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

    <!-- 1. 4 Core KPI Stat Cards Grid (Exact Orders / Dashboard Page Style) -->
    <div class="dash-stat-row customer-stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Customers</span>
                <div class="stat-value">{{ number_format($totalCustomers) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Registered
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active Accounts</span>
                <div class="stat-value">{{ number_format($activeCustomers) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Active
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box yellow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Loyalty Members</span>
                <div class="stat-value">{{ number_format($loyaltyMembers) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Points Active
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Customer Revenue</span>
                <div class="stat-value">${{ number_format($totalCustomerRevenue, 2) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Total Spend
                </span>
            </div>
        </div>
    </div>

    <!-- 2. Action & Filter Toolbar Row (Exact Orders & Ingredients Style) -->
    <div class="ingredients-toolbar-row" id="customers_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Left: Filters without toolbar search input -->
        <form method="GET" action="{{ route('admin.customers.index') }}" id="customers_filter_form" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin: 0;">
            @if(request('search'))
                <input type="hidden" name="search" id="customer_search_input" value="{{ request('search') }}">
            @endif

            <!-- Pill Filter Card for Tier & Status -->
            <div class="supplier-filter-pill-card">
                <select name="tier" id="customer_tier_select" onchange="this.form.submit()">
                    <option value="all" {{ (!request()->filled('tier') || request('tier') === 'all') ? 'selected' : '' }}>All Loyalty Tiers</option>
                    <option value="standard" {{ request('tier') == 'standard' ? 'selected' : '' }}>Standard Tier</option>
                    <option value="silver" {{ request('tier') == 'silver' ? 'selected' : '' }}>Silver Tier (100+ pts)</option>
                    <option value="gold" {{ request('tier') == 'gold' ? 'selected' : '' }}>Gold Tier (300+ pts)</option>
                    <option value="vip" {{ request('tier') == 'vip' ? 'selected' : '' }}>VIP Tier (500+ pts)</option>
                </select>

                <select name="status" id="customer_status_select" onchange="this.form.submit()">
                    <option value="all" {{ (!request()->filled('status') || request('status') === 'all') ? 'selected' : '' }}>All Status</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>

                <button type="submit" class="btn" style="border-radius: 50px; padding: 0.35rem 1rem; font-weight: 700; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.825rem;">Filter</button>

                @if(request()->hasAny(['search', 'tier', 'status']) && (request('search') || (request('tier') && request('tier') !== 'all') || (request('status') && request('status') !== 'all')))
                    <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.35rem 0.85rem; font-weight: 600; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem;">Clear</a>
                @endif
            </div>
        </form>

        <!-- Right: Add Customer Button -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <button type="button" class="btn" id="btn_open_add_customer_modal" style="border-radius: 50px; height: 36px; padding: 0 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span data-lang-key="add_customer">{{ __('messages.add_customer') }}</span>
            </button>
        </div>
    </div>

    <!-- 3. Customers Table Card (Full Viewport Height with internal scrolling) -->
    <div class="orders-card" id="customers_table_card">
        <div class="orders-table-wrapper" id="customers_table_wrapper">
            <table class="orders-table" id="customers_table">
                <thead>
                    <tr>
                        <th style="width: 26%;">Customer Name</th>
                        <th style="width: 18%;">Contact Info</th>
                        <th style="width: 18%;">Loyalty Points & Tier</th>
                        <th style="width: 12%;">Orders</th>
                        <th style="width: 12%;">Status</th>
                        <th style="width: 14%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($customers as $customer)
                        @php
                            $initials = strtoupper(substr($customer->name, 0, 2));
                            $tier = strtolower($customer->loyalty_tier ?? 'standard');
                            $tierSvg = match($tier) {
                                'vip' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>',
                                'gold' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
                                'silver' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
                                default => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
                            };
                        @endphp
                        <tr id="customer_row_{{ $customer->id }}">
                            <!-- Customer Name & Address -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #563020 0%, #ea580c 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
                                        {{ $initials }}
                                    </div>
                                    <div>
                                        <button
                                            type="button"
                                            class="btn-view-customer"
                                            style="background: none; border: none; padding: 0; font-weight: 700; font-size: 0.9rem; color: var(--cust-text, #0f172a); cursor: pointer; text-align: left; transition: color 0.15s ease;"
                                            onmouseover="this.style.color='#ea580c'"
                                            onmouseout="this.style.color='var(--cust-text, #0f172a)'"
                                            title="View Customer Profile"
                                            data-id="{{ $customer->id }}"
                                            data-name="{{ $customer->name }}"
                                            data-phone="{{ $customer->phone ?? '' }}"
                                            data-email="{{ $customer->email ?? '' }}"
                                            data-address="{{ $customer->address ?? '' }}"
                                            data-points="{{ $customer->loyalty_points }}"
                                            data-tier="{{ strtolower($customer->loyalty_tier ?? 'standard') }}"
                                            data-status="{{ $customer->status }}"
                                            data-orders-count="{{ $customer->orders_count }}"
                                            data-spent="${{ number_format($customer->total_spent, 2) }}"
                                            data-avg="${{ number_format($customer->average_order_value, 2) }}"
                                            data-created="{{ $customer->created_at->format('M d, Y') }}"
                                            data-show-url="{{ route('admin.customers.show', $customer->id) }}"
                                        >
                                            {{ $customer->name }}
                                        </button>
                                        <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); max-width: 240px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ $customer->address ?? 'No address registered' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Info -->
                            <td>
                                <div style="font-weight: 700; font-size: 0.875rem; color: var(--text-primary, #0f172a);">{{ $customer->phone ?? '—' }}</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted, #71717a);">{{ $customer->email ?? '—' }}</div>
                            </td>

                            <!-- Loyalty Points & Tier -->
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <span class="tier-pill tier-{{ $tier }}" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 2px 8px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;">
                                        {!! $tierSvg !!} <span>{{ strtoupper($tier) }}</span>
                                    </span>
                                    <span style="font-weight: 800; font-size: 0.85rem; color: var(--text-primary, #0f172a);">
                                        {{ number_format($customer->loyalty_points) }} pts
                                    </span>
                                </div>
                            </td>

                            <!-- Orders -->
                            <td>
                                <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                    {{ $customer->orders_count }} Orders
                                </span>
                            </td>

                            <!-- Status -->
                            <td>
                                <span class="status-badge {{ $customer->status === 'active' ? 'status-completed' : 'status-cancelled' }}">
                                    {{ ucfirst($customer->status) }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.4rem;">
                                    <!-- View Customer Profile & History Modal Button -->
                                    <button
                                        type="button"
                                        class="btn-icon-action btn-view-customer"
                                        title="View Customer Profile"
                                        data-id="{{ $customer->id }}"
                                        data-name="{{ $customer->name }}"
                                        data-phone="{{ $customer->phone ?? '' }}"
                                        data-email="{{ $customer->email ?? '' }}"
                                        data-address="{{ $customer->address ?? '' }}"
                                        data-points="{{ $customer->loyalty_points }}"
                                        data-tier="{{ strtolower($customer->loyalty_tier ?? 'standard') }}"
                                        data-status="{{ $customer->status }}"
                                        data-orders-count="{{ $customer->orders_count }}"
                                        data-spent="${{ number_format($customer->total_spent, 2) }}"
                                        data-avg="${{ number_format($customer->average_order_value, 2) }}"
                                        data-created="{{ $customer->created_at->format('M d, Y') }}"
                                        data-show-url="{{ route('admin.customers.show', $customer->id) }}"
                                    >
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>

                                    <!-- Edit Customer Button -->
                                    <button
                                        type="button"
                                        class="btn-icon-action btn-edit-customer"
                                        title="Edit Customer"
                                        data-id="{{ $customer->id }}"
                                        data-name="{{ $customer->name }}"
                                        data-phone="{{ $customer->phone }}"
                                        data-email="{{ $customer->email }}"
                                        data-address="{{ $customer->address }}"
                                        data-points="{{ $customer->loyalty_points }}"
                                        data-status="{{ $customer->status }}"
                                    >
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                    </button>

                                    <!-- Delete Customer Form -->
                                    <form action="{{ route('admin.customers.destroy', $customer->id) }}" method="POST" onsubmit="return confirm('Delete customer \'{{ $customer->name }}\'? Their past order records will remain safely preserved with customer detached.');" style="display: inline; margin: 0;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon-action delete" title="Delete Customer">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted, #71717a);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🥖</div>
                                <div style="font-size: 1rem; font-weight: 700; color: var(--text-primary, #0f172a);">No Customers Found</div>
                                <p style="font-size: 0.85rem; margin-top: 0.25rem;">Try adjusting your search criteria or register a new customer above.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ==========================================================================
     ADD CUSTOMER MODAL
     ========================================================================== -->
<div class="cust-modal-backdrop" id="add_customer_modal">
    <div class="cust-modal-card">
        <div class="cust-modal-header">
            <h3 data-lang-key="add_customer">Add Customer</h3>
        </div>
        <form action="{{ route('admin.customers.store') }}" method="POST">
            @csrf
            <div class="cust-modal-body">
                <div class="form-field-group">
                    <label for="add_cust_name" data-lang-key="customer_name">Full Name *</label>
                    <input type="text" id="add_cust_name" name="name" required placeholder="e.g. John Doe">
                </div>

                <div class="form-field-group">
                    <label for="add_cust_phone" data-lang-key="phone">Phone Number</label>
                    <input type="text" id="add_cust_phone" name="phone" placeholder="e.g. 012 345 678">
                </div>

                <div class="form-field-group">
                    <label for="add_cust_email" data-lang-key="email">Email Address</label>
                    <input type="email" id="add_cust_email" name="email" placeholder="e.g. customer@example.com">
                </div>

                <div class="form-field-group">
                    <label for="add_cust_address" data-lang-key="address">Address</label>
                    <textarea id="add_cust_address" name="address" rows="2" placeholder="Street, Khan / District, City"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="add_cust_points" data-lang-key="loyalty_points">Initial Loyalty Points</label>
                        <input type="number" id="add_cust_points" name="loyalty_points" min="0" value="0">
                    </div>

                    <div class="form-field-group">
                        <label for="add_cust_status" data-lang-key="status">Status</label>
                        <select id="add_cust_status" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="cust-modal-footer">
                <x-button variant="secondary" id="btn_cancel_add_customer" data-lang-key="cancel">Cancel</x-button>
                <x-button variant="primary" type="submit" class="btn-cust-primary" data-lang-key="save">Save Customer</x-button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     EDIT CUSTOMER MODAL
     ========================================================================== -->
<div class="cust-modal-backdrop" id="edit_customer_modal">
    <div class="cust-modal-card">
        <div class="cust-modal-header">
            <h3 data-lang-key="edit_customer">Edit Customer Profile</h3>
        </div>
        <form id="form_edit_customer" method="POST">
            @csrf
            @method('PUT')
            <div class="cust-modal-body">
                <div class="form-field-group">
                    <label for="edit_cust_name" data-lang-key="customer_name">Full Name *</label>
                    <input type="text" id="edit_cust_name" name="name" required>
                </div>

                <div class="form-field-group">
                    <label for="edit_cust_phone" data-lang-key="phone">Phone Number</label>
                    <input type="text" id="edit_cust_phone" name="phone">
                </div>

                <div class="form-field-group">
                    <label for="edit_cust_email" data-lang-key="email">Email Address</label>
                    <input type="email" id="edit_cust_email" name="email">
                </div>

                <div class="form-field-group">
                    <label for="edit_cust_address" data-lang-key="address">Address</label>
                    <textarea id="edit_cust_address" name="address" rows="2"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-field-group">
                        <label for="edit_cust_points" data-lang-key="loyalty_points">Loyalty Points</label>
                        <input type="number" id="edit_cust_points" name="loyalty_points" min="0">
                    </div>

                    <div class="form-field-group">
                        <label for="edit_cust_status" data-lang-key="status">Status</label>
                        <select id="edit_cust_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="cust-modal-footer">
                <x-button variant="secondary" id="btn_cancel_edit_customer" data-lang-key="cancel">Cancel</x-button>
                <x-button variant="primary" type="submit" class="btn-cust-primary" data-lang-key="save">Update Customer</x-button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================================================
     VIEW CUSTOMER PROFILE & ORDER HISTORY MODAL (STAY IN PAGE)
     ========================================================================== -->
<div class="cust-modal-backdrop" id="view_customer_modal">
    <div class="cust-modal-card cust-modal-card-lg">
        <div class="cust-modal-header" style="align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 0.85rem;">
                <div class="cust-profile-avatar-large" id="view_cust_avatar" style="width: 52px; height: 52px; font-size: 1.25rem; margin: 0; flex-shrink: 0;">--</div>
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <h3 id="view_cust_name" style="font-size: 1.2rem; font-weight: 800; margin: 0; color: var(--cust-text);">Customer Name</h3>
                        <span id="view_cust_tier_badge" class="tier-pill tier-standard" style="font-size: 0.725rem; padding: 0.2rem 0.6rem; display: inline-flex; align-items: center; gap: 0.35rem;"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> <span>Standard Tier</span></span>
                        <span id="view_cust_status_badge" class="status-badge status-completed" style="font-size: 0.725rem; padding: 0.2rem 0.6rem;">Active</span>
                    </div>
                    <div id="view_cust_created" style="font-size: 0.8rem; color: var(--cust-muted); margin-top: 0.25rem;">Member since --</div>
                </div>
            </div>
        </div>

        <div class="cust-modal-body" style="gap: 1.25rem;">
            <!-- 4 Quick Stats KPI Boxes -->
            <div class="cust-history-stats-grid" style="grid-template-columns: repeat(4, 1fr); gap: 0.65rem;">
                <div class="cust-history-stat-box" style="padding: 0.75rem 0.5rem;">
                    <div class="box-label" style="font-size: 0.7rem;">Total Spent</div>
                    <div class="box-value" id="view_cust_spent" style="font-size: 1.15rem; color: #16a34a;">$0.00</div>
                </div>
                <div class="cust-history-stat-box" style="padding: 0.75rem 0.5rem;">
                    <div class="box-label" style="font-size: 0.7rem;">Orders</div>
                    <div class="box-value" id="view_cust_orders_count" style="font-size: 1.15rem;">0</div>
                </div>
                <div class="cust-history-stat-box" style="padding: 0.75rem 0.5rem;">
                    <div class="box-label" style="font-size: 0.7rem;">Avg Order</div>
                    <div class="box-value" id="view_cust_avg" style="font-size: 1.15rem;">$0.00</div>
                </div>
                <div class="cust-history-stat-box" style="padding: 0.75rem 0.5rem;">
                    <div class="box-label" style="font-size: 0.7rem;">Loyalty Pts</div>
                    <div class="box-value" id="view_cust_points" style="font-size: 1.15rem; color: #d97706;">0 pts</div>
                </div>
            </div>

            <!-- Customer Details & Contacts -->
            <div style="background: rgba(125, 125, 125, 0.08); border: 1px solid var(--cust-border); border-radius: 12px; padding: 0.85rem 1rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; font-size: 0.825rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--cust-muted);">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                        <span><strong style="color: var(--cust-text);">Phone:</strong> <span id="view_cust_phone">—</span></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; color: var(--cust-muted);">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <span><strong style="color: var(--cust-text);">Email:</strong> <span id="view_cust_email">—</span></span>
                    </div>
                    <div style="grid-column: 1 / -1; display: flex; align-items: center; gap: 0.5rem; color: var(--cust-muted);">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span><strong style="color: var(--cust-text);">Address:</strong> <span id="view_cust_address">—</span></span>
                    </div>
                </div>
            </div>

            <!-- Recent Orders Section -->
            <div>
                <div style="margin-bottom: 0.65rem;">
                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--cust-text); display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span>Recent Orders</span>
                    </div>
                </div>

                <div id="view_cust_orders_container" style="max-height: 240px; overflow-y: auto; border: 1px solid var(--cust-border); border-radius: 12px;">
                    <div id="view_cust_orders_loading" style="padding: 1.5rem; text-align: center; color: var(--cust-muted); font-size: 0.85rem;">
                        Loading orders...
                    </div>
                    <table class="customers-table" id="view_cust_orders_table" style="display: none; margin: 0; font-size: 0.825rem;">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody id="view_cust_orders_tbody">
                        </tbody>
                    </table>
                    <div id="view_cust_orders_empty" style="display: none; padding: 1.5rem; text-align: center; color: var(--cust-muted); font-size: 0.85rem;">
                        No Purchase History Yet
                    </div>
                </div>
            </div>
        </div>

        <div class="cust-modal-footer" style="justify-content: space-between;">
            <button type="button" class="btn-modal-cancel" id="btn_edit_from_view" style="display: inline-flex; align-items: center; gap: 0.4rem; color: var(--cust-text);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span>Edit Profile</span>
            </button>
            <x-button variant="secondary" id="btn_close_view_cust_btn">Close</x-button>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add Modal handlers
    const addModal = document.getElementById('add_customer_modal');
    const btnOpenAdd = document.getElementById('btn_open_add_customer_modal');
    const btnCancelAdd = document.getElementById('btn_cancel_add_customer');

    if (btnOpenAdd) {
        btnOpenAdd.addEventListener('click', () => addModal.classList.add('is-active'));
    }
    if (btnCancelAdd) {
        btnCancelAdd.addEventListener('click', () => addModal.classList.remove('is-active'));
    }

    // Edit Modal handlers
    const editModal = document.getElementById('edit_customer_modal');
    const editForm = document.getElementById('form_edit_customer');
    const btnCancelEdit = document.getElementById('btn_cancel_edit_customer');

    function openEditModal(cust) {
        editForm.action = `/admin/customers/${cust.id}`;
        document.getElementById('edit_cust_name').value = cust.name || '';
        document.getElementById('edit_cust_phone').value = cust.phone || '';
        document.getElementById('edit_cust_email').value = cust.email || '';
        document.getElementById('edit_cust_address').value = cust.address || '';
        document.getElementById('edit_cust_points').value = cust.points || 0;
        document.getElementById('edit_cust_status').value = cust.status || 'active';
        editModal.classList.add('is-active');
    }

    document.querySelectorAll('.btn-edit-customer').forEach(btn => {
        btn.addEventListener('click', function() {
            openEditModal({
                id: this.getAttribute('data-id'),
                name: this.getAttribute('data-name'),
                phone: this.getAttribute('data-phone'),
                email: this.getAttribute('data-email'),
                address: this.getAttribute('data-address'),
                points: this.getAttribute('data-points'),
                status: this.getAttribute('data-status')
            });
        });
    });

    if (btnCancelEdit) {
        btnCancelEdit.addEventListener('click', () => editModal.classList.remove('is-active'));
    }

    // View Modal handlers (stay on page like edit modal)
    const viewModal = document.getElementById('view_customer_modal');
    const btnCloseViewBtn = document.getElementById('btn_close_view_cust_btn');
    const btnEditFromView = document.getElementById('btn_edit_from_view');
    let currentViewingCustomer = null;

    const tierSvgs = {
        'vip': '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg>',
        'gold': '<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'silver': '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
        'standard': '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display:inline-block;vertical-align:middle;flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>'
    };

    const tierLabels = {
        'vip': 'VIP Tier',
        'gold': 'Gold Tier',
        'silver': 'Silver Tier',
        'standard': 'Standard Tier'
    };

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, function(m) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
        });
    }

    document.querySelectorAll('.btn-view-customer').forEach(btn => {
        btn.addEventListener('click', function() {
            const cust = {
                id: this.getAttribute('data-id'),
                name: this.getAttribute('data-name'),
                phone: this.getAttribute('data-phone'),
                email: this.getAttribute('data-email'),
                address: this.getAttribute('data-address'),
                points: this.getAttribute('data-points') || 0,
                tier: (this.getAttribute('data-tier') || 'standard').toLowerCase(),
                status: (this.getAttribute('data-status') || 'active').toLowerCase(),
                ordersCount: this.getAttribute('data-orders-count') || 0,
                spent: this.getAttribute('data-spent') || '$0.00',
                avg: this.getAttribute('data-avg') || '$0.00',
                created: this.getAttribute('data-created') || '',
                showUrl: this.getAttribute('data-show-url') || '#'
            };

            currentViewingCustomer = cust;

            // Populate static data
            document.getElementById('view_cust_avatar').textContent = (cust.name || '--').substring(0, 2).toUpperCase();
            document.getElementById('view_cust_name').textContent = cust.name;
            document.getElementById('view_cust_created').textContent = 'Member since ' + (cust.created || 'N/A');

            const tierBadge = document.getElementById('view_cust_tier_badge');
            tierBadge.className = 'tier-pill tier-' + cust.tier;
            const svgIcon = tierSvgs[cust.tier] || tierSvgs['standard'];
            const labelText = tierLabels[cust.tier] || (cust.tier.toUpperCase() + ' Tier');
            tierBadge.innerHTML = `${svgIcon} <span>${escapeHtml(labelText)}</span>`;

            const statusBadge = document.getElementById('view_cust_status_badge');
            statusBadge.className = 'status-badge ' + (cust.status === 'active' ? 'status-completed' : 'status-cancelled');
            statusBadge.textContent = cust.status === 'active' ? 'Active' : 'Inactive';

            document.getElementById('view_cust_spent').textContent = cust.spent;
            document.getElementById('view_cust_orders_count').textContent = cust.ordersCount;
            document.getElementById('view_cust_avg').textContent = cust.avg;
            document.getElementById('view_cust_points').textContent = Number(cust.points).toLocaleString() + ' pts';

            document.getElementById('view_cust_phone').textContent = cust.phone ? cust.phone : 'Not provided';
            document.getElementById('view_cust_email').textContent = cust.email ? cust.email : 'Not provided';
            document.getElementById('view_cust_address').textContent = cust.address ? cust.address : 'No address registered';

            // Reset order list state
            const loadingEl = document.getElementById('view_cust_orders_loading');
            const tableEl = document.getElementById('view_cust_orders_table');
            const emptyEl = document.getElementById('view_cust_orders_empty');
            const tbodyEl = document.getElementById('view_cust_orders_tbody');

            loadingEl.style.display = 'block';
            tableEl.style.display = 'none';
            emptyEl.style.display = 'none';
            tbodyEl.innerHTML = '';

            viewModal.classList.add('is-active');

            // Fetch real order history via JSON AJAX
            fetch(cust.showUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                loadingEl.style.display = 'none';
                if (data && data.orders && data.orders.length > 0) {
                    tbodyEl.innerHTML = data.orders.map(order => `
                        <tr>
                            <td style="font-weight: 700;">#${escapeHtml(order.order_number)}</td>
                            <td style="color: var(--cust-muted);">${escapeHtml(order.date)}</td>
                            <td><span class="cust-order-items-badge">${order.items_count} items</span></td>
                            <td>${escapeHtml(order.payment_method)}</td>
                            <td><span style="background: ${order.status.toLowerCase() === 'completed' ? 'rgba(22, 163, 74, 0.12)' : 'rgba(239, 68, 68, 0.12)'}; color: ${order.status.toLowerCase() === 'completed' ? '#16a34a' : '#ef4444'}; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 9999px; font-size: 0.725rem;">${escapeHtml(order.status)}</span></td>
                            <td style="text-align: right; font-weight: 800; color: #16a34a;">$${escapeHtml(order.total)}</td>
                        </tr>
                    `).join('');
                    tableEl.style.display = 'table';
                } else {
                    emptyEl.style.display = 'block';
                }
            })
            .catch(() => {
                loadingEl.style.display = 'none';
                emptyEl.textContent = 'Could not load order history';
                emptyEl.style.display = 'block';
            });
        });
    });

    if (btnCloseViewBtn) {
        btnCloseViewBtn.addEventListener('click', () => viewModal.classList.remove('is-active'));
    }
    if (btnEditFromView) {
        btnEditFromView.addEventListener('click', function() {
            viewModal.classList.remove('is-active');
            if (currentViewingCustomer) {
                openEditModal(currentViewingCustomer);
            }
        });
    }

    // Close on backdrop click
    [addModal, editModal, viewModal].forEach(modal => {
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.classList.remove('is-active');
                }
            });
        }
    });
});
</script>
@endpush
@endsection
