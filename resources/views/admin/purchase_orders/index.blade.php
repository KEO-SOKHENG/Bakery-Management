@extends('layouts.app')

@section('title', 'Purchase Orders - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <style>
        .po-modal-card {
            max-width: 620px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.35);
        }
        .po-modal-badge {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: #ea580c;
            background: rgba(234, 88, 12, 0.1);
            border: 1px solid rgba(234, 88, 12, 0.25);
            padding: 0.35rem 0.75rem;
            border-radius: 10px;
            line-height: 1;
            letter-spacing: 0.03em;
        }
        [data-theme="dark"] .po-modal-badge,
        body.dark-mode .po-modal-badge {
            color: #ff8a4c !important;
            background: rgba(234, 88, 12, 0.15) !important;
            border-color: rgba(234, 88, 12, 0.35) !important;
        }
        [data-theme="dark"] .po-modal-card .po-table-wrap,
        body.dark-mode .po-modal-card .po-table-wrap {
            background: #18181c !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        [data-theme="dark"] .po-modal-card .order-detail-summary-box,
        body.dark-mode .po-modal-card .order-detail-summary-box {
            background: #131316 !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        [data-theme="dark"] .po-modal-card .order-detail-info-grid,
        body.dark-mode .po-modal-card .order-detail-info-grid {
            background: #141417 !important;
            border: 1px solid rgba(255, 255, 255, 0.08) !important;
        }
        .po-modal-ing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.825rem;
        }
        .po-modal-ing-table th {
            text-align: left;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #94a3b8);
            font-weight: 700;
            padding: 0.55rem 0.75rem;
            border-bottom: 1px solid var(--border, #e2e8f0);
            background: rgba(0, 0, 0, 0.02);
        }
        .po-modal-ing-table td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid var(--border, #f1f5f9);
            color: inherit;
        }
        .po-modal-ing-table tbody tr:last-child td {
            border-bottom: none;
        }
        [data-theme="dark"] .po-modal-ing-table th,
        body.dark-mode .po-modal-ing-table th {
            border-bottom-color: rgba(255, 255, 255, 0.08) !important;
            background: rgba(255, 255, 255, 0.02) !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .po-modal-ing-table td,
        body.dark-mode .po-modal-ing-table td {
            border-bottom-color: rgba(255, 255, 255, 0.05) !important;
        }
        [data-theme="dark"] .order-detail-modal-actions,
        body.dark-mode .order-detail-modal-actions {
            background: #18181c !important;
            border-top: 1px solid rgba(255, 255, 255, 0.08) !important;
        }

        /* Create Purchase Order Modal Card ("Cart") */
        .create-po-modal-card {
            max-width: 980px;
            width: 100%;
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        .create-po-layout-grid {
            display: grid;
            grid-template-columns: 1.7fr 1fr;
            gap: 1.25rem;
            align-items: start;
        }
        @media (max-width: 860px) {
            .create-po-layout-grid {
                grid-template-columns: 1fr;
            }
        }
        .create-po-section-card {
            background: #ffffff;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 14px;
            padding: 1.15rem;
        }
        .create-po-summary-card {
            background: #ffffff;
            border: 1px solid var(--border, #e2e8f0);
            border-radius: 14px;
            padding: 1.15rem;
            position: sticky;
            top: 0;
        }
        .create-po-input {
            width: 100%;
            padding: 0.55rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #d4d4d8;
            background: #ffffff;
            color: #0f172a;
            font-size: 0.825rem;
            height: 38px;
            outline: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }
        .create-po-input:focus {
            border-color: #ea580c;
            box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.2);
        }
        .create-po-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.825rem;
        }
        .create-po-items-table th {
            text-align: left;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #94a3b8);
            font-weight: 700;
            padding: 0.55rem 0.5rem;
            border-bottom: 1px solid var(--border, #e2e8f0);
            background: rgba(0, 0, 0, 0.02);
        }
        .create-po-items-table td {
            padding: 0.55rem 0.5rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border, #f1f5f9);
        }
        .create-po-items-table tbody tr:last-child td {
            border-bottom: none;
        }
        .create-po-tip-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }

        /* Dark Mode Overrides for Create PO Modal */
        [data-theme="dark"] .create-po-section-card,
        body.dark-mode .create-po-section-card,
        [data-theme="dark"] .create-po-summary-card,
        body.dark-mode .create-po-summary-card {
            background: #1f1f25 !important;
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        [data-theme="dark"] .create-po-input,
        body.dark-mode .create-po-input {
            background: #27272f !important;
            border-color: rgba(255, 255, 255, 0.14) !important;
            color: #f8fafc !important;
        }
        [data-theme="dark"] .create-po-input:focus,
        body.dark-mode .create-po-input:focus {
            border-color: #ea580c !important;
        }
        [data-theme="dark"] .create-po-items-table th,
        body.dark-mode .create-po-items-table th {
            border-bottom-color: rgba(255, 255, 255, 0.08) !important;
            background: rgba(255, 255, 255, 0.02) !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .create-po-items-table td,
        body.dark-mode .create-po-items-table td {
            border-bottom-color: rgba(255, 255, 255, 0.05) !important;
        }
        [data-theme="dark"] .create-po-tip-box,
        body.dark-mode .create-po-tip-box {
            background: rgba(255, 255, 255, 0.03) !important;
            border-color: rgba(255, 255, 255, 0.07) !important;
            color: #94a3b8 !important;
        }
        [data-theme="dark"] .modal-unit-badge,
        body.dark-mode .modal-unit-badge {
            background: rgba(255, 255, 255, 0.08) !important;
            color: #cbd5e1 !important;
        }

        .create-po-modal-card,
        .po-modal-card {
            scrollbar-width: none !important;
            -ms-overflow-style: none !important;
        }
        .create-po-modal-card::-webkit-scrollbar,
        .po-modal-card::-webkit-scrollbar {
            display: none !important;
            width: 0 !important;
            height: 0 !important;
        }


        /* Custom Floating Alert / Toast Notification */
        .po-floating-toast {
            position: fixed;
            top: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(-24px) scale(0.96);
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            z-index: 9999999;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.7rem 1.25rem;
            border-radius: 50px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(245, 158, 11, 0.45);
            box-shadow: 0 16px 36px -8px rgba(0, 0, 0, 0.16), 0 4px 16px rgba(245, 158, 11, 0.15);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            font-size: 0.875rem;
            font-weight: 600;
            color: #0f172a;
            transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.28s cubic-bezier(0.16, 1, 0.3, 1), visibility 0.28s;
            max-width: min(520px, 92vw);
            box-sizing: border-box;
        }

        .po-floating-toast.is-visible {
            transform: translateX(-50%) translateY(0) scale(1);
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        .po-floating-toast.is-shaking {
            animation: poToastShake 0.4s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        @keyframes poToastShake {
            10%, 90% { transform: translateX(calc(-50% - 2px)) translateY(0) scale(1); }
            20%, 80% { transform: translateX(calc(-50% + 4px)) translateY(0) scale(1); }
            30%, 50%, 70% { transform: translateX(calc(-50% - 4px)) translateY(0) scale(1); }
            40%, 60% { transform: translateX(calc(-50% + 2px)) translateY(0) scale(1); }
        }

        [data-theme="dark"] .po-floating-toast,
        body.dark-mode .po-floating-toast {
            background: rgba(24, 24, 28, 0.94) !important;
            border-color: rgba(245, 158, 11, 0.4) !important;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.7), 0 4px 20px rgba(245, 158, 11, 0.2) !important;
            color: #f8fafc !important;
        }

        .po-toast-icon-wrap {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .po-toast-icon-wrap.warning {
            background: rgba(245, 158, 11, 0.16);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        [data-theme="dark"] .po-toast-icon-wrap.warning,
        body.dark-mode .po-toast-icon-wrap.warning {
            background: rgba(245, 158, 11, 0.22) !important;
            color: #fbbf24 !important;
            border-color: rgba(245, 158, 11, 0.45) !important;
        }

        .po-toast-text {
            line-height: 1.4;
            font-weight: 600;
        }

        .po-toast-close {
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 4px;
            margin-left: 0.25rem;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }

        .po-toast-close:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.12);
        }

        @keyframes rowWarningFlash {
            0%, 100% { background: transparent; }
            25%, 75% { background: rgba(245, 158, 11, 0.18); }
            50% { background: rgba(245, 158, 11, 0.08); }
        }

        .row-warning-flash {
            animation: rowWarningFlash 0.9s ease;
        }
    </style>
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.purchase-orders.index', array_merge(request()->except('status'), ['status' => 'all'])) }}"
           class="period-pill {{ (!request()->filled('status') || request('status') === 'all') ? 'active' : '' }}">
            <span>All Orders ({{ $stats['total'] }})</span>
        </a>
        <a href="{{ route('admin.purchase-orders.index', array_merge(request()->except('status'), ['status' => 'draft'])) }}"
           class="period-pill {{ request('status') === 'draft' ? 'active' : '' }}">
            <span>Drafts ({{ $stats['draft'] }})</span>
        </a>
        <a href="{{ route('admin.purchase-orders.index', array_merge(request()->except('status'), ['status' => 'ordered'])) }}"
           class="period-pill {{ request('status') === 'ordered' ? 'active' : '' }}">
            <span>Ordered ({{ $stats['ordered'] }})</span>
        </a>
        <a href="{{ route('admin.purchase-orders.index', array_merge(request()->except('status'), ['status' => 'received'])) }}"
           class="period-pill {{ request('status') === 'received' ? 'active' : '' }}">
            <span>Received ({{ $stats['received'] }})</span>
        </a>
        <a href="{{ route('admin.purchase-orders.index', array_merge(request()->except('status'), ['status' => 'cancelled'])) }}"
           class="period-pill {{ request('status') === 'cancelled' ? 'active' : '' }}">
            <span>Cancelled ({{ $stats['cancelled'] }})</span>
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container">

    <h1 class="sr-only">Raw Material Purchase Orders</h1>

    <!-- Alert Notifications -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-alert type="danger" :message="session('error')" />
    @endif

    <!-- 1. 4 Core KPI Stat Cards Grid (Exact Orders / Dashboard Page Style) -->
    <div class="dash-stat-row">
        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Purchase Orders</span>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Procurement
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Pending Orders</span>
                <div class="stat-value">{{ $stats['draft'] + $stats['ordered'] }}</div>
                <span class="stat-trend {{ ($stats['draft'] + $stats['ordered']) > 0 ? 'warning' : 'up' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ $stats['ordered'] }} ordered
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Received & Stocked In</span>
                <div class="stat-value">{{ $stats['received'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Stock Replenished
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Procured Value</span>
                <div class="stat-value">${{ number_format($stats['total_spend'], 2) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Spend Track
                </span>
            </div>
        </div>
    </div>

    <!-- 2. Action & Filter Toolbar Row (Exact Orders & Ingredients Style) -->
    <div class="ingredients-toolbar-row" id="po_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
        <!-- Left: Filters Form -->
        <form method="GET" action="{{ route('admin.purchase-orders.index') }}" id="po_filter_form" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin: 0;">
            @if(request('status') && request('status') !== 'all')
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif

            @if(request('search'))
                <input type="hidden" name="search" id="po_search_input" value="{{ request('search') }}">
            @endif

            <!-- Supplier Pill Filter -->
            <div class="supplier-filter-pill-card">
                <select name="supplier_id" id="po_supplier_select" onchange="this.form.submit()">
                    <option value="all">All Suppliers</option>
                    @foreach($suppliers as $s)
                        <option value="{{ $s->id }}" {{ request('supplier_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                    @endforeach
                </select>

                <input type="date" name="date" value="{{ request('date') }}" onchange="this.form.submit()" style="border: none; background: transparent; color: inherit; font-size: 0.825rem; padding: 0 0.5rem; height: 32px; border-left: 1px solid var(--border-color, #333);">

                <button type="submit" class="btn" style="border-radius: 50px; padding: 0.35rem 1rem; font-weight: 700; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.825rem;">Filter</button>

                @if(request()->hasAny(['search', 'supplier_id', 'date']) && (request('search') || (request('supplier_id') && request('supplier_id') !== 'all') || request('date')))
                    <a href="{{ route('admin.purchase-orders.index', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.35rem 0.85rem; font-weight: 600; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem;">Clear</a>
                @endif
            </div>
        </form>

        <!-- Right: Action Buttons -->
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <a href="{{ route('admin.suppliers') }}" class="btn btn-secondary" style="border-radius: 50px; height: 36px; padding: 0 1rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 600; font-size: 0.825rem;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/></svg>
                <span>Suppliers</span>
            </a>
            <button type="button" class="btn" id="btn_create_po" onclick="openCreatePurchaseOrderModal()" style="border-radius: 50px; height: 36px; padding: 0 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>New Purchase Order</span>
            </button>
        </div>
    </div>

    <!-- 3. Purchase Orders List Table Card (Full Viewport Height with internal scrolling) -->
    <div class="orders-card" id="purchase_orders_table_card">
        <div class="orders-table-wrapper" id="purchase_orders_table_wrapper">
            <table class="orders-table" id="purchase_orders_table">
                <thead>
                    <tr>
                        <th style="width: 14%;">PO Number</th>
                        <th style="width: 20%;">Supplier / Vendor</th>
                        <th style="width: 13%;">Order Date</th>
                        <th style="width: 13%;">Expected Delivery</th>
                        <th style="width: 12%;">Items Ordered</th>
                        <th style="width: 11%;">Total Cost</th>
                        <th style="width: 9%;">Status</th>
                        <th style="width: 8%; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purchaseOrders as $po)
                        @php
                            $poModalData = [
                                'id'                     => $po->id,
                                'po_number'              => $po->po_number,
                                'status'                 => $po->status,
                                'total_cost'             => (float) $po->total_cost,
                                'order_date'             => $po->order_date ? $po->order_date->format('M d, Y') : '—',
                                'expected_delivery_date' => $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : 'Flexible / Unspecified',
                                'received_date'          => $po->received_date ? $po->received_date->format('M d, Y H:i') : null,
                                'created_at'             => $po->created_at ? $po->created_at->format('M d, Y H:i') : null,
                                'notes'                  => $po->notes,
                                'creator_name'           => $po->user->name ?? 'System Admin',
                                'supplier'               => $po->supplier ? [
                                    'id'             => $po->supplier->id,
                                    'name'           => $po->supplier->name,
                                    'contact_person' => $po->supplier->contact_person,
                                    'phone'          => $po->supplier->phone,
                                    'email'          => $po->supplier->email,
                                    'address'        => $po->supplier->address,
                                ] : null,
                                'items'                  => $po->items->map(function ($item) {
                                    return [
                                        'name'           => $item->ingredient->name ?? 'Ingredient',
                                        'current_stock'  => $item->ingredient ? (float) $item->ingredient->quantity : 0,
                                        'quantity'       => (float) $item->quantity,
                                        'unit'           => $item->unit,
                                        'purchase_price' => (float) $item->purchase_price,
                                        'subtotal'       => (float) $item->subtotal,
                                    ];
                                })->values()->all(),
                                'routes'                 => [
                                    'edit'    => route('admin.purchase-orders.edit', $po->id),
                                    'order'   => route('admin.purchase-orders.order', $po->id),
                                    'receive' => route('admin.purchase-orders.receive', $po->id),
                                    'cancel'  => route('admin.purchase-orders.cancel', $po->id),
                                ],
                            ];
                        @endphp
                        <tr>
                            <!-- PO Number -->
                            <td>
                                <a href="javascript:void(0)" onclick="openPurchaseOrderDetailModalFromRow(this)" style="font-family: monospace; font-weight: 800; color: #ea580c; text-decoration: none; font-size: 0.95rem;" title="View PO Details">
                                    #{{ $po->po_number }}
                                </a>
                                <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); margin-top: 0.2rem;">
                                    By {{ $po->user->name ?? 'System' }}
                                </div>
                            </td>

                            <!-- Supplier / Vendor -->
                            <td>
                                <div style="font-weight: 700; color: inherit; font-size: 0.9rem;">
                                    {{ $po->supplier->name ?? 'Unassigned Supplier' }}
                                </div>
                                <div style="font-size: 0.75rem; color: var(--text-muted, #71717a);">
                                    {{ $po->supplier->phone ?? ($po->supplier->email ?? '—') }}
                                </div>
                            </td>

                            <!-- Order Date -->
                            <td style="font-size: 0.85rem; font-weight: 600; color: inherit;">
                                {{ $po->order_date ? $po->order_date->format('M d, Y') : '—' }}
                            </td>

                            <!-- Expected Delivery -->
                            <td style="font-size: 0.85rem; color: var(--text-muted, #a1a1aa);">
                                {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : '—' }}
                            </td>

                            <!-- Items Ordered -->
                            <td>
                                <span class="type-pill" style="background: rgba(234, 88, 12, 0.08); color: #ea580c; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.6rem; border-radius: 6px;">
                                    {{ $po->items->count() }} items
                                </span>
                            </td>

                            <!-- Total Cost -->
                            <td style="font-weight: 800; font-size: 1rem; color: inherit;">
                                ${{ number_format($po->total_cost, 2) }}
                            </td>

                            <!-- Status -->
                            <td>
                                <x-badge :status="$po->status" />
                            </td>

                            <!-- Action -->
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 0.4rem; justify-content: flex-end;">
                                    <button type="button" class="table-icon-btn btn-view-po" title="View Purchase Order Details"
                                        data-po='@json($poModalData)'
                                        onclick="openPurchaseOrderDetailModal(this)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                    @if($po->isDraft())
                                        <button type="button" onclick="openEditPurchaseOrderModalById({{ $po->id }})" class="table-icon-btn" title="Edit Draft PO" style="background: none; border: none; cursor: pointer; color: inherit;">
                                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                        </button>
                                    @elseif($po->isOrdered())
                                        <form action="{{ route('admin.purchase-orders.receive', $po->id) }}" method="POST" onsubmit="return confirm('Confirm receipt of PO #{{ $po->po_number }}? This will instantly increase raw ingredient inventory stock.');" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="table-icon-btn" title="Receive Stock" style="color: #16a34a; background: none; border: none; cursor: pointer; padding: 4px; display: inline-flex; align-items: center;">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" style="text-align: center; color: var(--text-muted, #71717a); padding: 3rem;">
                                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">📋</div>
                                <div style="font-weight: 700; font-size: 1.05rem; color: inherit;">No purchase orders found</div>
                                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Click "New Purchase Order" to procure baking ingredients.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================
     PURCHASE ORDER DETAIL MODAL CARD ("CART")
     ======================================================================== -->
<div id="purchaseOrderDetailModal" class="order-detail-modal-backdrop" onclick="if(event.target === this) closePurchaseOrderDetailModal()">
    <div class="order-detail-modal-card po-modal-card">
        <!-- Modal Header (Clean with no top X button - single close button is in footer) -->
        <div class="order-detail-modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.15rem 1.4rem;">
            <div class="order-detail-modal-header-left" style="display: flex; align-items: center; gap: 0.85rem;">
                <div class="po-modal-badge" id="po_modal_badge">
                    #PO-0001
                </div>
                <div>
                    <h3 class="order-detail-modal-title" style="font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-title, #0f172a);">
                        Purchase Order
                    </h3>
                    <p class="order-detail-modal-date" id="po_modal_creator_date" style="font-size: 0.775rem; margin: 0.2rem 0 0; color: var(--text-muted, #71717a);">
                        Created on — by System Admin
                    </p>
                </div>
            </div>
            <div id="po_modal_status_badge">
                <!-- Dynamic status badge -->
            </div>
        </div>

        <!-- Modal Body -->
        <div class="order-detail-modal-body" style="padding: 1.25rem 1.4rem; display: flex; flex-direction: column; gap: 1rem; overflow-y: auto; max-height: calc(85vh - 120px);">
            
            <!-- Received Status Banner -->
            <div id="po_modal_received_banner" style="display: none; padding: 0.75rem 1rem; border-radius: 12px; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); align-items: center; gap: 0.65rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2.5" style="flex-shrink: 0;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <div>
                    <div style="font-weight: 800; color: #10b981; font-size: 0.85rem;">Order Successfully Received & Stock Replenished</div>
                    <div id="po_modal_received_sub" style="font-size: 0.75rem; color: var(--text-muted, #94a3b8); margin-top: 1px;">Received on —. Stock quantities added to inventory.</div>
                </div>
            </div>

            <!-- Cancelled Status Banner -->
            <div id="po_modal_cancelled_banner" style="display: none; padding: 0.75rem 1rem; border-radius: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.25); align-items: center; gap: 0.65rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" style="flex-shrink: 0;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                <div>
                    <div style="font-weight: 800; color: #ef4444; font-size: 0.85rem;">Purchase Order Cancelled</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted, #94a3b8); margin-top: 1px;">This purchase order was cancelled. No stock replenishment occurred.</div>
                </div>
            </div>

            <!-- Ordered In Transit Banner -->
            <div id="po_modal_ordered_banner" style="display: none; padding: 0.75rem 1rem; border-radius: 12px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); align-items: center; gap: 0.65rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" style="flex-shrink: 0;"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <div>
                    <div style="font-weight: 800; color: #f59e0b; font-size: 0.85rem;">Order Placed with Vendor (In Transit)</div>
                    <div id="po_modal_ordered_sub" style="font-size: 0.75rem; color: var(--text-muted, #94a3b8); margin-top: 1px;">Awaiting delivery from supplier. Click Receive when stock arrives.</div>
                </div>
            </div>

            <!-- Vendor & Schedule 2-Column Info Grid -->
            <div class="order-detail-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem; padding: 0.95rem 1.1rem; border-radius: 14px;">
                <!-- Vendor Column -->
                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                    <span class="info-label" style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8);">
                        Vendor Information
                    </span>
                    <div id="po_modal_vendor_name" style="font-size: 0.95rem; font-weight: 800; color: #ea580c; line-height: 1.25;">
                        —
                    </div>
                    <div id="po_modal_vendor_contact_row" style="font-size: 0.8rem; color: var(--text-title, #0f172a); font-weight: 600; display: flex; align-items: center; gap: 0.4rem;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.6; flex-shrink: 0;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        <span id="po_modal_vendor_contact">—</span>
                    </div>
                    <div id="po_modal_vendor_meta_row" style="font-size: 0.775rem; color: var(--text-muted, #64748b); display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <span id="po_modal_vendor_phone_wrap" style="display: inline-flex; align-items: center; gap: 0.3rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.6; flex-shrink: 0;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            <span id="po_modal_vendor_phone">—</span>
                        </span>
                        <span id="po_modal_vendor_email_wrap" style="display: inline-flex; align-items: center; gap: 0.3rem;">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.6; flex-shrink: 0;"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                            <span id="po_modal_vendor_email">—</span>
                        </span>
                    </div>
                    <div id="po_modal_vendor_address_row" style="font-size: 0.75rem; color: var(--text-muted, #64748b); display: flex; align-items: center; gap: 0.35rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.6; flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <span id="po_modal_vendor_address">—</span>
                    </div>
                </div>

                <!-- Schedule Column (Clean rows without duplicate created-by) -->
                <div style="display: flex; flex-direction: column; gap: 0.4rem;">
                    <span class="info-label" style="font-size: 0.68rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8);">
                        Order Dates & Schedule
                    </span>
                    <div style="display: grid; grid-template-columns: 1fr; gap: 0.45rem; margin-top: 0.15rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--border, #e2e8f0); padding-bottom: 0.3rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted, #64748b);">Order Date</span>
                            <span id="po_modal_order_date" style="font-weight: 700; font-size: 0.825rem; color: var(--text-title, #0f172a);">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed var(--border, #e2e8f0); padding-bottom: 0.3rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted, #64748b);">Expected Delivery</span>
                            <span id="po_modal_expected_date" style="font-weight: 700; font-size: 0.825rem; color: #f59e0b;">—</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.1rem;">
                            <span style="font-size: 0.75rem; color: var(--text-muted, #64748b);">Received Date</span>
                            <span id="po_modal_received_date" style="font-weight: 700; font-size: 0.825rem; color: #10b981;">—</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes & Special Instructions (if any) -->
            <div id="po_modal_notes_box" class="order-detail-note-box" style="display: none;">
                <span class="note-tag" style="display: flex; align-items: center; gap: 0.35rem; font-weight: 800; font-size: 0.775rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                    Order Notes & Special Instructions
                </span>
                <span id="po_modal_notes" class="note-text" style="font-size: 0.825rem; line-height: 1.45;"></span>
            </div>

            <!-- Line Items Breakdown Table -->
            <div class="order-detail-items-section" style="display: flex; flex-direction: column; gap: 0.6rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border, #e2e8f0); padding-bottom: 0.45rem;">
                    <span style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8);">
                        Ordered Ingredients (<span id="po_modal_items_count">0</span>)
                    </span>
                </div>

                <div class="po-table-wrap" style="border-radius: 12px; border: 1px solid var(--border, #e2e8f0); overflow: hidden; background: var(--bg-surface, #ffffff);">
                    <table class="po-modal-ing-table">
                        <thead>
                            <tr>
                                <th style="width: 44%;">Ingredient</th>
                                <th style="width: 20%;">Quantity</th>
                                <th style="width: 18%;">Unit Price</th>
                                <th style="width: 18%; text-align: right;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="po_modal_items_tbody">
                            <!-- Dynamic Items Injected Here -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Grand Total Financial Summary Box -->
            <div class="order-detail-summary-box" style="padding: 0.85rem 1.1rem; border-radius: 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 0.95rem; font-weight: 800; color: var(--text-muted, #64748b);">Grand Total</span>
                    <span id="po_modal_total_cost" style="font-size: 1.25rem; font-weight: 900; color: #ea580c;">$0.00</span>
                </div>
            </div>
        </div>

        <!-- Modal Footer Actions (Single Close button, or clean action group) -->
        <div class="order-detail-modal-actions" id="po_modal_footer_actions" style="padding: 0.9rem 1.4rem; border-top: 1px solid var(--border, #e2e8f0); display: flex; align-items: center; justify-content: flex-end; background: var(--bg-surface, #faf6f0);">
            <!-- Dynamic Form buttons for Draft / Ordered / Cancelled / Received -->
        </div>
    </div>
</div>

<!-- ========================================================================
     CREATE PURCHASE ORDER MODAL CARD ("CART")
     ======================================================================== -->
<div id="createPurchaseOrderModal" class="order-detail-modal-backdrop" onclick="if(event.target === this) closeCreatePurchaseOrderModal()">
    <div class="order-detail-modal-card create-po-modal-card">
        <!-- Modal Header -->
        <div class="order-detail-modal-header" style="display: flex; align-items: center; justify-content: space-between; padding: 1.15rem 1.4rem;">
            <div class="order-detail-modal-header-left" style="display: flex; align-items: center; gap: 0.85rem;">
                <div class="order-detail-modal-badge" id="create_po_modal_badge" style="font-size: 1.05rem; background: rgba(234, 88, 12, 0.15); color: #ea580c; border: 1px solid rgba(234, 88, 12, 0.3);">
                    + PO
                </div>
                <div>
                    <h3 class="order-detail-modal-title" id="create_po_modal_title" style="font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-title, #0f172a);">
                        Create Purchase Order
                    </h3>
                </div>
            </div>
        </div>

        <!-- Modal Body Form -->
        <form action="{{ route('admin.purchase-orders.store') }}" method="POST" id="create_po_modal_form" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            @csrf
            <input type="hidden" name="_method" id="create_po_method_field" value="POST">
            <input type="hidden" name="redirect_to" value="index">

            <div class="order-detail-modal-body" style="padding: 1.25rem 1.4rem; overflow-y: auto; flex: 1; max-height: calc(88vh - 75px);">
                <div class="create-po-layout-grid">
                    
                    <!-- Left Column: Form Details & Items Table -->
                    <div style="display: flex; flex-direction: column; gap: 1.15rem;">
                        
                        <!-- 1. Supplier & Delivery Information -->
                        <div class="create-po-section-card">
                            <h4 style="margin: 0 0 0.9rem 0; font-size: 0.95rem; font-weight: 800; color: #ea580c; display: flex; align-items: center; gap: 0.4rem;">
                                <span>1. Supplier & Delivery Information</span>
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                                <div>
                                    <label class="form-label" style="display: block; font-weight: 700; font-size: 0.775rem; margin-bottom: 0.35rem; color: var(--text-primary, #1e293b);">
                                        Select Supplier <span style="color: #ef4444;">*</span>
                                    </label>
                                    <select name="supplier_id" id="modal_supplier_select" class="create-po-input" required>
                                        <option value="">-- Choose Vendor / Supplier --</option>
                                        @foreach($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}" {{ (old('supplier_id', request('supplier_id')) == $supplier->id) ? 'selected' : '' }}>
                                                {{ $supplier->name }} ({{ $supplier->phone ?? $supplier->email ?? 'No contact' }})
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label" style="display: block; font-weight: 700; font-size: 0.775rem; margin-bottom: 0.35rem; color: var(--text-primary, #1e293b);">
                                        Order Date <span style="color: #ef4444;">*</span>
                                    </label>
                                    <input type="date" name="order_date" id="modal_order_date" value="{{ old('order_date', date('Y-m-d')) }}" required class="create-po-input">
                                </div>

                                <div>
                                    <label class="form-label" style="display: block; font-weight: 700; font-size: 0.775rem; margin-bottom: 0.35rem; color: var(--text-primary, #1e293b);">
                                        Expected Delivery Date
                                    </label>
                                    <input type="date" name="expected_delivery_date" id="modal_expected_delivery_date" value="{{ old('expected_delivery_date') }}" class="create-po-input">
                                </div>

                                <div>
                                    <label class="form-label" style="display: block; font-weight: 700; font-size: 0.775rem; margin-bottom: 0.35rem; color: var(--text-primary, #1e293b);">
                                        Internal Notes & Terms
                                    </label>
                                    <input type="text" name="notes" id="modal_notes" value="{{ old('notes') }}" placeholder="e.g. Bulk discount applied, Net 15" class="create-po-input">
                                </div>
                            </div>
                        </div>

                        <!-- 2. Purchase Order Items -->
                        <div class="create-po-section-card">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                                <div>
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 800; color: #ea580c;">
                                        2. Purchase Order Items
                                    </h4>
                                </div>
                                <button type="button" id="btn_modal_add_item" onclick="createModalItemRow()" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.4rem 0.85rem; border-radius: 50px; font-weight: 700; font-size: 0.775rem; border: 1px solid var(--border-color, #d4d4d8); cursor: pointer;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Add Item</span>
                                </button>
                            </div>

                            <div style="overflow-x: auto;">
                                <table class="create-po-items-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 38%;">Ingredient</th>
                                            <th style="width: 13%;">Unit</th>
                                            <th style="width: 17%;">Quantity</th>
                                            <th style="width: 17%;">Unit Price ($)</th>
                                            <th style="width: 15%;">Subtotal ($)</th>
                                            <th style="width: 28px; text-align: center;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="modal_items_tbody">
                                        <!-- Dynamic item rows injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                    <!-- Right Column: Order Summary Card -->
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div class="create-po-summary-card">
                            <h4 style="margin: 0 0 0.85rem 0; font-size: 1rem; font-weight: 800; color: var(--text-title, #0f172a);">
                                Order Summary
                            </h4>

                            <div style="display: flex; flex-direction: column; gap: 0.75rem; border-bottom: 1px solid var(--border-color, #e2e8f0); padding-bottom: 0.85rem;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem;">
                                    <span style="color: var(--text-muted, #64748b);">Total Items</span>
                                    <span style="font-weight: 700; color: var(--text-title, #0f172a);" id="modal_summary_total_items">0</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 0.85rem; align-items: center;">
                                    <span style="color: var(--text-muted, #64748b);">Initial Status</span>
                                    <span class="status-pill pending" style="background: rgba(148, 163, 184, 0.15); color: #475569; font-weight: 700; padding: 0.2rem 0.55rem; border-radius: 50px; font-size: 0.75rem;">Draft</span>
                                </div>
                            </div>

                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin: 1rem 0;">
                                <span style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary, #0f172a);">Estimated Total:</span>
                                <span style="font-size: 1.5rem; font-weight: 900; color: #ea580c;" id="modal_summary_grand_total">$0.00</span>
                            </div>

                            <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; background: #ea580c; border: 1px solid #ea580c; color: #fff; border-radius: 50px; font-weight: 800; font-size: 0.925rem; cursor: pointer; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.25); display: flex; align-items: center; justify-content: center; gap: 0.4rem;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    <span>Save Purchase Order</span>
                                </button>
                                <button type="button" onclick="closeCreatePurchaseOrderModal()" class="btn btn-secondary" style="width: 100%; padding: 0.65rem; border-radius: 50px; border: 1px solid var(--border-color, #d4d4d8); text-align: center; color: var(--text-muted, #64748b); font-weight: 600; font-size: 0.85rem; cursor: pointer; background: transparent;">
                                    Cancel
                                </button>
                            </div>


                        </div>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    const CSRF_TOKEN = '{{ csrf_token() }}';

    let poToastTimer = null;

    window.showPoToast = function(message, type = 'warning') {
        let toast = document.getElementById('po_floating_toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'po_floating_toast';
            toast.className = 'po-floating-toast';
            document.body.appendChild(toast);
        }

        const iconHtml = type === 'warning' ? `
            <div class="po-toast-icon-wrap warning">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
        ` : `
            <div class="po-toast-icon-wrap info">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="16" x2="12" y2="12"/>
                    <line x1="12" y1="8" x2="12.01" y2="8"/>
                </svg>
            </div>
        `;

        toast.innerHTML = `
            ${iconHtml}
            <span class="po-toast-text">${escapeHtml(message)}</span>
            <button type="button" class="po-toast-close" onclick="closePoToast()" aria-label="Dismiss">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        `;

        toast.className = `po-floating-toast po-toast-${type} is-visible`;

        toast.classList.remove('is-shaking');
        void toast.offsetWidth;
        toast.classList.add('is-shaking');

        if (poToastTimer) clearTimeout(poToastTimer);
        poToastTimer = setTimeout(() => {
            closePoToast();
        }, 3600);
    };

    window.closePoToast = function() {
        const toast = document.getElementById('po_floating_toast');
        if (toast) {
            toast.classList.remove('is-visible', 'is-shaking');
        }
        if (poToastTimer) {
            clearTimeout(poToastTimer);
            poToastTimer = null;
        }
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    window.openPurchaseOrderDetailModalFromRow = function(linkEl) {
        const row = linkEl.closest('tr');
        if (!row) return;
        const viewBtn = row.querySelector('.btn-view-po');
        if (viewBtn) {
            openPurchaseOrderDetailModal(viewBtn);
        }
    };

    window.renderPurchaseOrderDetailModal = function(po) {
        const modal = document.getElementById('purchaseOrderDetailModal');
        if (!modal || !po) return;

        // 1. PO Number & Dates
        document.getElementById('po_modal_badge').textContent = `#${po.po_number}`;
        document.getElementById('po_modal_creator_date').textContent = `Created on ${po.created_at || '—'} by ${po.creator_name || 'System Admin'}`;

        // 2. Status Badge & Banners
        const statusMap = {
            'draft': { label: 'Draft', class: 'status-pending', color: '#94a3b8', bg: 'rgba(148, 163, 184, 0.12)', border: 'rgba(148, 163, 184, 0.25)' },
            'ordered': { label: 'Ordered (In Transit)', class: 'status-pending', color: '#f59e0b', bg: 'rgba(245, 158, 11, 0.12)', border: 'rgba(245, 158, 11, 0.25)' },
            'received': { label: 'Received & Stocked In', class: 'status-completed', color: '#10b981', bg: 'rgba(16, 185, 129, 0.12)', border: 'rgba(16, 185, 129, 0.25)' },
            'cancelled': { label: 'Cancelled', class: 'status-cancelled', color: '#ef4444', bg: 'rgba(239, 68, 68, 0.12)', border: 'rgba(239, 68, 68, 0.25)' }
        };
        const st = statusMap[po.status] || { label: po.status, class: 'status-pending', color: '#ea580c', bg: 'rgba(234, 88, 12, 0.12)', border: 'rgba(234, 88, 12, 0.25)' };
        document.getElementById('po_modal_status_badge').innerHTML = `<span class="status-pill ${st.class}" style="background: ${st.bg}; color: ${st.color}; border: 1px solid ${st.border}; font-weight: 700; padding: 0.3rem 0.85rem; border-radius: 9999px; font-size: 0.775rem; letter-spacing: 0.02em;">${st.label}</span>`;

        // Status Notification Banners
        const recvBanner = document.getElementById('po_modal_received_banner');
        const cancBanner = document.getElementById('po_modal_cancelled_banner');
        const ordBanner = document.getElementById('po_modal_ordered_banner');

        recvBanner.style.display = 'none';
        cancBanner.style.display = 'none';
        ordBanner.style.display = 'none';

        if (po.status === 'received') {
            recvBanner.style.display = 'flex';
            document.getElementById('po_modal_received_sub').textContent = `Received on ${po.received_date || '—'}. Stock quantities and cost prices have been added to inventory.`;
        } else if (po.status === 'cancelled') {
            cancBanner.style.display = 'flex';
        } else if (po.status === 'ordered') {
            ordBanner.style.display = 'flex';
        }

        // 3. Vendor Information (Structured micro-SVG presentation)
        if (po.supplier) {
            document.getElementById('po_modal_vendor_name').textContent = po.supplier.name || 'Unassigned Supplier';
            
            const contactPerson = po.supplier.contact_person || '';
            const contactRow = document.getElementById('po_modal_vendor_contact_row');
            if (contactPerson) {
                document.getElementById('po_modal_vendor_contact').textContent = contactPerson;
                contactRow.style.display = 'flex';
            } else {
                contactRow.style.display = 'none';
            }

            const phone = po.supplier.phone || '';
            const email = po.supplier.email || '';
            const phoneWrap = document.getElementById('po_modal_vendor_phone_wrap');
            const emailWrap = document.getElementById('po_modal_vendor_email_wrap');
            if (phone) {
                document.getElementById('po_modal_vendor_phone').textContent = phone;
                phoneWrap.style.display = 'inline-flex';
            } else {
                phoneWrap.style.display = 'none';
            }
            if (email) {
                document.getElementById('po_modal_vendor_email').textContent = email;
                emailWrap.style.display = 'inline-flex';
            } else {
                emailWrap.style.display = 'none';
            }
            document.getElementById('po_modal_vendor_meta_row').style.display = (!phone && !email) ? 'none' : 'flex';

            const address = po.supplier.address || '';
            const addressRow = document.getElementById('po_modal_vendor_address_row');
            if (address) {
                document.getElementById('po_modal_vendor_address').textContent = address;
                addressRow.style.display = 'flex';
            } else {
                addressRow.style.display = 'none';
            }
        } else {
            document.getElementById('po_modal_vendor_name').textContent = 'No vendor linked';
            document.getElementById('po_modal_vendor_contact_row').style.display = 'none';
            document.getElementById('po_modal_vendor_meta_row').style.display = 'none';
            document.getElementById('po_modal_vendor_address_row').style.display = 'none';
        }

        // 4. Order Dates & Schedule
        document.getElementById('po_modal_order_date').textContent = po.order_date || '—';
        document.getElementById('po_modal_expected_date').textContent = po.expected_delivery_date || '—';
        document.getElementById('po_modal_received_date').textContent = po.received_date || (po.status === 'received' ? 'Received' : '—');

        // 5. Notes
        const notesBox = document.getElementById('po_modal_notes_box');
        if (po.notes && po.notes.trim().length > 0) {
            document.getElementById('po_modal_notes').textContent = po.notes;
            notesBox.style.display = 'flex';
        } else {
            notesBox.style.display = 'none';
        }

        // 6. Ordered Ingredients List (Clean line items without redundant stock text)
        const items = po.items || [];
        document.getElementById('po_modal_items_count').textContent = items.length;
        const tbody = document.getElementById('po_modal_items_tbody');

        if (items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-muted, #94a3b8); padding: 1.5rem;">No items recorded in this order.</td></tr>`;
        } else {
            tbody.innerHTML = items.map(item => `
                <tr>
                    <td>
                        <div style="font-weight: 700; color: var(--text-title, #0f172a); font-size: 0.85rem;">
                            ${escapeHtml(item.name)}
                        </div>
                    </td>
                    <td style="font-weight: 700;">
                        ${Number(item.quantity).toFixed(2)} <span style="font-size: 0.725rem; color: var(--text-muted, #71717a); text-transform: uppercase;">${escapeHtml(item.unit)}</span>
                    </td>
                    <td style="color: var(--text-muted, #64748b);">
                        $${Number(item.purchase_price).toFixed(2)}
                    </td>
                    <td style="text-align: right; font-weight: 800; color: var(--text-title, #0f172a);">
                        $${Number(item.subtotal).toFixed(2)}
                    </td>
                </tr>
            `).join('');
        }

        // 7. Grand Total
        document.getElementById('po_modal_total_cost').textContent = '$' + Number(po.total_cost).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // 8. Footer Action Buttons (Only single professional Close button, or draft/ordered actions + Close)
        const footerActions = document.getElementById('po_modal_footer_actions');
        let actionsHtml = '';

        if (po.status === 'draft') {
            actionsHtml = `
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 0.6rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <button type="button" onclick="closePurchaseOrderDetailModal(); openEditPurchaseOrderModalById(${po.id});" class="btn btn-secondary" style="border-radius: 50px; padding: 0.5rem 1.1rem; font-size: 0.825rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.35rem; color: #2563eb; cursor: pointer; border: 1px solid #d4d4d8; background: #fff;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                            <span>Edit Draft</span>
                        </button>
                        <form method="POST" action="${po.routes.order}" onsubmit="return confirm('Place order with vendor? Status will become Ordered.');" style="display: inline; margin: 0;">
                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn" style="background: #ea580c; border: 1px solid #ea580c; color: #ffffff; border-radius: 50px; padding: 0.5rem 1.25rem; font-weight: 700; font-size: 0.825rem; display: inline-flex; align-items: center; gap: 0.35rem; cursor: pointer;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                                <span>Place Order</span>
                            </button>
                        </form>
                        <form method="POST" action="${po.routes.cancel}" onsubmit="return confirm('Cancel this purchase order?');" style="display: inline; margin: 0;">
                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn btn-secondary" style="border-radius: 50px; padding: 0.5rem 1rem; font-size: 0.825rem; font-weight: 600; border: 1px solid #ef4444; color: #ef4444; background: transparent; cursor: pointer;">
                                Cancel Order
                            </button>
                        </form>
                    </div>
                    <button type="button" onclick="closePurchaseOrderDetailModal();" class="btn-order-detail-close">
                        Close
                    </button>
                </div>
            `;
        } else if (po.status === 'ordered') {
            actionsHtml = `
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; gap: 0.6rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <form method="POST" action="${po.routes.receive}" onsubmit="return confirm('Receive Purchase Order #${po.po_number}?\\n\\nThis will:\\n- Add items to inventory stock\\n- Update ingredient costs\\n- Mark this order as Received.');" style="display: inline; margin: 0;">
                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn" style="background: #059669; border: 1px solid #059669; color: #ffffff; border-radius: 50px; padding: 0.5rem 1.35rem; font-weight: 700; font-size: 0.825rem; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; box-shadow: 0 2px 8px rgba(5, 150, 105, 0.25);">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                <span>Receive & Replenish Stock</span>
                            </button>
                        </form>
                        <form method="POST" action="${po.routes.cancel}" onsubmit="return confirm('Cancel this purchase order?');" style="display: inline; margin: 0;">
                            <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                            <button type="submit" class="btn btn-secondary" style="border-radius: 50px; padding: 0.5rem 1rem; font-size: 0.825rem; font-weight: 600; border: 1px solid #ef4444; color: #ef4444; background: transparent; cursor: pointer;">
                                Cancel Order
                            </button>
                        </form>
                    </div>
                    <button type="button" onclick="closePurchaseOrderDetailModal();" class="btn-order-detail-close">
                        Close
                    </button>
                </div>
            `;
        } else {
            actionsHtml = `
                <div style="display: flex; justify-content: flex-end; width: 100%;">
                    <button type="button" onclick="closePurchaseOrderDetailModal();" class="btn-order-detail-close">
                        Close
                    </button>
                </div>
            `;
        }

        footerActions.innerHTML = actionsHtml;

        // Show modal & lock background scrolling
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.openPurchaseOrderDetailModal = function(btn) {
        let po = null;
        const rawData = btn.getAttribute('data-po');
        if (rawData) {
            try {
                po = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
            } catch (e) {
                console.error('Failed to parse PO data:', e);
            }
        }
        if (po) {
            renderPurchaseOrderDetailModal(po);
        }
    };

    window.openPurchaseOrderDetailModalById = function(poId) {
        const buttons = document.querySelectorAll('.btn-view-po');
        for (const btn of buttons) {
            try {
                const po = JSON.parse(btn.getAttribute('data-po'));
                if (String(po.id) === String(poId)) {
                    renderPurchaseOrderDetailModal(po);
                    return;
                }
            } catch (e) {}
        }
        fetch(`/admin/purchase-orders/${poId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.purchase_order) {
                renderPurchaseOrderDetailModal(data.purchase_order);
            }
        })
        .catch(err => console.error('Failed to load PO:', err));
    };

    window.openEditPurchaseOrderModalById = function(poId) {
        fetch(`/admin/purchase-orders/${poId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            const po = data.purchase_order;
            if (!po) return;

            const modal = document.getElementById('createPurchaseOrderModal');
            const form = document.getElementById('create_po_modal_form');
            if (!modal || !form) return;

            form.action = `/admin/purchase-orders/${po.id}`;
            document.getElementById('create_po_method_field').value = 'PUT';
            document.getElementById('create_po_modal_title').textContent = `Edit Purchase Order #${po.po_number}`;
            document.getElementById('create_po_modal_badge').textContent = `#${po.po_number}`;

            document.getElementById('modal_supplier_select').value = po.supplier_id || '';
            document.getElementById('modal_order_date').value = po.order_date || '';
            document.getElementById('modal_expected_delivery_date').value = po.expected_delivery_date || '';
            document.getElementById('modal_notes').value = po.notes || '';

            const tbody = document.getElementById('modal_items_tbody');
            tbody.innerHTML = '';
            modalRowIndex = 0;

            if (po.items && po.items.length > 0) {
                po.items.forEach(item => {
                    createModalItemRow(item.ingredient_id, item.quantity, item.purchase_price);
                });
            } else {
                createModalItemRow('');
            }

            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        })
        .catch(err => console.error('Failed to load PO for edit:', err));
    };

    window.closePurchaseOrderDetailModal = function() {
        const modal = document.getElementById('purchaseOrderDetailModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    /* ======================================================================
       Create Purchase Order Modal ("Cart") Functionality
       ====================================================================== */
    const ingredientsCatalog = @json($ingredientsJson ?? []);
    let modalRowCount = 0;

    function createModalItemRow(ingredientId = '', quantity = 1, price = '') {
        const tbody = document.getElementById('modal_items_tbody');
        if (!tbody) return;
        const rowIndex = modalRowCount++;

        const tr = document.createElement('tr');
        tr.id = `modal_row_${rowIndex}`;
        tr.className = 'modal-item-row';

        let ingOptions = `<option value="">-- Select Ingredient --</option>`;
        for (const [id, ing] of Object.entries(ingredientsCatalog)) {
            const selected = (String(id) === String(ingredientId)) ? 'selected' : '';
            ingOptions += `<option value="${id}" ${selected}>${escapeHtml(ing.name)} (Stock: ${Number(ing.stock).toFixed(1)} ${escapeHtml(ing.unit)})</option>`;
        }

        const initialUnit = ingredientId && ingredientsCatalog[ingredientId] ? ingredientsCatalog[ingredientId].unit : '—';
        const initialCost = ingredientId && ingredientsCatalog[ingredientId] ? ingredientsCatalog[ingredientId].cost : 0.00;
        const initialPrice = price !== '' ? price : (initialCost ? Number(initialCost).toFixed(2) : '0.00');
        const initialSubtotal = (parseFloat(quantity || 0) * parseFloat(initialPrice || 0)).toFixed(2);

        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][ingredient_id]" class="create-po-input modal-ing-select" required style="height: 36px; padding: 0.35rem 0.5rem;">
                    ${ingOptions}
                </select>
            </td>
            <td>
                <span class="modal-unit-badge" id="modal_unit_badge_${rowIndex}" style="display: inline-block; padding: 0.25rem 0.5rem; border-radius: 6px; font-weight: 700; font-size: 0.75rem; background: rgba(0, 0, 0, 0.05); color: var(--text-primary, #334155); text-transform: uppercase;">
                    ${escapeHtml(initialUnit)}
                </span>
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][quantity]" value="${quantity}" required class="create-po-input modal-qty-input" style="height: 36px; padding: 0.35rem 0.5rem; font-weight: 700;">
            </td>
            <td>
                <input type="number" step="0.01" min="0.00" name="items[${rowIndex}][purchase_price]" value="${initialPrice}" required class="create-po-input modal-price-input" style="height: 36px; padding: 0.35rem 0.5rem; font-weight: 700;">
            </td>
            <td>
                <span style="font-weight: 800; font-size: 0.9rem; color: var(--text-title, #0f172a);" id="modal_subtotal_${rowIndex}">
                    $${initialSubtotal}
                </span>
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-remove-modal-row" data-row="${rowIndex}" title="Remove Item" style="background: none; border: none; color: #ef4444; font-size: 1.3rem; line-height: 1; cursor: pointer; padding: 0.2rem;">
                    &times;
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        const select = tr.querySelector('.modal-ing-select');
        const qtyInput = tr.querySelector('.modal-qty-input');
        const priceInput = tr.querySelector('.modal-price-input');
        const removeBtn = tr.querySelector('.btn-remove-modal-row');

        select.addEventListener('change', function() {
            const ingId = this.value;
            if (ingId && ingredientsCatalog[ingId]) {
                const ing = ingredientsCatalog[ingId];
                document.getElementById(`modal_unit_badge_${rowIndex}`).textContent = (ing.unit || '').toUpperCase();
                priceInput.value = Number(ing.cost || 0).toFixed(2);
            } else {
                document.getElementById(`modal_unit_badge_${rowIndex}`).textContent = '—';
            }
            updateModalRowSubtotal(rowIndex);
        });

        qtyInput.addEventListener('input', () => updateModalRowSubtotal(rowIndex));
        priceInput.addEventListener('input', () => updateModalRowSubtotal(rowIndex));

        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.modal-item-row').length > 1) {
                tr.remove();
                recalculateModalGrandTotal();
            } else {
                showPoToast('A purchase order must contain at least one ingredient.', 'warning');
                tr.classList.remove('row-warning-flash');
                void tr.offsetWidth;
                tr.classList.add('row-warning-flash');
                setTimeout(() => tr.classList.remove('row-warning-flash'), 1000);
            }
        });

        recalculateModalGrandTotal();
    }

    function updateModalRowSubtotal(rowIndex) {
        const tr = document.getElementById(`modal_row_${rowIndex}`);
        if (!tr) return;
        const qty = parseFloat(tr.querySelector('.modal-qty-input')?.value) || 0;
        const price = parseFloat(tr.querySelector('.modal-price-input')?.value) || 0;
        const subtotal = (qty * price).toFixed(2);
        const subEl = document.getElementById(`modal_subtotal_${rowIndex}`);
        if (subEl) subEl.textContent = `$${subtotal}`;
        recalculateModalGrandTotal();
    }

    function recalculateModalGrandTotal() {
        let grandTotal = 0;
        let totalItems = 0;

        document.querySelectorAll('.modal-item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.modal-qty-input')?.value) || 0;
            const price = parseFloat(row.querySelector('.modal-price-input')?.value) || 0;
            grandTotal += (qty * price);
            totalItems++;
        });

        const itemsEl = document.getElementById('modal_summary_total_items');
        const totalEl = document.getElementById('modal_summary_grand_total');
        if (itemsEl) itemsEl.textContent = totalItems;
        if (totalEl) totalEl.textContent = `$${grandTotal.toFixed(2)}`;
    }

    window.openCreatePurchaseOrderModal = function(prefill = {}) {
        const modal = document.getElementById('createPurchaseOrderModal');
        if (!modal) return;

        // Auto-detect supplier from ingredient if not provided
        if (!prefill.supplier_id && prefill.ingredient_id && ingredientsCatalog[prefill.ingredient_id]?.supplier_id) {
            prefill.supplier_id = ingredientsCatalog[prefill.ingredient_id].supplier_id;
        }

        if (prefill.supplier_id) {
            const suppSelect = document.getElementById('modal_supplier_select');
            if (suppSelect) suppSelect.value = prefill.supplier_id;
        }

        // If no rows exist yet, add first row with prefilled ingredient
        if (document.querySelectorAll('.modal-item-row').length === 0) {
            createModalItemRow(prefill.ingredient_id || '');
        } else if (prefill.ingredient_id) {
            const firstSelect = document.querySelector('.modal-ing-select');
            if (firstSelect) {
                firstSelect.value = prefill.ingredient_id;
                firstSelect.dispatchEvent(new Event('change'));
            }
        }

        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeCreatePurchaseOrderModal = function() {
        const modal = document.getElementById('createPurchaseOrderModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeCreatePurchaseOrderModal();
            closePurchaseOrderDetailModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('open_po')) {
            openPurchaseOrderDetailModalById(urlParams.get('open_po'));
        } else if (urlParams.has('edit_po')) {
            openEditPurchaseOrderModalById(urlParams.get('edit_po'));
        } else if (urlParams.get('action') === 'create' || urlParams.get('create') === '1' || urlParams.has('ingredient_id')) {
            openCreatePurchaseOrderModal({
                supplier_id: urlParams.get('supplier_id') || '',
                ingredient_id: urlParams.get('ingredient_id') || ''
            });
        }
    });
</script>
@endpush
@endsection
