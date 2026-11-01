@extends('layouts.app')

@section('title', 'Edit Purchase Order #' . $purchaseOrder->po_number . ' - Bakery Management System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('content')
<!-- Header Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
    <div>
        <h2 style="font-size: 1.35rem; font-weight: 800; margin: 0; color: var(--text-primary, #0f172a);">
            Edit Purchase Order #{{ $purchaseOrder->po_number }}
        </h2>
        <p style="font-size: 0.85rem; color: var(--text-muted, #64748b); margin-top: 0.25rem;">
            Update vendor selection, dates, items, and quantities before placing the order
        </p>
    </div>
    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-secondary" style="padding: 0.6rem 1.1rem; border-radius: 9999px; border: 1px solid var(--border-color, #e2e8f0); text-decoration: none; color: var(--text-primary, #1e293b); background: var(--bg-card, #ffffff); font-weight: 600;">
        &larr; View PO Details
    </a>
</div>

<form action="{{ route('admin.purchase-orders.update', $purchaseOrder->id) }}" method="POST" id="po_form">
    @csrf
    @method('PUT')

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        <!-- Left Column: Order Details & Dynamic Items -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            
            <!-- Order Header Card -->
            <div class="bakery-card" style="padding: 1.5rem; border-radius: 1.25rem; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
                <h3 style="margin: 0 0 1.25rem 0; font-size: 1.1rem; font-weight: 800; color: #4d2c20;">
                    1. Supplier & Delivery Information
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem;">
                    <div>
                        <label class="form-label" style="display: block; font-weight: 700; font-size: 0.825rem; margin-bottom: 0.4rem; color: var(--text-primary, #1e293b);">
                            Select Supplier <span style="color: #ef4444;">*</span>
                        </label>
                        <select name="supplier_id" id="supplier_select" class="ios-select-field" required style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 0.6rem; border: 1px solid #d4d4d8; height: 42px;">
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" {{ (old('supplier_id', $purchaseOrder->supplier_id) == $supplier->id) ? 'selected' : '' }}>
                                    {{ $supplier->name }} ({{ $supplier->phone ?? $supplier->email ?? 'No contact' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-weight: 700; font-size: 0.825rem; margin-bottom: 0.4rem; color: var(--text-primary, #1e293b);">
                            Order Date <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="date" name="order_date" value="{{ old('order_date', $purchaseOrder->order_date ? $purchaseOrder->order_date->format('Y-m-d') : date('Y-m-d')) }}" required class="ios-input-field" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 0.6rem; border: 1px solid #d4d4d8; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-weight: 700; font-size: 0.825rem; margin-bottom: 0.4rem; color: var(--text-primary, #1e293b);">
                            Expected Delivery Date
                        </label>
                        <input type="date" name="expected_delivery_date" value="{{ old('expected_delivery_date', $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('Y-m-d') : '') }}" class="ios-input-field" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 0.6rem; border: 1px solid #d4d4d8; height: 42px;">
                    </div>

                    <div>
                        <label class="form-label" style="display: block; font-weight: 700; font-size: 0.825rem; margin-bottom: 0.4rem; color: var(--text-primary, #1e293b);">
                            Internal Notes & Terms
                        </label>
                        <input type="text" name="notes" value="{{ old('notes', $purchaseOrder->notes) }}" placeholder="e.g. Bulk discount applied, Net 15" class="ios-input-field" style="width: 100%; padding: 0.65rem 0.85rem; border-radius: 0.6rem; border: 1px solid #d4d4d8; height: 42px;">
                    </div>
                </div>
            </div>

            <!-- Items Table Card -->
            <div class="bakery-card" style="padding: 1.5rem; border-radius: 1.25rem; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <div>
                        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #4d2c20;">
                            2. Purchase Order Items
                        </h3>
                        <p style="font-size: 0.8rem; color: var(--text-muted, #64748b); margin: 0.2rem 0 0 0;">
                            Update or add items to this draft purchase order.
                        </p>
                    </div>
                    <button type="button" id="btn_add_row" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1rem; border-radius: 9999px; font-weight: 700; border: 1px solid #d4d4d8; background: #fff; color: #4d2c20; cursor: pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        <span>Add Item</span>
                    </button>
                </div>

                <div class="table-responsive-wrapper">
                    <table class="recent-orders-table" id="items_table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Ingredient</th>
                                <th style="width: 15%;">Unit</th>
                                <th style="width: 15%;">Quantity</th>
                                <th style="width: 15%;">Unit Price ($)</th>
                                <th style="width: 15%;">Subtotal ($)</th>
                                <th style="width: 5%; text-align: center;"></th>
                            </tr>
                        </thead>
                        <tbody id="items_tbody">
                            <!-- Populated via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Right Column: Order Summary Card -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <div class="bakery-card" style="padding: 1.5rem; border-radius: 1.25rem; background: var(--card-bg, #ffffff); border: 1px solid var(--border-color, #e2e8f0); position: sticky; top: 1.5rem;">
                <h3 style="margin: 0 0 1rem 0; font-size: 1.1rem; font-weight: 800; color: #4d2c20;">
                    Order Summary
                </h3>

                <div style="display: flex; flex-direction: column; gap: 0.85rem; border-bottom: 1px solid var(--border-color, #e2e8f0); padding-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span style="color: var(--text-muted, #64748b);">Total Items</span>
                        <span style="font-weight: 700;" id="summary_total_items">0</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.9rem;">
                        <span style="color: var(--text-muted, #64748b);">Status</span>
                        <span class="status-pill pending" style="background: rgba(148, 163, 184, 0.15); color: #475569; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; font-size: 0.75rem;">Draft</span>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: baseline; margin: 1.25rem 0;">
                    <span style="font-size: 1rem; font-weight: 700; color: var(--text-primary, #0f172a);">Updated Total:</span>
                    <span style="font-size: 1.6rem; font-weight: 800; color: #4d2c20;" id="summary_grand_total">$0.00</span>
                </div>

                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.85rem; background: #4d2c20; color: #fff; border-radius: 0.75rem; border: none; font-weight: 700; font-size: 1rem; cursor: pointer; box-shadow: 0 4px 12px rgba(77, 44, 32, 0.25);">
                        Update Purchase Order
                    </button>
                    <a href="{{ route('admin.purchase-orders.show', $purchaseOrder->id) }}" class="btn btn-secondary" style="width: 100%; padding: 0.7rem; border-radius: 0.75rem; border: 1px solid #d4d4d8; text-decoration: none; text-align: center; color: #64748b; font-weight: 600;">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
    let poToastTimer = null;

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showPoToast(message, type = 'warning') {
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
    }

    function closePoToast() {
        const toast = document.getElementById('po_floating_toast');
        if (toast) {
            toast.classList.remove('is-visible', 'is-shaking');
        }
        if (poToastTimer) {
            clearTimeout(poToastTimer);
            poToastTimer = null;
        }
    }

    const ingredientsCatalog = @json($ingredientsJson);
    const existingItems = @json($purchaseOrder->items);
    let rowCount = 0;

    function createItemRow(ingredientId = '', quantity = 1, price = '') {
        const tbody = document.getElementById('items_tbody');
        const rowIndex = rowCount++;

        const tr = document.createElement('tr');
        tr.id = `row_${rowIndex}`;
        tr.className = 'item-row';

        let ingOptions = `<option value="">-- Select Ingredient --</option>`;
        for (const [id, ing] of Object.entries(ingredientsCatalog)) {
            const selected = (id == ingredientId) ? 'selected' : '';
            ingOptions += `<option value="${id}" ${selected}>${ing.name} (Stock: ${ing.stock} ${ing.unit})</option>`;
        }

        const initialUnit = ingredientId && ingredientsCatalog[ingredientId] ? ingredientsCatalog[ingredientId].unit : '—';
        const initialCost = ingredientId && ingredientsCatalog[ingredientId] ? ingredientsCatalog[ingredientId].cost : 0.00;
        const initialPrice = price !== '' ? price : initialCost;
        const initialSubtotal = (parseFloat(quantity) * parseFloat(initialPrice || 0)).toFixed(2);

        tr.innerHTML = `
            <td>
                <select name="items[${rowIndex}][ingredient_id]" class="ios-select-field ing-select" required style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d4d4d8;">
                    ${ingOptions}
                </select>
            </td>
            <td>
                <span class="unit-badge" id="unit_badge_${rowIndex}" style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-weight: 700; font-size: 0.8rem; color: #334155;">
                    ${initialUnit}
                </span>
            </td>
            <td>
                <input type="number" step="0.01" min="0.01" name="items[${rowIndex}][quantity]" value="${quantity}" required class="ios-input-field qty-input" style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d4d4d8; font-weight: 700;">
            </td>
            <td>
                <input type="number" step="0.01" min="0.00" name="items[${rowIndex}][purchase_price]" value="${initialPrice}" required class="ios-input-field price-input" style="width: 100%; padding: 0.5rem; border-radius: 0.5rem; border: 1px solid #d4d4d8; font-weight: 700;">
            </td>
            <td>
                <span style="font-weight: 800; font-size: 0.95rem; color: #0f172a;" id="subtotal_${rowIndex}">
                    $${initialSubtotal}
                </span>
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn-remove-row" data-row="${rowIndex}" style="background: none; border: none; color: #ef4444; font-size: 1.25rem; cursor: pointer; padding: 0.25rem;">
                    &times;
                </button>
            </td>
        `;

        tbody.appendChild(tr);

        // Event listeners
        const select = tr.querySelector('.ing-select');
        const qtyInput = tr.querySelector('.qty-input');
        const priceInput = tr.querySelector('.price-input');
        const removeBtn = tr.querySelector('.btn-remove-row');

        select.addEventListener('change', function() {
            const ingId = this.value;
            if (ingId && ingredientsCatalog[ingId]) {
                const ing = ingredientsCatalog[ingId];
                document.getElementById(`unit_badge_${rowIndex}`).textContent = ing.unit.toUpperCase();
                priceInput.value = Number(ing.cost).toFixed(2);
            } else {
                document.getElementById(`unit_badge_${rowIndex}`).textContent = '—';
            }
            updateRowSubtotal(rowIndex);
        });

        qtyInput.addEventListener('input', () => updateRowSubtotal(rowIndex));
        priceInput.addEventListener('input', () => updateRowSubtotal(rowIndex));

        removeBtn.addEventListener('click', function() {
            if (document.querySelectorAll('.item-row').length > 1) {
                tr.remove();
                recalculateGrandTotal();
            } else {
                showPoToast('A purchase order must contain at least one ingredient.', 'warning');
                tr.classList.remove('row-warning-flash');
                void tr.offsetWidth;
                tr.classList.add('row-warning-flash');
                setTimeout(() => tr.classList.remove('row-warning-flash'), 1000);
            }
        });

        recalculateGrandTotal();
    }

    function updateRowSubtotal(rowIndex) {
        const tr = document.getElementById(`row_${rowIndex}`);
        if (!tr) return;
        const qty = parseFloat(tr.querySelector('.qty-input').value) || 0;
        const price = parseFloat(tr.querySelector('.price-input').value) || 0;
        const subtotal = (qty * price).toFixed(2);
        document.getElementById(`subtotal_${rowIndex}`).textContent = `$${subtotal}`;
        recalculateGrandTotal();
    }

    function recalculateGrandTotal() {
        let grandTotal = 0;
        let totalItems = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            const price = parseFloat(row.querySelector('.price-input').value) || 0;
            grandTotal += (qty * price);
            totalItems++;
        });

        document.getElementById('summary_total_items').textContent = totalItems;
        document.getElementById('summary_grand_total').textContent = `$${grandTotal.toFixed(2)}`;
    }

    document.addEventListener('DOMContentLoaded', function() {
        if (existingItems && existingItems.length > 0) {
            existingItems.forEach(item => {
                createItemRow(item.ingredient_id, item.quantity, item.purchase_price);
            });
        } else {
            createItemRow();
        }

        document.getElementById('btn_add_row').addEventListener('click', function() {
            createItemRow();
        });
    });
</script>
@endpush
@endsection
