@extends('layouts.app')

@section('title', __('messages.production_management') . ' - Bakery System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/production.css') }}?v={{ @filemtime(public_path('css/production.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}?v={{ @filemtime(public_path('css/orders.css')) ?: '1.0' }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.production', array_merge(request()->except('tab'), ['tab' => 'all'])) }}"
           class="period-pill {{ (!request()->filled('tab') || request('tab') === 'all') ? 'active' : '' }}">
            <span data-lang-key="all_batches">{{ __('messages.all_batches') }}</span> ({{ $stats['total'] }})
        </a>
        <a href="{{ route('admin.production', array_merge(request()->except('tab'), ['tab' => 'active'])) }}"
           class="period-pill {{ request('tab') === 'active' ? 'active' : '' }}">
            <span data-lang-key="active_batches">{{ __('messages.active_batches') }}</span> ({{ $stats['scheduled'] + $stats['in_progress'] }})
        </a>
        <a href="{{ route('admin.production', array_merge(request()->except('tab'), ['tab' => 'history'])) }}"
           class="period-pill {{ request('tab') === 'history' ? 'active' : '' }}">
            <span data-lang-key="completed_history">{{ __('messages.completed_history') }}</span> ({{ $stats['completed'] + $stats['cancelled'] }})
        </a>
        @if(auth()->user()->role === 'baker')
            <a href="{{ route('admin.production', array_merge(request()->except('tab'), ['tab' => 'mine'])) }}"
               class="period-pill {{ request('tab') === 'mine' ? 'active' : '' }}">
                <span data-lang-key="assigned_to_me">{{ __('messages.assigned_to_me') }}</span> ({{ $stats['my_batches'] }})
            </a>
        @endif
    </div>
@endsection

@section('content')
<div class="orders-container">

    <!-- Screen Reader & Compatibility Heading -->
    <div class="production-hero-title sr-only">
        <h1>Production Management & Baking Batches</h1>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if(session('error'))
        <x-alert type="danger" :message="session('error')" />
    @endif

    <!-- 1. 4 Core KPI Stat Cards Grid (Exact Orders / Dashboard Page Style) -->
    <div class="dash-stat-row prod-stats-grid">
        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label" data-lang-key="all_batches">{{ __('messages.all_batches') }}</span>
                <div class="stat-value">{{ number_format($stats['total']) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Total Batches
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label" data-lang-key="scheduled">{{ __('messages.scheduled') }}</span>
                <div class="stat-value">{{ number_format($stats['scheduled']) }}</div>
                <span class="stat-trend {{ $stats['scheduled'] > 0 ? 'warning' : 'up' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Awaiting Oven
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label" data-lang-key="in_progress">{{ __('messages.in_progress') }}</span>
                <div class="stat-value">{{ number_format($stats['in_progress']) }}</div>
                <span class="stat-trend {{ $stats['in_progress'] > 0 ? 'warning' : 'neutral' }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Baking Active
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box green">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label" data-lang-key="completed">{{ __('messages.completed') }}</span>
                <div class="stat-value">{{ number_format($stats['completed']) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    {{ number_format($stats['completed_units']) }} Units Baked
                </span>
            </div>
        </div>
    </div>

    <!-- 2. Action & Filter Toolbar Row (Exact Orders & Ingredients Style) -->
    <div class="ingredients-toolbar-row" id="production_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; background: transparent !important; padding: 0 !important; border: none !important;">
        <!-- Left: Filters Form -->
        <form method="GET" action="{{ route('admin.production') }}" id="prod_filter_form" class="prod-toolbar-card" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap; margin: 0; background: transparent !important; border: none !important; padding: 0 !important;">
            <input type="hidden" name="tab" value="{{ $tab }}">

            @if(request('search'))
                <input type="hidden" name="search" id="prod_search_input" value="{{ request('search') }}">
            @endif

            <!-- Pill Filter Group -->
            <div class="supplier-filter-pill-card">
                <select name="product_id" id="prod_product_select" onchange="this.form.submit()">
                    <option value="all">All Products</option>
                    @foreach($products as $prod)
                        <option value="{{ $prod->id }}" {{ request('product_id') == $prod->id ? 'selected' : '' }}>
                            {{ $prod->name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" id="prod_status_select" onchange="this.form.submit()" style="border-left: 1px solid var(--border-color, #333);">
                    <option value="all">All Statuses</option>
                    <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>{{ __('messages.scheduled') }}</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>{{ __('messages.in_progress') }}</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>{{ __('messages.completed') }}</option>
                    <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>{{ __('messages.cancelled') }}</option>
                </select>

                <button type="submit" class="btn" style="border-radius: 50px; padding: 0.35rem 1rem; font-weight: 700; height: 32px; display: inline-flex; align-items: center; justify-content: center; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.825rem;">Filter</button>

                @if(request()->hasAny(['search', 'status', 'product_id', 'baker_id']) && (request('search') || (request('status') && request('status') !== 'all') || (request('product_id') && request('product_id') !== 'all') || (request('baker_id') && request('baker_id') !== 'all')))
                    <a href="{{ route('admin.production', ['tab' => $tab]) }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.35rem 0.85rem; font-weight: 600; height: 32px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem;">Clear</a>
                @endif
            </div>
        </form>

        <!-- Right: Action Button -->
        @if(in_array(auth()->user()->role, ['admin', 'manager']))
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <button type="button" class="btn" id="btn_open_prod_modal" style="border-radius: 50px; height: 36px; padding: 0 1.15rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>{{ __('messages.schedule_production_batch') }}</span>
                </button>
            </div>
        @endif
    </div>

    <!-- 3. Production Batches Table Card (Full Viewport Height with internal scrolling) -->
    <div class="orders-card prod-table-card" id="production_table_card">
        <div class="orders-table-wrapper" id="production_table_wrapper">
            <table class="orders-table prod-table" id="production_batches_table">
                <thead>
                    <tr>
                        <th style="width: 14%;">{{ __('messages.batch_number') }}</th>
                        <th style="width: 20%;">Product & Recipe</th>
                        <th style="width: 6%;">Qty</th>
                        <th style="width: 13%;">{{ __('messages.baker') }}</th>
                        <th style="width: 14%;">{{ __('messages.scheduled_at') }}</th>
                        <th style="width: 12%;">{{ __('messages.status') }}</th>
                        <th style="width: 21%; text-align: right;">{{ __('messages.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $batch)
                        @php
                            $statusClass = strtolower($batch->status);
                            $canStart = $batch->isScheduled();
                            $canComplete = $batch->isInProgress();
                            $canCancel = in_array($statusClass, ['scheduled', 'in_progress']);
                            $isAssignedToUser = auth()->check() && (auth()->user()->role !== 'baker' || auth()->id() === $batch->baker_id);

                            $batchData = [
                                'id' => $batch->id,
                                'batch_number' => $batch->batch_number ?? ('PROD-' . str_pad($batch->id, 4, '0', STR_PAD_LEFT)),
                                'status' => $statusClass,
                                'status_label' => ucfirst(str_replace('_', ' ', $batch->status)),
                                'quantity' => $batch->quantity,
                                'scheduled_at' => $batch->scheduled_at ? $batch->scheduled_at->format('M d, Y h:i A') : ($batch->production_date ? $batch->production_date->format('M d, Y') : '-'),
                                'started_at' => $batch->started_at ? $batch->started_at->format('M d, Y h:i A') : null,
                                'completed_at' => $batch->completed_at ? $batch->completed_at->format('M d, Y h:i A') : null,
                                'created_at' => $batch->created_at ? $batch->created_at->format('M d, Y h:i A') : null,
                                'duration' => $batch->duration,
                                'notes' => $batch->notes,
                                'product' => [
                                    'name' => $batch->product->name ?? 'Bakery Product',
                                    'image_emoji' => $batch->product->image_emoji ?? '🥖',
                                    'category' => $batch->product->category->name ?? 'Bakery',
                                    'stock' => $batch->product->stock ?? 0,
                                    'sku' => $batch->product->sku ?? 'N/A',
                                ],
                                'recipe' => $batch->recipe ? [
                                    'name' => $batch->recipe->name,
                                    'instructions' => $batch->recipe->instructions ?? $batch->recipe->description ?? '',
                                ] : null,
                                'baker' => [
                                    'name' => $batch->baker->name ?? 'Unassigned',
                                    'initials' => $batch->baker ? strtoupper(substr($batch->baker->name, 0, 2)) : 'UB',
                                    'role' => $batch->baker ? ucfirst($batch->baker->role) : 'N/A',
                                ],
                                'creator' => [
                                    'name' => $batch->creator->name ?? 'System',
                                ],
                                'ingredients' => $batch->getRequiredIngredients()->values()->all(),
                                'can_start' => $canStart && $isAssignedToUser,
                                'can_complete' => $canComplete && $isAssignedToUser,
                                'can_cancel' => $canCancel && $isAssignedToUser,
                                'update_url' => route('admin.production.updateStatus', $batch->id),
                            ];
                        @endphp
                        <tr class="prod-batch-row" data-batch-id="{{ $batch->id }}" data-status="{{ $statusClass }}" data-batch="{{ json_encode($batchData) }}">
                            <!-- Batch Number -->
                            <td>
                                <button type="button" class="batch-code-pill" onclick="openBatchDetailModal(this)" title="View Details">
                                    {{ $batch->batch_number ?? ('PROD-' . str_pad($batch->id, 4, '0', STR_PAD_LEFT)) }}
                                </button>
                            </td>

                            <!-- Product -->
                            <td>
                                <div>
                                    <div class="prod-product-name">{{ $batch->product->name ?? 'Unknown Product' }}</div>
                                    <div class="prod-recipe-sub">
                                        @if($batch->recipe)
                                            <span>{{ $batch->recipe->name }}</span>
                                        @else
                                            <span style="color: #a1a1aa; font-style: italic;">No Recipe</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            <!-- Quantity -->
                            <td>
                                <span class="prod-qty-num">{{ $batch->quantity }}</span>
                                <span class="prod-qty-unit">units</span>
                            </td>

                            <!-- Assigned Baker -->
                            <td>
                                @if($batch->baker)
                                    <div class="baker-pill" title="{{ $batch->baker->name }}">
                                        <span class="baker-avatar">
                                            {{ strtoupper(substr($batch->baker->name, 0, 2)) }}
                                        </span>
                                        <span class="baker-name">{{ $batch->baker->name }}</span>
                                    </div>
                                @else
                                    <span style="color: #a1a1aa; font-style: italic; font-size: 0.74rem;">Unassigned</span>
                                @endif
                            </td>

                            <!-- Schedule & Timeline -->
                            <td>
                                <div class="prod-schedule-date">
                                    {{ $batch->scheduled_at ? $batch->scheduled_at->format('M d, Y h:i A') : ($batch->production_date ? $batch->production_date->format('M d, Y') : '-') }}
                                </div>
                                @if($batch->duration)
                                    <div class="prod-duration-badge">
                                        Duration: {{ $batch->duration }}
                                    </div>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td>
                                <x-badge :status="$batch->status" class="badge-status {{ $statusClass }}" />
                            </td>

                            <!-- Actions -->
                            <td style="text-align: right;">
                                <div class="prod-actions-row">
                                    <!-- Start Production Button (Scheduled -> In Progress) -->
                                    @if($canStart && $isAssignedToUser)
                                        <form action="{{ route('admin.production.updateStatus', $batch->id) }}" method="POST" style="display: inline-flex; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="status" value="in_progress">
                                            <button type="submit" class="btn-prod-action btn-prod-start" title="Start Baking" aria-label="Start Baking">
                                                <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                                <span>Start</span>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Complete Production Button (In Progress -> Completed) -->
                                    @if($canComplete && $isAssignedToUser)
                                        <form action="{{ route('admin.production.updateStatus', $batch->id) }}" method="POST" style="display: inline-flex; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="status" value="completed">
                                            <button type="submit" class="btn-prod-action btn-prod-complete" title="Complete Baking" aria-label="Complete Baking">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                                <span>Complete</span>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- Cancel Button -->
                                    @if($canCancel && $isAssignedToUser)
                                        <form action="{{ route('admin.production.updateStatus', $batch->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this production batch?');" style="display: inline-flex; margin: 0;">
                                            @csrf
                                            <input type="hidden" name="status" value="cancelled">
                                            <button type="submit" class="btn-prod-icon cancel" title="Cancel Production" aria-label="Cancel Production">
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                            </button>
                                        </form>
                                    @endif

                                    <!-- View Details Modal Trigger Button ("Cart" Detail Modal) -->
                                    <button type="button" class="btn-prod-icon view" onclick="openBatchDetailModal(this)" title="View Batch Details" aria-label="View Batch Details">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>

                                    <!-- Delete Button (Only Scheduled or Cancelled, Admin/Manager Only) -->
                                    @if(in_array(auth()->user()->role, ['admin', 'manager']) && ($batch->isScheduled() || $batch->isCancelled()))
                                        <form action="{{ route('admin.production.destroy', $batch->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete production batch #{{ $batch->batch_number }}?');" style="display: inline-flex; margin: 0;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-prod-icon delete" title="Delete Batch" aria-label="Delete Batch">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted, #71717a);">
                                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🥖</div>
                                <div style="font-weight: 700; font-size: 1rem; color: inherit;">No production batches found</div>
                                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Try adjusting your search criteria or schedule a new production batch above.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================================================
     CREATE / SCHEDULE PRODUCTION BATCH MODAL
     ======================================================================== -->
<div class="prod-modal-overlay" id="modal_schedule_batch" style="display: none;">
    <div class="prod-modal-content">
        <div class="prod-modal-header">
            <h3 class="prod-modal-title" data-lang-key="schedule_production_batch">
                {{ __('messages.schedule_production_batch') }}
            </h3>
            
        </div>

        <form action="{{ route('admin.production.store') }}" method="POST" id="form_create_production">
            @csrf

            <div class="prod-form-grid">
                <!-- Product Selection -->
                <div class="prod-form-group full-width">
                    <label class="prod-form-label" for="prod_select_product">
                        {{ __('messages.product') }} *
                    </label>
                    <select name="product_id" id="prod_select_product" class="prod-form-select" required>
                        <option value="">-- Choose Product to Bake --</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" data-has-recipe="{{ $p->recipe ? 'true' : 'false' }}">
                                {{ $p->image_emoji ?? '' }} {{ $p->name }} (Current Stock: {{ $p->stock }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Quantity to Produce -->
                <div class="prod-form-group">
                    <label class="prod-form-label" for="prod_input_qty">
                        {{ __('messages.quantity') }} *
                    </label>
                    <input type="number" name="quantity" id="prod_input_qty" class="prod-form-input" value="10" min="1" required>
                </div>

                <!-- Assign Baker -->
                <div class="prod-form-group">
                    <label class="prod-form-label" for="prod_select_baker">
                        {{ __('messages.assign_baker') }}
                    </label>
                    <select name="baker_id" id="prod_select_baker" class="prod-form-select">
                        @foreach($bakers as $baker)
                            @php
                                $bName = $baker->name;
                                $bRole = $baker->role;
                                if ($bRole && !str_contains(strtolower($bName), strtolower($bRole)) && !str_contains($bName, '(')) {
                                    $bakerDisplay = "{$bName} (" . ucfirst($bRole) . ")";
                                } else {
                                    $bakerDisplay = $bName;
                                }
                            @endphp
                            <option value="{{ $baker->id }}" {{ $baker->role === 'baker' ? 'selected' : '' }}>
                                {{ $bakerDisplay }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Scheduled Date & Time -->
                <div class="prod-form-group">
                    <label class="prod-form-label" for="prod_input_scheduled_at">
                        {{ __('messages.scheduled_at') }}
                    </label>
                    <input type="datetime-local" name="scheduled_at" id="prod_input_scheduled_at" class="prod-form-input"
                           value="{{ now()->format('Y-m-d\TH:i') }}">
                </div>

                <!-- Initial Status -->
                <div class="prod-form-group">
                    <label class="prod-form-label" for="prod_select_status">
                        Initial {{ __('messages.status') }}
                    </label>
                    <select name="status" id="prod_select_status" class="prod-form-select">
                        <option value="scheduled" selected>{{ __('messages.scheduled') }} (Plan ahead, no stock change)</option>
                        <option value="in_progress">{{ __('messages.in_progress') }} (Deduct ingredients now)</option>
                        <option value="completed">{{ __('messages.completed') }} (Deduct ingredients & increase product stock)</option>
                    </select>
                </div>

                <!-- Notes -->
                <div class="prod-form-group full-width">
                    <label class="prod-form-label" for="prod_textarea_notes">
                        {{ __('messages.notes') }}
                    </label>
                    <textarea name="notes" id="prod_textarea_notes" class="prod-form-textarea" rows="2" placeholder="e.g. Special order for morning catering, double proofed..."></textarea>
                </div>
            </div>

            <!-- LIVE INGREDIENT REQUIREMENT PREVIEW -->
            <div class="prod-ingredients-preview-box" id="preview_ingredients_container" style="display: none; margin-top: 1rem;">
                <div class="preview-box-header">
                    <span class="preview-box-title" data-lang-key="ingredient_requirements">{{ __('messages.ingredient_requirements') }}</span>
                    <span class="preview-recipe-badge" id="preview_recipe_badge">Recipe</span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="preview-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Unit Need</th>
                                <th>Batch Total</th>
                                <th>In Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="preview_ingredients_tbody">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>
                </div>

                <div id="preview_shortage_alert" style="display: none; color: #dc2626; font-size: 0.775rem; font-weight: 700; background: #fef2f2; padding: 0.5rem; border-radius: 6px; margin-top: 0.25rem;">
                    ⚠ Warning: One or more ingredients have insufficient inventory stock!
                </div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color, #e4e4e7); padding-top: 1rem;">
                <x-button variant="secondary" id="btn_cancel_schedule">Cancel</x-button>
                <x-button variant="primary" type="submit" class="btn-schedule-batch" id="btn_submit_production">
                    Save Production Batch
                </x-button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================================
     BATCH DETAIL MODAL CARD ("CART")
     ======================================================================== -->
<div id="batchDetailModal" class="order-detail-modal-backdrop" onclick="if(event.target === this) closeBatchDetailModal()">
    <div class="order-detail-modal-card prod-modal-card-detail">
        <!-- Modal Header -->
        <div class="order-detail-modal-header">
            <div class="order-detail-modal-header-left">
                <div class="order-detail-modal-badge" id="modal_batch_code">BATCH-0001</div>
                <div>
                    <h3 class="order-detail-modal-title">Production Details</h3>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div id="modal_batch_status_badge">
                    <!-- Status badge injected here -->
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="order-detail-modal-body">
            <!-- Product & Quantity Banner -->
            <div class="prod-detail-banner-card">
                <div class="prod-detail-banner-left">
                    <span class="prod-detail-banner-emoji" id="modal_batch_product_emoji">🥖</span>
                    <div style="min-width: 0;">
                        <div class="prod-detail-banner-name" id="modal_batch_product_name">Product Name</div>
                        <div class="prod-detail-banner-sub" id="modal_batch_product_sub">Bakery</div>
                    </div>
                </div>
                <div class="prod-detail-banner-qty">
                    <span class="prod-detail-banner-qty-val" id="modal_batch_qty_val">0</span>
                    <span class="prod-detail-banner-qty-lbl">To Bake</span>
                </div>
            </div>

            <!-- Lifecycle Timeline -->
            <div class="prod-modal-timeline-grid">
                <div class="prod-modal-timeline-item">
                    <div class="timeline-step-dot scheduled"></div>
                    <div style="min-width: 0;">
                        <div class="timeline-step-label">Scheduled</div>
                        <div class="timeline-step-val" id="modal_timeline_scheduled">-</div>
                    </div>
                </div>
                <div class="prod-modal-timeline-item">
                    <div class="timeline-step-dot in-progress" id="modal_dot_started"></div>
                    <div style="min-width: 0;">
                        <div class="timeline-step-label">Started Baking</div>
                        <div class="timeline-step-val" id="modal_timeline_started">-</div>
                    </div>
                </div>
                <div class="prod-modal-timeline-item">
                    <div class="timeline-step-dot completed" id="modal_dot_completed"></div>
                    <div style="min-width: 0;">
                        <div class="timeline-step-label" id="modal_timeline_completed_label">Completed</div>
                        <div class="timeline-step-val" id="modal_timeline_completed">-</div>
                    </div>
                </div>
            </div>

            <!-- Personnel & Recipe 2-column Grid -->
            <div class="order-detail-info-grid">
                <div class="order-detail-info-item">
                    <span class="info-label">Assigned Baker</span>
                    <div style="display: flex; align-items: center; gap: 0.45rem; margin-top: 0.25rem;">
                        <span class="baker-avatar" id="modal_batch_baker_avatar">UB</span>
                        <span class="info-value" id="modal_batch_baker_name" style="margin: 0;">Unassigned</span>
                    </div>
                </div>
                <div class="order-detail-info-item">
                    <span class="info-label">Recipe</span>
                    <span class="info-value" id="modal_batch_recipe_name" style="margin-top: 0.25rem; color: #ea580c;">Standard Recipe</span>
                </div>
            </div>

            <!-- Special Notes if any -->
            <div id="modal_batch_notes_box" class="order-detail-note-box" style="display: none;">
                <div style="display: flex; align-items: center; gap: 0.4rem; font-weight: 700; font-size: 0.75rem;">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.85;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
                    <span>Notes:</span>
                </div>
                <span id="modal_batch_notes_text" class="note-text" style="font-size: 0.8rem; line-height: 1.4;"></span>
            </div>

            <!-- Itemized Ingredients Breakdown Section -->
            <div class="order-detail-items-section">
                <div class="items-header">
                    <span>Required Ingredients</span>
                    <span id="modal_batch_ingredients_count" class="items-count-pill">0 ingredients</span>
                </div>
                <div class="prod-modal-ingredients-wrapper">
                    <table class="prod-modal-ing-table">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Required</th>
                                <th>In Stock</th>
                                <th style="text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="modal_batch_ingredients_tbody">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Modal Footer Actions -->
            <div class="order-detail-modal-actions" style="display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 0.75rem; flex-wrap: wrap;">
                <div id="modal_batch_status_actions" style="display: flex; align-items: center; gap: 0.5rem;">
                    <!-- Dynamic Start / Complete / Cancel forms -->
                </div>
                <button type="button" id="btn_close_batch_detail_modal" onclick="closeBatchDetailModal()" class="btn-order-detail-close">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    window.PRODUCTS_CATALOG = @json($productsJson);
    const CSRF_TOKEN = '{{ csrf_token() }}';

    window.openBatchDetailModal = function (triggerEl) {
        let batch = null;

        if (typeof triggerEl === 'object' && triggerEl !== null) {
            const row = triggerEl.closest('tr');
            if (row) {
                const rawData = row.getAttribute('data-batch');
                if (rawData) {
                    try {
                        batch = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
                    } catch (e) {
                        console.error('Failed to parse batch JSON:', e);
                    }
                }
            }
        } else if (typeof triggerEl === 'number' || typeof triggerEl === 'string') {
            const row = document.querySelector(`tr[data-batch-id="${triggerEl}"]`);
            if (row) {
                const rawData = row.getAttribute('data-batch');
                if (rawData) {
                    try {
                        batch = typeof rawData === 'string' ? JSON.parse(rawData) : rawData;
                    } catch (e) {}
                }
            }
        }

        if (batch) {
            populateBatchDetailModal(batch);
            const modal = document.getElementById('batchDetailModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        } else {
            // Fallback: Fetch from server via AJAX
            const batchId = (typeof triggerEl === 'object' && triggerEl?.closest('tr'))
                ? triggerEl.closest('tr').getAttribute('data-batch-id')
                : triggerEl;
            if (!batchId) return;

            fetch(`/admin/production/${batchId}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.batch) {
                    populateBatchDetailModal(data.batch);
                    const modal = document.getElementById('batchDetailModal');
                    if (modal) {
                        modal.style.display = 'flex';
                        document.body.style.overflow = 'hidden';
                    }
                }
            })
            .catch(err => console.error('Error loading batch:', err));
        }
    };

    window.populateBatchDetailModal = function (batch) {
        // 1. Batch header
        document.getElementById('modal_batch_code').textContent = batch.batch_number || `#${batch.id}`;

        // 2. Status badge
        const statusMap = {
            'scheduled': { text: 'Scheduled', class: 'scheduled' },
            'in_progress': { text: 'In Progress', class: 'in_progress' },
            'completed': { text: 'Completed', class: 'completed' },
            'cancelled': { text: 'Cancelled', class: 'cancelled' }
        };
        const st = statusMap[batch.status] || { text: batch.status_label || batch.status, class: batch.status };
        document.getElementById('modal_batch_status_badge').innerHTML = `<span class="badge-status ${st.class}">${st.text}</span>`;

        // 3. Product & Quantity
        document.getElementById('modal_batch_product_emoji').textContent = batch.product?.image_emoji || '🥖';
        document.getElementById('modal_batch_product_name').textContent = batch.product?.name || 'Bakery Product';
        document.getElementById('modal_batch_product_sub').textContent = batch.product?.category || 'Bakery';
        document.getElementById('modal_batch_qty_val').textContent = `${batch.quantity} units`;

        // 4. Lifecycle timeline
        document.getElementById('modal_timeline_scheduled').textContent = batch.scheduled_at || '-';
        document.getElementById('modal_timeline_started').textContent = batch.started_at || (batch.status === 'scheduled' ? 'Pending' : '-');

        const completedDot = document.getElementById('modal_dot_completed');
        const completedLabel = document.getElementById('modal_timeline_completed_label');
        const startedDot = document.getElementById('modal_dot_started');

        if (startedDot) {
            startedDot.className = 'timeline-step-dot in-progress' + (batch.started_at ? ' active' : '');
        }

        if (batch.status === 'cancelled') {
            completedLabel.textContent = 'Cancelled';
            document.getElementById('modal_timeline_completed').textContent = 'Cancelled';
            if (completedDot) completedDot.className = 'timeline-step-dot cancelled active';
        } else if (batch.completed_at) {
            completedLabel.textContent = 'Completed';
            document.getElementById('modal_timeline_completed').textContent = batch.completed_at;
            if (completedDot) completedDot.className = 'timeline-step-dot completed active';
        } else {
            completedLabel.textContent = 'Completed';
            document.getElementById('modal_timeline_completed').textContent = batch.status === 'in_progress' ? '—' : 'Pending';
            if (completedDot) completedDot.className = 'timeline-step-dot completed';
        }

        // 5. Personnel & Recipe
        document.getElementById('modal_batch_baker_avatar').textContent = batch.baker?.initials || 'UB';
        const bName = batch.baker?.name || 'Unassigned';
        const bRole = batch.baker?.role;
        if (bRole && !bName.toLowerCase().includes(bRole.toLowerCase()) && !bName.includes('(')) {
            document.getElementById('modal_batch_baker_name').textContent = `${bName} (${bRole})`;
        } else {
            document.getElementById('modal_batch_baker_name').textContent = bName;
        }

        document.getElementById('modal_batch_recipe_name').textContent = batch.recipe?.name || 'Standard Recipe';

        // 6. Notes (clean custom notes only, no redundant dump)
        const notesBox = document.getElementById('modal_batch_notes_box');
        const notesText = document.getElementById('modal_batch_notes_text');
        const notes = (batch.notes || '').trim();

        if (notes && notes !== 'No notes provided.') {
            notesText.textContent = notes;
            notesBox.style.display = 'flex';
        } else {
            notesBox.style.display = 'none';
        }

        // 7. Itemized Ingredients Breakdown
        const tbody = document.getElementById('modal_batch_ingredients_tbody');
        const countPill = document.getElementById('modal_batch_ingredients_count');
        tbody.innerHTML = '';
        const ingredients = batch.ingredients || [];
        countPill.textContent = `${ingredients.length} ${ingredients.length === 1 ? 'ingredient' : 'ingredients'}`;

        if (ingredients.length === 0) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: #a1a1aa; padding: 1rem;">No ingredients required for this batch.</td></tr>`;
        } else {
            ingredients.forEach(ing => {
                const tr = document.createElement('tr');
                const isSufficient = ing.is_sufficient;
                tr.innerHTML = `
                    <td style="font-weight: 600;">${ing.name}</td>
                    <td style="font-weight: 700;">${ing.required_total} ${ing.unit}</td>
                    <td>${ing.current_stock} ${ing.unit}</td>
                    <td style="text-align: right;">
                        <span class="badge-status ${isSufficient ? 'completed' : 'cancelled'}" style="font-size: 0.68rem; padding: 0.15rem 0.45rem;">
                            ${isSufficient ? '✓ In Stock' : '⚠ Short (' + ing.shortage + ' ' + ing.unit + ')'}
                        </span>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        // 8. Actions
        const actionsContainer = document.getElementById('modal_batch_status_actions');
        actionsContainer.innerHTML = '';

        if (batch.can_start) {
            actionsContainer.innerHTML += `
                <form action="${batch.update_url}" method="POST" style="display: inline-flex; margin: 0;">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="status" value="in_progress">
                    <button type="submit" class="btn-prod-action btn-prod-start" style="height: 32px; padding: 0 0.9rem; font-size: 0.76rem;">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="currentColor"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                        <span>Start Baking</span>
                    </button>
                </form>
            `;
        }

        if (batch.can_complete) {
            actionsContainer.innerHTML += `
                <form action="${batch.update_url}" method="POST" style="display: inline-flex; margin: 0;">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="btn-prod-action btn-prod-complete" style="height: 32px; padding: 0 0.9rem; font-size: 0.76rem;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>Complete Production</span>
                    </button>
                </form>
            `;
        }

        if (batch.can_cancel) {
            actionsContainer.innerHTML += `
                <form action="${batch.update_url}" method="POST" onsubmit="return confirm('Cancel this batch?');" style="display: inline-flex; margin: 0;">
                    <input type="hidden" name="_token" value="${CSRF_TOKEN}">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="btn-order-detail-close" style="color: #ef4444 !important; border-color: rgba(239, 68, 68, 0.3) !important; background: rgba(239, 68, 68, 0.08) !important; padding: 0.45rem 0.9rem !important; font-size: 0.74rem !important;">
                        Cancel Batch
                    </button>
                </form>
            `;
        }
    };

    window.closeBatchDetailModal = function () {
        const modal = document.getElementById('batchDetailModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    };

    // Close on escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeBatchDetailModal();
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('modal_schedule_batch');
        const openBtn = document.getElementById('btn_open_prod_modal');
        const closeBtn = document.getElementById('btn_close_schedule_modal');
        const cancelBtn = document.getElementById('btn_cancel_schedule');

        const productSelect = document.getElementById('prod_select_product');
        const qtyInput = document.getElementById('prod_input_qty');
        const previewContainer = document.getElementById('preview_ingredients_container');
        const previewBadge = document.getElementById('preview_recipe_badge');
        const previewTbody = document.getElementById('preview_ingredients_tbody');
        const shortageAlert = document.getElementById('preview_shortage_alert');

        // Modal open/close
        if (openBtn && modal) {
            openBtn.addEventListener('click', () => {
                modal.style.display = 'flex';
                updateIngredientPreview();
            });
        }

        [closeBtn, cancelBtn].forEach(btn => {
            if (btn && modal) {
                btn.addEventListener('click', () => modal.style.display = 'none');
            }
        });

        // Close on backdrop click
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) modal.style.display = 'none';
            });
        }

        // Live calculation of ingredients on product / quantity change
        function updateIngredientPreview() {
            if (!productSelect || !qtyInput) return;
            const productId = productSelect.value;
            const batchQty = parseInt(qtyInput.value) || 0;

            if (!productId || !window.PRODUCTS_CATALOG || !window.PRODUCTS_CATALOG[productId]) {
                previewContainer.style.display = 'none';
                return;
            }

            const prodData = window.PRODUCTS_CATALOG[productId];
            if (!prodData.recipe || !prodData.recipe.ingredients || prodData.recipe.ingredients.length === 0) {
                previewContainer.style.display = 'block';
                previewBadge.textContent = 'No Recipe Configured';
                previewTbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: #a1a1aa; padding: 0.5rem;">No linked recipe. No ingredients will be deducted.</td></tr>`;
                shortageAlert.style.display = 'none';
                return;
            }

            previewContainer.style.display = 'block';
            previewBadge.textContent = prodData.recipe.name;
            previewTbody.innerHTML = '';
            let hasShortage = false;

            prodData.recipe.ingredients.forEach(ing => {
                const totalRequired = (ing.unit_quantity * batchQty).toFixed(2);
                const currentStock = parseFloat(ing.current_stock).toFixed(2);
                const isSufficient = parseFloat(currentStock) >= parseFloat(totalRequired);

                if (!isSufficient) hasShortage = true;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td style="font-weight: 600;">${ing.name}</td>
                    <td>${ing.unit_quantity} ${ing.unit}</td>
                    <td style="font-weight: 700;">${totalRequired} ${ing.unit}</td>
                    <td>${currentStock} ${ing.unit}</td>
                    <td>
                        <span class="${isSufficient ? 'tag-in-stock' : 'tag-shortage'}">
                            ${isSufficient ? '✓ In Stock' : '⚠ Short'}
                        </span>
                    </td>
                `;
                previewTbody.appendChild(tr);
            });

            shortageAlert.style.display = hasShortage ? 'block' : 'none';
        }

        if (productSelect) productSelect.addEventListener('change', updateIngredientPreview);
        if (qtyInput) qtyInput.addEventListener('input', updateIngredientPreview);

        // Auto-open batch modal if navigated with open_batch parameter
        const urlParams = new URLSearchParams(window.location.search);
        const autoOpenBatch = urlParams.get('open_batch') || urlParams.get('batch');
        if (autoOpenBatch && window.openBatchDetailModal) {
            setTimeout(() => {
                window.openBatchDetailModal(autoOpenBatch);
            }, 60);
        }
    });
</script>
@endpush
@endsection
