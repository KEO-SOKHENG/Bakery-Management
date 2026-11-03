@extends('layouts.app')

@section('title', $supplier->name . ' - Supplier Details')

@section('content')
<!-- Header Bar -->
<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div style="width: 52px; height: 52px; border-radius: 14px; background: rgba(77, 44, 32, 0.1); display: flex; align-items: center; justify-content: center; color: #4d2c20; font-size: 1.5rem; font-weight: 800;">
            {{ strtoupper(substr($supplier->name, 0, 1)) }}
        </div>
        <div>
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <h2 style="font-size: 1.4rem; font-weight: 800; margin: 0; color: var(--text-primary, #0f172a);">{{ $supplier->name }}</h2>
                @if($supplier->status === 'active')
                    <span class="status-pill completed" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 700; padding: 0.2rem 0.65rem; border-radius: 9999px; font-size: 0.75rem;">Active Vendor</span>
                @else
                    <span class="status-pill danger" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-weight: 700; padding: 0.2rem 0.65rem; border-radius: 9999px; font-size: 0.75rem;">Inactive</span>
                @endif
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted, #64748b); margin-top: 0.2rem;">
                Registered vendor partner since {{ $supplier->created_at->format('M d, Y') }}
            </p>
        </div>
    </div>
    <div style="display: flex; gap: 0.75rem; align-items: center;">
        <a href="{{ route('admin.suppliers') }}" class="btn btn-secondary" style="padding: 0.6rem 1.1rem; border-radius: 9999px; border: 1px solid var(--border-color, #e2e8f0); text-decoration: none; color: var(--text-primary, #1e293b); background: var(--bg-card, #ffffff); font-weight: 600;">
            &larr; Back to Suppliers
        </a>
        <a href="{{ route('admin.purchase-orders.index', ['action' => 'create', 'supplier_id' => $supplier->id]) }}" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; background: #4d2c20; color: #fff; border-radius: 9999px; text-decoration: none; font-weight: 700; box-shadow: 0 4px 12px rgba(77, 44, 32, 0.25);">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            <span>Create Purchase Order</span>
        </a>
    </div>
</div>

<!-- Supplier Contact & Info Card -->
<div class="bakery-card" style="padding: 1.25rem 1.5rem; border-radius: 1rem; margin-bottom: 1.5rem; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem;">
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); font-weight: 700;">Account Representative</div>
            <div style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary, #1e293b); margin-top: 0.25rem;">
                {{ $supplier->contact_person ?? 'Not specified' }}
            </div>
        </div>
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); font-weight: 700;">Direct Phone</div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary, #1e293b); margin-top: 0.25rem;">
                {{ $supplier->phone ?? '—' }}
            </div>
        </div>
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); font-weight: 700;">Email Address</div>
            <div style="font-size: 0.95rem; font-weight: 600; margin-top: 0.25rem;">
                @if($supplier->email)
                    <a href="mailto:{{ $supplier->email }}" style="color: #2563eb; text-decoration: none;">{{ $supplier->email }}</a>
                @else
                    <span style="color: #a1a1aa;">—</span>
                @endif
            </div>
        </div>
        <div>
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); font-weight: 700;">Facility / Warehouse Address</div>
            <div style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary, #1e293b); margin-top: 0.25rem;">
                {{ $supplier->address ?? 'No physical address provided' }}
            </div>
        </div>
    </div>
    @if($supplier->notes)
        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border-color, #e2e8f0);">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted, #94a3b8); font-weight: 700;">Procurement Terms & Notes</div>
            <div style="font-size: 0.875rem; color: var(--text-primary, #334155); margin-top: 0.25rem; line-height: 1.5;">
                {{ $supplier->notes }}
            </div>
        </div>
    @endif
</div>

<!-- KPI Metrics Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Purchase Orders</span>
            <div class="stat-value">{{ $stats['total_orders'] }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Pending Delivery</span>
            <div class="stat-value">{{ $stats['pending_orders'] }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Received & Stocked In</span>
            <div class="stat-value">{{ $stats['received_orders'] }}</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Procured Value</span>
            <div class="stat-value">${{ number_format($stats['total_spend'], 2) }}</div>
        </div>
    </div>
</div>

<!-- Section 1: Supplied Raw Materials -->
<div class="bakery-card" style="border-radius: 1rem; overflow: hidden; margin-bottom: 1.5rem; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-primary, #1e293b);">
            Supplied Ingredients & Raw Materials ({{ $supplier->ingredients->count() }})
        </h3>
        <a href="{{ route('admin.ingredients') }}" style="font-size: 0.85rem; color: #4d2c20; font-weight: 700; text-decoration: none;">Manage Inventory &rarr;</a>
    </div>
    <div class="table-responsive-wrapper">
        <table class="recent-orders-table" style="width: 100%;">
            <thead>
                <tr>
                    <th>Ingredient Name</th>
                    <th>Current Stock</th>
                    <th>Unit</th>
                    <th>Latest Purchase Price</th>
                    <th>Minimum Stock</th>
                    <th>Stock Condition</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supplier->ingredients as $ingredient)
                    <tr>
                        <td style="font-weight: 700; color: var(--text-primary, #1e293b);">
                            {{ $ingredient->name }}
                        </td>
                        <td style="font-weight: 800; font-size: 0.95rem;">
                            {{ number_format($ingredient->quantity, 2) }}
                        </td>
                        <td>
                            <span style="background: #f1f5f9; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">
                                {{ strtoupper($ingredient->unit) }}
                            </span>
                        </td>
                        <td style="font-weight: 700; color: #0f172a;">
                            ${{ number_format($ingredient->cost, 2) }}
                        </td>
                        <td style="color: var(--text-muted, #64748b);">
                            {{ number_format($ingredient->minimum_quantity, 2) }} {{ $ingredient->unit }}
                        </td>
                        <td>
                            @if($ingredient->isOutOfStock())
                                <span class="status-pill danger" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Out of Stock</span>
                            @elseif($ingredient->isLowStock())
                                <span class="status-pill pending" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Low Stock</span>
                            @else
                                <span class="status-pill completed" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">In Stock</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted, #71717a); padding: 2rem;">
                            No ingredients linked to this supplier yet. When receiving a purchase order, ingredients will automatically associate with this vendor.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Purchase Order History -->
<div class="bakery-card" style="border-radius: 1rem; overflow: hidden; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--text-primary, #1e293b);">
            Purchase Order History ({{ $supplier->purchaseOrders->count() }})
        </h3>
    </div>
    <div class="table-responsive-wrapper">
        <table class="recent-orders-table" style="width: 100%;">
            <thead>
                <tr>
                    <th>PO Number</th>
                    <th>Order Date</th>
                    <th>Expected Delivery</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Total Cost</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($supplier->purchaseOrders as $po)
                    <tr>
                        <td>
                            <a href="{{ route('admin.purchase-orders.index', ['open_po' => $po->id]) }}" style="font-family: monospace; font-weight: 800; color: #4d2c20; text-decoration: none;">
                                #{{ $po->po_number }}
                            </a>
                        </td>
                        <td style="color: var(--text-muted, #64748b); font-size: 0.85rem;">
                            {{ $po->order_date ? $po->order_date->format('M d, Y') : '—' }}
                        </td>
                        <td style="color: var(--text-muted, #64748b); font-size: 0.85rem;">
                            {{ $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : '—' }}
                        </td>
                        <td>
                            @if($po->status === 'draft')
                                <span class="status-pill pending" style="background: rgba(148, 163, 184, 0.15); color: #475569; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Draft</span>
                            @elseif($po->status === 'ordered')
                                <span class="status-pill inprogress" style="background: rgba(245, 158, 11, 0.15); color: #b45309; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Ordered</span>
                            @elseif($po->status === 'received')
                                <span class="status-pill completed" style="background: rgba(16, 185, 129, 0.15); color: #047857; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Received</span>
                            @else
                                <span class="status-pill danger" style="background: rgba(239, 68, 68, 0.15); color: #b91c1c; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 9999px; font-size: 0.75rem;">Cancelled</span>
                            @endif
                        </td>
                        <td style="font-weight: 600; font-size: 0.85rem;">
                            {{ $po->items->count() }} items
                        </td>
                        <td style="font-weight: 800; color: var(--text-primary, #0f172a);">
                            ${{ number_format($po->total_cost, 2) }}
                        </td>
                        <td style="text-align: right;">
                            <a href="{{ route('admin.purchase-orders.index', ['open_po' => $po->id]) }}" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.8rem; font-weight: 700; text-decoration: none; border: 1px solid #e2e8f0; color: #4d2c20;">
                                View PO
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted, #71717a); padding: 2.5rem;">
                            No purchase orders created with this supplier yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
