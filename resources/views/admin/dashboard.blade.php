@extends('layouts.app')

@section('title', 'Admin Analytics & Dashboard - Bakery Management System')
@section('body_class', 'is-catalog-page is-dashboard-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}?v={{ @filemtime(public_path('css/admin-dashboard.css')) ?: '1.0' }}">
@endpush

@section('header_toolbar')
    <form method="GET" action="{{ route('admin.dashboard') }}" class="header-filter-form">
        <div class="period-pill-group">
            <a href="{{ route('admin.dashboard', ['period' => 'today']) }}" class="period-pill {{ $period === 'today' ? 'active' : '' }}" data-lang-key="today">{{ __('messages.today') }}</a>
            <a href="{{ route('admin.dashboard', ['period' => 'yesterday']) }}" class="period-pill {{ $period === 'yesterday' ? 'active' : '' }}" data-lang-key="yesterday">{{ __('messages.yesterday') }}</a>
            <a href="{{ route('admin.dashboard', ['period' => '7days']) }}" class="period-pill {{ $period === '7days' ? 'active' : '' }}" data-lang-key="last_7_days">{{ __('messages.last_7_days') }}</a>
            <a href="{{ route('admin.dashboard', ['period' => '30days']) }}" class="period-pill {{ $period === '30days' ? 'active' : '' }}" data-lang-key="last_30_days">{{ __('messages.last_30_days') }}</a>
            <a href="{{ route('admin.dashboard', ['period' => 'this_month']) }}" class="period-pill {{ $period === 'this_month' ? 'active' : '' }}" data-lang-key="this_month">{{ __('messages.this_month') }}</a>
        </div>
    </form>
@endsection

@section('content')
<div class="dash-page-container">
    <div class="dash-scrollable-body" id="dash_scrollable_body">
<!-- 8 Core KPI Stat Cards Grid (Real Database Metrics Only) -->
<div class="dash-stat-row">
    <!-- 1. Today's Orders (Primary Card 0) -->
    <div class="stat-card">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="todays_orders">{{ __('messages.todays_orders') }}</span>
            <div class="stat-value">{{ number_format($todayOrdersCount) }}</div>
            @if($ordersGrowthPercent !== null)
                <span class="stat-trend {{ $ordersGrowthPercent >= 0 ? 'up' : 'down' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="{{ $ordersGrowthPercent >= 0 ? '18 15 12 9 6 15' : '6 9 12 15 18 9' }}"/>
                    </svg>
                    {{ $ordersGrowthPercent >= 0 ? '+' : '' }}{{ $ordersGrowthPercent }}% {{ __('messages.vs_yesterday') }}
                </span>
            @else
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ __('messages.live_sync') }}
                </span>
            @endif
        </div>
    </div>

    <!-- 2. Today's Revenue (Primary Card 1) -->
    <div class="stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="todays_revenue">{{ __('messages.todays_revenue') }}</span>
            <div class="stat-value">{{ $currency }}{{ number_format($todayRevenueSum, 2) }}</div>
            @if($salesGrowthPercent !== null)
                <span class="stat-trend {{ $salesGrowthPercent >= 0 ? 'up' : 'down' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="{{ $salesGrowthPercent >= 0 ? '18 15 12 9 6 15' : '6 9 12 15 18 9' }}"/>
                    </svg>
                    {{ $salesGrowthPercent >= 0 ? '+' : '' }}{{ $salesGrowthPercent }}% {{ __('messages.vs_yesterday') }}
                </span>
            @else
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ __('messages.live_today') }}
                </span>
            @endif
        </div>
    </div>

    <!-- 3. Pending Orders (Primary Card 2) -->
    <div class="stat-card">
        <div class="stat-icon-box yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="pending_orders">{{ __('messages.pending_orders') }}</span>
            <div class="stat-value">{{ number_format($pendingOrdersCount) }}</div>
            @if($pendingOrdersCount > 0)
                <span class="stat-trend warning">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ __('messages.requires_attention') }}
                </span>
            @else
                <span class="stat-trend neutral">All clear</span>
            @endif
        </div>
    </div>

    <!-- 4. Low Stock Items (Primary Card 3) -->
    <div class="stat-card">
        <div class="stat-icon-box red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="low_stock_items">{{ __('messages.low_stock_items') }}</span>
            <div class="stat-value">{{ number_format($lowStockCount) }}</div>
            <a href="{{ route('admin.ingredients') }}" class="stat-trend danger">
                {!! $outOfStockIngredientsCount > 0 ? $outOfStockIngredientsCount . ' out of stock &rarr;' : 'View details &rarr;' !!}
            </a>
        </div>
    </div>

    <!-- 5. Total Customers -->
    <div class="stat-card">
        <div class="stat-icon-box blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="total_customers">{{ __('messages.total_customers') }}</span>
            <div class="stat-value">{{ number_format($totalCustomersCount) }}</div>
            <span class="stat-trend neutral">+{{ $newCustomersThisMonth }} this month</span>
        </div>
    </div>

    <!-- 6. Active Products -->
    <div class="stat-card">
        <div class="stat-icon-box purple">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="active_products">{{ __('messages.active_products') }}</span>
            <div class="stat-value">{{ number_format($activeProductsCount) }}</div>
            <span class="stat-trend neutral">{{ $totalProductsCount }} in catalog</span>
        </div>
    </div>

    <!-- 7. Active Productions -->
    <div class="stat-card">
        <div class="stat-icon-box amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="active_productions">{{ __('messages.active_productions') }}</span>
            <div class="stat-value">{{ number_format($activeProductionsCount) }}</div>
            <span class="stat-trend neutral">{{ number_format($totalUnitsProduced) }} units baked</span>
        </div>
    </div>

    <!-- 8. Pending Purchase Orders -->
    <div class="stat-card">
        <div class="stat-icon-box teal">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label" data-lang-key="pending_purchase_orders">{{ __('messages.pending_purchase_orders') }}</span>
            <div class="stat-value">{{ number_format($pendingPosCount) }}</div>
            <span class="stat-trend {{ $pendingPosCount > 0 ? 'warning' : 'neutral' }}">{{ $orderedPosCount }} ordered</span>
        </div>
    </div>
</div>

<!-- Quick Actions Section -->
<div class="quick-actions-section">
    <h2 class="section-title">Quick Actions</h2>
    <div class="quick-actions-grid">
        <!-- Add Product -->
        <a href="{{ route('admin.products') }}" class="quick-action-btn cream" id="quick_action_add_product">
            <div class="quick-action-icon-circle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </div>
            <span>Add Product</span>
        </a>

        <!-- POS Terminal -->
        <a href="{{ route('cashier.dashboard') }}" class="quick-action-btn blue" id="quick_action_pos">
            <div class="quick-action-icon-circle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
            </div>
            <span>POS Terminal</span>
        </a>

        <!-- View Orders -->
        <a href="{{ route('admin.orders') }}" class="quick-action-btn purple" id="quick_action_orders">
            <div class="quick-action-icon-circle">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            </div>
            <span>View Orders</span>
        </a>
    </div>
</div>

<!-- Sales & Revenue Performance Visual Analytics -->
<div class="dash-chart-card" id="sales_revenue_performance_card">
    <div class="dash-chart-header">
        <div class="dash-chart-title-group">
            <div class="dash-chart-badge-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3v18h18"/>
                    <path d="m19 9-5 5-4-4-3 3"/>
                </svg>
            </div>
            <div>
                <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap;">
                    <h2 class="dash-chart-main-title" data-lang-key="sales_revenue_performance">
                        {{ __('messages.sales_revenue_performance') }}
                    </h2>
                    <span class="dash-live-badge">
                        <span class="dash-live-dot"></span>
                        <span>Live</span>
                    </span>
                </div>
                <p class="dash-chart-subtitle">
                    Continuous timeline aggregation based on completed POS sales & orders
                </p>
            </div>
        </div>

        <!-- Quick Timeframe Switcher -->
        @php
            $currentPeriod = request('period', '7days');
        @endphp
        <div class="dash-period-switch">
            <a href="{{ route('admin.dashboard', ['period' => '7days']) }}" class="dash-period-btn {{ $currentPeriod === '7days' ? 'active' : '' }}">7 Days</a>
            <a href="{{ route('admin.dashboard', ['period' => '30days']) }}" class="dash-period-btn {{ $currentPeriod === '30days' ? 'active' : '' }}">30 Days</a>
            <a href="{{ route('admin.dashboard', ['period' => 'this_month']) }}" class="dash-period-btn {{ $currentPeriod === 'this_month' ? 'active' : '' }}">This Month</a>
        </div>
    </div>

    <!-- Modern Elevated Metric Cards Row -->
    <div class="dash-chart-kpi-row">
        <!-- Total Revenue -->
        <div class="dash-kpi-chip kpi-revenue">
            <div class="kpi-chip-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <div class="kpi-chip-data">
                <span class="kpi-chip-label" data-lang-key="total_revenue">{{ __('messages.total_revenue') }}</span>
                <span class="kpi-chip-value val-green">{{ $currency }}{{ number_format($periodTotalRevenue, 2) }}</span>
            </div>
        </div>

        <!-- Total Orders -->
        <div class="dash-kpi-chip kpi-orders">
            <div class="kpi-chip-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/>
                    <path d="M3 6h18"/>
                    <path d="M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </div>
            <div class="kpi-chip-data">
                <span class="kpi-chip-label" data-lang-key="orders">{{ __('messages.orders') }}</span>
                <span class="kpi-chip-value val-neutral">{{ number_format($periodTotalOrders) }}</span>
            </div>
        </div>

        <!-- Average Order Value -->
        <div class="dash-kpi-chip kpi-aov">
            <div class="kpi-chip-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                    <polyline points="16 7 22 7 22 13"/>
                </svg>
            </div>
            <div class="kpi-chip-data">
                <span class="kpi-chip-label" data-lang-key="average_order_value">{{ __('messages.average_order_value') }}</span>
                <span class="kpi-chip-value val-amber">
                    {{ $currency }}{{ $periodTotalOrders > 0 ? number_format($periodTotalRevenue / $periodTotalOrders, 2) : '0.00' }}
                </span>
            </div>
        </div>
    </div>

    <!-- Responsive Smooth Spline SVG Chart Engine -->
    @php
        $rawMax = max(max($chartRevenues ?: [0]), 10);
        if ($rawMax <= 20) {
            $niceMax = ceil($rawMax / 5) * 5;
        } elseif ($rawMax <= 100) {
            $niceMax = ceil($rawMax / 10) * 10;
        } elseif ($rawMax <= 500) {
            $niceMax = ceil($rawMax / 50) * 50;
        } elseif ($rawMax <= 2000) {
            $niceMax = ceil($rawMax / 100) * 100;
        } else {
            $niceMax = ceil($rawMax / 500) * 500;
        }
        if ($niceMax <= 0) $niceMax = 100;

        $pointCount = count($chartLabels);
        $pts = [];
        foreach($chartRevenues as $idx => $r) {
            $x = $pointCount > 1 ? round(55 + ($idx / ($pointCount - 1)) * 705, 1) : 400;
            $y = round(190 - ($r / $niceMax) * 150, 1);
            $pts[] = ['x' => $x, 'y' => $y];
        }

        $tension = 0.2;
        $pathCommands = [];
        for ($i = 0; $i < $pointCount - 1; $i++) {
            $p0 = $i > 0 ? $pts[$i - 1] : $pts[$i];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $i + 2 < $pointCount ? $pts[$i + 2] : $p2;

            $cp1x = round($p1['x'] + ($p2['x'] - $p0['x']) * $tension, 1);
            $cp1y = round($p1['y'] + ($p2['y'] - $p0['y']) * $tension, 1);
            $cp2x = round($p2['x'] - ($p3['x'] - $p1['x']) * $tension, 1);
            $cp2y = round($p2['y'] - ($p3['y'] - $p1['y']) * $tension, 1);

            $cp1y = max(35, min(190, $cp1y));
            $cp2y = max(35, min(190, $cp2y));

            $pathCommands[] = "C $cp1x $cp1y, $cp2x $cp2y, {$p2['x']} {$p2['y']}";
        }

        $curveStr = implode(' ', $pathCommands);
        $linePath = $pointCount > 1 ? "M {$pts[0]['x']} {$pts[0]['y']} " . $curveStr : "";
        $areaPath = $pointCount > 1 ? "M {$pts[0]['x']} 190 L {$pts[0]['x']} {$pts[0]['y']} " . $curveStr . " L {$pts[$pointCount - 1]['x']} 190 Z" : "";
        $stepWidth = $pointCount > 1 ? 705 / ($pointCount - 1) : 700;
    @endphp

    <div class="dash-chart-svg-container" id="dashChartContainer">
        <!-- Floating Glassmorphic Interactive Tooltip -->
        <div id="dashChartTooltip" class="dash-chart-tooltip" style="display: none;">
            <div class="tooltip-header">
                <span class="tooltip-dot"></span>
                <span class="tooltip-date" id="dashTooltipDate">-</span>
            </div>
            <div class="tooltip-content">
                <div class="tooltip-metric">
                    <span class="tooltip-label">Revenue</span>
                    <span class="tooltip-val revenue" id="dashTooltipRev">$0.00</span>
                </div>
                <div class="tooltip-metric">
                    <span class="tooltip-label">Orders</span>
                    <span class="tooltip-val orders" id="dashTooltipOrders">0</span>
                </div>
            </div>
        </div>

        <svg viewBox="0 0 800 230" preserveAspectRatio="none" class="dash-revenue-svg" id="dashRevenueSvg">
            <defs>
                <!-- Warm Honey Gradient for Area Fill -->
                <linearGradient id="chartAreaGradient" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#f59e0b" stop-opacity="0.32" />
                    <stop offset="50%" stop-color="#d97706" stop-opacity="0.10" />
                    <stop offset="100%" stop-color="#d97706" stop-opacity="0.0" />
                </linearGradient>

                <!-- Gradient for Vibrant Smooth Curve -->
                <linearGradient id="chartLineGradient" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="#ea580c" />
                    <stop offset="50%" stop-color="#f59e0b" />
                    <stop offset="100%" stop-color="#d97706" />
                </linearGradient>

                <!-- Luminous Drop Shadow Glow for Curve -->
                <filter id="chartCurveGlow" x="-5%" y="-15%" width="110%" height="140%">
                    <feDropShadow dx="0" dy="5" stdDeviation="4" flood-color="#d97706" flood-opacity="0.25"/>
                </filter>
            </defs>

            <!-- Horizontal Background Guide Lines -->
            <g class="chart-grid-lines">
                <line x1="50" y1="40" x2="765" y2="40" stroke="currentColor" stroke-dasharray="3 4" class="grid-line" />
                <line x1="50" y1="90" x2="765" y2="90" stroke="currentColor" stroke-dasharray="3 4" class="grid-line" />
                <line x1="50" y1="140" x2="765" y2="140" stroke="currentColor" stroke-dasharray="3 4" class="grid-line" />
                <line x1="50" y1="190" x2="765" y2="190" stroke="currentColor" stroke-width="1.2" class="grid-line-baseline" />
            </g>

            <!-- Y Axis Clean Rounded Increments -->
            <g class="chart-y-labels">
                <text x="42" y="44" class="chart-axis-text" text-anchor="end">${{ number_format($niceMax, 0) }}</text>
                <text x="42" y="94" class="chart-axis-text" text-anchor="end">${{ number_format($niceMax * 0.66, 0) }}</text>
                <text x="42" y="144" class="chart-axis-text" text-anchor="end">${{ number_format($niceMax * 0.33, 0) }}</text>
                <text x="42" y="194" class="chart-axis-text" text-anchor="end">$0</text>
            </g>

            <!-- Smooth Spline Area Fill & Spline Stroke -->
            @if($pointCount > 1)
                <path d="{{ $areaPath }}" fill="url(#chartAreaGradient)" class="chart-spline-area" />
                <path d="{{ $linePath }}" fill="none" stroke="url(#chartLineGradient)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" filter="url(#chartCurveGlow)" class="chart-spline-line" />
            @endif

            <!-- Vertical Crosshair Guides (one per point, activated on hover) -->
            @foreach($pts as $idx => $pt)
                <line id="guide_line_{{ $idx }}" x1="{{ $pt['x'] }}" y1="35" x2="{{ $pt['x'] }}" y2="190" class="chart-guide-line" style="opacity: 0; pointer-events: none;" />
            @endforeach

            <!-- Data Point Nodes with Concentric Rings -->
            @foreach($chartRevenues as $idx => $r)
                @php
                    $cx = $pts[$idx]['x'] ?? 400;
                    $cy = $pts[$idx]['y'] ?? 190;
                    $lbl = $chartLabels[$idx] ?? '';
                    $cnt = $chartOrderCounts[$idx] ?? 0;
                    $hasSale = $r > 0;
                @endphp
                <!-- Concentric Pulse Halo on hover -->
                <circle id="node_halo_{{ $idx }}" cx="{{ $cx }}" cy="{{ $cy }}" r="12" fill="#f59e0b" fill-opacity="0.25" class="chart-node-halo" style="opacity: 0; pointer-events: none; transition: opacity 0.2s ease, r 0.2s ease;" />
                <!-- Core Node Circle -->
                <circle id="node_dot_{{ $idx }}" cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $hasSale ? 5 : 3.5 }}" fill="{{ $hasSale ? '#d97706' : '#94a3b8' }}" stroke="var(--card-bg, #ffffff)" stroke-width="{{ $hasSale ? 2.5 : 1.5 }}" class="chart-node-dot {{ $hasSale ? 'active-point' : 'zero-point' }}" style="transition: all 0.2s ease; pointer-events: none;" />
                <!-- X Axis Date Labels -->
                @if($pointCount <= 14 || $idx % intval(ceil($pointCount / 10)) === 0 || $idx === $pointCount - 1)
                    <text x="{{ $cx }}" y="215" class="chart-x-label {{ $hasSale ? 'active' : '' }}" text-anchor="middle">{{ $lbl }}</text>
                @endif
            @endforeach

            <!-- Invisible Hover Target Columns for Fluid Interaction -->
            @foreach($chartRevenues as $idx => $r)
                @php
                    $cx = $pts[$idx]['x'] ?? 400;
                    $cy = $pts[$idx]['y'] ?? 190;
                    $lbl = $chartLabels[$idx] ?? '';
                    $cnt = $chartOrderCounts[$idx] ?? 0;
                    $hw = max(24, $stepWidth);
                    $hx = $cx - ($hw / 2);
                @endphp
                <rect x="{{ $hx }}" y="25" width="{{ $hw }}" height="175" fill="transparent" class="chart-hover-trigger" data-index="{{ $idx }}" data-cx="{{ $cx }}" data-cy="{{ $cy }}" data-date="{{ $lbl }}" data-revenue="{{ $currency }}{{ number_format($r, 2) }}" data-orders="{{ $cnt }}" style="cursor: pointer;" />
            @endforeach
        </svg>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('dashChartContainer');
    const tooltip = document.getElementById('dashChartTooltip');
    const tipDate = document.getElementById('dashTooltipDate');
    const tipRev = document.getElementById('dashTooltipRev');
    const tipOrders = document.getElementById('dashTooltipOrders');
    const triggers = document.querySelectorAll('.chart-hover-trigger');

    if (!container || !tooltip || !triggers.length) return;

    triggers.forEach(trigger => {
        trigger.addEventListener('mouseenter', function() {
            const idx = this.getAttribute('data-index');
            const cx = parseFloat(this.getAttribute('data-cx'));
            const cy = parseFloat(this.getAttribute('data-cy'));
            const date = this.getAttribute('data-date');
            const rev = this.getAttribute('data-revenue');
            const orders = this.getAttribute('data-orders');

            // Show guide line
            const guide = document.getElementById('guide_line_' + idx);
            if (guide) guide.style.opacity = '1';

            // Show halo & expand dot
            const halo = document.getElementById('node_halo_' + idx);
            const dot = document.getElementById('node_dot_' + idx);
            if (halo) {
                halo.style.opacity = '1';
                halo.setAttribute('r', '14');
            }
            if (dot) {
                dot.setAttribute('r', '7');
                dot.setAttribute('fill', '#ea580c');
            }

            // Update tooltip content
            if (tipDate) tipDate.textContent = date;
            if (tipRev) tipRev.textContent = rev;
            if (tipOrders) tipOrders.textContent = orders + (orders === '1' ? ' order' : ' orders');

            // Position tooltip dynamically
            const containerRect = container.getBoundingClientRect();
            const scaleX = containerRect.width / 800;
            const scaleY = containerRect.height / 230;

            const pixelX = cx * scaleX;
            const pixelY = cy * scaleY;

            tooltip.style.display = 'block';
            tooltip.style.left = pixelX + 'px';
            tooltip.style.top = Math.max(10, pixelY - 12) + 'px';
            
            if (pixelX > containerRect.width * 0.78) {
                tooltip.style.transform = 'translate(-100%, -100%)';
            } else if (pixelX < containerRect.width * 0.22) {
                tooltip.style.transform = 'translate(0%, -100%)';
            } else {
                tooltip.style.transform = 'translate(-50%, -100%)';
            }
            tooltip.classList.add('visible');
        });

        trigger.addEventListener('mouseleave', function() {
            const idx = this.getAttribute('data-index');
            const guide = document.getElementById('guide_line_' + idx);
            if (guide) guide.style.opacity = '0';

            const halo = document.getElementById('node_halo_' + idx);
            const dot = document.getElementById('node_dot_' + idx);
            if (halo) halo.style.opacity = '0';
            if (dot) {
                const hasSale = !dot.classList.contains('zero-point');
                dot.setAttribute('r', hasSale ? '5' : '3.5');
                dot.setAttribute('fill', hasSale ? '#d97706' : '#94a3b8');
            }
        });
    });

    container.addEventListener('mouseleave', function() {
        tooltip.classList.remove('visible');
        setTimeout(() => {
            if (!tooltip.classList.contains('visible')) {
                tooltip.style.display = 'none';
            }
        }, 150);
    });
});
</script>

<!-- Two-Column Grid: Order Status Distribution & Best Selling Products -->
<div class="dash-two-col-grid" style="margin-bottom: 1.85rem;">
    <!-- 1. Order Status Distribution -->
    <div class="bakery-card">
        <div class="card-header-flex">
            <div>
                <h2 data-lang-key="order_status_distribution">{{ __('messages.order_status_distribution') }}</h2>
                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ number_format($totalAllOrders) }} total recorded orders</span>
            </div>
        </div>

        <!-- Proportional Multi-Segment Status Bar -->
        @if($totalAllOrders > 0)
            <div class="dash-status-progress-track" style="height: 12px; display: flex; width: 100%; border-radius: 8px; overflow: hidden; margin: 1rem 0 1.25rem 0; background: #e5e7eb;">
                @if($statusPending > 0)
                    <div style="width: {{ ($statusPending / $totalAllOrders) * 100 }}%; background: #ca8a04;" title="Pending: {{ $statusPending }}"></div>
                @endif
                @if($statusPreparing > 0)
                    <div style="width: {{ ($statusPreparing / $totalAllOrders) * 100 }}%; background: #2563eb;" title="Baking: {{ $statusPreparing }}"></div>
                @endif
                @if($statusReady > 0)
                    <div style="width: {{ ($statusReady / $totalAllOrders) * 100 }}%; background: #9333ea;" title="Ready: {{ $statusReady }}"></div>
                @endif
                @if($statusCompleted > 0)
                    <div style="width: {{ ($statusCompleted / $totalAllOrders) * 100 }}%; background: #16a34a;" title="Completed: {{ $statusCompleted }}"></div>
                @endif
                @if($statusCancelled > 0)
                    <div style="width: {{ ($statusCancelled / $totalAllOrders) * 100 }}%; background: #dc2626;" title="Cancelled: {{ $statusCancelled }}"></div>
                @endif
            </div>
        @endif

        <div class="status-dist-grid">
            <div class="status-dist-box">
                <span class="status-dist-label">Pending</span>
                <span class="status-dist-val" style="color: #ca8a04;">{{ number_format($statusPending) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Baking</span>
                <span class="status-dist-val" style="color: #2563eb;">{{ number_format($statusPreparing) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Ready</span>
                <span class="status-dist-val" style="color: #9333ea;">{{ number_format($statusReady) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Completed</span>
                <span class="status-dist-val" style="color: #16a34a;">{{ number_format($statusCompleted) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Cancelled</span>
                <span class="status-dist-val" style="color: #dc2626;">{{ number_format($statusCancelled) }}</span>
            </div>
        </div>
    </div>

    <!-- 2. Best Selling Products (Completed Orders Only) -->
    <div class="bakery-card">
        <div class="card-header-flex">
            <div>
                <h2 data-lang-key="best_selling_products">{{ __('messages.best_selling_products') }}</h2>
                <span style="font-size: 0.8rem; color: var(--text-muted);">Top items ranked by units sold from completed sales</span>
            </div>
        </div>

        <div class="bestseller-list">
            @forelse($topProducts as $index => $prod)
                @php
                    $rankClass = match($index) {
                        0 => 'gold',
                        1 => 'silver',
                        2 => 'bronze',
                        default => ''
                    };
                    $percent = $maxSold > 0 ? round(($prod->total_sold / $maxSold) * 100) : 0;
                @endphp
                <div class="bestseller-item">
                    <div class="bestseller-info-row">
                        <div style="display: flex; align-items: center; min-width: 0; gap: 0.4rem;">
                            <span class="bestseller-rank {{ $rankClass }}">{{ $index + 1 }}</span>
                            <span style="font-weight: 700; color: var(--text-title); text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                {{ $prod->name }}
                            </span>
                        </div>
                        <div style="text-align: right; flex-shrink: 0;">
                            <span style="font-weight: 800; color: var(--text-title);">{{ number_format($prod->total_sold) }} {{ __('messages.units_sold') }}</span>
                            <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">{{ $currency }}{{ number_format($prod->total_revenue, 2) }}</span>
                        </div>
                    </div>
                    <div class="bestseller-progress-track">
                        <div class="bestseller-progress-fill" style="width: {{ $percent }}%;"></div>
                    </div>
                </div>
            @empty
                <div style="text-align: center; color: var(--text-muted); padding: 2.5rem 1rem; font-size: 0.9rem;">
                    {{ __('messages.no_bestsellers') }}
                </div>
            @endforelse
        </div>
    </div>
</div>

<!-- Two-Column Grid: Stock & Expiry Alerts and Operations Overview -->
<div class="dash-two-col-grid" style="margin-bottom: 1.85rem;">
    <!-- Stock & Expiry Alerts -->
    <div class="bakery-card">
        <div class="card-header-flex">
            <div>
                <h2 data-lang-key="stock_expiry_alerts">{{ __('messages.stock_expiry_alerts') }}</h2>
                @if($expiredIngredientsCount > 0)
                    <span style="font-size: 0.8rem; color: #dc2626; font-weight: 700;">{{ $expiredIngredientsCount }} expired!</span>
                @endif
            </div>
        </div>

        <div class="stock-alerts-list">
            <!-- Low Stock Ingredients -->
            @forelse($lowStockIngredients as $ing)
                @php
                    $isCritical = $ing->quantity <= 0 || $ing->quantity <= ($ing->minimum_quantity / 2);
                    $tagClass = $isCritical ? 'critical' : 'low';
                    $tagLabel = $ing->quantity <= 0 ? 'Out of Stock' : ($isCritical ? 'Critical' : 'Low Stock');
                @endphp
                <div class="stock-alert-item">
                    <div class="stock-item-left">
                        <div class="stock-item-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="stock-item-info">
                            <div class="item-name">{{ $ing->name }}</div>
                            <div class="item-qty">{{ $ing->quantity }} {{ $ing->unit }} remaining (Min: {{ $ing->minimum_quantity }})</div>
                        </div>
                    </div>
                    <span class="alert-tag {{ $tagClass }}">{{ $tagLabel }}</span>
                </div>
            @empty
                <div style="text-align: center; color: var(--text-muted); padding: 1.5rem; font-size: 0.85rem;">
                    {{ __('messages.no_low_stock') }}
                </div>
            @endforelse

            <!-- Expiring Soon Ingredients -->
            @if($expiringSoonIngredients->count() > 0)
                <div style="margin-top: 1rem; border-top: 1px dashed var(--border-color); padding-top: 0.75rem;">
                    <span style="font-size: 0.75rem; font-weight: 700; color: #b45309; text-transform: uppercase; letter-spacing: 0.04em;">Expiring Soon (&le; 7 Days)</span>
                    @foreach($expiringSoonIngredients as $exp)
                        <div class="stock-alert-item" style="margin-top: 0.5rem;">
                            <div class="stock-item-left">
                                <div class="stock-item-icon" style="background: #fef3c7; color: #b45309;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div class="stock-item-info">
                                    <div class="item-name">{{ $exp->name }}</div>
                                    <div class="item-qty">Expires {{ \Carbon\Carbon::parse($exp->expiry_date)->format('M d, Y') }} ({{ $exp->quantity }} {{ $exp->unit }})</div>
                                </div>
                            </div>
                            <span class="expiry-tag soon">Expiring Soon</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="card-bottom-action-container">
            <a href="{{ route('admin.ingredients') }}" class="btn-card-outline" data-lang-key="view_all_stock">{{ __('messages.view_all_stock') }}</a>
        </div>
    </div>

    <!-- Procurement & Operations Summary -->
    <div class="bakery-card">
        <div class="card-header-flex">
            <div>
                <h2 data-lang-key="procurement_production_overview">{{ __('messages.procurement_production_overview') }}</h2>
            </div>
        </div>

        <div class="procurement-stat-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.25rem;">
            <div class="status-dist-box">
                <span class="status-dist-label">Active Suppliers</span>
                <span class="status-dist-val" style="color: var(--text-title);">{{ number_format($suppliersCount) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Total Procurement Spend</span>
                <span class="status-dist-val" style="color: #16a34a;">{{ $currency }}{{ number_format($totalProcurementSpend, 2) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Active Productions</span>
                <span class="status-dist-val" style="color: #d97706;">{{ number_format($activeProductionsCount) }}</span>
            </div>
            <div class="status-dist-box">
                <span class="status-dist-label">Total Units Produced</span>
                <span class="status-dist-val" style="color: #9333ea;">{{ number_format($totalUnitsProduced) }}</span>
            </div>
        </div>

        <!-- VIP Customers & Loyalty -->
        <div class="dash-loyalty-box" style="gap: 0.75rem; padding: 1rem; background: #faf6f0; border-radius: 12px; border: 1px solid rgba(238, 233, 224, 0.8); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-weight: 700; color: var(--text-title); font-size: 0.9rem;">Customer Loyalty Program</div>
            </div>
            <a href="{{ route('admin.customers.index') }}" class="btn-card-outline" style="padding: 0.4rem 0.85rem; font-size: 0.8rem;">View Customers</a>
        </div>
    </div>
</div>

<!-- Recent Orders Table -->
<div class="bakery-card recent-orders-card">
    <div class="ro-mobile-header">
        <h3>Recent Orders</h3>
        <p>Real-time bakery transactions</p>
    </div>


    <div class="table-responsive-wrapper">
        <table class="recent-orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Type</th>
                    <th>Customer</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentOrders as $order)
                    @php
                        $statusClass = strtolower($order->order_status ?? 'completed');
                        $statusBadge = match($statusClass) {
                            'completed' => '<span class="status-pill completed">Completed</span>',
                            'preparing', 'baking', 'in progress', 'processing' => '<span class="status-pill inprogress">In Baking</span>',
                            'ready_for_pickup', 'ready' => '<span class="status-pill completed">Ready</span>',
                            'cancelled' => '<span class="status-pill danger" style="background:#fee2e2; color:#dc2626;">Cancelled</span>',
                            default => '<span class="status-pill pending">Pending</span>',
                        };
                        $custName = $order->customer ? $order->customer->name : ($order->customer_name ?: 'Walk-in Customer');
                    @endphp
                    <tr onclick="if (window.innerWidth < 768 && !event.target.closest('.btn-view-order')) this.querySelector('.btn-view-order')?.click()">
                        <td data-label="Order ID" style="font-weight: 800;">#{{ $order->id }}</td>
                        @php
                            $roPayLabel = match($order->payment_method) { 'qr_code' => 'KHQR', 'card' => 'CARD', default => 'CASH' };
                            $roPaid = ($order->payment_status ?? 'unpaid') === 'paid';
                        @endphp
                        <td data-label="Type">
                            <span class="type-pill walkin">{{ strtoupper($order->payment_type ?? $order->order_type ?? 'POS') }}</span>
                            <span class="ro-pay-badge {{ $roPaid ? 'paid' : 'unpaid' }}">{{ $roPayLabel }} • {{ $roPaid ? 'PAID' : 'UNPAID' }}</span>
                        </td>
                        <td data-label="Customer">{{ $custName }}</td>
                        <td data-label="Items" class="ro-mobile-only">
                            <div class="ro-items-text">{{ $order->items_summary }}</div>
                            <div class="ro-items-count">{{ $order->items->count() }} {{ Str::plural('item', $order->items->count()) }}</div>
                        </td>
                        <td data-label="Date" class="ro-mobile-only">{{ $order->created_at ? $order->created_at->format('M d, Y, h:i A') : '' }}</td>
                        <td data-label="Total" style="font-weight: 700;">{{ $currency }}{{ number_format($order->total, 2) }}</td>
                        <td data-label="Status">{!! $statusBadge !!}</td>
                        <td>
                            <div class="action-btn-group">
                                <button type="button" class="table-icon-btn btn-view-order" 
                                    data-order='{"id":{{ $order->id }},"order_number":"#ORD-{{ $order->id }}","customer_name":"{{ addslashes($custName) }}","order_type":"{{ strtoupper($order->order_type ?? "STORE POS") }}","items_summary":"Assorted Bakery Items","total_amount":"{{ $order->total }}","status":"{{ strtoupper($order->order_status ?? "COMPLETED") }}"}' 
                                    title="View Order Details">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;" data-lang-key="no_recent_orders">
                            {{ __('messages.no_recent_orders') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card-bottom-action-container">
        <a href="{{ route('admin.orders') }}" class="btn-card-outline" data-lang-key="view_all_orders">{{ __('messages.view_all_orders') }}</a>
    </div>
</div>
    </div>
</div>

<!-- Order Detail Modal -->
<div class="modal-backdrop" id="view_order_modal">
    <div class="modal-box">
        <div class="modal-header">
            <h3 class="modal-title">Order Details</h3>
            <button type="button" class="modal-close" id="btn_close_view_modal">&times;</button>
        </div>
        <div class="modal-body" style="padding: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Order Code</label>
                    <div id="view_order_id" style="font-weight: 800; font-size: 1.1rem; color: var(--text-title);">-</div>
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Customer</label>
                    <div id="view_order_customer" style="font-weight: 700; color: var(--text-title);">-</div>
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Order Channel</label>
                    <div id="view_order_type" style="font-weight: 600;">-</div>
                </div>
                <div>
                    <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Status</label>
                    <div id="view_order_status" style="font-weight: 700; color: var(--primary);">-</div>
                </div>
            </div>
            <div style="margin-bottom: 1.25rem;">
                <label style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Items Summary</label>
                <div id="view_order_items" class="modal-item-summary-box" style="padding: 0.75rem; background: #faf6f0; border-radius: 8px; font-size: 0.875rem;">-</div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                <span style="font-weight: 700; font-size: 1rem;">Total Amount:</span>
                <span id="view_order_total" style="font-weight: 800; font-size: 1.35rem; color: #16a34a;">-</span>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-dashboard.js') }}"></script>
@endpush
