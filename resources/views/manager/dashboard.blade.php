@extends('layouts.app')

@section('title', 'Manager Dashboard - Bakery Management')
@section('body_class', 'is-catalog-page is-dashboard-page')
@section('header_title', 'Shift & Operations Management')
@section('header_subtitle', 'Oversee real daily production batches, floor inventory, and store orders')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/manager-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-dashboard.css') }}">
@endpush

@section('content')
<div class="dash-page-container">
    <div class="dash-scrollable-body" id="dash_scrollable_body">
<!-- Operational KPI Cards Grid -->
<div class="dash-stat-row" style="margin-bottom: 1.5rem;">
    <!-- Today's Orders -->
    <div class="stat-card">
        <div class="stat-icon-box orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Today's Orders</span>
            <div class="stat-value">{{ number_format($todayOrdersCount) }}</div>
            <span class="stat-trend up">Floor Active</span>
        </div>
    </div>

    <!-- Today's Sales -->
    <div class="stat-card">
        <div class="stat-icon-box green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 6v12"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Today's Sales</span>
            <div class="stat-value">{{ $currency }}{{ number_format($todaySalesSum, 2) }}</div>
            <span class="stat-trend up">Register Total</span>
        </div>
    </div>

    <!-- Active Baking Batches -->
    <div class="stat-card">
        <div class="stat-icon-box amber">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Active Batches</span>
            <div class="stat-value">{{ number_format($activeProductionsCount) }}</div>
            <span class="stat-trend neutral">{{ number_format($completedTodayUnits) }} units finished today</span>
        </div>
    </div>

    <!-- Low Stock Items -->
    <div class="stat-card">
        <div class="stat-icon-box red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Stock Alerts</span>
            <div class="stat-value">{{ number_format($lowStockCount) }}</div>
            <a href="{{ route('admin.ingredients') }}" class="stat-trend danger">Check Inventory &rarr;</a>
        </div>
    </div>
</div>

<div class="manager-grid">
    <!-- Today's Production Batches & Quotas (Real DB data) -->
    <div class="manager-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 class="manager-card-title" style="margin-bottom: 0;">Today's Baking Quotas & Batches</h2>
        </div>
        
        @forelse($todayProductions as $batch)
            @php
                $qty = max(1, $batch->quantity);
                $isDone = $batch->status === 'completed';
                $pct = $isDone ? 100 : ($batch->status === 'in_progress' ? 60 : 20);
                $badgeColor = match($batch->status) {
                    'completed' => '#10b981',
                    'in_progress' => '#3b82f6',
                    'cancelled' => '#ef4444',
                    default => '#f59e0b',
                };
            @endphp
            <div class="target-progress-item">
                <div class="target-label">
                    <div>
                        <span style="font-weight: 700; color: var(--text-title);">{{ $batch->product ? $batch->product->name : 'Batch #' . $batch->id }}</span>
                        <span style="font-size: 0.75rem; color: var(--text-muted); display: block;">
                            Baker: {{ $batch->baker ? $batch->baker->name : 'Unassigned' }} &bull; Batch #{{ $batch->batch_number ?? $batch->id }}
                        </span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-weight: 800; color: {{ $badgeColor }}; font-size: 0.8rem; text-transform: uppercase;">{{ str_replace('_', ' ', $batch->status) }}</span>
                        <span style="display: block; font-size: 0.8rem; color: var(--text-muted);">{{ $qty }} units</span>
                    </div>
                </div>
                <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: {{ $pct }}%; background-color: {{ $badgeColor }};"></div>
                </div>
            </div>
        @empty
            <div style="text-align: center; color: var(--text-muted); padding: 2rem 1rem; font-size: 0.9rem;">
                <p>No production batches scheduled for today yet.</p>
                <a href="{{ route('admin.production') }}" class="btn-card-outline" style="display: inline-block; margin-top: 0.75rem;">Schedule Production</a>
            </div>
        @endforelse
    </div>

    <!-- Inventory Alerts & Procurement -->
    <div class="manager-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h2 class="manager-card-title" style="margin-bottom: 0;">Floor Inventory & Replenishment</h2>
        </div>

        <div style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
            <div class="status-dist-box" style="flex: 1; text-align: center; padding: 0.85rem;">
                <span class="status-dist-label">Pending POs</span>
                <span class="status-dist-val" style="color: #d97706; display: block;">{{ $pendingPosCount }}</span>
                <a href="{{ route('admin.purchase-orders.index') }}" style="font-size: 0.725rem; color: #563020; font-weight: 700;">Manage POs &rarr;</a>
            </div>
            <div class="status-dist-box" style="flex: 1; text-align: center; padding: 0.85rem;">
                <span class="status-dist-label">Active Suppliers</span>
                <span class="status-dist-val" style="color: #563020; display: block;">{{ $suppliersCount }}</span>
                <a href="{{ route('admin.suppliers') }}" style="font-size: 0.725rem; color: #563020; font-weight: 700;">Suppliers &rarr;</a>
            </div>
        </div>

        <div class="stock-alerts-list">
            @forelse($lowStockIngredients as $ing)
                @php
                    $isCritical = $ing->quantity <= 0 || $ing->quantity <= ($ing->minimum_quantity / 2);
                    $tagClass = $isCritical ? 'critical' : 'low';
                    $tagLabel = $ing->quantity <= 0 ? 'Out of Stock' : ($isCritical ? 'Critical' : 'Low Stock');
                @endphp
                <div class="stock-alert-item" style="padding: 0.65rem 0.85rem;">
                    <div class="stock-item-left">
                        <div class="stock-item-info">
                            <div class="item-name" style="font-size: 0.875rem;">{{ $ing->name }}</div>
                            <div class="item-qty" style="font-size: 0.75rem;">{{ $ing->quantity }} {{ $ing->unit }} (Min: {{ $ing->minimum_quantity }})</div>
                        </div>
                    </div>
                    <span class="alert-tag {{ $tagClass }}" style="font-size: 0.7rem; padding: 0.2rem 0.5rem;">{{ $tagLabel }}</span>
                </div>
            @empty
                <div style="text-align: center; color: var(--text-muted); padding: 1.5rem 0.5rem; font-size: 0.85rem;">
                    All ingredient inventory levels are healthy!
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
