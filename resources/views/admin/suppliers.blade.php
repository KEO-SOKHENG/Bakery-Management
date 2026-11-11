@extends('layouts.app')

@section('title', 'Suppliers & Vendors - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <style>
        /* Supplier Quick Detail Modal Card ("Cart") */
        .supplier-modal-card {
            max-width: 580px;
            width: 100%;
        }
        .supplier-modal-stat-pill {
            padding: 0.65rem 0.75rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-align: center;
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .supplier-modal-stat-pill:hover {
            transform: translateY(-1px);
            border-color: #cbd5e1;
        }
        .supplier-tab-btn {
            padding: 0.35rem 0.85rem;
            border-radius: 20px;
            font-size: 0.775rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            background: transparent;
            color: var(--text-muted, #71717a);
            transition: all 0.15s ease;
        }
        .supplier-tab-btn:hover {
            color: #ea580c;
        }
        .supplier-tab-btn.active {
            background: rgba(234, 88, 12, 0.12) !important;
            color: #ea580c !important;
        }
        .supplier-mini-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.6rem 0.85rem;
            border-radius: 10px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            font-size: 0.825rem;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .supplier-mini-item:hover {
            border-color: #cbd5e1;
        }

        /* Dark mode overrides for Supplier Detail Modal */
        [data-theme="dark"] .supplier-modal-stat-pill,
        body.dark-mode .supplier-modal-stat-pill {
            background: #141417 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        [data-theme="dark"] .supplier-tab-btn,
        body.dark-mode .supplier-tab-btn {
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .supplier-tab-btn.active,
        body.dark-mode .supplier-tab-btn.active {
            background: rgba(234, 88, 12, 0.22) !important;
            color: #f97316 !important;
        }
        [data-theme="dark"] .supplier-mini-item,
        body.dark-mode .supplier-mini-item {
            background: #141417 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        [data-theme="dark"] .supplier-mini-item:hover,
        body.dark-mode .supplier-mini-item:hover {
            background: #1c1c22 !important;
            border-color: rgba(255, 255, 255, 0.16) !important;
        }
        [data-theme="dark"] .order-detail-modal-actions,
        body.dark-mode .order-detail-modal-actions {
            background: #18181c !important;
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
    </style>
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.suppliers', array_merge(request()->except('status'), ['status' => 'all'])) }}"
           class="period-pill {{ (!request()->filled('status') || request('status') === 'all') ? 'active' : '' }}">
            <span>All Suppliers ({{ $stats['total'] }})</span>
        </a>
        <a href="{{ route('admin.suppliers', array_merge(request()->except('status'), ['status' => 'active'])) }}"
           class="period-pill {{ request('status') === 'active' ? 'active' : '' }}">
            <span>Active Partners ({{ $stats['active'] }})</span>
        </a>
        <a href="{{ route('admin.suppliers', array_merge(request()->except('status'), ['status' => 'inactive'])) }}"
           class="period-pill {{ request('status') === 'inactive' ? 'active' : '' }}">
            <span>Inactive Vendors ({{ $stats['inactive'] }})</span>
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container">

    <h1 class="sr-only">Raw Material Suppliers</h1>

    <!-- Alert Notifications -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-alert type="danger" :message="session('error')" />
    @endif

    <!-- 1. 4 Core KPI Stat Cards Grid (Exact Orders / Dashboard Page Style) -->
    <div class="m-stat-row dash-stat-row">
        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Suppliers</span>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Vendor Base
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Active Partners</span>
                <div class="stat-value">{{ $stats['active'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Verified Active
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Inactive Vendors</span>
                <div class="stat-value">{{ $stats['inactive'] }}</div>
                <span class="stat-trend {{ $stats['inactive'] > 0 ? 'warning' : 'up' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ $stats['inactive'] > 0 ? 'Dormant' : 'All Active' }}
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Procured Spend</span>
                <div class="stat-value">${{ number_format($stats['total_spend'], 2) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Procurement
                </span>
            </div>
        </div>
    </div>

    <!-- 2. Action & Filter Toolbar Row (Exact Orders & Ingredients Style) -->
    <div class="ingredients-toolbar-row m-toolbar" id="suppliers_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Left: Search and Status Filters -->
        <form method="GET" action="{{ route('admin.suppliers') }}" id="suppliers_filter_form" data-mfilter style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin: 0;">
            @if(request('search'))
                <input type="hidden" name="search" id="suppliers_search_input" value="{{ request('search') }}">
            @endif

            <!-- Status Pill Filter -->
            <div class="supplier-filter-pill-card">
                <select name="status" id="supplier_status_select" onchange="this.form.submit()">
                    <option value="all" {{ (!request()->filled('status') || request('status') === 'all') ? 'selected' : '' }}>All Status</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                </select>


                @if(request()->hasAny(['search', 'status']) && (request('search') || (request('status') && request('status') !== 'all')))
                    <a href="{{ route('admin.suppliers') }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.35rem 0.85rem; font-weight: 600; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem;">Clear</a>
                @endif
            </div>
        </form>

        <!-- Right: Purchase Orders & Add Supplier Buttons -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="{{ route('admin.purchase-orders.index') }}" class="btn btn-secondary m-icon-btn" title="Purchase Orders" aria-label="Purchase Orders" style="border-radius: 50px; height: 36px; padding: 0 1rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; font-size: 0.825rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                <span>Purchase Orders</span>
            </a>
            <button type="button" class="btn m-icon-btn" id="btn_open_supplier_modal" title="Add Supplier" aria-label="Add Supplier" style="border-radius: 50px; height: 36px; padding: 0 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add Supplier</span>
            </button>
        </div>
    </div>

    <!-- 3. Suppliers Table Card (Full Viewport Height with internal scrolling) -->
    <div class="orders-card m-card-wrap" id="suppliers_table_card">
        <div class="orders-table-wrapper" id="suppliers_table_wrapper">
            <table class="orders-table m-card-table m-card-v2" id="suppliers_table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Vendor / Company</th>
                        <th style="width: 14%;">Contact Person</th>
                        <th style="width: 12%;">Phone</th>
                        <th style="width: 15%;">Email</th>
                        <th style="width: 11%;">Supplied Items</th>
                        <th style="width: 10%;">Orders</th>
                        <th style="width: 8%;">Status</th>
                        <th style="width: 8%; text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($suppliers as $supplier)
                        <tr data-tiles="3" class="m-card-row">
                            <!-- Vendor / Company -->
                            <td class="mc-title" data-label="Vendor">
                                <div style="display: flex; align-items: center; gap: 0.65rem;">
                                    <div style="width: 34px; height: 34px; border-radius: 8px; background: rgba(234, 88, 12, 0.1); display: flex; align-items: center; justify-content: center; color: #ea580c; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                        {{ strtoupper(substr($supplier->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <a href="javascript:void(0)" onclick="openSupplierDetailModalFromRow(this)" style="font-weight: 700; font-size: 0.9rem; color: #ea580c; text-decoration: none;" title="View Supplier Details">
                                            {{ $supplier->name }}
                                        </a>
                                        <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); max-width: 220px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ $supplier->address ?? 'No address registered' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Contact Person -->
                            <td class="mc-meta mc-inline-label" data-label="Contact" style="font-weight: 600; color: inherit; font-size: 0.85rem;">
                                {{ $supplier->contact_person ?? '—' }}
                            </td>

                            <!-- Phone -->
                            <td class="mc-tile mc-inline-label" data-label="Phone" style="font-size: 0.825rem; font-weight: 600; color: var(--text-muted, #a1a1aa);">
                                {{ $supplier->phone ?? '—' }}
                            </td>

                            <!-- Email -->
                            <td class="mc-wide mc-inline-label" data-label="Email" style="font-size: 0.825rem;">
                                @if($supplier->email)
                                    <a href="mailto:{{ $supplier->email }}" style="color: #3b82f6; text-decoration: none; font-weight: 600;">
                                        {{ $supplier->email }}
                                    </a>
                                @else
                                    <span style="color: #71717a;">—</span>
                                @endif
                            </td>

                            <!-- Supplied Ingredients -->
                            <td class="mc-tile" data-label="Items">
                                <span style="background: rgba(234, 88, 12, 0.08); color: #ea580c; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                    {{ $supplier->ingredients_count }} items
                                </span>
                            </td>

                            <!-- Purchase Orders -->
                            <td class="mc-tile" data-label="Orders">
                                <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 6px;">
                                    {{ $supplier->purchase_orders_count }} orders
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="mc-status" data-label="Status">
                                <span class="status-badge {{ $supplier->status === 'active' ? 'status-completed' : 'status-cancelled' }}">
                                    {{ ucfirst($supplier->status) }}
                                </span>
                            </td>

                            <!-- Actions -->
                            <td class="mc-action" data-label="Actions" style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.4rem;">
                                    <button type="button" class="table-icon-btn btn-view-supplier" title="View Supplier Details"
                                        data-id="{{ $supplier->id }}"
                                        data-name="{{ $supplier->name }}"
                                        data-contact="{{ $supplier->contact_person }}"
                                        data-phone="{{ $supplier->phone }}"
                                        data-email="{{ $supplier->email }}"
                                        data-address="{{ $supplier->address }}"
                                        data-status="{{ $supplier->status }}"
                                        data-notes="{{ $supplier->notes }}"
                                        data-items-count="{{ $supplier->ingredients_count }}"
                                        data-orders-count="{{ $supplier->purchase_orders_count }}"
                                        data-created-at="{{ $supplier->created_at ? $supplier->created_at->format('M d, Y') : '' }}">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    <button type="button" class="table-icon-btn btn-edit-supplier" title="Edit Supplier" 
                                        data-id="{{ $supplier->id }}"
                                        data-name="{{ $supplier->name }}"
                                        data-contact="{{ $supplier->contact_person }}"
                                        data-phone="{{ $supplier->phone }}"
                                        data-email="{{ $supplier->email }}"
                                        data-address="{{ $supplier->address }}"
                                        data-status="{{ $supplier->status }}"
                                        data-notes="{{ $supplier->notes }}">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                    </button>
                                    <form action="{{ route('admin.suppliers.destroy', $supplier->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete supplier \'{{ $supplier->name }}\'?');" style="display: inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-icon-action delete" title="Delete Supplier" style="background: none; border: none; color: #dc2626; cursor: pointer; padding: 4px; display: inline-flex; align-items: center;">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                                <line x1="14" y1="11" x2="14" y2="17"></line>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted, #71717a); padding: 3rem;">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">🏭</div>
                                <div style="font-weight: 700; font-size: 1rem; color: inherit;">No suppliers found</div>
                                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Try adjusting search terms or click "Add Supplier" to register a new vendor.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD SUPPLIER MODAL -->
<x-modal id="add_supplier_modal" title="Add Supplier" maxWidth="540px" class="dash-modal-overlay" :showClose="false">
    <form action="{{ route('admin.suppliers.store') }}" method="POST">
        @csrf
        <x-input name="name" label="Company / Vendor Name" placeholder="e.g. Royal Flour Mills Ltd." :required="true" />
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input name="contact_person" label="Contact Person" placeholder="e.g. John Doe" />
            <x-select name="status" label="Status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </x-select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input name="phone" label="Phone Number" placeholder="+855 12 345 678" />
            <x-input type="email" name="email" label="Email Address" placeholder="orders@vendor.com" />
        </div>
        <x-input name="address" label="Physical Address / Facility" placeholder="e.g. St. 2004, Phnom Penh, Cambodia" />
        <div class="form-group">
            <label class="form-label">Notes & Procurement Terms</label>
            <textarea name="notes" rows="2" class="form-control-textarea" placeholder="e.g. Net 30 payment terms, weekly delivery on Mondays"></textarea>
        </div>
        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_supplier">Cancel</x-button>
            <x-button variant="primary" type="submit">Create Supplier</x-button>
        </div>
    </form>
</x-modal>

<!-- EDIT SUPPLIER MODAL -->
<x-modal id="edit_supplier_modal" title="Edit Supplier" maxWidth="540px" class="dash-modal-overlay" :showClose="false">
    <form id="edit_supplier_form" method="POST">
        @csrf
        @method('PUT')
        <x-input name="name" id="edit_supplier_name" label="Company / Vendor Name" :required="true" />
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input name="contact_person" id="edit_supplier_contact" label="Contact Person" />
            <x-select name="status" id="edit_supplier_status" label="Status">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </x-select>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input name="phone" id="edit_supplier_phone" label="Phone Number" />
            <x-input type="email" name="email" id="edit_supplier_email" label="Email Address" />
        </div>
        <x-input name="address" id="edit_supplier_address" label="Physical Address / Facility" />
        <div class="form-group">
            <label class="form-label">Notes & Procurement Terms</label>
            <textarea name="notes" id="edit_supplier_notes" rows="2" class="form-control-textarea"></textarea>
        </div>
        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_edit_supplier">Cancel</x-button>
            <x-button variant="primary" type="submit">Update Supplier</x-button>
        </div>
    </form>
</x-modal>

<!-- ========================================================================
     SUPPLIER DETAIL MODAL CARD ("CART")
     ======================================================================== -->
<div id="supplierDetailModal" class="order-detail-modal-backdrop" onclick="if(event.target === this) closeSupplierDetailModal()">
    <div class="order-detail-modal-card supplier-modal-card">
        <!-- Modal Header -->
        <div class="order-detail-modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.15rem 1.4rem;">
            <div class="order-detail-modal-header-left" style="display: flex; align-items: center; gap: 0.85rem;">
                <div id="modal_supplier_initial" style="width: 42px; height: 42px; border-radius: 12px; background: rgba(234, 88, 12, 0.15); display: flex; align-items: center; justify-content: center; color: #ea580c; font-weight: 800; font-size: 1.25rem; flex-shrink: 0; border: 1px solid rgba(234, 88, 12, 0.25);">
                    E
                </div>
                <div>
                    <h3 class="order-detail-modal-title" id="modal_supplier_name" style="font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-title, #0f172a);">
                        Supplier Name
                    </h3>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div id="modal_supplier_status_badge">
                    <span class="status-badge status-completed">Active</span>
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="order-detail-modal-body" style="padding: 1.25rem 1.4rem; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; max-height: calc(85vh - 130px);">
            <!-- 3-KPI Stats Row -->
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.6rem;">
                <div class="supplier-modal-stat-pill">
                    <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted, #64748b); letter-spacing: 0.04em;">Supplied Items</div>
                    <div id="modal_supplier_stat_items" style="font-size: 0.95rem; font-weight: 800; color: #ea580c; margin-top: 0.2rem;">0 items</div>
                </div>
                <div class="supplier-modal-stat-pill">
                    <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted, #64748b); letter-spacing: 0.04em;">Purchase Orders</div>
                    <div id="modal_supplier_stat_orders" style="font-size: 0.95rem; font-weight: 800; color: #3b82f6; margin-top: 0.2rem;">0 orders</div>
                </div>
                <div class="supplier-modal-stat-pill">
                    <div style="font-size: 0.68rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted, #64748b); letter-spacing: 0.04em;">Total Spend</div>
                    <div id="modal_supplier_stat_spend" style="font-size: 0.95rem; font-weight: 800; color: #10b981; margin-top: 0.2rem;">$0.00</div>
                </div>
            </div>

            <!-- Contact & Facility Info Grid (2x2) -->
            <div class="order-detail-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; padding: 0.85rem 1rem; border-radius: 14px;">
                <div class="order-detail-info-item">
                    <span class="info-label">Contact Person</span>
                    <span class="info-value" id="modal_supplier_contact" style="font-size: 0.875rem;">—</span>
                </div>
                <div class="order-detail-info-item">
                    <span class="info-label">Phone Number</span>
                    <div class="info-value" id="modal_supplier_phone" style="font-size: 0.875rem;">—</div>
                </div>
                <div class="order-detail-info-item">
                    <span class="info-label">Email Address</span>
                    <div class="info-value" id="modal_supplier_email" style="font-size: 0.875rem; word-break: break-all;">—</div>
                </div>
                <div class="order-detail-info-item">
                    <span class="info-label">Facility / Address</span>
                    <span class="info-value" id="modal_supplier_address" style="font-size: 0.875rem; font-weight: 600;">—</span>
                </div>
            </div>

            <!-- Notes & Procurement Terms -->
            <div id="modal_supplier_notes_box" class="order-detail-note-box" style="display: none;">
                <span class="note-tag" style="display: flex; align-items: center; gap: 0.35rem; font-weight: 800; font-size: 0.775rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                    Notes:
                </span>
                <span id="modal_supplier_notes" class="note-text" style="font-size: 0.825rem; line-height: 1.45;"></span>
            </div>

            <!-- Interactive Tabs: Supplied Items vs Recent Orders -->
            <div class="order-detail-items-section" style="display: flex; flex-direction: column; gap: 0.6rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border, #e2e8f0); padding-bottom: 0.5rem;">
                    <div style="display: flex; gap: 0.4rem;">
                        <button type="button" id="tab_btn_ingredients" class="supplier-tab-btn active" onclick="switchSupplierTab('ingredients')">
                            Supplied Items (<span id="modal_tab_items_count">0</span>)
                        </button>
                        <button type="button" id="tab_btn_orders" class="supplier-tab-btn" onclick="switchSupplierTab('orders')">
                            Purchase Orders (<span id="modal_tab_orders_count">0</span>)
                        </button>
                    </div>

                </div>

                <!-- Tab Content 1: Supplied Ingredients -->
                <div id="supplier_tab_ingredients" style="display: block;">
                    <div id="supplier_ingredients_loading" style="display: none; padding: 1.25rem; text-align: center; color: var(--text-muted, #94a3b8); font-size: 0.825rem;">
                        <div style="display: inline-block; width: 18px; height: 18px; border: 2px solid #ea580c; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 0.35rem;"></div>
                        <div>Loading supplied materials...</div>
                    </div>
                    <div id="supplier_ingredients_list" style="display: flex; flex-direction: column; gap: 0.4rem; max-height: 180px; overflow-y: auto;">
                        <!-- Injected dynamically -->
                    </div>
                </div>

                <!-- Tab Content 2: Purchase Orders -->
                <div id="supplier_tab_orders" style="display: none;">
                    <div id="supplier_orders_loading" style="display: none; padding: 1.25rem; text-align: center; color: var(--text-muted, #94a3b8); font-size: 0.825rem;">
                        <div style="display: inline-block; width: 18px; height: 18px; border: 2px solid #3b82f6; border-top-color: transparent; border-radius: 50%; animation: spin 0.8s linear infinite; margin-bottom: 0.35rem;"></div>
                        <div>Loading purchase orders...</div>
                    </div>
                    <div id="supplier_orders_list" style="display: flex; flex-direction: column; gap: 0.4rem; max-height: 180px; overflow-y: auto;">
                        <!-- Injected dynamically -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="order-detail-modal-actions" style="padding: 0.9rem 1.4rem; border-top: 1px solid var(--border, #e2e8f0); display: flex; align-items: center; gap: 0.6rem; background: var(--bg-surface, #faf6f0);">
            <a id="modal_btn_create_po" href="#" class="btn" style="background: #ea580c; border: 1px solid #ea580c; color: #ffffff; border-radius: 50px; padding: 0.45rem 1rem; font-weight: 700; font-size: 0.825rem; display: inline-flex; align-items: center; gap: 0.35rem; text-decoration: none; box-shadow: 0 2px 6px rgba(234, 88, 12, 0.25);">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Create PO</span>
            </a>
            <button type="button" id="modal_btn_edit_supplier" class="btn btn-secondary" style="border-radius: 50px; padding: 0.45rem 0.9rem; font-size: 0.825rem; font-weight: 600; display: inline-flex; align-items: center; gap: 0.35rem;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span>Edit</span>
            </button>
            <button type="button" onclick="closeSupplierDetailModal()" class="btn-order-detail-close" style="margin-left: auto;">
                Close
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    let currentSupplierData = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.openSupplierDetailModalFromRow = function(linkEl) {
        const row = linkEl.closest('tr');
        if (!row) return;
        const viewBtn = row.querySelector('.btn-view-supplier');
        if (viewBtn) {
            openSupplierDetailModal(viewBtn);
        }
    };

    window.openSupplierDetailModal = function(btn) {
        const modal = document.getElementById('supplierDetailModal');
        if (!modal) return;

        const id = btn.getAttribute('data-id');
        const name = btn.getAttribute('data-name') || 'Supplier';
        const contact = btn.getAttribute('data-contact') || '';
        const phone = btn.getAttribute('data-phone') || '';
        const email = btn.getAttribute('data-email') || '';
        const address = btn.getAttribute('data-address') || '';
        const status = btn.getAttribute('data-status') || 'active';
        const notes = btn.getAttribute('data-notes') || '';
        const itemsCount = btn.getAttribute('data-items-count') || '0';
        const ordersCount = btn.getAttribute('data-orders-count') || '0';
        const createdAt = btn.getAttribute('data-created-at') || '';

        currentSupplierData = { id, name, contact, phone, email, address, status, notes };

        // Initial icon
        const initial = name.trim().charAt(0).toUpperCase() || 'S';
        document.getElementById('modal_supplier_initial').textContent = initial;

        // Header info
        document.getElementById('modal_supplier_name').textContent = name;
        const subEl = document.getElementById('modal_supplier_sub'); if (subEl) subEl.textContent = `Added ${createdAt || 'Recently'}`;

        // Status badge
        const badgeHtml = status === 'active'
            ? '<span class="status-badge status-completed">Active</span>'
            : '<span class="status-badge status-cancelled">Inactive</span>';
        document.getElementById('modal_supplier_status_badge').innerHTML = badgeHtml;

        // Quick Stats
        document.getElementById('modal_supplier_stat_items').textContent = itemsCount;
        document.getElementById('modal_supplier_stat_orders').textContent = ordersCount;
        document.getElementById('modal_supplier_stat_spend').textContent = '$0.00';

        // Contact info
        document.getElementById('modal_supplier_contact').textContent = contact || '—';
        document.getElementById('modal_supplier_phone').innerHTML = phone
            ? `<a href="tel:${escapeHtml(phone)}" style="color: inherit; text-decoration: none;">${escapeHtml(phone)}</a>`
            : '<span style="color: var(--text-muted, #71717a);">—</span>';
        document.getElementById('modal_supplier_email').innerHTML = email
            ? `<a href="mailto:${escapeHtml(email)}" style="color: #3b82f6; text-decoration: none; font-weight: 600;">${escapeHtml(email)}</a>`
            : '<span style="color: var(--text-muted, #71717a);">—</span>';
        document.getElementById('modal_supplier_address').textContent = address || '—';

        // Notes
        const notesBox = document.getElementById('modal_supplier_notes_box');
        if (notes && notes.trim().length > 0) {
            document.getElementById('modal_supplier_notes').textContent = notes;
            notesBox.style.display = 'flex';
        } else {
            notesBox.style.display = 'none';
        }

        // Tabs
        document.getElementById('modal_tab_items_count').textContent = itemsCount;
        document.getElementById('modal_tab_orders_count').textContent = ordersCount;
        switchSupplierTab('ingredients');

        // Footer actions
        const createPoBtn = document.getElementById('modal_btn_create_po');
        if (createPoBtn) createPoBtn.href = `/admin/purchase-orders?action=create&supplier_id=${id}`;

        // Reset list placeholders
        document.getElementById('supplier_ingredients_list').innerHTML = '';
        document.getElementById('supplier_orders_list').innerHTML = '';
        document.getElementById('supplier_ingredients_loading').style.display = 'block';
        document.getElementById('supplier_orders_loading').style.display = 'block';

        // Show modal & prevent background scrolling
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Fetch rich data via AJAX
        fetch(`/admin/suppliers/${id}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('supplier_ingredients_loading').style.display = 'none';
            document.getElementById('supplier_orders_loading').style.display = 'none';

            if (data && data.success && data.supplier) {
                if (data.stats && data.stats.total_spend !== undefined) {
                    document.getElementById('modal_supplier_stat_spend').textContent = '$' + Number(data.stats.total_spend).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }
                if (data.stats && data.stats.total_orders !== undefined) {
                    document.getElementById('modal_supplier_stat_orders').textContent = data.stats.total_orders;
                    document.getElementById('modal_tab_orders_count').textContent = data.stats.total_orders;
                }
                if (data.supplier.ingredients) {
                    document.getElementById('modal_tab_items_count').textContent = data.supplier.ingredients.length;
                    renderSupplierIngredients(data.supplier.ingredients);
                }
                if (data.supplier.purchase_orders) {
                    renderSupplierOrders(data.supplier.purchase_orders);
                }
            }
        })
        .catch(err => {
            console.error('Error fetching supplier details:', err);
            document.getElementById('supplier_ingredients_loading').style.display = 'none';
            document.getElementById('supplier_orders_loading').style.display = 'none';
            document.getElementById('supplier_ingredients_list').innerHTML = '<div style="text-align: center; color: var(--text-muted, #71717a); padding: 1rem; font-size: 0.8rem;">Could not load ingredients list.</div>';
            document.getElementById('supplier_orders_list').innerHTML = '<div style="text-align: center; color: var(--text-muted, #71717a); padding: 1rem; font-size: 0.8rem;">Could not load purchase orders.</div>';
        });
    };

    window.closeSupplierDetailModal = function() {
        const modal = document.getElementById('supplierDetailModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    window.switchSupplierTab = function(tab) {
        const tabIngredients = document.getElementById('supplier_tab_ingredients');
        const tabOrders = document.getElementById('supplier_tab_orders');
        const btnIngredients = document.getElementById('tab_btn_ingredients');
        const btnOrders = document.getElementById('tab_btn_orders');

        if (tab === 'ingredients') {
            tabIngredients.style.display = 'block';
            tabOrders.style.display = 'none';
            btnIngredients.classList.add('active');
            btnOrders.classList.remove('active');
        } else {
            tabIngredients.style.display = 'none';
            tabOrders.style.display = 'block';
            btnOrders.classList.add('active');
            btnIngredients.classList.remove('active');
        }
    };

    window.renderSupplierIngredients = function(ingredients) {
        const container = document.getElementById('supplier_ingredients_list');
        if (!ingredients || ingredients.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; color: var(--text-muted, #71717a); padding: 1.25rem; font-size: 0.825rem;">
                    <div style="opacity: 0.4; margin-bottom: 0.35rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                    </div>
                    No ingredients linked to this vendor.
                </div>
            `;
            return;
        }

        container.innerHTML = ingredients.map(ing => {
            let badgeHtml = '<span class="status-badge status-completed" style="font-size: 0.7rem; padding: 2px 7px;">In Stock</span>';
            if (ing.is_out_of_stock) {
                badgeHtml = '<span class="status-badge status-cancelled" style="font-size: 0.7rem; padding: 2px 7px;">Out of Stock</span>';
            } else if (ing.is_low_stock) {
                badgeHtml = '<span class="status-badge status-pending" style="font-size: 0.7rem; padding: 2px 7px; background: rgba(245, 158, 11, 0.15); color: #d97706;">Low Stock</span>';
            }

            return `
                <div class="supplier-mini-item">
                    <div>
                        <div style="font-weight: 700; color: var(--text-title, #0f172a); font-size: 0.85rem;">${escapeHtml(ing.name)}</div>
                        <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); margin-top: 2px;">
                            Stock: <strong>${Number(ing.quantity).toFixed(2)} ${escapeHtml(ing.unit ? ing.unit.toUpperCase() : '')}</strong> &bull; Cost: <strong>$${Number(ing.cost).toFixed(2)}</strong>
                        </div>
                    </div>
                    <div>
                        ${badgeHtml}
                    </div>
                </div>
            `;
        }).join('');
    };

    window.renderSupplierOrders = function(orders) {
        const container = document.getElementById('supplier_orders_list');
        if (!orders || orders.length === 0) {
            container.innerHTML = `
                <div style="text-align: center; color: var(--text-muted, #71717a); padding: 1.25rem; font-size: 0.825rem;">
                    <div style="opacity: 0.4; margin-bottom: 0.35rem;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
                    </div>
                    No purchase orders found.
                </div>
            `;
            return;
        }

        const statusClasses = {
            'draft': 'status-pending',
            'ordered': 'status-pending',
            'received': 'status-completed',
            'cancelled': 'status-cancelled'
        };

        container.innerHTML = orders.map(po => {
            const badgeClass = statusClasses[po.status] || 'status-pending';
            return `
                <div class="supplier-mini-item">
                    <div>
                        <div style="display: flex; align-items: center; gap: 0.4rem;">
                            <a href="${po.url}" style="font-weight: 800; font-family: monospace; font-size: 0.85rem; color: #ea580c; text-decoration: none;">
                                #${escapeHtml(po.po_number)}
                            </a>
                            <span class="status-badge ${badgeClass}" style="font-size: 0.68rem; padding: 1px 6px;">
                                ${escapeHtml(po.status ? po.status.charAt(0).toUpperCase() + po.status.slice(1) : '')}
                            </span>
                        </div>
                        <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); margin-top: 2px;">
                            ${escapeHtml(po.order_date)} &bull; ${po.items_count} items
                        </div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-weight: 800; color: var(--text-title, #0f172a); font-size: 0.875rem;">
                            $${Number(po.total_cost).toFixed(2)}
                        </div>
                        <a href="${po.url}" style="font-size: 0.725rem; font-weight: 700; color: #3b82f6; text-decoration: none;">View PO &rarr;</a>
                    </div>
                </div>
            `;
        }).join('');
    };

    window.openEditSupplierModal = function(data) {
        const editModal = document.getElementById('edit_supplier_modal');
        const editForm = document.getElementById('edit_supplier_form');
        if (!editModal || !editForm) return;

        editForm.action = `/admin/suppliers/${data.id}`;
        document.getElementById('edit_supplier_name').value = data.name || '';
        document.getElementById('edit_supplier_contact').value = data.contact || '';
        document.getElementById('edit_supplier_phone').value = data.phone || '';
        document.getElementById('edit_supplier_email').value = data.email || '';
        document.getElementById('edit_supplier_address').value = data.address || '';
        document.getElementById('edit_supplier_status').value = data.status || 'active';
        document.getElementById('edit_supplier_notes').value = data.notes || '';

        editModal.classList.add('active', 'is-active');
        editModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    document.addEventListener('DOMContentLoaded', function() {
        // Add Modal
        const addModal = document.getElementById('add_supplier_modal');
        const openAddBtn = document.getElementById('btn_open_supplier_modal');
        const closeAddBtn = document.getElementById('btn_close_supplier_modal');
        const cancelAddBtn = document.getElementById('btn_cancel_supplier');

        function openAdd() {
            if (addModal) {
                addModal.classList.add('active', 'is-active');
                addModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }
        function closeAdd() {
            if (addModal) {
                addModal.classList.remove('active', 'is-active');
                addModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        if (openAddBtn) openAddBtn.addEventListener('click', openAdd);
        if (closeAddBtn) closeAddBtn.addEventListener('click', closeAdd);
        if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAdd);
        if (addModal) {
            addModal.addEventListener('click', (e) => { if (e.target === addModal) closeAdd(); });
        }

        // Edit Modal
        const editModal = document.getElementById('edit_supplier_modal');
        const closeEditBtn = document.getElementById('btn_close_edit_modal');
        const cancelEditBtn = document.getElementById('btn_cancel_edit_supplier');

        function closeEdit() {
            if (editModal) {
                editModal.classList.remove('active', 'is-active');
                editModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }
        if (closeEditBtn) closeEditBtn.addEventListener('click', closeEdit);
        if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEdit);
        if (editModal) {
            editModal.addEventListener('click', (e) => { if (e.target === editModal) closeEdit(); });
        }

        // Edit button inside Detail Modal Card ("Cart")
        const modalEditBtn = document.getElementById('modal_btn_edit_supplier');
        if (modalEditBtn) {
            modalEditBtn.addEventListener('click', function() {
                if (currentSupplierData) {
                    closeSupplierDetailModal();
                    openEditSupplierModal(currentSupplierData);
                }
            });
        }

        // Edit buttons in table
        document.querySelectorAll('.btn-edit-supplier').forEach(btn => {
            btn.addEventListener('click', function() {
                openEditSupplierModal({
                    id: this.getAttribute('data-id'),
                    name: this.getAttribute('data-name'),
                    contact: this.getAttribute('data-contact'),
                    phone: this.getAttribute('data-phone'),
                    email: this.getAttribute('data-email'),
                    address: this.getAttribute('data-address'),
                    status: this.getAttribute('data-status'),
                    notes: this.getAttribute('data-notes')
                });
            });
        });

        // View buttons in table
        document.querySelectorAll('.btn-view-supplier').forEach(btn => {
            btn.addEventListener('click', function() {
                openSupplierDetailModal(this);
            });
        });

        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeSupplierDetailModal();
            }
        });
    });
</script>
@endpush
@endsection
