@extends('layouts.app')

@section('title', 'Report Management - Bakery Management System')
@section('body_class', 'is-catalog-page is-reports-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}?v={{ @filemtime(public_path('css/reports.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush


@section('header_toolbar')
    <form method="GET" action="{{ route('admin.reports') }}" class="header-filter-form dash-filter-bar" style="background: transparent; border: none; padding: 0; margin: 0; box-shadow: none;">
        <input type="hidden" name="type" value="{{ $type }}">
        <div class="period-pill-group">
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => 'today'])) }}" class="period-pill {{ $period === 'today' ? 'active' : '' }}" data-lang-key="today">{{ __('messages.today') }}</a>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => 'yesterday'])) }}" class="period-pill {{ $period === 'yesterday' ? 'active' : '' }}" data-lang-key="yesterday">{{ __('messages.yesterday') }}</a>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => '7days'])) }}" class="period-pill {{ $period === '7days' ? 'active' : '' }}" data-lang-key="last_7_days">{{ __('messages.last_7_days') }}</a>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => '30days'])) }}" class="period-pill {{ $period === '30days' ? 'active' : '' }}" data-lang-key="last_30_days">{{ __('messages.last_30_days') }}</a>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => 'this_month'])) }}" class="period-pill {{ $period === 'this_month' ? 'active' : '' }}" data-lang-key="this_month">{{ __('messages.this_month') }}</a>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => $type, 'period' => 'last_month'])) }}" class="period-pill {{ $period === 'last_month' ? 'active' : '' }}" data-lang-key="last_month">{{ __('messages.last_month') }}</a>
        </div>
    </form>
@endsection

@section('content')
<!-- Print Only Official Header -->
<div class="print-only-header">
    <div class="print-bakery-name">{{ $bakeryName }}</div>
    <div class="print-report-title">{{ strtoupper(str_replace('-', ' ', $type)) }} AUDIT REPORT</div>
    <div class="print-meta-row">
        <span>Period: {{ $startDate ? $startDate->format('M d, Y') : 'Start' }} &mdash; {{ $endDate ? $endDate->format('M d, Y') : 'End' }}</span>
        <span>Generated: {{ now()->format('M d, Y H:i:s') }}</span>
        <span>Status: Official Transactional Record</span>
    </div>
</div>

<h1 class="sr-only">Reports &amp; Analytics</h1>

<!-- Segmented Grouped Navigation: Overview | Sales | Inventory | Production | Customers -->
@php
    $tabParams = request()->except(['page', 'type']);
    $activeGroup = match($type) {
        'overview' => 'overview',
        'sales', 'revenue', 'orders' => 'sales',
        'products', 'inventory', 'low-stock', 'purchases' => 'inventory',
        'production' => 'production',
        'customers' => 'customers',
        'profit-loss' => 'overview',
        default => 'overview',
    };
@endphp

<div class="reports-page-container">
    <div class="reports-fixed-header-section">
        <!-- Sticky Report Controls: Analytical Navigation Tabs & Export Actions (Single Clean Row) -->
        <div class="reports-sticky-toolbar-wrapper">
    <div class="reports-toolbar-row">
        <div class="reports-nav-container">
            <nav class="reports-main-nav report-nav-tabs" aria-label="Reports Navigation">
            <!-- 1. Overview -->
            <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'overview'])) }}" 
               class="reports-nav-btn report-tab-btn {{ $activeGroup === 'overview' ? 'active' : '' }}" 
               id="nav_overview">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                <span>Overview</span>
            </a>

            <!-- 2. Sales (dropdown) -->
            <button type="button"
               class="reports-nav-btn report-tab-btn has-dropdown {{ $activeGroup === 'sales' ? 'active' : '' }}"
               id="nav_sales" aria-haspopup="true" aria-expanded="false" aria-controls="sales_report_menu">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
                <span>Sales</span>
                <svg class="report-nav-caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <!-- 3. Inventory (dropdown) -->
            <button type="button"
               class="reports-nav-btn report-tab-btn has-dropdown {{ $activeGroup === 'inventory' ? 'active' : '' }}"
               id="nav_inventory" aria-haspopup="true" aria-expanded="false" aria-controls="inventory_report_menu">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                <span>Inventory</span>
                <svg class="report-nav-caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
            </button>

            <!-- 4. Production -->
            <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'production'])) }}" 
               class="reports-nav-btn report-tab-btn {{ $activeGroup === 'production' ? 'active' : '' }}" 
               id="tab_production">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                <span>Production</span>
            </a>

            <!-- 5. Customers -->
            <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'customers'])) }}" 
               class="reports-nav-btn report-tab-btn {{ $activeGroup === 'customers' ? 'active' : '' }}" 
               id="tab_customers">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Customers</span>
            </a>
        </nav>
            <div class="report-dropdown-menu" id="sales_report_menu" role="menu" hidden>
                <div class="report-dropdown-label">Sales Reports</div>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'sales'])) }}" 
                       class="report-dropdown-item {{ $type === 'sales' ? 'active' : '' }}" 
                       id="tab_sales">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
                        <span>Sales Report</span>
                    </a>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'revenue'])) }}" 
                       class="report-dropdown-item {{ $type === 'revenue' ? 'active' : '' }}" 
                       id="tab_revenue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        <span>Revenue Report</span>
                    </a>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'orders'])) }}" 
                       class="report-dropdown-item {{ $type === 'orders' ? 'active' : '' }}" 
                       id="tab_orders">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span>Order Report</span>
                    </a>
            </div>
            <div class="report-dropdown-menu" id="inventory_report_menu" role="menu" hidden>
                <div class="report-dropdown-label">Inventory Reports</div>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'products'])) }}" 
                       class="report-dropdown-item {{ $type === 'products' ? 'active' : '' }}" 
                       id="tab_products">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        <span>Product Report</span>
                    </a>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'inventory'])) }}" 
                       class="report-dropdown-item {{ $type === 'inventory' ? 'active' : '' }}" 
                       id="tab_inventory">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                        <span>Inventory Report</span>
                    </a>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'low-stock'])) }}" 
                       class="report-dropdown-item {{ $type === 'low-stock' ? 'active' : '' }}" 
                       id="tab_low_stock">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 1 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Low Stock Report</span>
                    </a>
                <a href="{{ route('admin.reports', array_merge($tabParams, ['type' => 'purchases'])) }}" 
                       class="report-dropdown-item {{ $type === 'purchases' ? 'active' : '' }}" 
                       id="tab_purchases">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                        <span>Supplier Report</span>
                    </a>
            </div>
    </div>

    <div class="reports-export-actions">
        <button type="button" class="btn btn-secondary sticky-add-product-btn m-icon-btn" id="btn_print_report" aria-label="Print" onclick="window.print();" title="Print Layout">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
            <span>Print</span>
        </button>
        <a href="{{ route('admin.reports.export.pdf', request()->all()) }}" class="btn btn-secondary sticky-add-product-btn m-icon-btn" id="btn_export_pdf" aria-label="Export PDF" title="Export PDF">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span>PDF</span>
        </a>
        <a href="{{ route('admin.reports.export.excel', request()->all()) }}" class="btn btn-secondary sticky-add-product-btn m-icon-btn" id="btn_export_excel" aria-label="Export Excel" title="Export Excel / CSV">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            <span>Excel</span>
        </a>
    </div>
</div>



    </div>

    <!-- Scrollable Report Body Section: Only this scrolls up/down -->
    <div class="reports-scrollable-body" id="reports_scrollable_body">
<!-- =========================================================================
     DYNAMIC KPI STAT CARDS & OVERVIEW SUMMARY
     ========================================================================= -->
@if($type === 'overview')
    @php
        $salesSum = $reportData['sales'];
        $revSum = $reportData['revenue'];
        $orderSum = $reportData['orders'];
        $plSum = $reportData['profitLoss'];
        $lowStockSum = $reportData['lowStock'];
    @endphp

    <div class="reports-summary-grid">
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_sales">{{ __('messages.total_sales') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($salesSum['totalSalesRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">{{ number_format($salesSum['totalSalesCount']) }} completed sales</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_revenue">{{ __('messages.total_revenue') }}</div>
            <div class="report-stat-val val-default">{{ $currency }}{{ number_format($revSum['totalRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Net store revenue</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Total Orders Recorded</div>
            <div class="report-stat-val val-amber">{{ number_format($orderSum['totalOrders']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Gross: {{ $currency }}{{ number_format($orderSum['totalOrderAmount'], 2) }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Estimated Gross Profit</div>
            <div class="report-stat-val {{ $plSum['grossProfit'] >= 0 ? 'val-green' : 'val-blue' }}" style="{{ $plSum['grossProfit'] < 0 ? 'color: #dc2626;' : '' }}">{{ $currency }}{{ number_format($plSum['grossProfit'], 2) }}</div>
            <span style="font-size: 0.75rem; color: {{ $plSum['grossProfit'] >= 0 ? '#16a34a' : '#dc2626' }}; font-weight: 700;">Margin: {{ $plSum['profitMargin'] }}%</span>
        </div>
    </div>
</div>

    <div class="overview-summary-grid">
        <!-- 1. Sales Summary Card -->
        <div class="overview-kpi-card" id="overview_sales_card">
            <div>
                <div class="overview-card-header">
                    <div class="overview-card-title-group">
                        <div class="overview-card-icon" style="background: rgba(16, 185, 129, 0.12); color: #059669;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
                        </div>
                        <div>
                            <h3 class="overview-card-title">Sales Performance</h3>
                            <span style="font-size: 0.725rem; color: #71717a;">Completed retail sales</span>
                        </div>
                    </div>
                    <span class="overview-card-badge" style="background: rgba(16, 185, 129, 0.12); color: #059669;">Sales</span>
                </div>
                <div class="overview-metrics-list">
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Total Sales Revenue</span>
                        <span class="overview-metric-val green">{{ $currency }}{{ number_format($salesSum['totalSalesRevenue'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Completed Transactions</span>
                        <span class="overview-metric-val">{{ number_format($salesSum['totalSalesCount']) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Average Sale Value</span>
                        <span class="overview-metric-val amber">{{ $currency }}{{ number_format($salesSum['avgSaleValue'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Net Sales</span>
                        <span class="overview-metric-val">{{ $currency }}{{ number_format($salesSum['netSales'], 2) }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => 'sales'])) }}" class="overview-card-action">
                <span>View Full Sales Report</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- 2. Revenue Summary Card -->
        <div class="overview-kpi-card" id="overview_revenue_card">
            <div>
                <div class="overview-card-header">
                    <div class="overview-card-title-group">
                        <div class="overview-card-icon" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        </div>
                        <div>
                            <h3 class="overview-card-title">Revenue Stream</h3>
                            <span style="font-size: 0.725rem; color: #71717a;">Cash & digital payments</span>
                        </div>
                    </div>
                    <span class="overview-card-badge" style="background: rgba(37, 99, 235, 0.12); color: #2563eb;">Revenue</span>
                </div>
                <div class="overview-metrics-list">
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Total Period Revenue</span>
                        <span class="overview-metric-val green">{{ $currency }}{{ number_format($revSum['totalRevenue'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Physical Cash POS</span>
                        <span class="overview-metric-val">{{ $currency }}{{ number_format($revSum['cashRevenue'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Card & KHQR Digital</span>
                        <span class="overview-metric-val blue">{{ $currency }}{{ number_format($revSum['cardRevenue'] + $revSum['qrRevenue'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Average Order Value</span>
                        <span class="overview-metric-val amber">{{ $currency }}{{ number_format($revSum['avgOrderValue'], 2) }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => 'revenue'])) }}" class="overview-card-action">
                <span>View Revenue Breakdown</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- 3. Order Summary Card -->
        <div class="overview-kpi-card" id="overview_orders_card">
            <div>
                <div class="overview-card-header">
                    <div class="overview-card-title-group">
                        <div class="overview-card-icon" style="background: rgba(147, 51, 234, 0.12); color: #9333ea;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        </div>
                        <div>
                            <h3 class="overview-card-title">Order Status</h3>
                            <span style="font-size: 0.725rem; color: #71717a;">Fulfillment & execution</span>
                        </div>
                    </div>
                    <span class="overview-card-badge" style="background: rgba(147, 51, 234, 0.12); color: #9333ea;">Orders</span>
                </div>
                <div class="overview-metrics-list">
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Total Orders Recorded</span>
                        <span class="overview-metric-val">{{ number_format($orderSum['totalOrders']) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Completed Orders</span>
                        <span class="overview-metric-val green">{{ number_format($orderSum['completedOrders']) }} ({{ $orderSum['completionRate'] }}%)</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Baking / In Progress</span>
                        <span class="overview-metric-val amber">{{ number_format($orderSum['pendingOrders'] + $orderSum['preparingOrders']) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Cancelled Orders</span>
                        <span class="overview-metric-val red">{{ number_format($orderSum['cancelledOrders']) }} ({{ $orderSum['cancellationRate'] }}%)</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => 'orders'])) }}" class="overview-card-action">
                <span>View Order Status Details</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- 4. Profit & Loss Summary Card -->
        <div class="overview-kpi-card" id="overview_pl_card">
            <div>
                <div class="overview-card-header">
                    <div class="overview-card-title-group">
                        <div class="overview-card-icon" style="background: rgba(217, 119, 6, 0.12); color: #d97706;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        </div>
                        <div>
                            <h3 class="overview-card-title">Profit & Loss</h3>
                            <span style="font-size: 0.725rem; color: #71717a;">Financial margins & COGS</span>
                        </div>
                    </div>
                    <span class="overview-card-badge" style="background: rgba(217, 119, 6, 0.12); color: #d97706;">Financial</span>
                </div>
                <div class="overview-metrics-list">
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Gross Margin</span>
                        <span class="overview-metric-val green">{{ $plSum['profitMargin'] }}%</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Gross Profit</span>
                        <span class="overview-metric-val green">{{ $currency }}{{ number_format($plSum['grossProfit'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Product Cost (COGS)</span>
                        <span class="overview-metric-val">{{ $currency }}{{ number_format($plSum['productCogs'], 2) }}</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Procurement Cash Outflow</span>
                        <span class="overview-metric-val blue">{{ $currency }}{{ number_format($plSum['procurementSpend'], 2) }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => 'profit-loss'])) }}" class="overview-card-action">
                <span>View Full Profit & Loss Audit</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>

        <!-- 5. Low-Stock Summary Card -->
        <div class="overview-kpi-card" id="overview_stock_card">
            <div>
                <div class="overview-card-header">
                    <div class="overview-card-title-group">
                        <div class="overview-card-icon" style="background: rgba(220, 38, 38, 0.12); color: #dc2626;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 1 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <div>
                            <h3 class="overview-card-title">Inventory Alerts</h3>
                            <span style="font-size: 0.725rem; color: #71717a;">Ingredient replenishment</span>
                        </div>
                    </div>
                    <span class="overview-card-badge" style="background: rgba(220, 38, 38, 0.12); color: #dc2626;">Stock</span>
                </div>
                <div class="overview-metrics-list">
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Requiring Reorder</span>
                        <span class="overview-metric-val amber">{{ number_format($lowStockSum['totalNeedingReorder']) }} items</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Out of Stock (Zero)</span>
                        <span class="overview-metric-val red">{{ number_format($lowStockSum['outOfStockCount']) }} items</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Low Stock (Critical)</span>
                        <span class="overview-metric-val">{{ number_format($lowStockSum['lowStockCount']) }} items</span>
                    </div>
                    <div class="overview-metric-row">
                        <span class="overview-metric-label">Estimated Restock Cost</span>
                        <span class="overview-metric-val green">{{ $currency }}{{ number_format($lowStockSum['estimatedRestockCost'], 2) }}</span>
                    </div>
                </div>
            </div>
            <a href="{{ route('admin.reports', array_merge(request()->except('page'), ['type' => 'low-stock'])) }}" class="overview-card-action">
                <span>View Low Stock Alert Details</span>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </div>
@else
<div class="reports-summary-grid">
    @if($type === 'sales')
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_sales">{{ __('messages.total_sales') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['totalSalesRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Net: {{ $currency }}{{ number_format($reportData['netSales'], 2) }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="number_of_sales">{{ __('messages.number_of_sales') }}</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalSalesCount']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Completed sales</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="average_sale_value">{{ __('messages.average_sale_value') }}</div>
            <div class="report-stat-val val-amber">{{ $currency }}{{ number_format($reportData['avgSaleValue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Per transaction</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_discount">{{ __('messages.total_discount') }}</div>
            <div class="report-stat-val val-blue">{{ $currency }}{{ number_format($reportData['totalDiscount'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Tax: {{ $currency }}{{ number_format($reportData['totalTax'], 2) }}</span>
        </div>
    @elseif($type === 'revenue')
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_revenue">{{ __('messages.total_revenue') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['totalRevenue'], 2) }}</div>
            @if($reportData['revenueGrowthPercent'] !== null)
                <span style="font-size: 0.75rem; font-weight: 700; color: {{ $reportData['revenueGrowthPercent'] >= 0 ? '#16a34a' : '#dc2626' }};">
                    {{ $reportData['revenueGrowthPercent'] >= 0 ? '+' : '' }}{{ $reportData['revenueGrowthPercent'] }}% vs prev period
                </span>
            @else
                <span style="font-size: 0.75rem; color: #71717a;">Selected timeframe</span>
            @endif
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="average_order_value">{{ __('messages.average_order_value') }}</div>
            <div class="report-stat-val val-amber">{{ $currency }}{{ number_format($reportData['avgOrderValue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">{{ number_format($reportData['totalOrders']) }} orders</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Store POS Cash</div>
            <div class="report-stat-val val-default">{{ $currency }}{{ number_format($reportData['cashRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">In-store cash sales</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Card & Digital KHQR</div>
            <div class="report-stat-val val-blue">{{ $currency }}{{ number_format($reportData['cardRevenue'] + $reportData['qrRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Electronic payments</span>
        </div>
    @elseif($type === 'orders')
        <div class="report-stat-card">
            <div class="report-stat-title">Total Orders Recorded</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalOrders']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Gross: {{ $currency }}{{ number_format($reportData['totalOrderAmount'], 2) }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Completed Orders</div>
            <div class="report-stat-val val-green">{{ number_format($reportData['completedOrders']) }}</div>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700;">Rate: {{ $reportData['completionRate'] }}%</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">In Progress / Baking</div>
            <div class="report-stat-val val-amber">{{ number_format($reportData['pendingOrders'] + $reportData['preparingOrders']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Pending / Baking</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Cancelled Orders</div>
            <div class="report-stat-val val-blue" style="color: #dc2626;">{{ number_format($reportData['cancelledOrders']) }}</div>
            <span style="font-size: 0.75rem; color: #dc2626; font-weight: 700;">Rate: {{ $reportData['cancellationRate'] }}%</span>
        </div>
    @elseif($type === 'products')
        <div class="report-stat-card">
            <div class="report-stat-title">Units Sold (Completed)</div>
            <div class="report-stat-val val-green">{{ number_format($reportData['totalUnitsSold']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Delivered bakery units</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Product Sales Revenue</div>
            <div class="report-stat-val val-default">{{ $currency }}{{ number_format($reportData['totalRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Across all categories</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Active Products Sold</div>
            <div class="report-stat-val val-amber">{{ number_format($reportData['totalDistinctProducts']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">With completed sales</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Top Product Leader</div>
            <div class="report-stat-val val-blue" style="font-size: 1.15rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                {{ count($reportData['top5']) > 0 ? $reportData['top5'][0]->product_name : 'No sales' }}
            </div>
            <span style="font-size: 0.75rem; color: #71717a;">{{ count($reportData['top5']) > 0 ? $reportData['top5'][0]->total_units_sold . ' units sold' : 'N/A' }}</span>
        </div>
    @elseif($type === 'inventory')
        <div class="report-stat-card">
            <div class="report-stat-title">Total Ingredients</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalIngredients']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Physical inventory items</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Total Inventory Value</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['totalInventoryValue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">SUM(quantity &times; unit cost)</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Low Stock Alert</div>
            <div class="report-stat-val val-amber">{{ number_format($reportData['lowStockCount']) }}</div>
            <span style="font-size: 0.75rem; color: #dc2626;">Out of stock: {{ number_format($reportData['outOfStockCount']) }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Expired / Expiring Soon</div>
            <div class="report-stat-val val-blue" style="color: #dc2626;">{{ number_format($reportData['expiredCount']) }} / {{ number_format($reportData['expiringSoonCount']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Expired / Next 7 days</span>
        </div>
    @elseif($type === 'low-stock')
        <div class="report-stat-card">
            <div class="report-stat-title">Items Requiring Reorder</div>
            <div class="report-stat-val val-amber">{{ number_format($reportData['totalNeedingReorder']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">At or below minimum level</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Out of Stock (Zero)</div>
            <div class="report-stat-val val-blue" style="color: #dc2626;">{{ number_format($reportData['outOfStockCount']) }}</div>
            <span style="font-size: 0.75rem; color: #dc2626; font-weight: 700;">Critical replenishment</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Low Stock Remaining</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['lowStockCount']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Above zero, below threshold</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="estimated_restock_cost">{{ __('messages.estimated_restock_cost') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['estimatedRestockCost'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">To reach minimum thresholds</span>
        </div>
    @elseif($type === 'production')
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_batches">{{ __('messages.total_batches') }}</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalBatches']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Production runs scheduled</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Units Produced</div>
            <div class="report-stat-val val-green">{{ number_format($reportData['totalUnitsProduced']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">From completed batches</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Completed Batches</div>
            <div class="report-stat-val val-amber">{{ number_format($reportData['completedBatches']) }}</div>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700;">Rate: {{ $reportData['completionRate'] }}%</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">In Progress / Kitchen</div>
            <div class="report-stat-val val-blue">{{ number_format($reportData['inProgressBatches'] + $reportData['scheduledBatches']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Cancelled: {{ number_format($reportData['cancelledBatches']) }}</span>
        </div>
    @elseif($type === 'customers')
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_customers">{{ __('messages.total_customers') }}</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalCustomers']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Total customer accounts</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">New in Selected Period</div>
            <div class="report-stat-val val-blue">+{{ number_format($reportData['newCustomersPeriod']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Customer acquisition</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_spent">{{ __('messages.total_spent') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['totalCustomerSpend'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Completed customer purchases</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Average Customer Spend</div>
            <div class="report-stat-val val-amber">{{ $currency }}{{ number_format($reportData['avgCustomerSpend'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Active purchasing customers</span>
        </div>
    @elseif($type === 'purchases')
        <div class="report-stat-card">
            <div class="report-stat-title">Active Suppliers</div>
            <div class="report-stat-val val-default">{{ number_format($reportData['totalSuppliers']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Procurement partners</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Purchase Orders</div>
            <div class="report-stat-val val-blue">{{ number_format($reportData['totalPos']) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Draft: {{ number_format($reportData['draftPos']) }} | Ordered: {{ number_format($reportData['orderedPos']) }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="total_procurement_spend">{{ __('messages.total_procurement_spend') }}</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['totalPurchaseSpend'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #16a34a; font-weight: 700;">Received POs only (Actual Spend)</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title">Average Received PO</div>
            <div class="report-stat-val val-amber">{{ $currency }}{{ number_format($reportData['avgPoValue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">{{ number_format($reportData['receivedPos']) }} received orders</span>
        </div>
    @elseif($type === 'profit-loss')
        <div class="report-stat-card">
            <div class="report-stat-title">Sales Revenue</div>
            <div class="report-stat-val val-green">{{ $currency }}{{ number_format($reportData['salesRevenue'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">From completed orders</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="cost_of_goods_sold">{{ __('messages.cost_of_goods_sold') }}</div>
            <div class="report-stat-val val-blue" style="color: #dc2626;">{{ $currency }}{{ number_format($reportData['productCogs'], 2) }}</div>
            <span style="font-size: 0.75rem; color: #71717a;">Product costs &times; units sold</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="gross_profit">{{ __('messages.gross_profit') }}</div>
            <div class="report-stat-val {{ $reportData['grossProfit'] >= 0 ? 'val-green' : 'val-blue' }}" style="{{ $reportData['grossProfit'] < 0 ? 'color: #dc2626;' : '' }}">
                {{ $currency }}{{ number_format($reportData['grossProfit'], 2) }}
            </div>
            <span style="font-size: 0.75rem; color: #71717a;" data-lang-key="gross_profit_note">{{ __('messages.gross_profit_note') }}</span>
        </div>
        <div class="report-stat-card">
            <div class="report-stat-title" data-lang-key="profit_margin">{{ __('messages.profit_margin') }}</div>
            <div class="report-stat-val val-amber">{{ $reportData['profitMargin'] }}%</div>
            <span style="font-size: 0.75rem; color: #71717a;">(Gross Profit / Revenue) &times; 100</span>
        </div>
    @endif
</div>
@endif

<!-- =========================================================================
     CHARTS / VISUAL ANALYTICS COMPONENT
     ========================================================================= -->
@if($type === 'profit-loss')
    <div class="bakery-card" style="margin-bottom: 1.85rem; border-left: 4px solid #b45309;">
        <h3 style="font-size: 1.05rem; font-weight: 800; color: var(--text-title); margin: 0 0 0.5rem 0;">Financial Notes</h3>
        <p style="font-size: 0.85rem; color: var(--text-secondary); line-height: 1.5; margin: 0;">
            General operating expenses such as rent and store utilities are not tracked here. The calculations above reflect <strong>Gross Profit Based on Recorded Costs</strong> (Total Sales minus recipe ingredient costs). Purchase order spend is displayed as a separate cash outflow metric.
        </p>
    </div>
    @if(isset($reportData['categoryBreakdown']) && count($reportData['categoryBreakdown']) > 0)
        <div class="chart-card" style="margin-bottom: 1.85rem;" id="profit_loss_categories_card">
            <div class="card-header-flex" style="flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <h2 style="font-size: 1.15rem; font-weight: 800; color: var(--text-title); margin: 0;">Category Profitability Analysis</h2>
                    <p style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.2rem;">Revenue and estimated direct recipe cost by bakery product category</p>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                @foreach($reportData['categoryBreakdown'] as $cat)
                    <div class="report-inner-card" style="padding: 1rem;">
                        <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-title);">{{ $cat->category_name }}</div>
                        <div style="margin-top: 0.5rem; display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-secondary);">
                            <span>Revenue:</span>
                            <span style="font-weight: 700; color: #16a34a;">{{ $currency }}{{ number_format($cat->cat_revenue, 2) }}</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; font-size: 0.8rem; color: var(--text-secondary);">
                            <span>Recipe COGS:</span>
                            <span style="font-weight: 700; color: #dc2626;">-{{ $currency }}{{ number_format($cat->cat_cogs, 2) }}</span>
                        </div>
                        <div style="border-top: 1px dashed var(--border-color, #eee9e0); margin-top: 0.5rem; padding-top: 0.4rem; display: flex; justify-content: space-between; font-size: 0.875rem; font-weight: 800;">
                            <span>Gross Margin:</span>
                            <span style="color: {{ $cat->cat_profit >= 0 ? '#16a34a' : '#dc2626' }};">{{ $currency }}{{ number_format($cat->cat_profit, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endif

@if($type !== 'overview')

<!-- =========================================================================
     DATA TABLE COMPONENT
     ========================================================================= -->
<div class="report-table-wrapper">
    @if($type === 'sales')
        <table class="report-table" id="sales_report_table">
            <thead>
                <tr>
                    <th>Sale ID</th>
                    <th>Date & Time</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th style="text-align: right;">Subtotal</th>
                    <th style="text-align: right;">Discount</th>
                    <th style="text-align: right;">Tax</th>
                    <th style="text-align: right;">Total</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $sale)
                    @php $order = $sale->order; @endphp
                    <tr>
                        <td style="font-weight: 800;">#SALE-{{ str_pad($sale->id, 5, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $sale->sold_at ? $sale->sold_at->format('M d, Y H:i') : 'N/A' }}</td>
                        <td style="font-weight: 700;">{{ $order ? $order->order_number : 'N/A' }}</td>
                        <td>{{ $order ? ($order->customer_name ?? ($order->customer ? $order->customer->name : 'Walk-in')) : 'Walk-in' }}</td>
                        <td>{{ $order && $order->items ? $order->items->sum('quantity') : 1 }}x</td>
                        <td style="text-align: right;">{{ $currency }}{{ $order ? number_format((float) $order->subtotal, 2) : '0.00' }}</td>
                        <td style="text-align: right; color: #dc2626;">-{{ $currency }}{{ $order ? number_format((float) $order->discount, 2) : '0.00' }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ $order ? number_format((float) $order->tax, 2) : '0.00' }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) $sale->total, 2) }}</td>
                        <td><span class="type-pill walkin">{{ strtoupper($sale->payment_method) }}</span></td>
                        <td><span class="status-badge {{ $sale->payment_status }}">{{ ucfirst($sale->payment_status) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">
                            <div class="report-empty-state">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <h4>No Sales Records Found</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'revenue')
        <table class="report-table" id="revenue_report_table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th style="text-align: center;">Orders Completed</th>
                    <th style="text-align: right;">Daily Revenue</th>
                    <th style="text-align: right;">Average Order Value</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['dailyRecords'] as $row)
                    <tr>
                        <td style="font-weight: 800;">{{ $row['date'] }}</td>
                        <td>{{ $row['formatted'] }}</td>
                        <td style="text-align: center; font-weight: 700;">{{ $row['orders'] }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) $row['revenue'], 2) }}</td>
                        <td style="text-align: right; font-weight: 700; color: #b45309;">{{ $currency }}{{ number_format((float) $row['avg_value'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="report-empty-state">
                                <h4>No Revenue Data</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'orders')
        <table class="report-table" id="orders_report_table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Order Type</th>
                    <th style="text-align: right;">Subtotal</th>
                    <th style="text-align: right;">Discount</th>
                    <th style="text-align: right;">Tax</th>
                    <th style="text-align: right;">Total</th>
                    <th>Payment</th>
                    <th>Order Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $order)
                    <tr>
                        <td style="font-weight: 800;">{{ $order->order_number }}</td>
                        <td>{{ $order->created_at ? $order->created_at->format('M d, Y H:i') : '' }}</td>
                        <td>{{ $order->customer_name ?? ($order->customer ? $order->customer->name : 'Walk-in') }}</td>
                        <td><span class="type-pill {{ $order->is_custom ? 'custom' : 'walkin' }}">{{ $order->is_custom ? 'Custom Cake' : 'Standard' }}</span></td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $order->subtotal, 2) }}</td>
                        <td style="text-align: right; color: #dc2626;">-{{ $currency }}{{ number_format((float) $order->discount, 2) }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $order->tax, 2) }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) $order->total, 2) }}</td>
                        <td><span class="status-badge {{ $order->payment_status ?? 'paid' }}">{{ ucfirst($order->payment_status ?? 'paid') }}</span></td>
                        <td><span class="status-badge {{ $order->order_status }}">{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="report-empty-state">
                                <h4>No Orders Recorded</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'products')
        <table class="report-table" id="products_report_table">
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>SKU</th>
                    <th style="text-align: right;">Unit Price</th>
                    <th style="text-align: right;">Unit Cost</th>
                    <th style="text-align: center;">Units Sold</th>
                    <th style="text-align: right;">Total Revenue</th>
                    <th style="text-align: center;">Orders Count</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $index => $prod)
                    <tr>
                        <td style="font-weight: 800; color: var(--accent-brown, #d97736);">#{{ $index + 1 }}</td>
                        <td style="font-weight: 800;">{{ $prod->product_name }}</td>
                        <td>{{ $prod->category_name ?? 'Uncategorized' }}</td>
                        <td style="font-family: monospace;">{{ $prod->sku ?? 'N/A' }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $prod->unit_price, 2) }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $prod->unit_cost, 2) }}</td>
                        <td style="text-align: center; font-weight: 800; color: var(--text-title);">{{ number_format((float) $prod->total_units_sold) }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) $prod->total_revenue, 2) }}</td>
                        <td style="text-align: center;">{{ number_format($prod->total_orders_count) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="report-empty-state">
                                <h4>No Product Sales Found</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'inventory')
        <table class="report-table" id="inventory_report_table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Supplier</th>
                    <th style="text-align: right;">Current Stock</th>
                    <th>Unit</th>
                    <th style="text-align: right;">Reorder Level</th>
                    <th style="text-align: right;">Purchase Cost</th>
                    <th style="text-align: right;">Total Valuation</th>
                    <th>Expiry Date</th>
                    <th>Stock Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $item)
                    @php 
                        $statusClass = $item->quantity <= 0 ? 'out_of_stock' : ($item->quantity <= $item->minimum_quantity ? 'low_stock' : 'in_stock');
                        $statusLabel = $item->quantity <= 0 ? 'Out of Stock' : ($item->quantity <= $item->minimum_quantity ? 'Low Stock' : 'In Stock');
                    @endphp
                    <tr>
                        <td style="font-weight: 800;">{{ $item->name }}</td>
                        <td>{{ $item->supplier ? $item->supplier->name : 'N/A' }}</td>
                        <td style="text-align: right; font-weight: 800; color: {{ $item->quantity <= 0 ? '#dc2626' : 'inherit' }};">
                            {{ number_format((float) $item->quantity, 2) }}
                        </td>
                        <td>{{ $item->unit }}</td>
                        <td style="text-align: right;">{{ number_format((float) $item->minimum_quantity, 2) }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $item->cost, 2) }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) ($item->quantity * $item->cost), 2) }}</td>
                        <td>{{ $item->expiry_date ? $item->expiry_date->format('M d, Y') : 'N/A' }}</td>
                        <td><span class="status-badge {{ $statusClass }}">{{ $statusLabel }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="report-empty-state">
                                <h4>No Ingredient Records</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'low-stock')
        <table class="report-table" id="low_stock_report_table">
            <thead>
                <tr>
                    <th>Ingredient</th>
                    <th>Supplier</th>
                    <th style="text-align: right;">Current Stock</th>
                    <th>Unit</th>
                    <th style="text-align: right;">Reorder Level</th>
                    <th style="text-align: right;">Shortage Deficit</th>
                    <th style="text-align: right;">Unit Cost</th>
                    <th style="text-align: right;">Est. Restock Cost</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $item)
                    @php 
                        $deficit = max(0.0, (float) $item->minimum_quantity - (float) $item->quantity);
                        $restockCost = $deficit * (float) $item->cost;
                    @endphp
                    <tr>
                        <td style="font-weight: 800;">{{ $item->name }}</td>
                        <td>{{ $item->supplier ? $item->supplier->name : 'N/A' }}</td>
                        <td style="text-align: right; font-weight: 800; color: #dc2626;">{{ number_format((float) $item->quantity, 2) }}</td>
                        <td>{{ $item->unit }}</td>
                        <td style="text-align: right;">{{ number_format((float) $item->minimum_quantity, 2) }}</td>
                        <td style="text-align: right; font-weight: 800; color: #b45309;">{{ number_format($deficit, 2) }}</td>
                        <td style="text-align: right;">{{ $currency }}{{ number_format((float) $item->cost, 2) }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format($restockCost, 2) }}</td>
                        <td>
                            <span class="status-badge {{ $item->quantity <= 0 ? 'out_of_stock' : 'low_stock' }}">
                                {{ $item->quantity <= 0 ? 'Out of Stock' : 'Low Stock' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="report-empty-state">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <h4 style="color: #15803d;">All Stock Levels Optimal</h4>
                                <p>No ingredients are currently below their designated minimum threshold.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'production')
        <table class="report-table" id="production_report_table">
            <thead>
                <tr>
                    <th>Batch #</th>
                    <th>Product</th>
                    <th>Recipe</th>
                    <th>Baker</th>
                    <th>Production Date</th>
                    <th style="text-align: center;">Quantity</th>
                    <th>Status</th>
                    <th>Started</th>
                    <th>Completed</th>
                    <th>Duration</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $batch)
                    <tr>
                        <td style="font-weight: 800;">{{ $batch->batch_number }}</td>
                        <td style="font-weight: 700;">{{ $batch->product ? $batch->product->name : 'N/A' }}</td>
                        <td>{{ $batch->recipe ? $batch->recipe->name : 'N/A' }}</td>
                        <td>{{ $batch->baker ? $batch->baker->name : ($batch->creator ? $batch->creator->name : 'Unassigned') }}</td>
                        <td>{{ $batch->production_date ? $batch->production_date->format('M d, Y') : '' }}</td>
                        <td style="text-align: center; font-weight: 800;">{{ number_format($batch->quantity) }}</td>
                        <td><span class="status-badge {{ $batch->status }}">{{ ucfirst($batch->status) }}</span></td>
                        <td>{{ $batch->started_at ? $batch->started_at->format('H:i') : '-' }}</td>
                        <td>{{ $batch->completed_at ? $batch->completed_at->format('H:i') : '-' }}</td>
                        <td style="font-weight: 600;">{{ $batch->duration ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">
                            <div class="report-empty-state">
                                <h4>No Production Batches Found</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'customers')
        <table class="report-table" id="customers_report_table">
            <thead>
                <tr>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th style="text-align: center;">Completed Orders</th>
                    <th style="text-align: right;">Total Spent</th>
                    <th style="text-align: center;">Loyalty Points</th>
                    <th>Tier</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $cust)
                    <tr>
                        <td style="font-weight: 800;">{{ $cust->name }}</td>
                        <td>{{ $cust->phone ?? 'N/A' }}</td>
                        <td>{{ $cust->email ?? 'N/A' }}</td>
                        <td style="text-align: center; font-weight: 700;">{{ $cust->orders_count ?? 0 }}</td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">
                            {{ $currency }}{{ number_format((float) ($cust->orders_sum_total ?? 0), 2) }}
                        </td>
                        <td style="text-align: center; font-weight: 800; color: #b45309;">{{ number_format($cust->loyalty_points ?? 0) }}</td>
                        <td><span class="status-badge {{ strtolower($cust->loyalty_tier ?? 'standard') }}">{{ strtoupper($cust->loyalty_tier ?? 'standard') }}</span></td>
                        <td><span class="status-badge {{ $cust->status ?? 'active' }}">{{ ucfirst($cust->status ?? 'active') }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="report-empty-state">
                                <h4>No Customer Records</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'purchases')
        <table class="report-table" id="purchases_report_table">
            <thead>
                <tr>
                    <th>PO #</th>
                    <th>Supplier</th>
                    <th>Order Date</th>
                    <th>Expected Date</th>
                    <th>Received Date</th>
                    <th>Status</th>
                    <th style="text-align: right;">Total Cost</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reportData['records'] as $po)
                    <tr>
                        <td style="font-weight: 800;">{{ $po->po_number }}</td>
                        <td style="font-weight: 700;">{{ $po->supplier ? $po->supplier->name : 'N/A' }}</td>
                        <td>{{ $po->order_date ? $po->order_date->format('M d, Y') : '' }}</td>
                        <td>{{ $po->expected_delivery_date ? $po->expected_delivery_date->format('M d, Y') : 'N/A' }}</td>
                        <td>{{ $po->received_date ? $po->received_date->format('M d, Y H:i') : 'N/A' }}</td>
                        <td><span class="status-badge {{ $po->status }}">{{ strtoupper($po->status) }}</span></td>
                        <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format((float) $po->total_cost, 2) }}</td>
                        <td>{{ $po->user ? $po->user->name : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <div class="report-empty-state">
                                <h4>No Purchase Orders Found</h4>
                                <p data-lang-key="no_data_period">{{ __('messages.no_data_period') }}</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @elseif($type === 'profit-loss')
        <table class="report-table" id="profit_loss_report_table">
            <thead>
                <tr>
                    <th>Financial Performance Metric</th>
                    <th style="text-align: right;">Amount ({{ $currency }})</th>
                    <th>Calculation Scope Details</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="font-weight: 800;">1. Total Sales Revenue</td>
                    <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format($reportData['salesRevenue'], 2) }}</td>
                    <td>Sum of completed orders in selected period</td>
                </tr>
                <tr>
                    <td style="font-weight: 800;">2. Product Cost of Goods Sold (COGS)</td>
                    <td style="text-align: right; font-weight: 800; color: #dc2626;">-{{ $currency }}{{ number_format($reportData['productCogs'], 2) }}</td>
                    <td>Sum of (units sold &times; product recipe cost) for completed orders</td>
                </tr>
                <tr style="background: var(--bg-surface-subtle, rgba(217, 119, 54, 0.08));">
                    <td style="font-weight: 800; font-size: 0.95rem;">3. GROSS PROFIT (Based on Recorded Costs)</td>
                    <td style="text-align: right; font-weight: 800; font-size: 1.05rem; color: {{ $reportData['grossProfit'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $currency }}{{ number_format($reportData['grossProfit'], 2) }}
                    </td>
                    <td>Sales Revenue &minus; Product COGS</td>
                </tr>
                <tr>
                    <td style="font-weight: 800;">4. Gross Profit Margin</td>
                    <td style="text-align: right; font-weight: 800; color: #b45309;">{{ $reportData['profitMargin'] }}%</td>
                    <td>(Gross Profit / Sales Revenue) &times; 100</td>
                </tr>
                <tr>
                    <td style="font-weight: 800;">5. Total Procurement Spend</td>
                    <td style="text-align: right; font-weight: 800;">{{ $currency }}{{ number_format($reportData['procurementSpend'], 2) }}</td>
                    <td>Total cost of purchase orders marked as Received in period</td>
                </tr>
                <tr>
                    <td style="font-weight: 800;">6. Physical Product Units Sold</td>
                    <td style="text-align: right; font-weight: 800;">{{ number_format($reportData['totalUnitsSold']) }}</td>
                    <td>Total units delivered to customers</td>
                </tr>
                <tr>
                    <td style="font-weight: 800;">7. Average Gross Profit Per Unit</td>
                    <td style="text-align: right; font-weight: 800; color: #16a34a;">{{ $currency }}{{ number_format($reportData['avgProfitPerUnit'], 2) }}</td>
                    <td>Gross Profit / Units Sold</td>
                </tr>
            </tbody>
        </table>
    @endif
</div>

<!-- Pagination Links (When available) -->
@if(isset($reportData['records']) && is_object($reportData['records']) && method_exists($reportData['records'], 'links'))
    <div style="margin-top: 1rem;">
        {{ $reportData['records']->links() }}
    </div>
@endif
@endif
    </div>
</div>

@endsection

@push('scripts')
<script>
    // ── Mobile report cards: label each cell with its column header ──
    document.querySelectorAll('.report-table').forEach(function (table) {
        const headers = Array.from(table.querySelectorAll('thead th')).map(function (th) {
            return th.textContent.trim();
        });
        table.querySelectorAll('tbody tr').forEach(function (row) {
            const cells = row.querySelectorAll('td');
            if (cells.length < 2) return;
            row.classList.add('rpt-card-row');
            cells.forEach(function (td, i) {
                if (headers[i]) td.setAttribute('data-label', headers[i]);
            });
        });
    });

(function () {
    const pairs = [['nav_sales', 'sales_report_menu'], ['nav_inventory', 'inventory_report_menu']]
        .map(function (ids) { return { btn: document.getElementById(ids[0]), menu: document.getElementById(ids[1]) }; })
        .filter(function (d) { return d.btn && d.menu; });
    if (!pairs.length) return;

    // Move menus to <body>: ancestors with overflow / backdrop-filter would clip or offset a fixed menu
    pairs.forEach(function (d) { document.body.appendChild(d.menu); });

    function place(d) {
        const r = d.btn.getBoundingClientRect();
        d.menu.style.top = (r.bottom + 8) + 'px';
        d.menu.style.left = Math.max(8, Math.min(r.left, window.innerWidth - d.menu.offsetWidth - 8)) + 'px';
    }
    // Liquid pill: slides to the hovered item, rests on the active one (same as the sidebar)
    function initPill(d) {
        d.menu.classList.add('has-drop-pill');
        d.pill = document.createElement('div');
        d.pill.className = 'report-dropdown-pill';
        d.menu.prepend(d.pill);
        d.items = Array.prototype.slice.call(d.menu.querySelectorAll('.report-dropdown-item'));
        function moveTo(item, instant) {
            if (!item) { d.pill.style.opacity = '0'; d.items.forEach(function (i) { i.classList.remove('pill-highlighted'); }); return; }
            if (instant) d.pill.style.transition = 'none';
            d.pill.style.width = item.offsetWidth + 'px';
            d.pill.style.height = item.offsetHeight + 'px';
            d.pill.style.transform = 'translate3d(' + item.offsetLeft + 'px,' + item.offsetTop + 'px,0)';
            d.pill.style.opacity = '1';
            d.items.forEach(function (i) { i.classList.toggle('pill-highlighted', i === item); });
            if (instant) { void d.pill.offsetWidth; d.pill.style.transition = ''; }
        }
        d.rest = function (instant) { moveTo(d.menu.querySelector('.report-dropdown-item.active'), instant); };
        d.items.forEach(function (i) { i.addEventListener('mouseenter', function () { moveTo(i, false); }); i.addEventListener('focus', function () { moveTo(i, false); }); });
        d.menu.addEventListener('mouseleave', function () { d.rest(false); });
    }
    pairs.forEach(initPill);

    function setOpen(d, open) {
        d.menu.hidden = !open;
        d.btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        d.btn.classList.toggle('is-open', open);
        if (open) { place(d); d.rest(true); }
    }
    function closeAll(except) {
        pairs.forEach(function (d) { if (d !== except && !d.menu.hidden) setOpen(d, false); });
    }

    pairs.forEach(function (d) {
        d.btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            closeAll(d);
            setOpen(d, d.menu.hidden);
        });
    });
    document.addEventListener('click', function (e) {
        pairs.forEach(function (d) {
            if (!d.menu.hidden && !d.menu.contains(e.target) && !d.btn.contains(e.target)) setOpen(d, false);
        });
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAll(null);
    });
    // Close report menus when the sidebar opens/collapses (its toggle stops click propagation)
    const sidebarEl = document.querySelector('.bakery-sidebar');
    if (sidebarEl && 'MutationObserver' in window) {
        new MutationObserver(function () { closeAll(null); })
            .observe(sidebarEl, { attributes: true, attributeFilter: ['class'] });
    }
    window.addEventListener('resize', function () { pairs.forEach(function (d) { if (!d.menu.hidden) place(d); }); });
    window.addEventListener('scroll', function () { pairs.forEach(function (d) { if (!d.menu.hidden) place(d); }); }, true);
})();
</script>
@endpush
