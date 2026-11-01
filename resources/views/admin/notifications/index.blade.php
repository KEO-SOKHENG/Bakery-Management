@extends('layouts.app')

@section('title', 'Notification Center - Bakery Management')
@section('body_class', 'is-catalog-page is-settings-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/notifications.css') }}?v={{ @filemtime(public_path('css/notifications.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.settings') }}" class="period-pill" id="tab_general_settings">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            <span data-lang-key="general_settings">General Settings</span>
        </a>
        <a href="{{ route('admin.settings', ['tab' => 'notifications']) }}" class="period-pill active" id="tab_notifications_settings">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span data-lang-key="notifications">Notifications</span>
            @if(($stats['unread'] ?? $realUnreadCount ?? 0) > 0)
                <span style="display: inline-flex; align-items: center; justify-content: center; margin-left: 6px; min-width: 18px; height: 18px; padding: 0 5px; font-size: 0.72rem; font-weight: 700; line-height: 1; border-radius: 9999px; background: #ef4444; color: #ffffff;">{{ $stats['unread'] ?? $realUnreadCount }}</span>
            @endif
        </a>
    </div>
@endsection

@section('content')
<div class="orders-container notifications-page-container">
    <h1 class="sr-only" data-lang-key="notification_center">Notification Center</h1>

    <!-- Filter Tabs & Actions Toolbar (Single Compact Row Matching Staff & Accounts) -->
    @php
        $selectedStatus = request('status', 'all');
        $selectedType = request('type', 'all');
        $readCount = max(0, $stats['total'] - $stats['unread']);
    @endphp

    <div style="display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 0.75rem; flex-shrink: 0;">
        <!-- Left: Status Filter Tabs -->
        <div class="orders-filter-bar" id="notif_status_tabs" style="flex-shrink: 0;">
            <a href="{{ route('admin.notifications', array_merge(request()->except('status', 'page'), ['status' => 'all'])) }}"
               class="filter-btn {{ (!request()->filled('status') || request('status') === 'all') ? 'active' : '' }}">
                <span data-lang-key="all">{{ __('messages.all') ?? 'All' }}</span> ({{ $stats['total'] }})
            </a>
            <a href="{{ route('admin.notifications', array_merge(request()->except('status', 'page'), ['status' => 'unread'])) }}"
               class="filter-btn {{ request('status') === 'unread' ? 'active' : '' }}">
                <span data-lang-key="unread">{{ __('messages.unread') ?? 'Unread' }}</span> (<span id="stat_unread_count">{{ $stats['unread'] }}</span>)
            </a>
            <a href="{{ route('admin.notifications', array_merge(request()->except('status', 'page'), ['status' => 'read'])) }}"
               class="filter-btn {{ request('status') === 'read' ? 'active' : '' }}">
                <span data-lang-key="read">{{ __('messages.read') ?? 'Read' }}</span> ({{ $readCount }})
            </a>
        </div>

        <!-- Middle / Right: Search & Filters Form + Action Buttons -->
        <div style="display: flex; align-items: center; gap: 0.65rem; flex-wrap: wrap; margin-left: auto;">
            <form action="{{ route('admin.notifications') }}" method="GET" id="notif_filter_form" style="display: flex; gap: 0.5rem; align-items: center; margin: 0;">
                @if(request('status') && request('status') !== 'all')
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div style="width: 150px;">
                    <select name="type" class="form-control-select" onchange="this.form.submit()" style="height: 34px; font-size: 0.825rem; border-radius: 50px; padding: 0 0.85rem;">
                        <option value="all" {{ (!request()->filled('type') || request('type') === 'all') ? 'selected' : '' }} data-lang-key="all_categories">{{ __('messages.all_categories') ?? 'All Categories' }}</option>
                        <option value="inventory" {{ request('type') === 'inventory' ? 'selected' : '' }} data-lang-key="inventory">{{ __('messages.inventory') ?? 'Inventory' }}</option>
                        <option value="order" {{ request('type') === 'order' ? 'selected' : '' }} data-lang-key="orders">{{ __('messages.orders') ?? 'Orders' }}</option>
                        <option value="production" {{ request('type') === 'production' ? 'selected' : '' }} data-lang-key="production">{{ __('messages.production') ?? 'Production' }}</option>
                        <option value="purchase_order" {{ request('type') === 'purchase_order' ? 'selected' : '' }} data-lang-key="purchase_orders">{{ __('messages.purchase_orders') ?? 'Purchase Orders' }}</option>
                        <option value="promotion" {{ request('type') === 'promotion' ? 'selected' : '' }} data-lang-key="promotions">{{ __('messages.promotions') ?? 'Promotions' }}</option>
                    </select>
                </div>

                @if(request()->hasAny(['search', 'type']) && (request('search') || (request('type') && request('type') !== 'all')))
                    <a href="{{ route('admin.notifications', request('status') ? ['status' => request('status')] : []) }}" style="font-size: 0.8rem; font-weight: 700; color: #ef4444; text-decoration: none; padding: 0.25rem 0.5rem;" data-lang-key="clear_filters">
                        {{ __('messages.clear_filters') ?? 'Clear' }}
                    </a>
                @endif
            </form>

            <!-- Actions Group -->
            <div class="notif-action-group" style="display: flex; align-items: center; gap: 0.5rem;">
                @if(auth()->user()->isAdmin() || auth()->user()->hasPermission('notifications.promote'))
                    <x-button variant="warning" id="btn_open_promotion_modal" onclick="openPromotionModal();" style="height: 34px; padding: 0 0.95rem; font-size: 0.825rem; border-radius: 50px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        <span data-lang-key="broadcast_announcement">{{ __('messages.broadcast_announcement') ?? 'Broadcast' }}</span>
                    </x-button>
                @endif

                @if($stats['unread'] > 0)
                    <x-button variant="primary" id="page_mark_all_read" style="height: 34px; padding: 0 0.95rem; font-size: 0.825rem; border-radius: 50px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        <span data-lang-key="mark_all_read">{{ __('messages.mark_all_read') ?? 'Mark All Read' }}</span>
                    </x-button>
                @endif
            </div>
        </div>
    </div>

    <!-- Notification Feed -->
    <div class="notif-feed-card">
        <div class="notif-feed-header">
            <div class="notif-feed-header-left">
                <span class="notif-feed-title" data-lang-key="notification_activity">{{ __('messages.notification_activity') ?? 'Notification Activity' }}</span>
            </div>
            <div class="notif-feed-header-right">
                <span class="notif-feed-count-badge">
                    @if(method_exists($notifications, 'total'))
                        Showing {{ $notifications->firstItem() ?? 0 }} - {{ $notifications->lastItem() ?? 0 }} of {{ $notifications->total() }}
                    @else
                        Total: {{ count($notifications) }}
                    @endif
                </span>
            </div>
        </div>

        <div class="notif-feed-list" id="feed_container">
            @forelse($notifications as $notif)
                @php
                    $isUnread = !$notif->isRead();
                    $sev = $notif->severity ?? 'info';
                    $typeClass = $notif->type ?? 'system';
                @endphp
                <div class="notif-feed-row {{ $isUnread ? 'unread' : '' }}" 
                     id="notif_row_{{ $notif->id }}" 
                     data-id="{{ $notif->id }}"
                     data-url="{{ $notif->action_url ?: '' }}"
                     onclick="handleCardClick(event, {{ $notif->id }}, '{{ $notif->action_url ?: '' }}');">
                    

                    <!-- Category / Severity Icon Box -->
                    <div class="notif-row-icon {{ $sev }} {{ $typeClass }}">
                        @if($typeClass === 'production')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg>
                        @elseif($typeClass === 'order')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        @elseif($sev === 'danger' || $sev === 'critical')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 1 2 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        @elseif($sev === 'warning')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        @elseif($sev === 'success')
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        @else
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                        @endif
                    </div>

                    <!-- Notification Content (Middle) -->
                    <div class="notif-row-body">
                        <div class="notif-row-top">
                            <div class="notif-row-meta">
                                <span class="notif-type-tag {{ $typeClass }}">{{ str_replace('_', ' ', $notif->type) }}</span>
                                <span class="notif-row-time">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    {{ $notif->time_ago }}
                                </span>
                                @if($isUnread)
                                    <span class="notif-unread-indicator">
                                        <span class="unread-pulse"></span>
                                        <span data-lang-key="unread">{{ __('messages.unread') ?? 'Unread' }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="notif-row-title">{{ $notif->title }}</div>

                        <div class="notif-row-desc">
                            {{ $notif->message }}
                        </div>
                    </div>

                    <!-- Right Side Actions Toolbar -->
                    <div class="notif-row-actions" onclick="event.stopPropagation();">
                        @if($notif->action_url)
                            <a href="{{ $notif->action_url }}" class="btn-row-action btn-row-primary" onclick="markRowRead({{ $notif->id }});" title="{{ __('messages.view_details') ?? 'View Details' }}" aria-label="{{ __('messages.view_details') ?? 'View Details' }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        @endif

                        @if($isUnread)
                            <button type="button" class="btn-row-action btn-row-toggle" onclick="markRowRead({{ $notif->id }});" id="btn_read_{{ $notif->id }}" title="{{ __('messages.mark_as_read') ?? 'Mark as read' }}" aria-label="{{ __('messages.mark_as_read') ?? 'Mark as read' }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        @else
                            <button type="button" class="btn-row-action btn-row-toggle is-read" onclick="markRowUnread({{ $notif->id }});" id="btn_unread_{{ $notif->id }}" title="{{ __('messages.mark_as_unread') ?? 'Mark as unread' }}" aria-label="{{ __('messages.mark_as_unread') ?? 'Mark as unread' }}">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            </button>
                        @endif

                        <button type="button" class="btn-row-action btn-row-delete" onclick="deleteNotification({{ $notif->id }});" title="{{ __('messages.delete') ?? 'Delete' }}" aria-label="{{ __('messages.delete') ?? 'Delete' }}" id="btn_delete_{{ $notif->id }}">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="notif-empty-state">
                    <div class="notif-empty-icon">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </div>
                    <h3 class="notif-empty-title" data-lang-key="no_notifications_found">{{ __('messages.no_notifications_found') ?? 'No Notifications Found' }}</h3>
                    <p class="notif-empty-desc" data-lang-key="all_caught_up">{{ __('messages.all_caught_up') ?? "You're all caught up! There are currently no notifications matching this criteria." }}</p>
                </div>
            @endforelse
        </div>



    </div>
</div>

<!-- Promotion / Announcement Modal (Admin) -->
@if(auth()->user()->isAdmin() || auth()->user()->hasPermission('notifications.promote'))
    <div class="notif-modal-backdrop" id="promotion_modal">
        <div class="notif-modal-card">
            <div class="notif-modal-header">
                <h3 class="notif-modal-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    <span data-lang-key="broadcast_announcement">Broadcast Announcement</span>
                </h3>
                <button type="button" class="btn-notif-modal-close" id="btn_close_promotion_modal" onclick="closePromotionModal();">&times;</button>
            </div>

            <form id="promotion_form" action="{{ route('admin.notifications.promotions') }}" method="POST">
                @csrf
                <div class="notif-modal-body">
                    <div class="notif-form-group">
                        <label class="notif-form-label" for="promotion_title_input" data-lang-key="promotion_title">Announcement Title</label>
                        <input type="text" id="promotion_title_input" name="title" class="notif-form-input" placeholder="e.g., Weekend Special: 20% Off All Cakes" required maxlength="150">
                    </div>

                    <div class="notif-form-group">
                        <label class="notif-form-label" for="promotion_message_input" data-lang-key="promotion_message">Announcement Message</label>
                        <textarea id="promotion_message_input" name="message" class="notif-form-textarea" placeholder="Enter announcement message for bakery staff..." required maxlength="1000"></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="notif-form-group">
                            <label class="notif-form-label" for="promotion_role_select" data-lang-key="target_role">Target Role</label>
                            <select id="promotion_role_select" name="target_role" class="notif-form-select">
                                <option value="all" data-lang-key="all_roles">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="manager">Manager</option>
                                <option value="cashier">Cashier</option>
                                <option value="baker">Baker</option>
                            </select>
                        </div>

                        <div class="notif-form-group">
                            <label class="notif-form-label" for="promotion_severity_select" data-lang-key="severity">Severity</label>
                            <select id="promotion_severity_select" name="severity" class="notif-form-select">
                                <option value="info">Info</option>
                                <option value="warning">Warning</option>
                                <option value="success">Success</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="notif-modal-footer">
                    <x-button variant="secondary" onclick="closePromotionModal();" data-lang-key="cancel">{{ __('messages.cancel') ?? 'Cancel' }}</x-button>
                    <x-button variant="primary" type="submit" id="btn_submit_promotion" data-lang-key="send_promotion">{{ __('messages.send_promotion') ?? 'Send Promotion' }}</x-button>
                </div>
            </form>
        </div>
    </div>
@endif

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    function openPromotionModal() {
        const modal = document.getElementById('promotion_modal');
        if (modal) modal.classList.add('is-active');
    }

    function closePromotionModal() {
        const modal = document.getElementById('promotion_modal');
        if (modal) modal.classList.remove('is-active');
    }

    function handleCardClick(event, id, url) {
        if (event.target.closest('button') || event.target.closest('a') || event.target.closest('label') || event.target.closest('input')) {
            return;
        }
        if (url && url !== '#' && url !== 'javascript:void(0)') {
            markRowRead(id);
            window.location.href = url;
        }
    }

    function syncBadges(unreadCount) {
        if (unreadCount === undefined) return;
        const statBadge = document.getElementById('stat_unread_count');
        if (statBadge) statBadge.textContent = unreadCount;
        
        const headerBadge = document.getElementById('notification_badge');
        if (headerBadge) {
            if (unreadCount > 0) {
                headerBadge.textContent = unreadCount;
                headerBadge.style.display = 'inline-flex';
            } else {
                headerBadge.style.display = 'none';
            }
        }
        
        const markAllBtn = document.getElementById('page_mark_all_read');
        if (markAllBtn && unreadCount === 0) {
            markAllBtn.style.display = 'none';
        }
    }

    function markRowRead(id) {
        fetch(`/notifications/${id}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            const row = document.getElementById(`notif_row_${id}`);
            if (row) {
                row.classList.remove('unread');
                const ind = row.querySelector('.notif-unread-indicator');
                if (ind) ind.remove();
                
                const readBtn = document.getElementById(`btn_read_${id}`);
                if (readBtn) {
                    readBtn.outerHTML = `<button type="button" class="btn-row-action btn-row-toggle is-read" onclick="markRowUnread(${id});" id="btn_unread_${id}" title="Mark as unread" aria-label="Mark as unread">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </button>`;
                }
            }
            syncBadges(data.unread_count);
        }).catch(err => console.error(err));
    }

    function markRowUnread(id) {
        fetch(`/notifications/${id}/unread`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json'
            }
        }).then(res => res.json()).then(data => {
            const row = document.getElementById(`notif_row_${id}`);
            if (row) {
                row.classList.add('unread');
                const meta = row.querySelector('.notif-row-meta');
                if (meta && !meta.querySelector('.notif-unread-indicator')) {
                    const ind = document.createElement('span');
                    ind.className = 'notif-unread-indicator';
                    ind.innerHTML = '<span class="unread-pulse"></span> <span data-lang-key="unread">Unread</span>';
                    meta.appendChild(ind);
                }
                
                const unreadBtn = document.getElementById(`btn_unread_${id}`);
                if (unreadBtn) {
                    unreadBtn.outerHTML = `<button type="button" class="btn-row-action btn-row-toggle" onclick="markRowRead(${id});" id="btn_read_${id}" title="Mark as read" aria-label="Mark as read">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>`;
                }
            }
            syncBadges(data.unread_count);
        }).catch(err => console.error(err));
    }

    function deleteNotification(id) {
        const executeDelete = () => {
            fetch(`/notifications/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                }
            }).then(res => res.json()).then(data => {
                const row = document.getElementById(`notif_row_${id}`);
                if (row) {
                    row.style.opacity = '0';
                    row.style.transform = 'translateY(-8px)';
                    setTimeout(() => {
                        row.remove();
                    }, 220);
                }
                syncBadges(data.unread_count);
            }).catch(err => console.error(err));
        };

        if (typeof window.showConfirmDialog === 'function') {
            window.showConfirmDialog({
                title: 'Dismiss Notification',
                message: 'Are you sure you want to dismiss this notification?',
                confirmText: 'Dismiss',
                isDanger: true,
                onConfirm: executeDelete
            });
        } else if (confirm('Are you sure you want to dismiss this notification?')) {
            executeDelete();
        }
    }

    const markAllBtn = document.getElementById('page_mark_all_read');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function() {
            fetch('{{ route('notifications.readAll') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'Accept': 'application/json'
                }
            }).then(res => res.json()).then(data => {
                document.querySelectorAll('.notif-feed-row.unread').forEach(row => {
                    row.classList.remove('unread');
                    const ind = row.querySelector('.notif-unread-indicator');
                    if (ind) ind.remove();
                    const id = row.dataset.id;
                    const readBtn = document.getElementById(`btn_read_${id}`);
                    if (readBtn) {
                        readBtn.outerHTML = `<button type="button" class="btn-row-action btn-row-toggle is-read" onclick="markRowUnread(${id});" id="btn_unread_${id}" title="Mark as unread">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span class="btn-action-label" data-lang-key="mark_as_unread">Mark as unread</span>
                        </button>`;
                    }
                });
                syncBadges(0);
                markAllBtn.style.display = 'none';
            }).catch(err => console.error(err));
        });
    }
</script>
@endsection
