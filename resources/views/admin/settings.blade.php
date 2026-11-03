@extends('layouts.app')

@section('title', 'System Settings - Bakery Management System')
@section('body_class', 'is-catalog-page is-settings-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
@endpush

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('admin.settings') }}" class="period-pill {{ request('tab') !== 'notifications' ? 'active' : '' }}" id="tab_general_settings">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
            <span data-lang-key="general_settings">General Settings</span>
        </a>
        <a href="{{ route('admin.settings', ['tab' => 'notifications']) }}" class="period-pill {{ request('tab') === 'notifications' ? 'active' : '' }}" id="tab_notifications_settings">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 5px;"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            <span data-lang-key="notifications">Notifications</span>
            @if(($realUnreadCount ?? 0) > 0)
                <span style="display: inline-flex; align-items: center; justify-content: center; margin-left: 6px; min-width: 18px; height: 18px; padding: 0 5px; font-size: 0.72rem; font-weight: 700; line-height: 1; border-radius: 9999px; background: #ef4444; color: #ffffff;">{{ $realUnreadCount }}</span>
            @endif
        </a>
    </div>
@endsection

@section('content')
<div class="settings-page-container">
    <div class="settings-scrollable-body" id="settings_scrollable_body">

@if(!$canManage)
    <x-alert type="warning">
        <span data-lang-key="settings_readonly_notice">View-only mode: You have permission to inspect system configuration. Elevated privileges are required to save changes.</span>
    </x-alert>
@endif

@if(session('success'))
    <x-alert type="success" :message="session('success')" />
@endif

@if($errors->any())
    <x-alert type="danger" :message="$errors->first()" />
@endif

<!-- ==========================================================================
     PRIMARY 2-COLUMN GRID: Shop Information (Left) & Tax & Discount (Right)
     ========================================================================== -->
<div class="settings-primary-grid">
    
    <!-- SECTION 1: PROFILE & SHOP INFORMATION -->
    <x-card id="profile_info" title="Profile & Shop Information">
        <x-slot:actions>
            <span class="badge" style="background: rgba(217, 119, 6, 0.12); color: #d97706; font-size: 0.72rem; font-weight: 700; padding: 4px 10px; border-radius: 9999px; border: 1px solid rgba(217, 119, 6, 0.25); text-transform: uppercase;">
                {{ auth()->check() ? ucfirst(auth()->user()->role) : 'Admin' }}
            </span>
        </x-slot:actions>

        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="settings-form" id="form_shop_info">
            @csrf
            <div class="settings-card-body">
                <!-- User Profile & Account Name -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <x-input name="name" id="input_user_name" label="Full Name" :value="auth()->check() ? auth()->user()->name : 'Admin'" placeholder="Enter full name" required :disabled="!$canManage" />
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                            <span>Login Username</span>
                            <span class="username-locked-badge">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Read Only
                            </span>
                        </label>
                        <div class="username-locked-wrap">
                            <input type="text" class="form-control-input username-locked-input" value="{{ auth()->check() ? auth()->user()->username : 'admin' }}" disabled readonly>
                            <span class="username-locked-icon">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Store Name & Phone -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <x-input name="shop_name" id="input_shop_name" label="Shop Name" :value="$settings['shop_name'] ?? 'Sweet Delights Bakery'" placeholder="Enter bakery name" required :disabled="!$canManage" />
                    <x-input name="shop_phone" id="input_shop_phone" label="Phone Number" :value="auth()->check() && !empty(auth()->user()->phone) ? auth()->user()->phone : ($settings['shop_phone'] ?? '+855 23 123 456')" placeholder="+855 ..." required :disabled="!$canManage" />
                </div>

                <!-- Email & Address -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <x-input type="email" name="shop_email" id="input_shop_email" label="Email Address" :value="auth()->check() && !empty(auth()->user()->email) ? auth()->user()->email : ($settings['shop_email'] ?? 'info@sweetdelights.com')" placeholder="user@bakery.com" required :disabled="!$canManage" />
                    <x-input name="shop_address" id="input_shop_address" label="Address" :value="$settings['shop_address'] ?? 'Siemreap, Cambodia'" placeholder="Full store address" required :disabled="!$canManage" />
                </div>

                <!-- Avatar / Logo Upload -->
                <div class="form-group">
                    <label class="form-label" data-lang-key="shop_logo">Profile Avatar & Shop Logo</label>
                    <div class="logo-upload-box">
                        @php
                            $currentLogoOrAvatar = \App\Services\SettingsService::getString('user_avatar_' . auth()->id(), '')
                                ?: \App\Services\SettingsService::getString('admin_avatar', '')
                                ?: ($settings['shop_logo'] ?? '');
                        @endphp
                        <div class="logo-preview-img" id="logo_preview_box" style="border-radius: 50%; width: 64px; height: 64px; border: 2px solid rgba(217, 119, 6, 0.35); overflow: hidden; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.03);">
                            @if(!empty($currentLogoOrAvatar))
                                <img src="{{ asset($currentLogoOrAvatar) }}" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" alt="Avatar / Logo">
                            @else
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            @endif
                        </div>
                        <div class="logo-upload-info">
                            @if($canManage)
                                <label for="shop_logo_input" class="btn btn-sm btn-outline" style="cursor: pointer; margin-bottom: 0.25rem; display: inline-flex; align-items: center; gap: 0.35rem;">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                    <span data-lang-key="upload_new_logo">Upload Avatar / Logo</span>
                                </label>
                                <input type="file" name="shop_logo" id="shop_logo_input" accept="image/*" style="display: none;">
                            @endif
                            <span style="font-size: 0.75rem; color: var(--text-muted, #9ca3af);">PNG, JPG, WEBP (Max 2MB)</span>
                        </div>
                    </div>
                </div>

                <!-- Password Change Drawer (Optional) -->
                @if($canManage)
                    <div style="background: rgba(217, 119, 6, 0.05); border: 1px solid rgba(217, 119, 6, 0.2); border-radius: 12px; padding: 0.85rem 1rem; margin-top: 0.25rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;" id="btn_toggle_profile_pwd">
                            <div style="display: flex; align-items: center; gap: 0.65rem;">
                                <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(217, 119, 6, 0.15); color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </div>
                                <div style="font-size: 0.875rem; font-weight: 700; color: var(--text-title, #29150d);">Change Password</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline" style="border-radius: 20px; font-size: 0.75rem; padding: 4px 12px; display: inline-flex; align-items: center; gap: 4px;" id="btn_pwd_toggle_label">
                                <span id="pwd_toggle_text">Set Password</span>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" id="pwd_toggle_arrow" style="transition: transform 0.2s;"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        </div>
                        
                        <div id="profile_pwd_fields" style="display: none; margin-top: 1rem; padding-top: 0.85rem; border-top: 1px dashed rgba(217, 119, 6, 0.25);">
                            <div class="form-group" style="margin-bottom: 0.85rem;">
                                <label class="form-label" for="input_current_password" style="font-size: 0.825rem; font-weight: 600;">Current Password</label>
                                <input type="password" name="current_password" id="input_current_password" class="form-control-input" placeholder="Enter current password" autocomplete="current-password">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.85rem;">
                                <div class="form-group">
                                    <label class="form-label" for="input_new_password" style="font-size: 0.825rem; font-weight: 600;">New Password</label>
                                    <input type="password" name="new_password" id="input_new_password" class="form-control-input" placeholder="Min 6 characters" autocomplete="new-password">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="input_new_password_confirmation" style="font-size: 0.825rem; font-weight: 600;">Confirm Password</label>
                                    <input type="password" name="new_password_confirmation" id="input_new_password_confirmation" class="form-control-input" placeholder="Repeat new password" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_shop_info">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_changes">Save Profile & Settings</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

    <!-- SECTION 2: TAX & DISCOUNT -->
    <x-card title="Tax & Discount">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form" id="form_tax_discount">
            @csrf
            <div class="settings-card-body">
                <x-input type="number" name="tax_rate" id="input_tax_rate" label="Tax Rate (%)" step="0.1" min="0" max="100" :value="$settings['tax_rate'] ?? '10.0'" placeholder="0.0" required :disabled="!$canManage" />
                <x-input type="number" name="discount" id="input_discount" label="Default Discount (%)" step="0.1" min="0" max="100" :value="$settings['discount'] ?? '5.0'" placeholder="0.0" required :disabled="!$canManage" />
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_tax_discount">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_changes">Save Changes</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

</div>

<!-- ==========================================================================
     SECONDARY STORE SETTINGS GRID
     ========================================================================== -->
<div class="settings-secondary-grid">

    <!-- Card 3: Receipt Configuration -->
    <x-card title="Receipt Configuration">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form" id="form_receipt">
            @csrf
            <div class="settings-card-body">
                <div class="form-group">
                    <label class="form-label" for="input_receipt_header" data-lang-key="receipt_header">Receipt Header</label>
                    <textarea name="receipt_header" id="input_receipt_header" class="form-control-textarea" placeholder="Header text printed at top of receipts" {{ !$canManage ? 'disabled' : '' }}>{{ old('receipt_header', $settings['receipt_header'] ?? "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads") }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label" for="input_receipt_footer" data-lang-key="receipt_footer">Receipt Footer</label>
                    <textarea name="receipt_footer" id="input_receipt_footer" class="form-control-textarea" placeholder="Footer text printed at bottom of receipts" {{ !$canManage ? 'disabled' : '' }}>{{ old('receipt_footer', $settings['receipt_footer'] ?? "Thank you for visiting!\nFollow us @sweetdelights.bakery") }}</textarea>
                </div>
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_receipt">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_changes">Save Changes</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

    <!-- Card 4: General Preferences -->
    <x-card title="General Preferences">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form" id="form_general">
            @csrf
            <div class="settings-card-body">
                <x-select name="currency" id="currency_select" label="Currency" :disabled="!$canManage">
                    <option value="USD" {{ in_array($settings['currency'] ?? 'USD', ['USD', '$']) ? 'selected' : '' }}>USD ($) - US Dollar</option>
                    <option value="KHR" {{ in_array($settings['currency'] ?? '', ['KHR', '៛']) ? 'selected' : '' }}>KHR (៛) - Cambodian Riel</option>
                </x-select>

                <x-select name="date_format" id="date_format_select" label="Date Format" :disabled="!$canManage">
                    <option value="DD/MM/YYYY" {{ ($settings['date_format'] ?? 'DD/MM/YYYY') === 'DD/MM/YYYY' ? 'selected' : '' }}>DD/MM/YYYY (16/08/2026)</option>
                    <option value="MM/DD/YYYY" {{ ($settings['date_format'] ?? '') === 'MM/DD/YYYY' ? 'selected' : '' }}>MM/DD/YYYY (08/16/2026)</option>
                    <option value="YYYY-MM-DD" {{ ($settings['date_format'] ?? '') === 'YYYY-MM-DD' ? 'selected' : '' }}>YYYY-MM-DD (2026-08-16)</option>
                </x-select>

                <x-select name="language" id="language_select" label="Language" :disabled="!$canManage">
                    <option value="EN" {{ strtoupper($settings['language'] ?? 'EN') === 'EN' ? 'selected' : '' }}>English</option>
                    <option value="KM" {{ strtoupper($settings['language'] ?? '') === 'KM' ? 'selected' : '' }}>Khmer (ភាសាខ្មែរ)</option>
                </x-select>
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_general">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_changes">Save Changes</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

    <!-- Card 5: Business Hours & Operations -->
    <x-card title="Business Hours & Operations">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form" id="form_operations">
            @csrf
            <div class="settings-card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <x-input type="time" name="opening_time" id="input_opening_time" label="Opening Time" :value="$settings['opening_time'] ?? '07:00'" required :disabled="!$canManage" />
                    <x-input type="time" name="closing_time" id="input_closing_time" label="Closing Time" :value="$settings['closing_time'] ?? '20:00'" required :disabled="!$canManage" />
                </div>

                <x-input type="number" name="low_stock_threshold" id="input_low_stock_threshold" label="Default Low-Stock Threshold" step="0.5" min="0" max="10000" :value="$settings['low_stock_threshold'] ?? '5'"  required :disabled="!$canManage" />
                <x-input type="number" name="expiry_warning_days" id="input_expiry_warning_days" label="Expiry Warning Window (Days)" min="1" max="90" :value="$settings['expiry_warning_days'] ?? '3'"  required :disabled="!$canManage" />
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_operations">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_changes">Save Changes</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

    <!-- Card 6: Appearance & Alerts -->
    <x-card title="Appearance & Display">
        <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form" id="form_appearance">
            @csrf
            <input type="hidden" name="theme_mode" id="theme_mode_hidden_input" value="{{ $settings['theme_mode'] ?? 'light' }}">

            <div class="settings-card-body">
                <div class="form-group">
                    <label class="form-label" data-lang-key="theme_mode">Theme Mode</label>
                    <div class="ios-segmented-control">
                        <button type="button" class="segmented-option {{ ($settings['theme_mode'] ?? 'light') === 'light' ? 'active' : '' }}" data-theme="light">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            <span data-lang-key="light">Light</span>
                        </button>
                        <button type="button" class="segmented-option {{ ($settings['theme_mode'] ?? '') === 'dark' ? 'active' : '' }}" data-theme="dark">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                            <span data-lang-key="dark">Dark</span>
                        </button>
                    </div>
                </div>

                <div class="ios-switch-row" style="margin-top: 0.5rem;">
                    <div>
                        <div class="ios-switch-label" data-lang-key="system_notifications">System Notifications</div>
                        
                    </div>
                    <label class="ios-switch">
                        <input type="checkbox" name="notifications_enabled" value="1" id="notifications_toggle" {{ ($settings['notifications_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div>
                        <div class="ios-switch-label" data-lang-key="low_stock_alerts">Low Stock Alerts</div>
                        
                    </div>
                    <label class="ios-switch">
                        <input type="checkbox" name="low_stock_alerts_enabled" value="1" id="low_stock_toggle" {{ ($settings['low_stock_alerts_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                        <span class="switch-slider"></span>
                    </label>
                </div>

                <div class="ios-switch-row">
                    <div>
                        <div class="ios-switch-label" data-lang-key="expiry_alerts">Expiry Date Alerts</div>
                        
                    </div>
                    <label class="ios-switch">
                        <input type="checkbox" name="expiry_alerts_enabled" value="1" id="expiry_alerts_toggle" {{ ($settings['expiry_alerts_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                        <span class="switch-slider"></span>
                    </label>
                </div>
            </div>

            @if($canManage)
                <div class="card-footer" style="padding: 1rem 0 0 0; border-top: 1px solid var(--border-color, #eee9e0); margin-top: auto; display: flex; justify-content: flex-end;">
                    <x-button type="submit" variant="primary" id="btn_save_appearance">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span data-lang-key="save_preferences">Save Preferences</span>
                    </x-button>
                </div>
            @endif
        </form>
    </x-card>

    <!-- Card 7: System Backup & Restore (Admin Only) -->
    @if($isAdmin)
        <x-card title="System Backup & Restore" id="card_backup_restore">
            <div class="settings-card-body">
                <!-- Backup Creation -->
                <div style="padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-color, #eee9e0);">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap;">
                        <div>
                            <div style="font-size: 0.875rem; font-weight: 700; color: var(--text-title);" data-lang-key="download_backup">System Configuration Backup</div>
                            
                        </div>
                        <form action="{{ route('admin.settings.backup') }}" method="POST" id="form_backup">
                            @csrf
                            <x-button type="submit" variant="secondary" id="btn_create_backup" style="white-space: nowrap;">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                <span data-lang-key="backup_download">Download Backup</span>
                            </x-button>
                        </form>
                    </div>
                </div>

                <!-- Restore Form -->
                <form action="{{ route('admin.settings.restore') }}" method="POST" enctype="multipart/form-data" id="form_restore">
                    @csrf
                    <div class="form-group" style="margin-bottom: 0.75rem;">
                        <label class="form-label" for="input_backup_file" data-lang-key="restore_file">Select Backup File (.JSON)</label>
                        <input type="file" name="backup_file" id="input_backup_file" accept=".json,application/json" class="form-control-input" required>
                    </div>

                    <div style="background-color: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.65rem; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="confirm_restore" id="checkbox_confirm_restore" value="1" required style="cursor: pointer; flex-shrink: 0; width: 16px; height: 16px;">
                            <span style="font-size: 0.78rem; font-weight: 600; color: #dc2626;" data-lang-key="restore_warning">I confirm that I want to restore system configurations. Current store parameters will be overwritten.</span>
                        </label>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <x-button type="submit" variant="danger" id="btn_restore_settings">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span data-lang-key="restore_now">Restore Settings</span>
                        </x-button>
                    </div>
                </form>
            </div>
        </x-card>
    @endif

</div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/settings.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtns = document.querySelectorAll('.segmented-option');
            const hiddenThemeInput = document.getElementById('theme_mode_hidden_input');

            themeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    themeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const theme = this.getAttribute('data-theme');
                    if (hiddenThemeInput) hiddenThemeInput.value = theme;
                    if (typeof window.applyTheme === 'function') {
                        window.applyTheme(theme);
                    } else {
                        document.documentElement.setAttribute('data-theme', theme);
                        if (document.body) {
                            document.body.classList.toggle('dark-mode', theme === 'dark');
                            document.body.classList.toggle('theme-dark', theme === 'dark');
                        }
                        localStorage.setItem('bakery_theme', theme);
                    }
                });
            });
        });
    </script>
@endpush
