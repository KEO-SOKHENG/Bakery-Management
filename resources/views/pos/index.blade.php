@extends('layouts.app')

@section('title', 'POS Sales Terminal - Bakery Management')
@section('body_class', 'is-pos-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pos.css') }}?v={{ @filemtime(public_path('css/pos.css')) ?: '1.0' }}">
@endpush


@section('content')
<div class="pos-screen-layout">
    <!-- ======================================================================
         LEFT: CATALOG PANEL (Product Cards Grid)
         ====================================================================== -->
    <div class="pos-catalog-panel">

        <!-- Category Filter Tabs (Always Sticky, matching Order page style & size) -->
        <div class="pos-category-sticky-bar" id="pos_category_sticky_bar">
            <div class="pos-category-scroll">
                <div class="orders-filter-bar pos-category-tabs" id="pos_category_tabs">
                    <button type="button" class="filter-btn pos-cat-pill active" data-category-id="all">
                        <span data-lang-key="all_categories">All Items</span>
                    </button>
                    @foreach($categories as $category)
                        <button type="button" class="filter-btn pos-cat-pill" data-category-id="{{ $category->id }}">
                            <span>{{ $category->name }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="pos-grid-container" id="pos_product_grid">
            @forelse($products as $product)
                @php
                    $isOutOfStock = $product->stock <= 0;
                    $isLowStock = $product->stock > 0 && $product->stock <= 5;
                @endphp
                <div
                    class="pos-item-card {{ $isOutOfStock ? 'is-out-of-stock' : '' }}"
                    id="pos_prod_{{ $product->id }}"
                    data-id="{{ $product->id }}"
                    data-name="{{ $product->name }}"
                    data-price="{{ (float) $product->price }}"
                    data-stock="{{ (int) $product->stock }}"
                    data-category-id="{{ $product->category_id }}"
                    data-code="{{ strtolower($product->code ?? $product->sku ?? '') }}"
                    data-image="{{ $product->image_url ?? '' }}"
                >
                    <div class="pos-item-visual">
                        @if($product->image_url)
                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 18px; display: block;">
                        @else
                            {!! $product->getIconSvg(40) !!}
                        @endif
                    </div>

                    <div class="pos-item-name" title="{{ $product->name }}">{{ $product->name }}</div>
                    <div class="pos-item-price">{{ $currency }}{{ number_format($product->price, 2) }}</div>

                    <div class="pos-stock-badge {{ $isOutOfStock ? 'out-of-stock' : ($isLowStock ? 'low-stock' : 'in-stock') }}">
                        @if($isOutOfStock)
                            <span data-lang-key="out_of_stock">Out of Stock</span>
                        @elseif($isLowStock)
                            <span class="stock-text">Low stock: {{ $product->stock }}</span>
                        @else
                            <span class="stock-text">{{ $product->stock }} in stock</span>
                        @endif
                    </div>
                </div>
            @empty
                <div style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted);">
                    <p style="font-size: 1.1rem; font-weight: 700;">No active bakery products available.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- ======================================================================
         RIGHT: CART & CHECKOUT PANEL
         ====================================================================== -->
    <div class="pos-cart-panel" id="pos_cart_panel">
        <!-- Mobile Drawer Close & Drag Bar -->
        <div class="pos-cart-mobile-drawer-top" id="pos_cart_mobile_drawer_top">
            <div class="pos-cart-drag-handle"></div>
            <div class="pos-cart-mobile-bar-title">
                <span data-lang-key="current_sale">Current Sale</span>
                <button type="button" class="btn-close-cart-drawer" id="btn_close_cart_drawer" aria-label="Close Cart">&times;</button>
            </div>
        </div>

        <div class="pos-cart-header">
            <div class="pos-cart-title">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <span data-lang-key="current_sale">Current Sale</span>
                <span class="pos-cart-count-pill" id="pos_cart_count_pill">0</span>
            </div>
            <button type="button" class="btn-clear-cart" id="btn_clear_cart" title="Clear All Items" data-lang-key="clear_cart">
                Clear Cart
            </button>
        </div>

        <!-- Hidden Inputs for Form Reading & Backward Compatibility -->
        <input type="hidden" id="pos_customer_id" value="">
        <input type="hidden" id="pos_customer_name" value="Walk-in Customer">

        <!-- Cart Items Scroll List -->
        <div class="pos-cart-items-container" id="pos_cart_items_container">
            <div class="pos-cart-empty-state" id="pos_cart_empty_state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <circle cx="9" cy="21" r="1"/>
                    <circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <div style="font-weight: 700; font-size: 0.95rem; margin-bottom: 0.25rem;" data-lang-key="cart_empty">Your cart is empty</div>
                <div style="font-size: 0.8rem;" data-lang-key="cart_empty_sub">Click products on the left to add items</div>
            </div>
        </div>

        <!-- Payment Method Toggle Selector -->
        <div class="pos-cart-form-group">
            <label class="pos-cart-label" data-lang-key="payment_method">Payment Method</label>
            <div class="pos-payment-selector" id="pos_payment_selector">
                <button type="button" class="pos-pay-btn active" data-method="cash">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="2" y="6" width="20" height="12" rx="2"/>
                        <circle cx="12" cy="12" r="2"/>
                        <path d="M6 12h.01M18 12h.01"/>
                    </svg>
                    <span data-lang-key="cash">Cash</span>
                </button>
                <button type="button" class="pos-pay-btn" data-method="qr_code">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="3" y="3" width="7" height="7"/>
                        <rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/>
                    </svg>
                    <span data-lang-key="qr_code">KHQR</span>
                </button>
                <button type="button" class="pos-pay-btn" data-method="card">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <rect x="2" y="5" width="20" height="14" rx="2"/>
                        <line x1="2" y1="10" x2="22" y2="10"/>
                    </svg>
                    <span data-lang-key="card">Card</span>
                </button>
            </div>
        </div>

        <!-- Totals Breakdown -->
        <div class="pos-totals-table">
            <div class="pos-summary-row">
                <span data-lang-key="subtotal">Subtotal</span>
                <span id="pos_subtotal_display">{{ $currency }}0.00</span>
            </div>
            <div class="pos-summary-row" style="align-items: center;">
                <span data-lang-key="discount_label">Discount</span>
                <div style="display: flex; align-items: center; gap: 4px;">
                    <span style="font-size: 0.8rem; color: var(--text-muted);">{{ $currency }}</span>
                    <input
                        type="number"
                        id="pos_discount_input"
                        step="0.1"
                        min="0"
                        value="0.00"
                        class="pos-cart-input"
                        style="width: 76px; height: 28px; padding: 2px 6px; text-align: right; font-size: 0.85rem;"
                    >
                </div>
            </div>
            <div class="pos-summary-row">
                <span><span data-lang-key="tax_label">Tax</span> ({{ $taxPercentage }}%)</span>
                <span id="pos_tax_display">{{ $currency }}0.00</span>
            </div>
            <div class="pos-summary-row grand-total">
                <span data-lang-key="grand_total">Grand Total</span>
                <span class="pos-grand-amount" id="pos_grand_total_display">{{ $currency }}0.00</span>
            </div>
        </div>

        <!-- Complete Sale Button -->
        <button type="button" class="btn-complete-sale" id="btn_complete_sale" disabled>
            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span id="btn_complete_sale_text" data-lang-key="complete_sale">Complete Sale</span>
        </button>
    </div>
</div>

<!-- ======================================================================
     MOBILE STICKY BOTTOM CART TRIGGER BAR (<= 767px)
     ====================================================================== -->
<div class="pos-mobile-cart-bar" id="pos_mobile_cart_bar">
    <div class="pos-mobile-cart-summary">
        <div class="pos-mobile-cart-count-badge">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <span><strong id="pos_mobile_cart_count">0</strong> items</span>
        </div>
        <div class="pos-mobile-cart-total-val" id="pos_mobile_cart_total">{{ $currency }}0.00</div>
    </div>
    <button type="button" class="btn-pos-mobile-view-cart" id="btn_pos_mobile_view_cart">
        <span data-lang-key="view_cart">View Cart</span>
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
</div>

<!-- Mobile Cart Bottom Sheet Backdrop -->
<div class="pos-cart-backdrop" id="pos_cart_backdrop"></div>

<!-- ==========================================================================
     RECEIPT CONFIRMATION MODAL & THERMAL SLIP
     ========================================================================== -->
<div class="pos-modal-backdrop" id="pos_receipt_modal">
    <div class="pos-receipt-modal">
        <!-- Printable Area -->
        <div id="pos_receipt_print_area">
            <div class="pos-thermal-slip">
                <div class="slip-center">
                    <div class="slip-title" id="slip_bakery_name">{{ $bakeryName }}</div>
                    <div class="slip-meta" id="slip_bakery_address">{{ $bakeryAddress }}</div>
                    <div class="slip-meta" id="slip_bakery_phone">Tel: {{ $bakeryPhone }}</div>
                    <div class="slip-meta" style="margin-top: 4px; font-style: italic;" id="slip_receipt_header">{{ $receiptHeader }}</div>
                </div>

                <div class="slip-divider"></div>

                <div class="slip-meta" style="display: flex; justify-content: space-between;">
                    <span>Order: <strong id="slip_order_num">ORD-0000</strong></span>
                    <span id="slip_payment_ref">PAY-0000</span>
                </div>
                <div class="slip-meta" style="display: flex; justify-content: space-between;">
                    <span>Date: <span id="slip_datetime">2026-09-06 12:00</span></span>
                    <span>Cashier: <strong id="slip_cashier">Cashier</strong></span>
                </div>
                <div class="slip-meta" id="slip_customer_row">
                    <span>Customer: <span id="slip_customer">Walk-in</span></span>
                </div>
                <div class="slip-meta" id="slip_phone_row" style="display: none;">
                    <span>Phone: <span id="slip_customer_phone"></span></span>
                </div>
                <div class="slip-meta" id="slip_loyalty_row" style="display: none;">
                    <span>Loyalty: <strong id="slip_loyalty_info" style="color: #16a34a;"></strong></span>
                </div>
                <div class="slip-meta">
                    <span>Payment: <strong id="slip_method">CASH</strong></span>
                </div>

                <div class="slip-divider"></div>

                <table class="slip-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="align-right">Qty x Price</th>
                            <th class="align-right">Total</th>
                        </tr>
                    </thead>
                    <tbody id="slip_items_body">
                        <!-- Populated by JS -->
                    </tbody>
                </table>

                <div class="slip-divider"></div>

                <div class="slip-totals-row">
                    <span>Subtotal:</span>
                    <span id="slip_subtotal">{{ $currency }}0.00</span>
                </div>
                <div class="slip-totals-row">
                    <span>Discount:</span>
                    <span id="slip_discount">{{ $currency }}0.00</span>
                </div>
                <div class="slip-totals-row">
                    <span>Tax (<span id="slip_tax_percent">{{ $taxPercentage }}</span>%):</span>
                    <span id="slip_tax">{{ $currency }}0.00</span>
                </div>
                <div class="slip-totals-row grand">
                    <span>TOTAL:</span>
                    <span id="slip_grand_total">{{ $currency }}0.00</span>
                </div>

                <div class="slip-divider"></div>

                <div class="slip-footer" id="slip_receipt_footer">
                    {{ $receiptFooter }}
                </div>
                <div class="slip-center slip-meta" style="margin-top: 8px; font-size: 0.7rem;">
                    *** Thank You & Come Again! ***
                </div>
            </div>
        </div>

        <!-- Modal Actions -->
        <div class="pos-modal-actions">
            <x-button variant="secondary" id="btn_receipt_print" class="btn-receipt-print">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                <span data-lang-key="print_receipt">Print Receipt</span>
            </x-button>
            <x-button variant="primary" id="btn_receipt_close" class="btn-receipt-close" data-lang-key="new_sale">
                New Sale
            </x-button>
        </div>
    </div>
</div>

<!-- ==========================================================================
     QUICK REGISTER CUSTOMER MODAL (In POS Terminal)
     ========================================================================== -->
<div class="pos-modal-backdrop" id="pos_quick_customer_modal">
    <div class="pos-receipt-modal" style="max-width: 400px; padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h3 style="margin: 0; font-size: 1.15rem; font-weight: 800; color: var(--text-title, #29150d); display: flex; align-items: center; gap: 0.5rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                <span data-lang-key="quick_add_customer">Quick Register Customer</span>
            </h3>
            <button type="button" class="btn-modal-close" id="btn_close_quick_cust_modal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <form id="form_quick_customer">
            <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.25rem;">
                <div>
                    <label class="pos-cart-label" for="quick_cust_name" data-lang-key="customer_name">Full Name *</label>
                    <input type="text" id="quick_cust_name" class="pos-cart-input" required placeholder="e.g. Sokha Chan">
                </div>
                <div>
                    <label class="pos-cart-label" for="quick_cust_phone" data-lang-key="phone">Phone Number</label>
                    <input type="text" id="quick_cust_phone" class="pos-cart-input" placeholder="e.g. 098 765 432">
                </div>
                <div>
                    <label class="pos-cart-label" for="quick_cust_email" data-lang-key="email">Email Address (Optional)</label>
                    <input type="email" id="quick_cust_email" class="pos-cart-input" placeholder="e.g. customer@example.com">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <x-button variant="secondary" id="btn_cancel_quick_cust" class="btn-clear-cart" style="padding: 0.5rem 1rem;" data-lang-key="cancel">Cancel</x-button>
                <x-button variant="primary" type="submit" class="btn-complete-sale" style="width: auto; padding: 0.55rem 1.35rem; font-size: 0.875rem;" id="btn_save_quick_cust">
                    <span data-lang-key="save">Register & Select</span>
                </x-button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    window.POS_CONFIG = {
        taxPercentage: {{ (float) $taxPercentage }},
        currency: @json($currency),
        checkoutUrl: @json(route('pos.checkout')),
        customerSearchUrl: @json(route('pos.customers.search')),
        customerQuickStoreUrl: @json(route('pos.customers.quickStore')),
        csrfToken: @json(csrf_token()),
    };
</script>
<script src="{{ asset('js/pos.js') }}?v={{ @filemtime(public_path('js/pos.js')) ?: '1.0' }}"></script>
@endpush