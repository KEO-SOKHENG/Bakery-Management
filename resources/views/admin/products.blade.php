@extends('layouts.app')

@section('title', 'Products Management - Bakery System')
@section('body_class', 'is-catalog-page')

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.products', array_merge(request()->except('category_id', 'page'), [])) }}"
           class="period-pill {{ !request('category_id') ? 'active' : '' }}">
            <span>All Categories</span>
        </a>
        @foreach($categories as $category)
            <a href="{{ route('admin.products', array_merge(request()->except('category_id', 'page'), ['category_id' => $category->id])) }}"
               class="period-pill {{ (string)request('category_id') === (string)$category->id ? 'active' : '' }}">
                <span>{{ $category->name }}</span>
            </a>
        @endforeach
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}?v={{ @filemtime(public_path('css/products.css')) ?: '1.0' }}">
@endpush

@section('content')
<div class="products-container" style="display: flex; flex-direction: column; gap: 1.25rem;">

    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    <!-- Action Toolbar Row (Exact Orders, Users & Ingredients Style) -->
    <div class="products-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0;">
        <div style="font-weight: 700; color: #a1a1aa; font-size: 0.9rem;">
            Showing {{ $products->count() }} Products
        </div>
        <button type="button" class="btn" id="btn_open_create_product_modal" style="border-radius: 50px; height: 38px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Add Product</span>
        </button>
    </div>

    <!-- Products Card Grid -->
    <div class="products-grid">
    @forelse($products as $product)
        <div class="product-card" id="product_card_{{ $product->id }}">
            @php $theme = $product->getIconTheme(); @endphp

            <!-- Top Header: Category & Status -->
            <div class="product-card-header">
                <span class="product-category-chip">
                    {{ $product->category->name ?? 'Bakery' }}
                </span>
                <span class="product-status-pill {{ $product->status === 'inactive' ? 'status-pill-inactive' : 'status-pill-active' }}">
                    <span class="status-pulse-dot"></span>
                    {{ ucfirst($product->status ?? 'active') }}
                </span>
            </div>

            <!-- Visual Icon / Image -->
            <div class="product-card-visual-wrap">
                <div class="product-card-visual" style="background: {{ $theme['bg'] }}; border: 1.5px solid {{ $theme['border'] }}; color: {{ $theme['color'] }}; box-shadow: 0 4px 14px {{ $theme['shadow'] }};">
                    @if($product->image_url)
                        <img src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
                    @else
                        {!! $product->getIconSvg(36) !!}
                    @endif
                </div>
            </div>

            <!-- Product Title & SKU -->
            <div class="product-card-main-info">
                <h3 class="product-card-title" title="{{ $product->name }}">{{ $product->name }}</h3>
                <div class="product-card-sku-code">
                    <span class="sku-label">SKU:</span>
                    <span class="sku-value">{{ $product->sku ?: 'PRD-' . str_pad($product->id, 4, '0', STR_PAD_LEFT) }}</span>
                </div>
            </div>

            <!-- Meta Badges: Expiry & Formula -->
            <div class="product-card-tags-area">
                @if($product->shelf_life || $product->expiry_date)
                    <div class="product-meta-pill {{ $product->isExpired() ? 'pill-expired' : ($product->isExpiringSoon() ? 'pill-expiring' : '') }}" title="Shelf life & Expiration">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        <span>
                            @if($product->shelf_life) {{ $product->shelf_life }}d @endif
                            @if($product->expiry_date) &bull; Exp {{ $product->expiry_date->format('M d') }} @endif
                        </span>
                    </div>
                @endif

                @if($product->recipe && $product->recipe->recipeIngredients->count() > 0)
                    <div class="product-meta-pill pill-recipe" title="Recipe formula configured">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/><path d="M6 6h10"/><path d="M6 10h10"/></svg>
                        <span>Formula: {{ $product->recipe->recipeIngredients->count() }} items</span>
                    </div>
                @endif
            </div>

            <!-- Card Bottom: Pricing, Stock & Actions -->
            <div class="product-card-footer">
                <div class="product-card-pricing-row">
                    <div class="product-price-val">
                        <span class="price-symbol">$</span>{{ number_format($product->price, 2) }}
                    </div>
                    <div class="product-stock-tag {{ $product->stock <= 0 ? 'stock-empty' : ($product->stock <= $product->minimum_stock ? 'stock-low' : 'stock-ok') }}">
                        @if($product->stock <= 0)
                            <span>Out of stock</span>
                        @elseif($product->stock <= $product->minimum_stock)
                            <span>Low: {{ $product->stock }}</span>
                        @else
                            <span>{{ $product->stock }} in stock</span>
                        @endif
                    </div>
                </div>

                <div class="product-card-actions-row">
                    <button type="button" class="btn-icon-action edit btn-edit-product" title="Edit Product"
                        data-id="{{ $product->id }}"
                        data-name="{{ $product->name }}"
                        data-sku="{{ $product->sku }}"
                        data-category-id="{{ $product->category_id }}"
                        data-price="{{ $product->price }}"
                        data-cost="{{ $product->cost }}"
                        data-stock="{{ $product->stock }}"
                        data-minimum-stock="{{ $product->minimum_stock }}"
                        data-shelf-life="{{ $product->shelf_life }}"
                        data-expiry-date="{{ $product->expiry_date ? $product->expiry_date->format('Y-m-d') : '' }}"
                        data-status="{{ $product->status }}"
                        data-description="{{ $product->description }}"
                        data-image="{{ $product->image_url ?? '' }}"
                        data-recipe="{{ json_encode($product->recipe ? $product->recipe->recipeIngredients->map(function($ri) {
                            return [
                                'ingredient_id' => $ri->ingredient_id,
                                'quantity' => (float)$ri->quantity,
                                'unit' => $ri->unit ?? ($ri->ingredient ? $ri->ingredient->unit : ''),
                                'name' => $ri->ingredient ? $ri->ingredient->name : '',
                                'cost' => (float)($ri->ingredient ? $ri->ingredient->cost : 0),
                            ];
                        }) : []) }}"
                        data-update-url="{{ route('admin.products.update', $product->id) }}">
                        <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <form action="{{ route('admin.products.destroy', $product->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete product \'{{ $product->name }}\'?');" style="display: inline; margin: 0;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-icon-action delete" title="Delete Product">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 3rem;">
            No products found. Click "Add Product" to create one.
        </div>
    @endforelse
    </div>



<!-- ADD NEW PRODUCT MODAL DIALOG -->
<x-modal id="create_product_modal" title="Add Product" maxWidth="620px" class="product-modal-overlay" :showClose="false">
    <form id="form_create_product" action="{{ route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <x-select name="category_id" label="Category">
            <option value="">Select Category</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </x-select>
        <x-input name="name" label="Product Name" placeholder="e.g. Chocolate Cake" :required="true" />
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" name="price" label="Price ($)" placeholder="0.00" :required="true" />
            <x-input type="number" step="0.01" name="cost" id="create_product_cost" label="Cost ($)" placeholder="0.00" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" name="stock" label="Stock Quantity" value="10" :required="true" />
            <x-input type="number" name="minimum_stock" label="Minimum Stock Alert" value="5" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" name="shelf_life" label="Shelf Life (Days)" min="1" placeholder="e.g. 3" />
            <x-input type="date" name="expiry_date" label="Batch Expiry Date" />
        </div>
        <div class="form-group">
            <label class="form-label" for="create_product_image">Product Image</label>
            <div class="product-file-upload-card">
                <input type="file" name="image" id="create_product_image" class="file-upload-input-real" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                <label for="create_product_image" class="file-upload-dropzone">
                    <div class="dropzone-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div class="dropzone-text">
                        <span class="dropzone-main" id="create_image_filename">Choose product photo or drop here</span>
                        <span class="dropzone-hint">PNG, JPG, WEBP (Max 4MB)</span>
                    </div>
                    <span class="btn-dropzone-browse">Browse</span>
                </label>
            </div>
        </div>

        <!-- RECIPE INGREDIENTS BUILDER SECTION (CREATE) -->
        <div class="product-recipe-builder-section">
            <div class="recipe-builder-header">
                <div class="recipe-builder-title-wrap">
                    <div class="recipe-icon-box">
                        <svg class="recipe-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            <path d="M6 6h10"/>
                            <path d="M6 10h10"/>
                        </svg>
                    </div>
                    <div class="recipe-title-meta">
                        <span class="recipe-section-title">Recipe Ingredients</span>
                        <span class="recipe-section-sub">Link raw ingredients to calculate costs and automate inventory</span>
                    </div>
                    <span class="recipe-items-badge" id="create_ingredients_count">0 ingredients</span>
                </div>
                <div class="recipe-builder-header-actions">
                    <button type="button" class="btn-sync-ingredients" id="btn_sync_ingredients_create" title="Refresh live stock & cost from ingredients catalog">
                        <svg class="sync-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        <span class="sync-text">Live Sync</span>
                    </button>
                </div>
            </div>

            <!-- Column Headers -->
            <div class="recipe-ingredients-col-headers">
                <span class="col-head-ing">Ingredient</span>
                <span class="col-head-qty">Quantity & Unit</span>
                <span class="col-head-cost">Cost</span>
                <span class="col-head-action"></span>
            </div>

            <div class="recipe-ingredients-table-wrap">
                <div class="recipe-ingredients-list" id="create_ingredients_rows">
                    <!-- Dynamic ingredient rows -->
                </div>
                <div class="recipe-ingredients-empty-state" id="create_ingredients_empty">
                    <div class="empty-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            <path d="M6 6h10"/>
                            <path d="M6 10h10"/>
                        </svg>
                    </div>
                    <span class="empty-title">No Ingredients Linked</span>
                    <span class="empty-desc">Click "+ Add Ingredient" below to configure recipe formula items.</span>
                </div>
            </div>

            <div class="recipe-builder-footer">
                <button type="button" class="btn-add-ingredient-row" id="btn_add_ingredient_create">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Ingredient</span>
                </button>

                <div class="recipe-summary-cost-box">
                    <div class="recipe-cost-calc">
                        <span class="recipe-cost-label">Recipe Cost:</span>
                        <span class="recipe-cost-val" id="create_recipe_total_cost">$0.00</span>
                    </div>
                    <button type="button" class="btn-apply-cost" id="btn_apply_cost_create" title="Apply recipe cost to product cost input">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Apply to Cost</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_product_modal">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Product</x-button>
        </div>
    </form>
</x-modal>

<!-- EDIT PRODUCT MODAL DIALOG -->
<x-modal id="edit_product_modal" title="Edit Product" maxWidth="620px" class="product-modal-overlay" :showClose="false">
    <form id="form_edit_product" action="" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-select name="category_id" id="edit_product_category_id" label="Category">
            <option value="">Select Category</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </x-select>
        <x-input name="name" id="edit_product_name" label="Product Name" placeholder="e.g. Chocolate Cake" :required="true" />
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" step="0.01" name="price" id="edit_product_price" label="Price ($)" placeholder="0.00" :required="true" />
            <x-input type="number" step="0.01" name="cost" id="edit_product_cost" label="Cost ($)" placeholder="0.00" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" name="stock" id="edit_product_stock" label="Stock Quantity" :required="true" />
            <x-input type="number" name="minimum_stock" id="edit_product_minimum_stock" label="Minimum Stock Alert" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input type="number" name="shelf_life" id="edit_product_shelf_life" label="Shelf Life (Days)" min="1" placeholder="e.g. 3" />
            <x-input type="date" name="expiry_date" id="edit_product_expiry_date" label="Batch Expiry Date" />
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
            <x-input name="sku" id="edit_product_sku" label="SKU" placeholder="PRD-0001" />
            <div class="form-group">
                <label class="form-label" id="label_edit_product_status">Status</label>
                <input type="hidden" name="status" id="edit_product_status" value="active">
                <div class="product-status-btn-group" role="radiogroup" aria-labelledby="label_edit_product_status">
                    <button type="button" class="btn-status-toggle is-active-state" data-value="active" id="btn_status_active" aria-checked="true" role="radio" title="Set Product Active">
                        <span class="status-dot dot-active"></span>
                        <span class="status-text">Active</span>
                    </button>
                    <button type="button" class="btn-status-toggle" data-value="inactive" id="btn_status_inactive" aria-checked="false" role="radio" title="Set Product Inactive">
                        <span class="status-dot dot-inactive"></span>
                        <span class="status-text">Inactive</span>
                    </button>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label" for="edit_product_image">Product Image</label>
            <div class="product-file-upload-card">
                <input type="file" name="image" id="edit_product_image" class="file-upload-input-real" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp">
                <label for="edit_product_image" class="file-upload-dropzone">
                    <div class="dropzone-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                    <div class="dropzone-text">
                        <span class="dropzone-main" id="edit_image_filename">Replace photo or drop new image</span>
                        <span class="dropzone-hint">Leave empty to keep existing image</span>
                    </div>
                    <span class="btn-dropzone-browse">Browse</span>
                </label>
            </div>
        </div>

        <!-- RECIPE INGREDIENTS BUILDER SECTION (EDIT) -->
        <div class="product-recipe-builder-section">
            <div class="recipe-builder-header">
                <div class="recipe-builder-title-wrap">
                    <div class="recipe-icon-box">
                        <svg class="recipe-section-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            <path d="M6 6h10"/>
                            <path d="M6 10h10"/>
                        </svg>
                    </div>
                    <div class="recipe-title-meta">
                        <span class="recipe-section-title">Recipe Ingredients</span>
                        <span class="recipe-section-sub">Link raw ingredients to calculate costs and automate inventory</span>
                    </div>
                    <span class="recipe-items-badge" id="edit_ingredients_count">0 ingredients</span>
                </div>
                <div class="recipe-builder-header-actions">
                    <button type="button" class="btn-sync-ingredients" id="btn_sync_ingredients_edit" title="Refresh live stock & cost from ingredients catalog">
                        <svg class="sync-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="23 4 23 10 17 10"></polyline>
                            <polyline points="1 20 1 14 7 14"></polyline>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                        </svg>
                        <span class="sync-text">Live Sync</span>
                    </button>
                </div>
            </div>

            <!-- Column Headers -->
            <div class="recipe-ingredients-col-headers">
                <span class="col-head-ing">Ingredient</span>
                <span class="col-head-qty">Quantity & Unit</span>
                <span class="col-head-cost">Cost</span>
                <span class="col-head-action"></span>
            </div>

            <div class="recipe-ingredients-table-wrap">
                <div class="recipe-ingredients-list" id="edit_ingredients_rows">
                    <!-- Dynamic ingredient rows -->
                </div>
                <div class="recipe-ingredients-empty-state" id="edit_ingredients_empty">
                    <div class="empty-icon-wrap">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1-2.5-2.5Z"/>
                            <path d="M6 6h10"/>
                            <path d="M6 10h10"/>
                        </svg>
                    </div>
                    <span class="empty-title">No Ingredients Linked</span>
                    <span class="empty-desc">Click "+ Add Ingredient" below to configure recipe formula items.</span>
                </div>
            </div>

            <div class="recipe-builder-footer">
                <button type="button" class="btn-add-ingredient-row" id="btn_add_ingredient_edit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add Ingredient</span>
                </button>

                <div class="recipe-summary-cost-box">
                    <div class="recipe-cost-calc">
                        <span class="recipe-cost-label">Recipe Cost:</span>
                        <span class="recipe-cost-val" id="edit_recipe_total_cost">$0.00</span>
                    </div>
                    <button type="button" class="btn-apply-cost" id="btn_apply_cost_edit" title="Apply recipe cost to product cost input">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Apply to Cost</span>
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-sticky-footer">
            <x-button variant="secondary" type="button" id="btn_cancel_edit_product_modal">Cancel</x-button>
            <x-button variant="primary" type="submit" id="btn_submit_edit_product">Update Product</x-button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const createModal = document.getElementById('create_product_modal');
        const openCreateBtn = document.getElementById('btn_open_create_product_modal');
        const closeCreateBtn = document.getElementById('btn_close_product_modal');
        const cancelCreateBtn = document.getElementById('btn_cancel_product_modal');

        // =========================================================================
        // Real-Time Recipe Ingredients Builder
        // =========================================================================
        let cachedIngredients = @json($ingredients ?? []);
        const INGREDIENTS_API_URL = "{{ route('admin.products.ingredients-list') }}";
        let createRowIndex = 0;
        let editRowIndex = 0;

        function fetchLiveIngredients(buttonElement = null, onComplete = null) {
            if (buttonElement) {
                buttonElement.classList.add('is-syncing');
            }
            fetch(INGREDIENTS_API_URL, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success && Array.isArray(data.ingredients)) {
                    cachedIngredients = data.ingredients;
                    updateAllDropdownOptions();
                    recalculateRecipeTotal('create');
                    recalculateRecipeTotal('edit');
                    if (buttonElement) {
                        const textEl = buttonElement.querySelector('.sync-text');
                        if (textEl) {
                            const original = textEl.textContent;
                            textEl.textContent = 'Synced!';
                            setTimeout(() => { textEl.textContent = original; }, 1800);
                        }
                    }
                }
                if (typeof onComplete === 'function') onComplete(cachedIngredients);
            })
            .catch(err => {
                console.error('Error fetching live ingredients:', err);
            })
            .finally(() => {
                if (buttonElement) {
                    buttonElement.classList.remove('is-syncing');
                }
            });
        }

        function updateAllDropdownOptions() {
            ['create', 'edit'].forEach(type => {
                const container = document.getElementById(`${type}_ingredients_rows`);
                if (!container) return;
                const rows = container.querySelectorAll('.recipe-ingredient-row');
                rows.forEach(row => {
                    const sel = row.querySelector('.ingredient-select');
                    if (!sel) return;
                    const currentVal = sel.value;
                    let html = '<option value="">-- Choose Ingredient --</option>';
                    cachedIngredients.forEach(ing => {
                        const stock = parseFloat(ing.quantity || 0).toLocaleString();
                        const cost = parseFloat(ing.cost || 0).toFixed(2);
                        const isSel = String(ing.id) === String(currentVal) ? 'selected' : '';
                        html += `<option value="${ing.id}" data-unit="${ing.unit || 'unit'}" data-cost="${ing.cost || 0}" data-stock="${ing.quantity || 0}" ${isSel}>${ing.name} (Stock: ${stock} ${ing.unit} | $${cost}/${ing.unit})</option>`;
                    });
                    sel.innerHTML = html;
                });
            });
        }

        function clearIngredientsRows(type) {
            const container = document.getElementById(`${type}_ingredients_rows`);
            if (container) container.innerHTML = '';
            recalculateRecipeTotal(type);
        }

        function addIngredientRow(type, initial = null) {
            const container = document.getElementById(`${type}_ingredients_rows`);
            if (!container) return;

            const idx = type === 'create' ? createRowIndex++ : editRowIndex++;
            const row = document.createElement('div');
            row.className = 'recipe-ingredient-row';
            row.dataset.index = idx;

            const selectedId = initial ? initial.ingredient_id : '';
            const qtyVal = initial && initial.quantity !== undefined ? initial.quantity : '';
            let initialUnit = initial && initial.unit ? initial.unit : 'unit';

            let optionsHtml = '<option value="">-- Choose Ingredient --</option>';
            cachedIngredients.forEach(ing => {
                const isSel = String(ing.id) === String(selectedId) ? 'selected' : '';
                if (isSel && ing.unit) initialUnit = ing.unit;
                const stock = parseFloat(ing.quantity || 0).toLocaleString();
                const cost = parseFloat(ing.cost || 0).toFixed(2);
                optionsHtml += `<option value="${ing.id}" data-unit="${ing.unit || 'unit'}" data-cost="${ing.cost || 0}" data-stock="${ing.quantity || 0}" ${isSel}>${ing.name} (Stock: ${stock} ${ing.unit} | $${cost}/${ing.unit})</option>`;
            });

            row.innerHTML = `
                <div class="ingredient-select-col">
                    <select name="ingredients[${idx}][ingredient_id]" class="ingredient-select">
                        ${optionsHtml}
                    </select>
                </div>
                <div class="ingredient-qty-col">
                    <div class="ingredient-qty-wrap">
                        <input type="number" step="any" min="0.0001" name="ingredients[${idx}][quantity]" class="ingredient-qty-input" placeholder="0.00" value="${qtyVal}">
                        <span class="ingredient-unit-badge">${initialUnit}</span>
                        <input type="hidden" name="ingredients[${idx}][unit]" class="ingredient-unit-hidden" value="${initialUnit}">
                    </div>
                </div>
                <div class="ingredient-cost-col">
                    <span class="ingredient-row-cost">$0.00</span>
                </div>
                <div class="ingredient-action-col">
                    <button type="button" class="btn-remove-ingredient-row" title="Remove ingredient">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            `;

            const selectEl = row.querySelector('.ingredient-select');
            const qtyInput = row.querySelector('.ingredient-qty-input');
            const unitBadge = row.querySelector('.ingredient-unit-badge');
            const unitHidden = row.querySelector('.ingredient-unit-hidden');
            const removeBtn = row.querySelector('.btn-remove-ingredient-row');

            selectEl.addEventListener('change', function() {
                const opt = selectEl.selectedOptions[0];
                const unit = opt && opt.dataset.unit ? opt.dataset.unit : 'unit';
                unitBadge.textContent = unit;
                unitHidden.value = unit;
                recalculateRecipeTotal(type);
            });

            qtyInput.addEventListener('input', function() {
                recalculateRecipeTotal(type);
            });

            removeBtn.addEventListener('click', function() {
                row.remove();
                recalculateRecipeTotal(type);
            });

            container.appendChild(row);
            recalculateRecipeTotal(type);
        }

        function recalculateRecipeTotal(type) {
            const container = document.getElementById(`${type}_ingredients_rows`);
            const emptyEl = document.getElementById(`${type}_ingredients_empty`);
            const countBadge = document.getElementById(`${type}_ingredients_count`);
            const totalEl = document.getElementById(`${type}_recipe_total_cost`);

            if (!container) return;

            const rows = container.querySelectorAll('.recipe-ingredient-row');
            let total = 0.0;
            let count = 0;

            rows.forEach(row => {
                const sel = row.querySelector('.ingredient-select');
                const qtyInput = row.querySelector('.ingredient-qty-input');
                const costBadge = row.querySelector('.ingredient-row-cost');

                const opt = sel ? sel.selectedOptions[0] : null;
                const unitCost = opt && opt.dataset.cost ? parseFloat(opt.dataset.cost) : 0.0;
                const qty = qtyInput && qtyInput.value ? parseFloat(qtyInput.value) : 0.0;

                const subtotal = qty * unitCost;
                if (costBadge) {
                    costBadge.textContent = `$${subtotal.toFixed(2)}`;
                }

                if (sel && sel.value && qty > 0) {
                    count++;
                    total += subtotal;
                }
            });

            if (emptyEl) {
                emptyEl.style.display = rows.length === 0 ? 'flex' : 'none';
            }
            if (countBadge) {
                countBadge.textContent = `${rows.length} ${rows.length === 1 ? 'ingredient' : 'ingredients'}`;
            }
            if (totalEl) {
                totalEl.textContent = `$${total.toFixed(2)}`;
            }
            return total;
        }

        function applyRecipeCost(type) {
            const total = recalculateRecipeTotal(type);
            const targetInput = type === 'create'
                ? document.getElementById('create_product_cost')
                : document.getElementById('edit_product_cost');

            if (targetInput) {
                targetInput.value = total.toFixed(2);
                targetInput.style.transition = 'box-shadow 0.3s ease, border-color 0.3s ease';
                targetInput.style.borderColor = '#10b981';
                targetInput.style.boxShadow = '0 0 10px rgba(16, 185, 129, 0.4)';
                setTimeout(() => {
                    targetInput.style.borderColor = '';
                    targetInput.style.boxShadow = '';
                }, 1200);
            }
        }

        // Attach builder footer & sync buttons
        const btnAddCreate = document.getElementById('btn_add_ingredient_create');
        if (btnAddCreate) btnAddCreate.addEventListener('click', () => addIngredientRow('create'));

        const btnAddEdit = document.getElementById('btn_add_ingredient_edit');
        if (btnAddEdit) btnAddEdit.addEventListener('click', () => addIngredientRow('edit'));

        const btnSyncCreate = document.getElementById('btn_sync_ingredients_create');
        if (btnSyncCreate) btnSyncCreate.addEventListener('click', function() { fetchLiveIngredients(this); });

        const btnSyncEdit = document.getElementById('btn_sync_ingredients_edit');
        if (btnSyncEdit) btnSyncEdit.addEventListener('click', function() { fetchLiveIngredients(this); });

        const btnApplyCreate = document.getElementById('btn_apply_cost_create');
        if (btnApplyCreate) btnApplyCreate.addEventListener('click', () => applyRecipeCost('create'));

        const btnApplyEdit = document.getElementById('btn_apply_cost_edit');
        if (btnApplyEdit) btnApplyEdit.addEventListener('click', () => applyRecipeCost('edit'));

        // Custom File Upload Dynamic Name & Live Thumbnail Preview Update
        const createImgInput = document.getElementById('create_product_image');
        const createImgName = document.getElementById('create_image_filename');
        if (createImgInput && createImgName) {
            createImgInput.addEventListener('change', function() {
                const dropzone = this.closest('.product-file-upload-card')?.querySelector('.file-upload-dropzone');
                const dropzoneIcon = dropzone?.querySelector('.dropzone-icon');
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    createImgName.textContent = file.name;
                    createImgName.classList.add('has-file');

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (dropzoneIcon) {
                            dropzoneIcon.innerHTML = `<img src="${e.target.result}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 8px;" alt="Preview">`;
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    createImgName.textContent = 'Choose product photo or drop here';
                    createImgName.classList.remove('has-file');
                }
            });
        }

        const editImgInput = document.getElementById('edit_product_image');
        const editImgName = document.getElementById('edit_image_filename');
        if (editImgInput && editImgName) {
            editImgInput.addEventListener('change', function() {
                const dropzone = this.closest('.product-file-upload-card')?.querySelector('.file-upload-dropzone');
                const dropzoneIcon = dropzone?.querySelector('.dropzone-icon');
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    editImgName.textContent = file.name;
                    editImgName.classList.add('has-file');

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (dropzoneIcon) {
                            dropzoneIcon.innerHTML = `<img src="${e.target.result}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 8px;" alt="Preview">`;
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    editImgName.textContent = 'Replace photo or drop new image';
                    editImgName.classList.remove('has-file');
                }
            });
        }

        if (openCreateBtn && createModal) {
            openCreateBtn.addEventListener('click', () => {
                createModal.classList.add('is-active');
                createModal.style.display = 'flex';
                if (createImgName) {
                    createImgName.textContent = 'Choose product photo or drop here';
                    createImgName.classList.remove('has-file');
                }
                const createDropzoneIcon = createModal.querySelector('.dropzone-icon');
                if (createDropzoneIcon) {
                    createDropzoneIcon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
                }
                if (createImgInput) createImgInput.value = '';
                clearIngredientsRows('create');
                addIngredientRow('create');
                fetchLiveIngredients();
            });
        }
        [closeCreateBtn, cancelCreateBtn].forEach(btn => {
            if (btn && createModal) {
                btn.addEventListener('click', () => {
                    createModal.classList.remove('is-active');
                    createModal.style.display = 'none';
                });
            }
        });
        if (createModal) {
            createModal.addEventListener('click', (e) => {
                if (e.target === createModal) {
                    createModal.classList.remove('is-active');
                    createModal.style.display = 'none';
                }
            });
        }

        // Edit Product Modal Handling
        const editModal = document.getElementById('edit_product_modal');
        const editForm = document.getElementById('form_edit_product');
        const cancelEditBtn = document.getElementById('btn_cancel_edit_product_modal');

        function setEditProductStatus(status) {
            const hiddenStatus = document.getElementById('edit_product_status');
            const btnActive = document.getElementById('btn_status_active');
            const btnInactive = document.getElementById('btn_status_inactive');
            const isAct = (status || 'active').toLowerCase() === 'active';

            if (hiddenStatus) hiddenStatus.value = isAct ? 'active' : 'inactive';
            if (btnActive) {
                btnActive.classList.toggle('is-active-state', isAct);
                btnActive.setAttribute('aria-checked', isAct ? 'true' : 'false');
            }
            if (btnInactive) {
                btnInactive.classList.toggle('is-active-state', !isAct);
                btnInactive.setAttribute('aria-checked', !isAct ? 'true' : 'false');
            }
        }

        const btnStatusActive = document.getElementById('btn_status_active');
        const btnStatusInactive = document.getElementById('btn_status_inactive');
        if (btnStatusActive) {
            btnStatusActive.addEventListener('click', () => setEditProductStatus('active'));
        }
        if (btnStatusInactive) {
            btnStatusInactive.addEventListener('click', () => setEditProductStatus('inactive'));
        }

        function closeEditModal() {
            if (editModal) {
                editModal.classList.remove('is-active', 'active');
                editModal.style.display = 'none';
            }
        }

        if (cancelEditBtn) {
            cancelEditBtn.addEventListener('click', closeEditModal);
        }

        if (editModal) {
            editModal.addEventListener('click', (e) => {
                if (e.target === editModal) {
                    closeEditModal();
                }
            });
        }

        document.querySelectorAll('.btn-edit-product').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!editModal || !editForm) return;

                editForm.action = this.dataset.updateUrl || '';
                const catSelect = document.getElementById('edit_product_category_id');
                if (catSelect) catSelect.value = this.dataset.categoryId || '';

                const nameInput = document.getElementById('edit_product_name');
                if (nameInput) nameInput.value = this.dataset.name || '';

                const skuInput = document.getElementById('edit_product_sku');
                if (skuInput) skuInput.value = this.dataset.sku || '';

                const priceInput = document.getElementById('edit_product_price');
                if (priceInput) priceInput.value = this.dataset.price || '';

                const costInput = document.getElementById('edit_product_cost');
                if (costInput) costInput.value = this.dataset.cost || '';

                const stockInput = document.getElementById('edit_product_stock');
                if (stockInput) stockInput.value = this.dataset.stock || '';

                const minStockInput = document.getElementById('edit_product_minimum_stock');
                if (minStockInput) minStockInput.value = this.dataset.minimumStock || '';

                const shelfLifeInput = document.getElementById('edit_product_shelf_life');
                if (shelfLifeInput) shelfLifeInput.value = this.dataset.shelfLife || '';

                const expiryDateInput = document.getElementById('edit_product_expiry_date');
                if (expiryDateInput) expiryDateInput.value = this.dataset.expiryDate || '';

                setEditProductStatus(this.dataset.status || 'active');

                const editDropzoneIcon = editModal.querySelector('.dropzone-icon');
                if (this.dataset.image) {
                    if (editDropzoneIcon) {
                        editDropzoneIcon.innerHTML = `<img src="${this.dataset.image}" style="width: 36px; height: 36px; object-fit: cover; border-radius: 8px;" alt="Current Image">`;
                    }
                    if (editImgName) {
                        editImgName.textContent = 'Current image loaded (choose file to replace)';
                        editImgName.classList.add('has-file');
                    }
                } else {
                    if (editDropzoneIcon) {
                        editDropzoneIcon.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>`;
                    }
                    if (editImgName) {
                        editImgName.textContent = 'Replace photo or drop new image';
                        editImgName.classList.remove('has-file');
                    }
                }
                if (editImgInput) editImgInput.value = '';

                // Populate existing recipe ingredients
                clearIngredientsRows('edit');
                try {
                    const recipeData = this.dataset.recipe ? JSON.parse(this.dataset.recipe) : [];
                    if (Array.isArray(recipeData) && recipeData.length > 0) {
                        recipeData.forEach(item => addIngredientRow('edit', item));
                    }
                } catch (e) {
                    console.error('Failed to parse product recipe:', e);
                }
                recalculateRecipeTotal('edit');
                fetchLiveIngredients(null, () => recalculateRecipeTotal('edit'));

                editModal.classList.add('is-active');
                editModal.style.display = 'flex';
            });
        });

        // Close on Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (editModal && (editModal.classList.contains('is-active') || editModal.style.display === 'flex')) {
                    closeEditModal();
                }
                if (createModal && (createModal.classList.contains('is-active') || createModal.style.display === 'flex')) {
                    createModal.classList.remove('is-active', 'active');
                    createModal.style.display = 'none';
                }
            }
        });

        // Live Header Search Integration
        const searchInput = document.getElementById('global_header_search');
        if (searchInput) {
            @if(request('search'))
                searchInput.value = {!! json_encode(request('search')) !!};
            @endif

            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.products-grid .product-card');
                let visibleCount = 0;
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (!term || text.includes(term)) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const url = new URL(window.location.href);
                    if (this.value.trim()) {
                        url.searchParams.set('search', this.value.trim());
                    } else {
                        url.searchParams.delete('search');
                    }
                    url.searchParams.delete('page');
                    window.location.href = url.toString();
                }
            });
        }
    });
</script>
@endpush
</div>
@endsection
