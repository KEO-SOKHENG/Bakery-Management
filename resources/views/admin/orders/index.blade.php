@extends('layouts.app')

@section('title', 'Orders Management - Bakery System')
@section('header_title', 'Customer & Wholesale Orders')
@section('header_subtitle', 'Monitor live order fulfillment, custom cakes, and POS sales')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.orders', array_merge(request()->except('date', 'page'), ['date' => ''])) }}"
           class="period-pill {{ !request('date') ? 'active' : '' }}">All Dates</a>
        <a href="{{ route('admin.orders', array_merge(request()->except('date', 'page'), ['date' => 'today'])) }}"
           class="period-pill {{ request('date') === 'today' ? 'active' : '' }}">Today</a>
        <a href="{{ route('admin.orders', array_merge(request()->except('date', 'page'), ['date' => 'yesterday'])) }}"
           class="period-pill {{ request('date') === 'yesterday' ? 'active' : '' }}">Yesterday</a>
        <a href="{{ route('admin.orders', array_merge(request()->except('date', 'page'), ['date' => 'this_week'])) }}"
           class="period-pill {{ request('date') === 'this_week' ? 'active' : '' }}">This Week</a>
        <a href="{{ route('admin.orders', array_merge(request()->except('date', 'page'), ['date' => 'this_month'])) }}"
           class="period-pill {{ request('date') === 'this_month' ? 'active' : '' }}">This Month</a>
    </div>
@endsection

@section('content')
<div class="orders-container">

    <!-- Flash Alerts -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-alert type="danger" :message="session('error')" />
    @endif

    <!-- 4 Core KPI Stat Cards Grid (Exact Dashboard Page Style, Size, and Font) -->
    <div class="dash-stat-row">
        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Orders</span>
                <div class="stat-value" id="kpi_total_orders">{{ number_format($totalOrdersCount) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Live Sync
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box yellow">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Pending / Baking</span>
                <div class="stat-value" id="kpi_pending_orders">{{ number_format($pendingCount + $preparingCount) }}</div>
                <span class="stat-trend {{ ($pendingCount + $preparingCount) > 0 ? 'warning' : 'up' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ ($pendingCount + $preparingCount) > 0 ? 'In Kitchen' : 'All clear' }}
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box teal">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Ready for Pickup</span>
                <div class="stat-value" id="kpi_ready_orders">{{ number_format($readyCount) }}</div>
                <span class="stat-trend {{ $readyCount > 0 ? 'up' : 'neutral' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ $readyCount > 0 ? 'Ready to Serve' : 'Queue Empty' }}
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box green">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Completed Sales</span>
                <div class="stat-value" id="kpi_total_revenue">{{ $currency }}{{ number_format($totalRevenue, 2) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Live Sales
                </span>
            </div>
        </div>
    </div>

    <!-- Header Actions -->
    <div class="orders-header" style="justify-content: flex-end;">
        <!-- Action Buttons -->
        <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            @if(Auth::user() && Auth::user()->isAdmin())
                <button type="button" class="btn btn-purge-orders" id="btn_open_purge_modal" onclick="openPurgeModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    <span>Clear All Orders</span>
                </button>
            @endif
        </div>
    </div>


    <!-- Orders Table Card (Full Screen with internal scrolling) -->
    <div class="orders-card">
        <div class="orders-table-wrapper" id="orders_table_wrapper">
            <table class="orders-table">
                <thead>
                    <tr>
                        <th style="width: 10%;">Order #</th>
                        <th style="width: 20%;">Customer</th>
                        <th style="width: 26%;">Items</th>
                        <th style="width: 18%;">Total & Payment</th>
                        <th style="width: 14%;">Status</th>
                        <th style="width: 12%;" class="col-date">Date</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($orders as $order)
                    @php
                        $normStatus = $order->normalized_status;
                        $statusBadge = match($normStatus) {
                            'completed' => '<span class="status-badge status-completed"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg> Completed</span>',
                            'preparing' => '<span class="status-badge status-preparing"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v4"/><path d="m4.93 4.93 2.83 2.83"/><path d="M2 12h4"/><path d="m4.93 19.07 2.83-2.83"/></svg> Preparing</span>',
                            'ready_for_pickup' => '<span class="status-badge status-ready"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6 9 17l-5-5"/></svg> Ready</span>',
                            'cancelled' => '<span class="status-badge status-cancelled"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Cancelled</span>',
                            default => '<span class="status-badge status-pending"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg> Pending</span>',
                        };

                        $paymentMethodLabel = match($order->payment_method) {
                            'qr_code' => 'KHQR',
                            'card' => 'Card',
                            default => 'Cash',
                        };

                        $orderModalData = [
                            'id' => $order->id,
                            'order_number' => '#' . $order->id,
                            'order_code' => $order->order_number,
                            'customer_name' => $order->customer_name ?: 'Walk-in Customer',
                            'customer_phone' => $order->customer ? $order->customer->phone : null,
                            'payment_method' => $paymentMethodLabel,
                            'payment_status' => ucfirst($order->payment_status ?? 'unpaid'),
                            'status_html' => $statusBadge,
                            'date' => $order->created_at ? $order->created_at->format('M d, Y • h:i A') : 'N/A',
                            'is_custom' => (bool)$order->is_custom,
                            'special_instructions' => $order->special_instructions,
                            'subtotal' => $currency . number_format($order->subtotal, 2),
                            'discount' => (float)$order->discount,
                            'discount_formatted' => $currency . number_format($order->discount, 2),
                            'tax' => $currency . number_format($order->tax, 2),
                            'total' => $currency . number_format($order->total, 2),
                            'items_count' => $order->items->count(),
                            'items' => $order->items->map(function($item) use ($currency) {
                                return [
                                    'name' => $item->product ? $item->product->name : 'Bakery Item',
                                    'quantity' => $item->quantity,
                                    'price' => $currency . number_format($item->price, 2),
                                    'subtotal' => $currency . number_format($item->subtotal, 2),
                                ];
                            })->values()->toArray(),
                        ];
                    @endphp
                    <tr id="order_row_{{ $order->id }}" data-status="{{ $normStatus }}" data-order-code="{{ $order->order_number }}" class="order-clickable-row" onclick="openOrderDetailModal(this)" data-order='@json($orderModalData)'>
                        <td>
                            <span class="order-number-pill">
                                #{{ $order->id }}
                            </span>
                            @if($order->is_custom)
                                <div style="margin-top: 2px;">
                                    <span class="type-pill type-custom" style="font-size: 0.68rem; padding: 2px 7px;">🎂 Custom</span>
                                </div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 700; color: var(--text-title); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $order->customer_name }}">
                                {{ $order->customer_name }}
                            </div>
                            @if($order->customer && $order->customer->phone)
                                <div style="font-size: 0.75rem; color: #64748b;">{{ $order->customer->phone }}</div>
                            @endif
                        </td>
                        <td>
                            <div style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $order->items_summary }}">
                                {{ $order->items_summary }}
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b;">
                                {{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}
                            </div>
                        </td>
                        <td>
                            <div style="font-weight: 800; font-size: 0.925rem; color: var(--text-title);">
                                {{ $currency }}{{ number_format($order->total, 2) }}
                            </div>
                            <div style="margin-top: 2px;">
                                <span class="payment-badge {{ $order->payment_status === 'paid' ? 'paid' : 'unpaid' }}" style="font-size: 0.68rem; padding: 2px 6px;">
                                    {{ $paymentMethodLabel }} • {{ ucfirst($order->payment_status ?? 'unpaid') }}
                                </span>
                            </div>
                        </td>
                        <td>
                            {!! $statusBadge !!}
                        </td>
                        <td class="col-date" style="font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                            <div>{{ $order->created_at ? $order->created_at->format('M d, Y') : 'N/A' }}</div>
                            <div style="font-size: 0.725rem;">{{ $order->created_at ? $order->created_at->format('h:i A') : '' }}</div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted, #9ca3af); padding: 3rem;">
                            <div style="display: flex; flex-direction: column; align-items: center; gap: 0.75rem;">
                                <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#cbd5e1" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                                <span style="font-weight: 700; font-size: 0.95rem;">No orders match your selected filters.</span>
                                <span style="font-size: 0.825rem; color: #94a3b8;">Try clearing filters to display orders.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>


    </div>

    <!-- Simple Order Detail Modal Card ("Cart") -->
    <div id="orderDetailModal" class="order-detail-modal-backdrop" onclick="if(event.target === this) closeOrderDetailModal()">
        <div class="order-detail-modal-card">
            <!-- Modal Header -->
            <div class="order-detail-modal-header">
                <div class="order-detail-modal-header-left">
                    <div class="order-detail-modal-badge" id="modal_order_number">#1</div>
                    <div>
                        <h3 class="order-detail-modal-title">Order Details</h3>
                        <p class="order-detail-modal-date" id="modal_order_date">-</p>
                    </div>
                </div>
                <div id="modal_order_status_badge">
                    <!-- Status pill injected here -->
                </div>
            </div>

            <!-- Modal Body -->
            <div class="order-detail-modal-body">
                <!-- Customer & Payment Quick Info -->
                <div class="order-detail-info-grid">
                    <div class="order-detail-info-item">
                        <span class="info-label">Customer</span>
                        <span class="info-value" id="modal_customer_name">Walk-in Customer</span>
                        <span class="info-sub" id="modal_customer_phone" style="display: none;"></span>
                    </div>
                    <div class="order-detail-info-item">
                        <span class="info-label">Payment</span>
                        <span class="info-value" id="modal_payment_method">Cash</span>
                        <span class="info-sub" id="modal_payment_status">Paid</span>
                    </div>
                </div>

                <!-- Custom cake notes if any -->
                <div id="modal_custom_note_box" class="order-detail-note-box" style="display: none;">
                    <span class="note-tag">🎂 Special Instructions:</span>
                    <span id="modal_custom_note_text" class="note-text"></span>
                </div>

                <!-- Ordered Items Section -->
                <div class="order-detail-items-section">
                    <div class="items-header">
                        <span>Ordered Items</span>
                        <span id="modal_items_count" class="items-count-pill">0 items</span>
                    </div>
                    <div class="items-list-container" id="modal_items_list">
                        <!-- Dynamic item rows inserted here -->
                    </div>
                </div>

                <!-- Financial Summary Box -->
                <div class="order-detail-summary-box">
                    <div class="summary-line" id="modal_subtotal_row">
                        <span>Subtotal</span>
                        <span id="modal_subtotal_val">$0.00</span>
                    </div>
                    <div class="summary-line discount" id="modal_discount_row" style="display: none;">
                        <span>Discount</span>
                        <span id="modal_discount_val">-$0.00</span>
                    </div>
                    <div class="summary-line" id="modal_tax_row">
                        <span>Tax</span>
                        <span id="modal_tax_val">+$0.00</span>
                    </div>
                    <div class="summary-divider"></div>
                    <div class="summary-line total">
                        <span>Total Amount</span>
                        <span id="modal_total_val">$0.00</span>
                    </div>
                </div>

                <!-- Actions: Print Receipt & Close Buttons -->
                <div class="order-detail-modal-actions" style="display: flex; gap: 0.75rem; justify-content: flex-end; align-items: center;">
                    <button type="button" id="btn_print_order_receipt" onclick="printOrderReceipt()" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.15rem; font-size: 0.875rem; font-weight: 600; border-radius: 8px; cursor: pointer;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        <span>Print Receipt</span>
                    </button>
                    <button type="button" onclick="closeOrderDetailModal()" class="btn-order-detail-close">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Isolated Thermal Receipt Print Container (Used exclusively when printing an order receipt) -->
    <div id="thermal_slip_print_area" style="display: none;">
        <div style="font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 1.35; color: #000; width: 72mm; margin: 0 auto; padding: 12px 6px;">
            <div style="text-align: center; margin-bottom: 8px;">
                <div style="font-size: 15px; font-weight: bold; text-transform: uppercase;" id="thermal_bakery_name">{{ \App\Models\Setting::get('shop_name', 'Moon Cake Bakery') }}</div>
                <div style="font-size: 10.5px;" id="thermal_bakery_address">{{ \App\Models\Setting::get('shop_address', 'Phnom Penh, Cambodia') }}</div>
                <div style="font-size: 10.5px;" id="thermal_bakery_phone">Tel: {{ \App\Models\Setting::get('shop_phone', '+855 12 345 678') }}</div>
                <div style="font-size: 10.5px; margin-top: 2px; font-style: italic;">Official Customer Receipt</div>
            </div>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Order: <strong id="thermal_order_num">#0</strong></span>
                <span id="thermal_order_code">ORD-0000</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Date: <span id="thermal_order_date"></span></span>
            </div>
            <div style="font-size: 11px;">
                <span>Customer: <strong id="thermal_customer_name">Walk-in Customer</strong></span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Payment: <strong id="thermal_payment_method">CASH</strong></span>
                <span>Status: <strong id="thermal_payment_status">PAID</strong></span>
            </div>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
            <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                <thead>
                    <tr style="border-bottom: 1px dashed #000;">
                        <th style="text-align: left; padding: 2px 0;">Item</th>
                        <th style="text-align: center; padding: 2px 0;">Qty</th>
                        <th style="text-align: right; padding: 2px 0;">Price</th>
                        <th style="text-align: right; padding: 2px 0;">Total</th>
                    </tr>
                </thead>
                <tbody id="thermal_items_body">
                </tbody>
            </table>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Subtotal:</span>
                <span id="thermal_subtotal">$0.00</span>
            </div>
            <div id="thermal_discount_row" style="display: none; justify-content: space-between; font-size: 11px;">
                <span>Discount:</span>
                <span id="thermal_discount">-$0.00</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-size: 11px;">
                <span>Tax:</span>
                <span id="thermal_tax">+$0.00</span>
            </div>
            <div style="border-top: 1px dashed #000; margin: 4px 0;"></div>
            <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: bold;">
                <span>Total Amount:</span>
                <span id="thermal_total">$0.00</span>
            </div>
            <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
            <div style="text-align: center; font-size: 10.5px; margin-top: 8px;">
                Thank you for your purchase!<br>Please visit us again.
            </div>
        </div>
    </div>

    <script>
        let currentModalOrder = null;

        function printOrderReceipt() {
            if (!currentModalOrder) return;

            document.getElementById('thermal_order_num').textContent = currentModalOrder.order_number || `#${currentModalOrder.id}`;
            document.getElementById('thermal_order_code').textContent = currentModalOrder.order_code || '';
            document.getElementById('thermal_order_date').textContent = currentModalOrder.date || '';
            document.getElementById('thermal_customer_name').textContent = currentModalOrder.customer_name || 'Walk-in Customer';
            document.getElementById('thermal_payment_method').textContent = (currentModalOrder.payment_method || 'CASH').toUpperCase();
            document.getElementById('thermal_payment_status').textContent = (currentModalOrder.payment_status || 'PAID').toUpperCase();

            const tbody = document.getElementById('thermal_items_body');
            tbody.innerHTML = '';
            if (currentModalOrder.items && currentModalOrder.items.length > 0) {
                currentModalOrder.items.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td style="text-align: left; padding: 2px 0;">${item.name}</td>
                        <td style="text-align: center; padding: 2px 0;">${item.quantity}</td>
                        <td style="text-align: right; padding: 2px 0;">${item.price}</td>
                        <td style="text-align: right; padding: 2px 0;">${item.subtotal}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById('thermal_subtotal').textContent = currentModalOrder.subtotal || '$0.00';
            const discRow = document.getElementById('thermal_discount_row');
            if (currentModalOrder.discount && currentModalOrder.discount > 0) {
                document.getElementById('thermal_discount').textContent = `-${currentModalOrder.discount_formatted}`;
                discRow.style.display = 'flex';
            } else {
                discRow.style.display = 'none';
            }
            document.getElementById('thermal_tax').textContent = currentModalOrder.tax || '$0.00';
            document.getElementById('thermal_total').textContent = currentModalOrder.total || '$0.00';

            const printArea = document.getElementById('thermal_slip_print_area');
            if (printArea) {
                printArea.style.display = 'block';
                window.print();
                printArea.style.display = 'none';
            }
        }

        function openOrderDetailModal(rowEl) {
            const rawData = rowEl.getAttribute('data-order');
            if (!rawData) return;
            try {
                const order = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
                currentModalOrder = order;
                
                document.getElementById('modal_order_number').textContent = order.order_number || `#${order.id}`;
                document.getElementById('modal_order_date').textContent = order.date || '';
                document.getElementById('modal_order_status_badge').innerHTML = order.status_html || `<span class="status-badge status-completed">${order.status || 'Completed'}</span>`;
                document.getElementById('modal_customer_name').textContent = order.customer_name || 'Walk-in Customer';
                
                const phoneEl = document.getElementById('modal_customer_phone');
                if (order.customer_phone) {
                    phoneEl.textContent = order.customer_phone;
                    phoneEl.style.display = 'block';
                } else {
                    phoneEl.style.display = 'none';
                }

                document.getElementById('modal_payment_method').textContent = order.payment_method || 'Cash';
                document.getElementById('modal_payment_status').textContent = `• ${order.payment_status || 'Paid'}`;

                const noteBox = document.getElementById('modal_custom_note_box');
                const noteText = document.getElementById('modal_custom_note_text');
                if (order.special_instructions) {
                    noteText.textContent = order.special_instructions;
                    noteBox.style.display = 'flex';
                } else {
                    noteBox.style.display = 'none';
                }

                const itemsCountEl = document.getElementById('modal_items_count');
                const count = order.items_count || (order.items ? order.items.length : 0);
                itemsCountEl.textContent = `${count} ${count === 1 ? 'item' : 'items'}`;

                const itemsListContainer = document.getElementById('modal_items_list');
                itemsListContainer.innerHTML = '';
                if (order.items && order.items.length > 0) {
                    order.items.forEach(item => {
                        const row = document.createElement('div');
                        row.className = 'order-item-row';
                        row.innerHTML = `
                            <div class="order-item-name-qty">
                                <span class="order-item-qty">${item.quantity}x</span>
                                <span class="order-item-name">${item.name}</span>
                            </div>
                            <div class="order-item-pricing">
                                <div class="order-item-subtotal">${item.subtotal}</div>
                                <div class="order-item-unit-price">${item.price} each</div>
                            </div>
                        `;
                        itemsListContainer.appendChild(row);
                    });
                } else {
                    itemsListContainer.innerHTML = '<div style="padding: 0.75rem; color: #94a3b8; font-size: 0.85rem; text-align: center;">No item details recorded.</div>';
                }

                document.getElementById('modal_subtotal_val').textContent = order.subtotal || '$0.00';
                
                const discountRow = document.getElementById('modal_discount_row');
                if (order.discount && order.discount > 0) {
                    document.getElementById('modal_discount_val').textContent = `-${order.discount_formatted}`;
                    discountRow.style.display = 'flex';
                } else {
                    discountRow.style.display = 'none';
                }

                document.getElementById('modal_tax_val').textContent = `+${order.tax || '$0.00'}`;
                document.getElementById('modal_total_val').textContent = order.total || '$0.00';

                const modal = document.getElementById('orderDetailModal');
                if (modal) {
                    if (modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                    modal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } catch (err) {
                console.error('Failed to parse order data', err);
            }
        }

        function closeOrderDetailModal() {
            const modal = document.getElementById('orderDetailModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeOrderDetailModal();
                if (typeof closePurgeModal === 'function') closePurgeModal();
            }
        });
    </script>

    <!-- Easy 1-Click Delete / Clear Orders Modal (Admin Only) -->
    @if(Auth::user() && Auth::user()->isAdmin())
    <div id="purgeModal" class="purge-modal-backdrop" onclick="if(event.target === this) closePurgeModal()">
        <div class="purge-modal-card">
            <!-- Modal Header -->
            <div class="purge-modal-header">
                <div class="purge-modal-header-left">
                    <div class="purge-modal-icon-badge">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                    </div>
                    <div>
                        <h3 class="purge-modal-title">Delete Orders</h3>
                        <p class="purge-modal-subtitle">Select what you want to delete</p>
                    </div>
                </div>
            </div>

            <!-- Modal Form -->
            <form action="{{ route('admin.orders.purgeOld') }}" method="POST" id="purgeForm" class="purge-modal-body">
                @csrf
                <!-- Hidden inputs for seamless backend compatibility -->
                <input type="hidden" name="scope" id="form_purge_scope" value="all">
                <input type="hidden" name="days" id="form_purge_days" value="30">
                <input type="hidden" name="status" id="form_purge_status" value="all">
                <input type="hidden" name="restore_stock" value="1">
                <input type="hidden" name="backup" value="1">
                <input type="hidden" name="confirm_phrase" id="confirm_phrase_input" value="CONFIRM">

                <!-- Fast 1-Click Scope Buttons -->
                <label class="purge-section-label">Select Delete Option</label>
                <div class="purge-scope-grid">
                    <button type="button" class="scope-quick-btn active" id="btn_scope_all" onclick="setScopeMode('all', 0, 'all', this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/></svg>
                        <span>All Orders</span>
                    </button>

                    <button type="button" class="scope-quick-btn" id="btn_scope_30" onclick="setScopeMode('days', 30, 'all', this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Older than 30d</span>
                    </button>

                    <button type="button" class="scope-quick-btn" id="btn_scope_90" onclick="setScopeMode('days', 90, 'all', this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <span>Older than 90d</span>
                    </button>

                    <button type="button" class="scope-quick-btn" id="btn_scope_cancelled" onclick="setScopeMode('all', 0, 'cancelled', this)">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span>Cancelled Only</span>
                    </button>
                </div>

                <!-- Live Impact Preview Box -->
                <div id="purge_preview_box" class="purge-preview-box">
                    <div class="purge-preview-row">
                        <span class="purge-preview-label">Matching orders to delete:</span>
                        <strong id="preview_orders_count" class="purge-preview-count">Loading...</strong>
                    </div>
                    <div class="purge-preview-sub">
                        <span>Includes linked items and sales records</span>
                        <span id="preview_sub_counts">...</span>
                    </div>
                </div>

                <!-- Action Buttons: Cancel vs 1-Click Delete -->
                <div class="purge-modal-actions">
                    <button type="button" onclick="closePurgeModal()" class="btn btn-purge-cancel">
                        Cancel
                    </button>
                    <button type="submit" id="btn_submit_purge" class="btn btn-purge-submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                        <span>Delete Orders Now</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setScopeMode(scope, days, status, btnEl) {
            document.querySelectorAll('.scope-quick-btn').forEach(btn => btn.classList.remove('active'));
            if (btnEl) btnEl.classList.add('active');

            const scopeInput = document.getElementById('form_purge_scope');
            const daysInput = document.getElementById('form_purge_days');
            const statusInput = document.getElementById('form_purge_status');

            if (scopeInput) scopeInput.value = scope;
            if (daysInput) daysInput.value = days;
            if (statusInput) statusInput.value = status;

            fetchPurgePreview();
        }

        function openPurgeModal() {
            const modal = document.getElementById('purgeModal');
            if (modal) {
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
                modal.style.display = 'flex';
                fetchPurgePreview();
            }
        }

        function closePurgeModal() {
            const modal = document.getElementById('purgeModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        let previewDebounceTimer = null;
        function fetchPurgePreview() {
            clearTimeout(previewDebounceTimer);
            const countEl = document.getElementById('preview_orders_count');
            const subCountEl = document.getElementById('preview_sub_counts');
            if (countEl) countEl.innerText = 'Calculating...';

            previewDebounceTimer = setTimeout(() => {
                const scope = document.getElementById('form_purge_scope')?.value || 'all';
                const days = document.getElementById('form_purge_days')?.value || '30';
                const status = document.getElementById('form_purge_status')?.value || 'all';

                const url = `{{ route('admin.orders.purgePreview') }}?scope=${encodeURIComponent(scope)}&days=${encodeURIComponent(days)}&status=${encodeURIComponent(status)}`;

                fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        if (countEl) countEl.innerText = `${data.orders_count} orders`;
                        if (subCountEl) subCountEl.innerText = `${data.items_count} items, ${data.sales_count} sales, ${data.payments_count} payments`;
                        const submitBtn = document.getElementById('btn_submit_purge');
                        if (submitBtn) {
                            if (data.orders_count === 0) {
                                submitBtn.disabled = true;
                                submitBtn.style.opacity = '0.6';
                                submitBtn.style.cursor = 'not-allowed';
                            } else {
                                submitBtn.disabled = false;
                                submitBtn.style.opacity = '1';
                                submitBtn.style.cursor = 'pointer';
                            }
                        }
                    }
                })
                .catch(err => {
                    console.error('Failed to preview purge count', err);
                    if (countEl) countEl.innerText = '0 orders';
                });
            }, 150);
        }
    </script>
    @endif
</div>
@endsection
