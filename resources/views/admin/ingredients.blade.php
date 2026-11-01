@extends('layouts.app')

@section('title', 'Ingredients Inventory - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.ingredients', array_merge(request()->except('stock_status', 'page'), ['stock_status' => ''])) }}"
           class="period-pill {{ !request('stock_status') ? 'active' : '' }}">
            <span>All Ingredients ({{ $stats['total'] }})</span>
        </a>
        <a href="{{ route('admin.ingredients', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'normal'])) }}"
           class="period-pill {{ request('stock_status') === 'normal' ? 'active' : '' }}">
            <span>In Stock</span>
        </a>
        <a href="{{ route('admin.ingredients', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'low'])) }}"
           class="period-pill {{ request('stock_status') === 'low' ? 'active' : '' }}">
            <span>Low Stock ({{ $stats['low_stock'] }})</span>
        </a>
        <a href="{{ route('admin.ingredients', array_merge(request()->except('stock_status', 'page'), ['stock_status' => 'out'])) }}"
           class="period-pill {{ request('stock_status') === 'out' ? 'active' : '' }}">
            <span>Out of Stock ({{ $stats['out_of_stock'] }})</span>
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container">

<!-- KPI Metrics Grid (Exact Orders / Dashboard Page Style) -->
<div class="dash-stat-row">
    <div class="stat-card">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Ingredients</span>
            <div class="stat-value">{{ $stats['total'] }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Catalog Track
            </span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Low Stock Warnings</span>
            <div class="stat-value">{{ $stats['low_stock'] }}</div>
            <span class="stat-trend {{ $stats['low_stock'] > 0 ? 'warning' : 'up' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                {{ $stats['low_stock'] > 0 ? 'Restock Soon' : 'Optimal Levels' }}
            </span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Out of Stock</span>
            <div class="stat-value">{{ $stats['out_of_stock'] }}</div>
            <span class="stat-trend {{ $stats['out_of_stock'] > 0 ? 'warning' : 'up' }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                {{ $stats['out_of_stock'] > 0 ? 'Urgent Replenish' : 'Fully Stocked' }}
            </span>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon-box blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total Stock Value</span>
            <div class="stat-value">${{ number_format($stats['total_value'], 2) }}</div>
            <span class="stat-trend up">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                Valuation
            </span>
        </div>
    </div>
</div>

<!-- Filter & Action Toolbar (Exact Orders Toolbar / Header Style) -->
<div class="ingredients-toolbar-row" id="ingredients_sticky_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0;">
    <!-- Left: Supplier Filter Pill Card -->
    <form method="GET" action="{{ route('admin.ingredients') }}" id="ingredients_filter_form" style="display: inline-flex; align-items: center; margin: 0;">
        @if(request('stock_status'))
            <input type="hidden" name="stock_status" value="{{ request('stock_status') }}">
        @endif
        <div class="supplier-filter-pill-card">
            <select name="supplier_id" id="supplier_select">
                <option value="all">All Suppliers</option>
                @foreach($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" {{ request('supplier_id') == $supplier->id ? 'selected' : '' }}>{{ $supplier->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn" style="border-radius: 50px; padding: 0.45rem 1.35rem; font-weight: 700; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem;">Filter</button>
            @if(request()->filled('supplier_id') && request('supplier_id') !== 'all')
                <a href="{{ route('admin.ingredients', request('stock_status') ? ['stock_status' => request('stock_status')] : []) }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.45rem 0.9rem; font-weight: 600; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.825rem;">Clear</a>
            @endif
        </div>
    </form>

    <!-- Right: Action Buttons (Purchase Order & Add Ingredient) -->
    <div class="ingredients-action-group" style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <a href="{{ route('admin.purchase-orders.index', ['action' => 'create']) }}" class="btn" id="btn_create_po" style="border-radius: 50px; height: 38px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700; background: #d97706; border: 1px solid #d97706; color: #ffffff; text-decoration: none; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(217, 119, 6, 0.25);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
            <span>Purchase Order</span>
        </a>
        <button type="button" class="btn" id="btn_open_ingredient_modal" style="border-radius: 50px; height: 38px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #f59e0b; border: 1px solid #f59e0b; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Add Ingredient</span>
        </button>
    </div>
</div>

<!-- Ingredients Table Card (Full Screen with internal scrolling - Exact Orders Page Style) -->
<div class="orders-card" id="ingredients_table_card">
    <div class="orders-table-wrapper" id="ingredients_table_wrapper">
        <table class="orders-table" id="ingredients_table">
            <thead>
                <tr>
                    <th style="width: 20%;">Ingredient Name</th>
                    <th style="width: 10%;">Current Stock</th>
                    <th style="width: 8%;">Unit</th>
                    <th style="width: 11%;">Purchase Cost</th>
                    <th style="width: 12%;">Reorder Min</th>
                    <th style="width: 15%;">Primary Supplier</th>
                    <th style="width: 10%;">Expiry Date</th>
                    <th style="width: 14%;">Stock Status</th>
                    <th style="width: 12%; text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ingredients as $ing)
                    @php
                        $qty = (float) $ing->quantity;
                        $min = (float) $ing->minimum_quantity;
                        $isOut = $qty <= 0;
                        $isLow = $qty > 0 && $qty <= $min;
                    @endphp
                    <tr>
                        <td>
                            <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary, #0f172a);">
                                {{ $ing->name }}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted, #71717a);">
                                In {{ $ing->recipes_count }} recipes | {{ $ing->purchase_order_items_count }} PO items
                            </div>
                        </td>
                        <td>
                            <span style="font-weight: 800; font-size: 1.05rem; {{ $isOut ? 'color: #ef4444;' : ($isLow ? 'color: #d97706;' : 'color: #0f172a;') }}">
                                {{ number_format($ing->quantity, 2) }}
                            </span>
                        </td>
                        <td>
                            <span style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.75rem; color: #334155;">
                                {{ strtoupper($ing->unit) }}
                            </span>
                        </td>
                        <td style="font-weight: 700; color: #0f172a;">
                            ${{ number_format($ing->cost, 2) }}
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted, #64748b);">
                            {{ number_format($ing->minimum_quantity, 2) }} {{ $ing->unit }}
                        </td>
                        <td>
                            @if($ing->supplier)
                                <a href="{{ route('admin.suppliers.show', $ing->supplier->id) }}" style="color: #4d2c20; font-weight: 700; text-decoration: none; font-size: 0.85rem;">
                                    {{ $ing->supplier->name }}
                                </a>
                            @else
                                <span style="color: #a1a1aa; font-size: 0.85rem;">Unassigned</span>
                            @endif
                        </td>
                        <td style="font-size: 0.85rem; color: var(--text-muted, #64748b);">
                            {{ $ing->expiry_date ? $ing->expiry_date->format('M d, Y') : '—' }}
                        </td>
                        <td>
                            @if($isOut)
                                <span class="status-badge status-cancelled">Out of Stock</span>
                            @elseif($isLow)
                                <span class="status-badge status-pending">Low Stock</span>
                            @else
                                <span class="status-badge status-completed">In Stock</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.4rem;">
                                <a href="{{ route('admin.purchase-orders.index', ['action' => 'create', 'ingredient_id' => $ing->id]) }}" class="table-icon-btn" title="Procure Ingredient via PO">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </a>
                                <button type="button" class="table-icon-btn btn-edit-ing" title="Edit Ingredient"
                                    data-id="{{ $ing->id }}"
                                    data-name="{{ $ing->name }}"
                                    data-unit="{{ $ing->unit }}"
                                    data-quantity="{{ $ing->quantity }}"
                                    data-cost="{{ $ing->cost }}"
                                    data-min="{{ $ing->minimum_quantity }}"
                                    data-supplier="{{ $ing->supplier_id }}"
                                    data-expiry="{{ $ing->expiry_date ? $ing->expiry_date->format('Y-m-d') : '' }}"
                                    data-status="{{ $ing->status }}">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                                </button>
                                <button type="button" class="btn-icon-action delete btn-delete-ing" title="Delete Ingredient"
                                    data-id="{{ $ing->id }}"
                                    data-name="{{ $ing->name }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" style="text-align: center; color: var(--text-muted, #71717a); padding: 3rem;">
                            <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">🌾</div>
                            <div style="font-weight: 700; font-size: 1.05rem;">No ingredients found</div>
                            <div style="font-size: 0.825rem; margin-top: 0.25rem;">Click "Add Ingredient" to register baking raw materials.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>

<!-- ADD INGREDIENT MODAL -->
<x-modal id="add_ingredient_modal" title="Add Ingredient" maxWidth="540px" class="dash-modal-overlay" :showClose="false">
    <form action="{{ route('admin.ingredients.store') }}" method="POST">
        @csrf
        <x-input name="name" label="Ingredient Name" placeholder="e.g. Unbleached Bread Flour" :required="true" />

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-select name="supplier_id" label="Supplier">
                <option value="">-- Optional Supplier --</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </x-select>
            <x-input name="unit" label="Unit (kg, g, L, pcs)" placeholder="kg" :required="true" value="kg" />
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" min="0" name="quantity" label="Initial Stock Quantity" placeholder="0.00" :required="true" value="0" />
            <x-input type="number" step="0.01" min="0" name="cost" label="Unit Purchase Cost ($)" placeholder="1.50" :required="true" value="0.00" />
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" min="0" name="minimum_quantity" label="Reorder Warning Threshold" placeholder="10.00" value="10.00" />
            <x-input type="date" name="expiry_date" label="Expiry Date" />
        </div>

        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_ing">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Ingredient</x-button>
        </div>
    </form>
</x-modal>

<!-- EDIT INGREDIENT MODAL -->
<x-modal id="edit_ingredient_modal" title="Edit Ingredient" maxWidth="540px" class="dash-modal-overlay" :showClose="false">
    <form id="edit_ingredient_form" method="POST">
        @csrf
        @method('PUT')
        <x-input name="name" id="edit_ing_name" label="Ingredient Name" :required="true" />

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-select name="supplier_id" id="edit_ing_supplier" label="Supplier">
                <option value="">-- Optional Supplier --</option>
                @foreach($suppliers as $s)
                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                @endforeach
            </x-select>
            <x-input name="unit" id="edit_ing_unit" label="Unit" :required="true" />
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" min="0" name="quantity" id="edit_ing_quantity" label="Current Quantity" :required="true" />
            <x-input type="number" step="0.01" min="0" name="cost" id="edit_ing_cost" label="Purchase Cost ($)" :required="true" />
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" min="0" name="minimum_quantity" id="edit_ing_minimum" label="Reorder Threshold" />
            <x-input type="date" name="expiry_date" id="edit_ing_expiry" label="Expiry Date" />
        </div>

        <x-select name="status" id="edit_ing_status" label="Status">
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </x-select>

        <!-- Danger Zone / Delete Ingredient Action Card -->
        <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <div class="user-action-card user-action-card-danger">
                <div>
                    <div class="user-action-card-title" style="color: #ef4444;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Delete Ingredient</span>
                    </div>
                    <div class="user-action-card-desc">
                        Permanently delete this ingredient from bakery inventory records
                    </div>
                </div>
                <button type="button" class="btn btn-action-card-btn btn-danger-action" id="btn_delete_from_edit_ing">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <span>Delete</span>
                </button>
            </div>
        </div>

        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_edit_ing">Cancel</x-button>
            <x-button variant="primary" type="submit">Update Ingredient</x-button>
        </div>
    </form>
</x-modal>

<!-- Hidden form for ingredient deletion -->
<form id="form_delete_ingredient" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- ==========================================================================
     UNIFIED DELETE INGREDIENT CONFIRMATION MODAL CARD
     ========================================================================== -->
<div class="user-modal-backdrop" id="delete_ingredient_confirm_modal">
    <div class="user-modal-card" style="max-width: 440px;">
        <div class="user-modal-header" style="border-bottom: 1px solid rgba(220, 38, 38, 0.2);">
            <h3 style="color: #ef4444; display: flex; align-items: center; gap: 0.5rem; font-size: 1.05rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Delete Ingredient</span>
            </h3>
        </div>
        <div class="user-modal-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
            <p style="font-size: 0.925rem; margin: 0; color: var(--user-text, #ffffff); line-height: 1.5;">
                Are you sure you want to delete ingredient <strong id="delete_modal_ing_name" style="color: #f59e0b;"></strong>?
            </p>
            <div style="background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--user-muted, #a1a1aa); line-height: 1.45;">
                <div style="font-weight: 700; color: #ef4444; margin-bottom: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Recipe & Production Safety</span>
                </div>
                If this ingredient is linked to recipe formulations or historical batches, past production history remains safely recorded.
            </div>
        </div>
        <div class="user-modal-footer" style="padding: 1rem 1.5rem; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-modal-cancel" id="btn_cancel_delete_ing_confirm">Close</button>
            <button type="button" class="btn btn-action-card-btn btn-danger-action" id="btn_confirm_delete_ing_execute" style="height: 40px; padding: 0 1.25rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span>Delete Ingredient</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add Modal
        const addModal = document.getElementById('add_ingredient_modal');
        const openAddBtn = document.getElementById('btn_open_ingredient_modal');
        const closeAddBtn = document.getElementById('btn_close_ing_modal');
        const cancelAddBtn = document.getElementById('btn_cancel_ing');

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
        const editModal = document.getElementById('edit_ingredient_modal');
        const editForm = document.getElementById('edit_ingredient_form');
        const closeEditBtn = document.getElementById('btn_close_edit_ing_modal');
        const cancelEditBtn = document.getElementById('btn_cancel_edit_ing');

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

        let currentEditIngId = null;
        let currentEditIngName = '';

        document.querySelectorAll('.btn-edit-ing').forEach(btn => {
            btn.addEventListener('click', function() {
                currentEditIngId = this.getAttribute('data-id');
                currentEditIngName = this.getAttribute('data-name') || '';
                editForm.action = `/admin/ingredients/${currentEditIngId}`;
                document.getElementById('edit_ing_name').value = currentEditIngName;
                document.getElementById('edit_ing_unit').value = this.getAttribute('data-unit') || '';
                document.getElementById('edit_ing_quantity').value = this.getAttribute('data-quantity') || '';
                document.getElementById('edit_ing_cost').value = this.getAttribute('data-cost') || '';
                document.getElementById('edit_ing_minimum').value = this.getAttribute('data-min') || '';
                document.getElementById('edit_ing_supplier').value = this.getAttribute('data-supplier') || '';
                document.getElementById('edit_ing_expiry').value = this.getAttribute('data-expiry') || '';
                document.getElementById('edit_ing_status').value = this.getAttribute('data-status') || 'active';

                editModal.classList.add('active', 'is-active');
                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        // Unified Delete Confirmation Modal
        const deleteConfirmModal = document.getElementById('delete_ingredient_confirm_modal');
        const deleteModalName = document.getElementById('delete_modal_ing_name');
        const btnCloseDeleteConfirm = document.getElementById('btn_close_delete_ing_confirm');
        const btnCancelDeleteConfirm = document.getElementById('btn_cancel_delete_ing_confirm');
        const btnConfirmDeleteExecute = document.getElementById('btn_confirm_delete_ing_execute');
        const formDeleteIngredient = document.getElementById('form_delete_ingredient');
        let targetDeleteId = null;

        function openDeleteModal(id, name) {
            targetDeleteId = id;
            if (deleteModalName) deleteModalName.textContent = `"${name}"`;
            if (deleteConfirmModal) {
                deleteConfirmModal.classList.add('active', 'is-active');
                deleteConfirmModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeDeleteModal() {
            if (deleteConfirmModal) {
                deleteConfirmModal.classList.remove('active', 'is-active');
                deleteConfirmModal.style.display = 'none';
                if (!editModal || editModal.style.display !== 'flex') {
                    document.body.style.overflow = '';
                }
            }
        }

        // Table row delete buttons
        document.querySelectorAll('.btn-delete-ing').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                openDeleteModal(id, name);
            });
        });

        // Delete button from inside Edit Modal
        const btnDeleteFromEdit = document.getElementById('btn_delete_from_edit_ing');
        if (btnDeleteFromEdit) {
            btnDeleteFromEdit.addEventListener('click', function() {
                if (currentEditIngId) {
                    openDeleteModal(currentEditIngId, currentEditIngName);
                }
            });
        }

        if (btnCloseDeleteConfirm) btnCloseDeleteConfirm.addEventListener('click', closeDeleteModal);
        if (btnCancelDeleteConfirm) btnCancelDeleteConfirm.addEventListener('click', closeDeleteModal);
        if (deleteConfirmModal) {
            deleteConfirmModal.addEventListener('click', (e) => {
                if (e.target === deleteConfirmModal) closeDeleteModal();
            });
        }

        if (btnConfirmDeleteExecute) {
            btnConfirmDeleteExecute.addEventListener('click', function() {
                if (!targetDeleteId) return;
                btnConfirmDeleteExecute.disabled = true;
                btnConfirmDeleteExecute.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <span>Deleting...</span>';
                formDeleteIngredient.action = `/admin/ingredients/${targetDeleteId}`;
                formDeleteIngredient.submit();
            });
        }

        // Real-time instant search filtering for ingredients table
        const searchInput = document.getElementById('ingredient_search_input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#ingredients_table tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
</script>
@endpush
@endsection
