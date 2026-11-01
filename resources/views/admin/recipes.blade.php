@extends('layouts.app')

@section('title', 'Bakery Formulas & Recipes - Bakery System')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.recipes', array_merge(request()->except('status', 'page'), ['status' => ''])) }}"
           class="period-pill {{ !request('status') ? 'active' : '' }}">
            <span>All Recipes ({{ $stats['total'] }})</span>
        </a>
        <a href="{{ route('admin.recipes', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}"
           class="period-pill {{ request('status') === 'active' ? 'active' : '' }}">
            <span>Active ({{ $stats['active'] }})</span>
        </a>
        <a href="{{ route('admin.recipes', array_merge(request()->except('status', 'page'), ['status' => 'inactive'])) }}"
           class="period-pill {{ request('status') === 'inactive' ? 'active' : '' }}">
            <span>Inactive ({{ $stats['inactive'] }})</span>
        </a>
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

    <!-- 4 Core KPI Stat Cards Grid (Exact Orders / Dashboard Page Style) -->
    <div class="dash-stat-row">
        <div class="stat-card">
            <div class="stat-icon-box orange">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Total Formulas</span>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Formula Catalog
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box yellow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Avg Baking Time</span>
                <div class="stat-value">{{ $stats['avg_time'] }} mins</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Oven Standard
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box teal">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Linked Products</span>
                <div class="stat-value">{{ $stats['linked_products'] }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Catalog Linked
                </span>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon-box blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            </div>
            <div class="stat-info">
                <span class="stat-label">Avg Unit Cost</span>
                <div class="stat-value">${{ number_format($stats['avg_unit_cost'], 2) }}</div>
                <span class="stat-trend up">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="18 15 12 9 6 15"/></svg>
                    Cost Efficiency
                </span>
            </div>
        </div>
    </div>

    <!-- Filter & Action Toolbar (Exact Orders & Ingredients Style) -->
    <div class="ingredients-toolbar-row" id="recipes_toolbar" style="margin-bottom: 0.75rem; flex-shrink: 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <!-- Left: Product Filter Pill Card -->
        <form method="GET" action="{{ route('admin.recipes') }}" id="recipes_filter_form" style="display: inline-flex; align-items: center; margin: 0;">
            @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
            @endif
            <div class="supplier-filter-pill-card">
                <select name="product_id" id="recipe_product_select">
                    <option value="all">All Products</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" {{ request('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn" style="border-radius: 50px; padding: 0.45rem 1.35rem; font-weight: 700; height: 34px; display: inline-flex; align-items: center; justify-content: center; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem;">Filter</button>
                @if(request()->filled('product_id') && request('product_id') !== 'all')
                    <a href="{{ route('admin.recipes', request('status') ? ['status' => request('status')] : []) }}" class="btn btn-secondary" style="border-radius: 50px; padding: 0.45rem 0.9rem; font-weight: 600; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-size: 0.825rem;">Clear</a>
                @endif
            </div>
        </form>

        <!-- Right: Add Recipe Action Button -->
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button type="button" class="btn" id="btn_open_recipe_modal" style="border-radius: 50px; height: 38px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 700; background: #f59e0b; border: 1px solid #f59e0b; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                <span>Add Recipe</span>
            </button>
        </div>
    </div>

    <!-- Recipes Table Card (Full Viewport Height with internal scrolling) -->
    <div class="orders-card" id="recipes_table_card">
        <div class="orders-table-wrapper" id="recipes_table_wrapper">
            <table class="orders-table" id="recipes_table">
                <thead>
                    <tr>
                        <th style="width: 22%;">Formula Name</th>
                        <th style="width: 16%;">Target Product</th>
                        <th style="width: 14%;">Bake Time & Temp</th>
                        <th style="width: 10%;">Yield</th>
                        <th style="width: 22%;">Ingredients Summary</th>
                        <th style="width: 10%;">Cost Per Unit</th>
                        <th style="width: 10%;">Total Cost</th>
                        <th style="width: 10%;">Status</th>
                        <th style="width: 10%; text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recipes as $recipe)
                        <tr>
                            <td>
                                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-primary, #0f172a);">
                                    {{ $recipe->recipe_name }}
                                </div>
                                @if($recipe->baking_instructions)
                                    <div style="font-size: 0.75rem; color: var(--text-muted, #71717a); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 240px;" title="{{ $recipe->baking_instructions }}">
                                        {{ $recipe->baking_instructions }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight: 700; font-size: 0.9rem; color: #ea580c;">
                                    {{ $recipe->product_name }}
                                </div>
                                <div style="font-size: 0.725rem; color: var(--text-muted, #71717a);">
                                    {{ $recipe->product && $recipe->product->category ? $recipe->product->category->name : 'Bakery Item' }}
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.85rem; font-weight: 600;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span>{{ $recipe->formatted_production_time }}</span>
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: var(--text-muted, #71717a); margin-top: 2px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/></svg>
                                    <span>{{ $recipe->bake_temp }}</span>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 50px; padding: 3px 10px; font-weight: 700; font-size: 0.8rem; white-space: nowrap;">
                                    {{ $recipe->yield_quantity }} pcs
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.825rem; color: var(--text-body, #334155); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 250px;" title="{{ $recipe->ingredients_summary }}">
                                    {{ $recipe->ingredients_summary }}
                                </div>
                                <div style="font-size: 0.725rem; color: var(--text-muted, #71717a); margin-top: 2px;">
                                    {{ $recipe->ingredients->count() }} ingredients used
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 800; font-size: 0.95rem; color: #10b981;">
                                    ${{ number_format($recipe->cost_per_unit, 2) }}
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 800; font-size: 0.95rem; color: var(--text-title, #29150d);">
                                    ${{ number_format($recipe->total_cost, 2) }}
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="border-radius: 50px; padding: 3px 10px; font-size: 0.75rem; font-weight: 700; {{ $recipe->status === 'active' ? 'background: rgba(16, 185, 129, 0.12); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.25);' : 'background: rgba(100, 116, 139, 0.12); color: #94a3b8; border: 1px solid rgba(100, 116, 139, 0.25);' }}">
                                    {{ ucfirst($recipe->status ?? 'active') }}
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; justify-content: flex-end; gap: 0.45rem;">
                                    <!-- Edit Button -->
                                    <button type="button" class="btn-icon-action btn-edit-recipe"
                                            title="Edit Formula"
                                            data-id="{{ $recipe->id }}"
                                            data-name="{{ $recipe->name }}"
                                            data-product="{{ $recipe->product_id }}"
                                            data-yield="{{ $recipe->yield_quantity }}"
                                            data-time="{{ $recipe->production_time }}"
                                            data-desc="{{ $recipe->description }}"
                                            data-instructions="{{ $recipe->instructions }}"
                                            data-status="{{ $recipe->status ?? 'active' }}"
                                            style="background: transparent; border: none; cursor: pointer; color: var(--text-muted, #71717a); padding: 5px; border-radius: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </button>

                                    <!-- Delete Button -->
                                    <button type="button" class="btn-icon-action delete btn-delete-recipe" title="Delete Recipe"
                                        data-id="{{ $recipe->id }}"
                                        data-name="{{ $recipe->recipe_name ?? 'Recipe' }}"
                                        style="background: transparent; border: none; cursor: pointer; color: #ef4444; padding: 5px; border-radius: 6px;">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
                            <td colspan="9" style="text-align: center; color: var(--text-muted, #71717a); padding: 3.5rem;">
                                <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">📖</div>
                                <div style="font-weight: 700; font-size: 1.05rem;">No recipes found</div>
                                <div style="font-size: 0.825rem; margin-top: 0.25rem;">Click "Add Recipe" to register your first bakery formula.</div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD RECIPE MODAL -->
<x-modal id="add_recipe_modal" title="Add Recipe Formula" maxWidth="560px" class="dash-modal-overlay" :showClose="false">
    <form action="{{ route('admin.recipes.store') }}" method="POST">
        @csrf
        <x-input name="name" label="Recipe Name" placeholder="e.g. Classic Butter Croissant" :required="true" />
        <x-select name="product_id" label="Bakery Product" :required="true">
            @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->category ? $p->category->name : 'Bakery' }})</option>
            @endforeach
        </x-select>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
            <x-input type="number" name="yield_quantity" label="Batch Yield (pcs)" value="1" min="1" placeholder="50" :required="true" />
            <x-input type="number" name="production_time" label="Baking Time (mins)" min="1" placeholder="45" :required="true" />
            <x-input name="bake_temp" label="Bake Temp" placeholder="190°C" />
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Ingredients Description</label>
            <textarea name="description" class="form-control-textarea" rows="2" placeholder="e.g. 10kg Flour, 6.5kg Butter, 500g Sugar, 250g Yeast, 180g Salt"></textarea>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Baking Notes / Oven Settings</label>
            <textarea name="instructions" class="form-control-textarea" rows="2" placeholder="Steps, dough folding notes, and oven temperature settings..."></textarea>
        </div>
        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_recipe">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Recipe</x-button>
        </div>
    </form>
</x-modal>

<!-- EDIT RECIPE MODAL -->
<x-modal id="edit_recipe_modal" title="Edit Recipe Formula" maxWidth="560px" class="dash-modal-overlay" :showClose="false">
    <form action="" method="POST" id="edit_recipe_form">
        @csrf
        @method('PUT')
        <x-input name="name" id="edit_recipe_name" label="Recipe Name" placeholder="e.g. Classic Butter Croissant" :required="true" />
        <x-select name="product_id" id="edit_recipe_product" label="Bakery Product" :required="true">
            @foreach($products as $p)
                <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->category ? $p->category->name : 'Bakery' }})</option>
            @endforeach
        </x-select>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
            <x-input type="number" name="yield_quantity" id="edit_recipe_yield" label="Batch Yield (pcs)" min="1" :required="true" />
            <x-input type="number" name="production_time" id="edit_recipe_time" label="Baking Time (mins)" min="1" :required="true" />
            <x-select name="status" id="edit_recipe_status" label="Status" :required="true">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </x-select>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Ingredients Description</label>
            <textarea name="description" id="edit_recipe_description" class="form-control-textarea" rows="2" placeholder="Ingredients summary..."></textarea>
        </div>
        <div class="form-group" style="margin-top: 0.75rem;">
            <label class="form-label">Baking Notes / Oven Settings</label>
            <textarea name="instructions" id="edit_recipe_instructions" class="form-control-textarea" rows="2" placeholder="Baking instructions..."></textarea>
        </div>
        <!-- Danger Zone / Delete Recipe Action Card -->
        <div style="margin-top: 1.25rem; border-top: 1px solid var(--border); padding-top: 1rem;">
            <div class="user-action-card user-action-card-danger">
                <div>
                    <div class="user-action-card-title" style="color: #ef4444;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span>Delete Recipe</span>
                    </div>
                    <div class="user-action-card-desc">
                        Permanently delete this baking formula from active production
                    </div>
                </div>
                <button type="button" class="btn btn-action-card-btn btn-danger-action" id="btn_delete_from_edit_recipe">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                    <span>Delete</span>
                </button>
            </div>
        </div>

        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_edit_recipe">Cancel</x-button>
            <x-button variant="primary" type="submit">Update Recipe</x-button>
        </div>
    </form>
</x-modal>

<!-- Hidden form for recipe deletion -->
<form id="form_delete_recipe" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- ==========================================================================
     UNIFIED DELETE RECIPE CONFIRMATION MODAL CARD
     ========================================================================== -->
<div class="user-modal-backdrop" id="delete_recipe_confirm_modal">
    <div class="user-modal-card" style="max-width: 440px;">
        <div class="user-modal-header" style="border-bottom: 1px solid rgba(220, 38, 38, 0.2);">
            <h3 style="color: #ef4444; display: flex; align-items: center; gap: 0.5rem; font-size: 1.05rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Delete Recipe</span>
            </h3>
        </div>
        <div class="user-modal-body" style="padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.85rem;">
            <p style="font-size: 0.925rem; margin: 0; color: var(--user-text, #ffffff); line-height: 1.5;">
                Are you sure you want to delete recipe <strong id="delete_modal_recipe_name" style="color: #f59e0b;"></strong>?
            </p>
            <div style="background: rgba(239, 68, 68, 0.06); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; padding: 0.85rem 1rem; font-size: 0.8rem; color: var(--user-muted, #a1a1aa); line-height: 1.45;">
                <div style="font-weight: 700; color: #ef4444; margin-bottom: 0.2rem; display: flex; align-items: center; gap: 0.35rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>Production Integrity Protected</span>
                </div>
                Completed production batches that used this formula remain intact in past bakery logs and cost audits.
            </div>
        </div>
        <div class="user-modal-footer" style="padding: 1rem 1.5rem; justify-content: flex-end; gap: 0.75rem;">
            <button type="button" class="btn btn-modal-cancel" id="btn_cancel_delete_recipe_confirm">Close</button>
            <button type="button" class="btn btn-action-card-btn btn-danger-action" id="btn_confirm_delete_recipe_execute" style="height: 40px; padding: 0 1.25rem;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                <span>Delete Recipe</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add Modal
        const addModal = document.getElementById('add_recipe_modal');
        const openAddBtn = document.getElementById('btn_open_recipe_modal');
        const closeAddBtn = document.getElementById('btn_close_recipe_modal');
        const cancelAddBtn = document.getElementById('btn_cancel_recipe');

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
        const editModal = document.getElementById('edit_recipe_modal');
        const editForm = document.getElementById('edit_recipe_form');
        const closeEditBtn = document.getElementById('btn_close_edit_recipe_modal');
        const cancelEditBtn = document.getElementById('btn_cancel_edit_recipe');

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

        let currentEditRecipeId = null;
        let currentEditRecipeName = '';

        document.querySelectorAll('.btn-edit-recipe').forEach(btn => {
            btn.addEventListener('click', function() {
                currentEditRecipeId = this.getAttribute('data-id');
                currentEditRecipeName = this.getAttribute('data-name') || '';
                editForm.action = `/admin/recipes/${currentEditRecipeId}`;
                document.getElementById('edit_recipe_name').value = currentEditRecipeName;
                document.getElementById('edit_recipe_product').value = this.getAttribute('data-product') || '';
                document.getElementById('edit_recipe_yield').value = this.getAttribute('data-yield') || '1';
                document.getElementById('edit_recipe_time').value = this.getAttribute('data-time') || '';
                document.getElementById('edit_recipe_description').value = this.getAttribute('data-desc') || '';
                document.getElementById('edit_recipe_instructions').value = this.getAttribute('data-instructions') || '';
                document.getElementById('edit_recipe_status').value = this.getAttribute('data-status') || 'active';

                editModal.classList.add('active', 'is-active');
                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        // Unified Delete Confirmation Modal
        const deleteRecipeModal = document.getElementById('delete_recipe_confirm_modal');
        const deleteRecipeModalName = document.getElementById('delete_modal_recipe_name');
        const btnCloseDeleteRecipe = document.getElementById('btn_close_delete_recipe_confirm');
        const btnCancelDeleteRecipe = document.getElementById('btn_cancel_delete_recipe_confirm');
        const btnConfirmDeleteRecipe = document.getElementById('btn_confirm_delete_recipe_execute');
        const formDeleteRecipe = document.getElementById('form_delete_recipe');
        let targetDeleteRecipeId = null;

        function openDeleteRecipeModal(id, name) {
            targetDeleteRecipeId = id;
            if (deleteRecipeModalName) deleteRecipeModalName.textContent = `"${name}"`;
            if (deleteRecipeModal) {
                deleteRecipeModal.classList.add('active', 'is-active');
                deleteRecipeModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeDeleteRecipeModal() {
            if (deleteRecipeModal) {
                deleteRecipeModal.classList.remove('active', 'is-active');
                deleteRecipeModal.style.display = 'none';
                if (!editModal || editModal.style.display !== 'flex') {
                    document.body.style.overflow = '';
                }
            }
        }

        // Table row delete buttons
        document.querySelectorAll('.btn-delete-recipe').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                openDeleteRecipeModal(id, name);
            });
        });

        // Delete button from inside Edit Modal
        const btnDeleteFromEditRecipe = document.getElementById('btn_delete_from_edit_recipe');
        if (btnDeleteFromEditRecipe) {
            btnDeleteFromEditRecipe.addEventListener('click', function() {
                if (currentEditRecipeId) {
                    openDeleteRecipeModal(currentEditRecipeId, currentEditRecipeName);
                }
            });
        }

        if (btnCloseDeleteRecipe) btnCloseDeleteRecipe.addEventListener('click', closeDeleteRecipeModal);
        if (btnCancelDeleteRecipe) btnCancelDeleteRecipe.addEventListener('click', closeDeleteRecipeModal);
        if (deleteRecipeModal) {
            deleteRecipeModal.addEventListener('click', (e) => {
                if (e.target === deleteRecipeModal) closeDeleteRecipeModal();
            });
        }

        if (btnConfirmDeleteRecipe) {
            btnConfirmDeleteRecipe.addEventListener('click', function() {
                if (!targetDeleteRecipeId) return;
                btnConfirmDeleteRecipe.disabled = true;
                btnConfirmDeleteRecipe.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <span>Deleting...</span>';
                formDeleteRecipe.action = `/admin/recipes/${targetDeleteRecipeId}`;
                formDeleteRecipe.submit();
            });
        }

        // Real-time table search filtering
        const searchInput = document.getElementById('recipe_search_input') || document.querySelector('.header-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#recipes_table tbody tr');
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
