@extends('layouts.app')

@section('title', 'Product Categories - Bakery Management System')
@section('body_class', 'is-catalog-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/categories.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.categories') }}" class="period-pill {{ !request('search') ? 'active' : '' }}">
            <span>All Categories ({{ $categories->count() }})</span>
        </a>
    </div>
@endsection

@section('content')
<div class="categories-container" style="display: flex; flex-direction: column; gap: 1.25rem;">

    <!-- Action Toolbar Row (Exact Orders, Users & Ingredients Style) -->
    <div class="categories-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 0;">
        <div style="font-weight: 700; color: #a1a1aa; font-size: 0.9rem;">
            Total: {{ $categories->count() }} Categories
        </div>
        <button type="button" class="btn" id="btn_open_category_modal" style="border-radius: 50px; height: 38px; padding: 0 1.25rem; display: inline-flex; align-items: center; gap: 0.45rem; font-weight: 700; background: #ea580c; border: 1px solid #ea580c; color: #ffffff; cursor: pointer; font-size: 0.875rem; box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            <span>Add Category</span>
        </button>
    </div>

    <!-- Flash Alerts -->
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif
    @if(session('error'))
        <x-alert type="danger" :message="session('error')" />
    @endif

    <div class="categories-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1.25rem;">
    @forelse($categories as $cat)
        @php
            $catName = strtolower($cat->name ?? '');
            $catSvg = match(true) {
                str_contains($catName, 'viennoiserie') || str_contains($catName, 'pastr') => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97736" stroke-width="2"><path d="M6 13.8a4.5 4.5 0 1 1 2.6-8.3 5 5 0 0 1 6.8 0 4.5 4.5 0 1 1 2.6 8.3v4.2H6v-4.2z"/><path d="M6 18h12v2H6z"/></svg>',
                str_contains($catName, 'cake') || str_contains($catName, 'dessert') => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97736" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 3v4"/></svg>',
                str_contains($catName, 'beverage') || str_contains($catName, 'coffee') => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97736" stroke-width="2"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4V8z"/><line x1="6" y1="2" x2="6" y2="4"/><line x1="10" y1="2" x2="10" y2="4"/><line x1="14" y1="2" x2="14" y2="4"/></svg>',
                str_contains($catName, 'savory') || str_contains($catName, 'snack') => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97736" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M3 12h18"/></svg>',
                default => '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97736" stroke-width="2"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
            };
        @endphp
        <div class="category-card" style="display: flex; align-items: center; gap: 1rem; padding: 1.25rem; background: var(--bg-card, #ffffff); border-radius: 1rem; border: 1px solid var(--border-subtle, #e4e4e7); position: relative;">
            <div class="category-icon" style="width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; background: rgba(217, 119, 54, 0.1); border-radius: 0.75rem; border: 1px solid rgba(217, 119, 54, 0.2); flex-shrink: 0;">
                {!! $catSvg !!}
            </div>
            <div class="category-info" style="flex: 1; min-width: 0;">
                <h3 style="font-size: 1rem; font-weight: 800; margin: 0 0 0.25rem 0;">{{ $cat->name }}</h3>
                <p style="font-size: 0.8rem; color: #71717a; margin: 0;">{{ $cat->products_count ?? 0 }} Active Products</p>
            </div>
            <div style="position: absolute; top: 0.75rem; right: 0.75rem; display: flex; gap: 0.35rem; align-items: center;">
                <button type="button" class="btn-icon-action edit btn-edit-category" title="Edit Category" style="width: 28px; height: 28px;"
                    data-id="{{ $cat->id }}"
                    data-name="{{ $cat->name }}"
                    data-description="{{ $cat->description }}"
                    data-status="{{ $cat->status ?? 'active' }}"
                    data-update-url="{{ route('admin.categories.update', $cat->id) }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 14px; height: 14px;">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </button>
                <form action="{{ route('admin.categories.destroy', $cat->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete category \'{{ $cat->name }}\'?');" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-icon-action delete" title="Delete Category" style="width: 28px; height: 28px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" style="width: 14px; height: 14px;">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div style="grid-column: 1 / -1; text-align: center; color: var(--text-muted); padding: 3rem;">
            No categories found. Click "Add Category" to create one.
        </div>
    @endforelse
</div>

<!-- ADD CATEGORY MODAL -->
<x-modal id="add_category_modal" title="Add Category" maxWidth="480px" class="dash-modal-overlay">
    <form action="{{ route('admin.categories.store') }}" method="POST">
        @csrf
        <x-input name="name" label="Category Name" placeholder="e.g. Artisanal Breads" :required="true" />
        <div class="form-group">
            <label class="form-label" for="add_category_status">Status</label>
            <select name="status" id="add_category_status" class="form-control">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control-textarea" rows="3" placeholder="Brief catalog description..."></textarea>
        </div>
        <div class="modal-footer" style="padding-top: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <x-button variant="secondary" type="button" id="btn_cancel_cat">Cancel</x-button>
            <x-button variant="primary" type="submit">Save Category</x-button>
        </div>
    </form>
</x-modal>

<!-- EDIT CATEGORY MODAL -->
<x-modal id="edit_category_modal" title="Edit Category" maxWidth="480px" class="dash-modal-overlay">
    <form id="form_edit_category" action="" method="POST">
        @csrf
        @method('PUT')
        <x-input name="name" id="edit_category_name" label="Category Name" placeholder="e.g. Artisanal Breads" :required="true" />
        <div class="form-group">
            <label class="form-label" for="edit_category_status">Status</label>
            <select name="status" id="edit_category_status" class="form-control" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" for="edit_category_description">Description</label>
            <textarea name="description" id="edit_category_description" class="form-control-textarea" rows="3" placeholder="Brief catalog description..."></textarea>
        </div>
        <div class="modal-footer" style="padding-top: 1rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
            <x-button variant="secondary" type="button" id="btn_cancel_edit_cat">Cancel</x-button>
            <x-button variant="primary" type="submit" id="btn_submit_edit_category">Update Category</x-button>
        </div>
    </form>
</x-modal>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Add Category Modal
        const addModal = document.getElementById('add_category_modal');
        const openAddBtn = document.getElementById('btn_open_category_modal');
        const closeAddBtn = document.getElementById('btn_close_cat_modal');
        const cancelAddBtn = document.getElementById('btn_cancel_cat');

        function openAddModal() {
            if (addModal) {
                addModal.classList.add('active', 'is-active');
                addModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAddModal() {
            if (addModal) {
                addModal.classList.remove('active', 'is-active');
                addModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        if (openAddBtn) openAddBtn.addEventListener('click', openAddModal);
        if (closeAddBtn) closeAddBtn.addEventListener('click', closeAddModal);
        if (cancelAddBtn) cancelAddBtn.addEventListener('click', closeAddModal);

        if (addModal) {
            addModal.addEventListener('click', function(e) {
                if (e.target === addModal) closeAddModal();
            });
        }

        // Edit Category Modal
        const editModal = document.getElementById('edit_category_modal');
        const editForm = document.getElementById('form_edit_category');
        const cancelEditBtn = document.getElementById('btn_cancel_edit_cat');
        const closeEditBtn = document.getElementById('btn_close_edit_cat_modal');

        function closeEditModal() {
            if (editModal) {
                editModal.classList.remove('active', 'is-active');
                editModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        if (cancelEditBtn) cancelEditBtn.addEventListener('click', closeEditModal);
        if (closeEditBtn) closeEditBtn.addEventListener('click', closeEditModal);

        if (editModal) {
            editModal.addEventListener('click', function(e) {
                if (e.target === editModal) closeEditModal();
            });
        }

        document.querySelectorAll('.btn-edit-category').forEach(btn => {
            btn.addEventListener('click', function() {
                if (!editModal || !editForm) return;

                editForm.action = this.dataset.updateUrl || '';
                const nameInput = document.getElementById('edit_category_name');
                if (nameInput) nameInput.value = this.dataset.name || '';

                const statusSelect = document.getElementById('edit_category_status');
                if (statusSelect) statusSelect.value = this.dataset.status || 'active';

                const descInput = document.getElementById('edit_category_description');
                if (descInput) descInput.value = this.dataset.description || '';

                editModal.classList.add('active', 'is-active');
                editModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            });
        });

        // Search filtering (client-side dynamic + submit on Enter)
        const searchInput = document.getElementById('category_search_input') || document.getElementById('global_header_search');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase().trim();
                const cards = document.querySelectorAll('.categories-grid .category-card');
                cards.forEach(card => {
                    const text = card.textContent.toLowerCase();
                    if (!term || text.includes(term)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const url = new URL(window.location.href);
                    const term = this.value.trim();
                    if (term) {
                        url.searchParams.set('search', term);
                    } else {
                        url.searchParams.delete('search');
                    }
                    window.location.href = url.toString();
                }
            });
        }
    });
</script>
@endpush
</div>
@endsection
