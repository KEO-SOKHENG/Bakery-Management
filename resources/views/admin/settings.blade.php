@extends('layouts.app')
@section('title', 'System Settings - Bakery Management System')
@section('body_class', 'is-catalog-page is-settings-page')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}?v={{ @filemtime(public_path('css/settings.css')) ?: '1.0' }}">
    <link rel="stylesheet" href="{{ asset('css/orders.css') }}">
    <link rel="stylesheet" href="{{ asset('css/settings-ui.css') }}?v={{ @filemtime(public_path('css/settings-ui.css')) ?: '1.0' }}">
@endpush

@section('header_toolbar')
    <div class="header-page-title-wrap" style="display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap;">
        <h1 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: var(--text-title, #29150d); letter-spacing: -0.015em; line-height: 1.2;" data-lang-key="system_settings">System Settings</h1>
        <div id="settings_autosave_badge" class="settings-autosave-badge" style="display: inline-flex; align-items: center; gap: 6px; font-size: 0.76rem; font-weight: 600; color: #10b981; background: rgba(16, 185, 129, 0.12); padding: 3px 10px; border-radius: 9999px; opacity: 0; pointer-events: none; transition: opacity 0.25s ease, background 0.25s ease, color 0.25s ease;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
            <span id="settings_autosave_text">Saved automatically</span>
        </div>
    </div>
@endsection

@section('content')
<div class="apple-settings-frame">
    @if(!$canManage)
        <div style="margin-bottom: 0.5rem;">
            <x-alert type="warning">
                <span data-lang-key="settings_readonly_notice">View-only mode: You have permission to inspect system configuration. Elevated privileges are required to save changes.</span>
            </x-alert>
        </div>
    @endif

    @if(session('success'))
        <div style="margin-bottom: 0.5rem;">
            <x-alert type="success" :message="session('success')" />
        </div>
    @endif

    @if($errors->any())
        <div style="margin-bottom: 0.5rem;">
            <x-alert type="danger" :message="$errors->first()" />
        </div>
    @endif

    <!-- Apple iOS / macOS 2-Panel Settings Architecture -->
    <div class="apple-settings-shell" id="apple_settings_shell">

        <!-- ==============================================================
             LEFT PANEL: Grouped Categories Navigation (~268px width)
             3 Groups: GENERAL, PREFERENCES, MANAGEMENT (8 Items total)
             ============================================================== -->
        <aside class="apple-nav-pane" id="apple_nav_pane" aria-label="Settings Categories">
            
            <!-- GROUP 1: GENERAL -->
            <div class="apple-nav-section-title" data-lang-key="settings_group_general">General</div>

            <!-- 1. Profile & Business -->
            <button type="button" class="apple-nav-item active" role="tab" id="nav_profile_business" data-target="panel_profile_business" aria-selected="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span class="apple-nav-title" data-lang-key="profile_business">Profile &amp; Business</span>
            </button>

            <!-- GROUP 2: PREFERENCES -->
            <div class="apple-nav-section-title" data-lang-key="settings_group_preferences">Preferences</div>

            <!-- 3. Business Settings -->
            <button type="button" class="apple-nav-item" role="tab" id="nav_business" data-target="panel_business" aria-selected="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="21" x2="4" y2="14"></line><line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line><line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line><line x1="20" y1="12" x2="20" y2="3"></line>
                    <line x1="1" y1="14" x2="7" y2="14"></line><line x1="9" y1="8" x2="15" y2="8"></line><line x1="17" y1="16" x2="23" y2="16"></line>
                </svg>
                <span class="apple-nav-title" data-lang-key="business_settings">Business Settings</span>
            </button>

            <!-- 4. Notifications -->
            <button type="button" class="apple-nav-item" role="tab" id="nav_notifications" data-target="panel_notifications" aria-selected="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                </svg>
                <span class="apple-nav-title" data-lang-key="notifications">Notifications</span>
            </button>

            <!-- 5. Appearance -->
            <button type="button" class="apple-nav-item" role="tab" id="nav_appearance" data-target="panel_appearance" aria-selected="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                </svg>
                <span class="apple-nav-title" data-lang-key="appearance">Appearance &amp; Language</span>
            </button>

            <!-- GROUP 3: MANAGEMENT -->
            <div class="apple-nav-section-title" data-lang-key="settings_group_management">Management</div>

            

            <!-- 7. Storage & Files -->
            <button type="button" class="apple-nav-item" role="tab" id="nav_storage" data-target="panel_storage" aria-selected="false">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line>
                </svg>
                <span class="apple-nav-title" data-lang-key="storage_files">Storage & Files</span>
            </button>

            <!-- 8. Backup & Restore (Admin Only) -->
            @if($isAdmin)
                <button type="button" class="apple-nav-item" role="tab" id="nav_backup" data-target="panel_backup" aria-selected="false">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span class="apple-nav-title" data-lang-key="nav_backup_restore">Backup & Restore</span>
                </button>
            @endif
        </aside>

        <!-- ==============================================================
             RIGHT PANEL: Apple Detail Content
             ============================================================== -->
        <main class="apple-detail-pane" id="apple_detail_pane" aria-label="Setting Details">
            
            <!-- Mobile Back Button Header -->
            <div class="apple-mobile-header">
                <button type="button" class="apple-mobile-back-btn" id="apple_mobile_back">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                    <span>Settings</span>
                </button>
            </div>

            <div class="apple-detail-body" id="apple_detail_body">

                <!-- ----------------------------------------------------------
                     1. PROFILE & BUSINESS PANEL (Unified Bakery + Personal Account)
                     ---------------------------------------------------------- -->
                <section class="apple-panel active" id="panel_profile_business" role="tabpanel" aria-labelledby="nav_profile_business">
                    <div class="profile-business-container storage-page profile-page">

                        <!-- Page header -->
                        <div class="storage-page-header">
                            <div class="storage-page-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            </div>
                            <div class="storage-page-heading">
                                <h2 class="storage-page-title">Profile &amp; Business</h2>
                                <span class="storage-page-subtitle">Your bakery details and admin account.</span>
                            </div>
                        </div>

                        @php
                            $currentLogo = \App\Services\SettingsService::getString('shop_logo', '');
                            $hasCustomLogo = !empty($currentLogo);
                            $currentLogoUrl = $hasCustomLogo
                                ? ((str_starts_with($currentLogo, 'http://') || str_starts_with($currentLogo, 'https://')) ? $currentLogo : asset(ltrim($currentLogo, '/')))
                                : '';

                            $currentAvatar = auth()->check()
                                ? (\App\Services\SettingsService::getString('user_avatar_' . auth()->id(), '')
                                    ?: \App\Services\SettingsService::getString('admin_avatar', ''))
                                : \App\Services\SettingsService::getString('admin_avatar', '');
                            $hasCustomAvatar = !empty($currentAvatar);
                            $currentAvatarUrl = $hasCustomAvatar
                                ? ((str_starts_with($currentAvatar, 'http://') || str_starts_with($currentAvatar, 'https://')) ? $currentAvatar : asset(ltrim($currentAvatar, '/')))
                                : '';
                        @endphp

                        <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="settings-form" id="form_profile_business">
                            @csrf
                            <!-- Bakery Logo inputs -->
                            <input type="hidden" name="remove_shop_logo" id="input_remove_bakery_logo" value="0">
                            <input type="file" name="shop_logo" id="bakery_logo_file_input" accept="image/png,image/jpeg,image/webp" style="display: none;">

                            <!-- Admin Avatar inputs (mirrored from the single profile image picker) -->
                            <input type="hidden" name="remove_avatar" id="input_remove_admin_avatar" value="0">
                            <input type="file" name="avatar" id="admin_avatar_file_input" accept="image/png,image/jpeg,image/webp" style="display: none;">

                            <!-- 1. Profile (bakery + personal, one shared image) -->
                            <div class="storage-section-head" id="section_bakery_profile">
                                <h3>Bakery Profile</h3>
                                <span>Logo, name and your account</span>
                            </div>

                            <div class="sp-card">
                                <div class="profile-media-row">
                                    <div class="profile-avatar-wrap">
                                        <div class="profile-avatar bakery-logo-avatar" id="bakery_logo_preview_box">
                                            @if($hasCustomLogo || $hasCustomAvatar)
                                                <img src="{{ $currentLogoUrl ?: $currentAvatarUrl }}" alt="Profile" id="bakery_logo_img">
                                            @else
                                                <div class="profile-avatar-placeholder" id="bakery_logo_placeholder">
                                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                                    </svg>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="profile-media-body">
                                        <div class="apple-field-wrap">
                                            <label class="apple-field-label" for="input_shop_name">
                                                <span>Bakery Name</span>
                                                <span class="required-star" style="color: #ef4444; font-weight: 700; margin-left: 2px;">*</span>
                                            </label>
                                            <input type="text" name="shop_name" id="input_shop_name" class="apple-input" value="{{ old('shop_name', $settings['shop_name'] ?? 'Sweet Delights Bakery') }}" placeholder="Enter bakery name" required {{ !$canManage ? 'disabled' : '' }}>
                                        </div>
                                        <div class="profile-fields-grid" style="margin-top: 0.75rem;">
                                            <div class="apple-field-wrap">
                                                <label class="apple-field-label" for="input_user_name">
                                                    <span>Full Name</span>
                                                    <span class="required-star" style="color: #ef4444; font-weight: 700; margin-left: 2px;">*</span>
                                                </label>
                                                <input type="text" name="name" id="input_user_name" class="apple-input" value="{{ old('name', auth()->check() ? auth()->user()->name : 'Admin') }}" placeholder="Enter full name" required {{ !$canManage ? 'disabled' : '' }}>
                                            </div>
                                            <div class="apple-field-wrap">
                                                <label class="apple-field-label" for="input_username">
                                                    <span>Login Username</span>
                                                </label>
                                                <div class="username-locked-wrap">
                                                    <input type="text" id="input_username" class="apple-input username-locked-input" value="{{ auth()->check() ? auth()->user()->username : 'admin' }}" disabled readonly>
                                                    <span class="username-locked-icon" title="Username cannot be modified">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        @if($canManage)
                                            <div class="profile-media-actions" style="margin-top: 0.75rem;">
                                                <label for="bakery_logo_file_input" class="btn-apple-secondary profile-action-btn" id="btn_change_bakery_logo" title="Upload profile photo (used as bakery logo and account photo)">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
                                                        <circle cx="12" cy="13" r="4"/>
                                                    </svg>
                                                    <span id="btn_change_logo_text">{{ ($hasCustomLogo || $hasCustomAvatar) ? 'Change Photo' : 'Upload Photo' }}</span>
                                                </label>
                                                <button type="button" class="btn-apple-secondary profile-action-btn profile-remove-btn" id="btn_reset_bakery_logo" style="{{ ($hasCustomLogo || $hasCustomAvatar) ? '' : 'display: none !important;' }}">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                                    </svg>
                                                    <span>Remove</span>
                                                </button>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Contact Information -->
                            <div class="storage-section-head" id="section_contact_info">
                                <h3>Contact Information</h3>
                                <span>Shown on receipts</span>
                            </div>

                            <div class="sp-card">
                                <div class="contact-info-grid">
                                    <div class="apple-field-wrap">
                                        <label class="apple-field-label" for="input_shop_phone">
                                            <span>Contact Phone</span>
                                            <span class="required-star" style="color: #ef4444; font-weight: 700; margin-left: 2px;">*</span>
                                        </label>
                                        <input type="text" name="shop_phone" id="input_shop_phone" class="apple-input" value="{{ old('shop_phone', $settings['shop_phone'] ?? '+855 23 123 456') }}" placeholder="+855 ..." required {{ !$canManage ? 'disabled' : '' }}>
                                    </div>

                                    <div class="apple-field-wrap">
                                        <label class="apple-field-label" for="input_shop_email">
                                            <span>Official Email</span>
                                            <span class="required-star" style="color: #ef4444; font-weight: 700; margin-left: 2px;">*</span>
                                        </label>
                                        <input type="email" name="shop_email" id="input_shop_email" class="apple-input" value="{{ old('shop_email', $settings['shop_email'] ?? 'admin@bakery.com') }}" placeholder="admin@bakery.com" required {{ !$canManage ? 'disabled' : '' }}>
                                    </div>

                                    <div class="apple-field-wrap contact-grid-full">
                                        <label class="apple-field-label" for="input_shop_address">
                                            <span>Business Address</span>
                                            <span class="required-star" style="color: #ef4444; font-weight: 700; margin-left: 2px;">*</span>
                                        </label>
                                        <input type="text" name="shop_address" id="input_shop_address" class="apple-input" value="{{ old('shop_address', $settings['shop_address'] ?? 'Monivong Blvd, Phnom Penh, Cambodia') }}" placeholder="Full store address" required {{ !$canManage ? 'disabled' : '' }}>
                                    </div>
                                </div>
                            </div>

                            <!-- 3. Login Security (saved together with the rest by the single Save Changes button) -->
                            <div class="storage-section-head" id="security_info">
                                <h3>Login Security</h3>
                                <span>Optional: leave blank to keep your current password</span>
                            </div>

                            <div class="sp-card">
                                <div class="apple-row-stacked">
                                    <div class="apple-field-wrap">
                                        <label class="apple-field-label" for="input_current_password">
                                            <span>Current Password</span>

                                        </label>
                                        <input type="password" name="current_password" id="input_current_password" class="apple-input" placeholder="Enter current password" autocomplete="current-password" {{ !$canManage ? 'disabled' : '' }}>
                                    </div>
                                    <div class="security-passwords-grid" style="margin-top: 0.85rem;">
                                        <div class="apple-field-wrap">
                                            <label class="apple-field-label" for="input_new_password">
                                                <span>New Password</span>

                                            </label>
                                            <input type="password" name="new_password" id="input_new_password" class="apple-input" placeholder="Min 6 characters" autocomplete="new-password" {{ !$canManage ? 'disabled' : '' }}>
                                        </div>
                                        <div class="apple-field-wrap">
                                            <label class="apple-field-label" for="input_new_password_confirmation">
                                                <span>Confirm New Password</span>

                                            </label>
                                            <input type="password" name="new_password_confirmation" id="input_new_password_confirmation" class="apple-input" placeholder="Repeat new password" autocomplete="new-password" {{ !$canManage ? 'disabled' : '' }}>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Single Save button, below all sections -->
                            <div class="sp-card-actions profile-save-row">
                                <span id="profile_business_save_status" class="profile-business-save-status"></span>
                                @if($canManage)
                                    <button type="submit" class="storage-sync-btn profile-save-btn" id="btn_save_profile_business">
                                        <svg class="btn-spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: none; animation: spin 0.8s linear infinite;">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-linecap="round" fill="none"/>
                                        </svg>
                                        <svg class="btn-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                        <span class="btn-text">Save Changes</span>
                                    </button>
                                @endif
                            </div>
                        </form>
                    </div>
                </section>

                <!-- ----------------------------------------------------------
                     3. BUSINESS SETTINGS PANEL
                     ---------------------------------------------------------- -->
                <section class="apple-panel" id="panel_business" role="tabpanel" aria-labelledby="nav_business">
<div class="profile-business-container storage-page profile-page">
                    
                    <div class="storage-page-header">
                            <div class="storage-page-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/></svg>
                            </div>
                            <div class="storage-page-heading">
                                <h2 class="storage-page-title">Business Settings</h2>
                                <span class="storage-page-subtitle">Hours, tax, receipts and regional settings.</span>
                            </div>
                        </div>


                    <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form sp-form" id="form_business">
                        @csrf

                        <!-- Group 1: Business Hours & Operations -->
                        <div class="sp-card bz-card">
                            <div class="bz-head">
                                <span class="bz-head-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/></svg></span>
                                <h3>Business Hours &amp; Operations</h3>
                                <span class="bz-head-note">Opening and closing times</span>
                            </div>
                            <div class="apple-row-stacked" style="padding: 0; border-bottom: none;">
                                <div class="apple-input-grid">
                                    <x-input type="time" name="opening_time" id="input_opening_time" label="Daily Opening Time" :value="$settings['opening_time'] ?? '07:00'" required :disabled="!$canManage" />
                                    <x-input type="time" name="closing_time" id="input_closing_time" label="Daily Closing Time" :value="$settings['closing_time'] ?? '20:00'" required :disabled="!$canManage" />
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: Tax & Discount -->
                        <div class="sp-card bz-card">
                            <div class="bz-head">
                                <span class="bz-head-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg></span>
                                <h3 data-lang-key="tax_discount">Tax &amp; Discount</h3>
                                <span class="bz-head-note">Applied to every order</span>
                            </div>
                            <div class="apple-row-stacked" style="padding: 0; border-bottom: none;">
                                <div class="apple-input-grid">
                                    <x-input type="number" name="tax_rate" id="input_tax_rate" label="Standard Tax Rate (%)" step="0.1" min="0" max="100" :value="$settings['tax_rate'] ?? '10.0'" placeholder="0.0" required :disabled="!$canManage" />
                                    <x-input type="number" name="discount" id="input_discount" label="Default Order Discount (%)" step="0.1" min="0" max="100" :value="$settings['discount'] ?? '5.0'" placeholder="0.0" required :disabled="!$canManage" />
                                </div>

                                <!-- Live Interactive Calculation Simulator -->
                                <div class="tax-discount-simulator" id="tax_discount_simulator">
                                    <div class="simulator-header">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                        <span>Live Order Calculation Preview (Sample $100 Order)</span>
                                    </div>
                                    <div class="simulator-grid">
                                        <div class="sim-row"><span>Sample Subtotal:</span><strong>$100.00</strong></div>
                                        <div class="sim-row"><span>Default Discount (<span id="sim_discount_pct">{{ $settings['discount'] ?? '5.0' }}</span>%):</span><strong style="color: #ef4444;">-$<span id="sim_discount_val">5.00</span></strong></div>
                                        <div class="sim-row"><span>Taxable Net:</span><strong>$<span id="sim_net_val">95.00</span></strong></div>
                                        <div class="sim-row"><span>VAT / Tax (<span id="sim_tax_pct">{{ $settings['tax_rate'] ?? '10.0' }}</span>%):</span><strong style="color: #10b981;">+$<span id="sim_tax_val">9.50</span></strong></div>
                                        <div class="sim-divider"></div>
                                        <div class="sim-row sim-total"><span>Customer Payable:</span><strong>$<span id="sim_total_val">104.50</span></strong></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 3: Printed Receipt Branding -->
                        <div class="storage-section-head" id="section_receipt_branding">
                            <h3>Receipt Branding</h3>
                            <span>Printed on customer slips</span>
                        </div>
                        <div class="sp-card">
                            <div class="apple-row-stacked" style="padding: 0; border-bottom: none;">
                                <div class="receipt-preview-wrapper">
                                    <div style="flex: 1; min-width: 280px;">
                                        <div class="form-group">
                                            <label class="form-label" for="input_receipt_header">Receipt Header</label>
                                            <textarea name="receipt_header" id="input_receipt_header" class="apple-textarea" rows="3" placeholder="Header text printed at top of receipts" {{ !$canManage ? 'disabled' : '' }}>{{ old('receipt_header', $settings['receipt_header'] ?? "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads") }}</textarea>
                                            <span class="apple-row-desc" style="margin-top: 4px;">Displayed directly beneath the bakery title on customer slips.</span>
                                        </div>
                                        <div class="form-group" style="margin-top: 0.85rem;">
                                            <label class="form-label" for="input_receipt_footer">Receipt Footer</label>
                                            <textarea name="receipt_footer" id="input_receipt_footer" class="apple-textarea" rows="3" placeholder="Footer text printed at bottom of receipts" {{ !$canManage ? 'disabled' : '' }}>{{ old('receipt_footer', $settings['receipt_footer'] ?? "Thank you for visiting!\nFollow us @sweetdelights.bakery") }}</textarea>
                                            <span class="apple-row-desc" style="margin-top: 4px;">Greeting or social media handle printed at the end of customer receipts.</span>
                                        </div>
                                    </div>

                                    <!-- Thermal Paper Preview Mockup -->
                                    <div class="thermal-receipt-preview">
                                        <div class="thermal-paper-header">
                                            <div class="thermal-paper-title">{{ $settings['shop_name'] ?? 'Sweet Delights Bakery' }}</div>
                                            <div class="thermal-paper-custom-header" id="receipt_preview_header">{{ $settings['receipt_header'] ?? "Sweet Delights Bakery & Cafe\nFresh Daily Artisan Breads" }}</div>
                                        </div>
                                        <div class="thermal-paper-items">
                                            <div class="thermal-paper-row"><span>1x Sourdough Loaf</span><span>$4.50</span></div>
                                            <div class="thermal-paper-row"><span>2x Butter Croissant</span><span>$6.00</span></div>
                                            <div class="thermal-paper-row" style="font-weight: 700; margin-top: 6px; border-top: 1px dotted #9ca3af; padding-top: 4px;"><span>Subtotal:</span><span>$10.50</span></div>
                                            <div class="thermal-paper-row"><span>Tax (10%):</span><span>+$1.05</span></div>
                                            <div class="thermal-paper-row" style="font-weight: 800; font-size: 0.95rem; margin-top: 4px;"><span>TOTAL:</span><span>$11.55</span></div>
                                        </div>
                                        <div class="thermal-paper-footer" id="receipt_preview_footer">{{ $settings['receipt_footer'] ?? "Thank you for visiting!\nFollow us @sweetdelights.bakery" }}</div>
                                        <div class="thermal-barcode-box">
                                            <div class="thermal-barcode-bars"></div>
                                            <span style="font-size: 0.68rem; letter-spacing: 2px;">*BK-2026-9812*</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Group 4: Regional Preferences & Formatting -->
                        <div class="sp-card bz-card">
                            <div class="bz-head">
                                <span class="bz-head-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
                                <h3 data-lang-key="regional_preferences">Regional Preferences</h3>
                                <span class="bz-head-note">Currency, date and language</span>
                            </div>
                            <div class="apple-row-stacked" style="padding: 0; border-bottom: none;">
                                <div class="apple-input-grid">
                                    <x-select name="currency" id="currency_select" data-styled-select label="Currency" :disabled="!$canManage">
                                        <option value="USD" {{ in_array($settings['currency'] ?? 'USD', ['USD', '$']) ? 'selected' : '' }}>USD ($) - US Dollar</option>
                                        <option value="KHR" {{ in_array($settings['currency'] ?? '', ['KHR', '៛']) ? 'selected' : '' }}>KHR (៛) - Cambodian Riel</option>
                                    </x-select>

                                    <x-select name="date_format" id="date_format_select" data-styled-select label="Date Format" :disabled="!$canManage">
                                        <option value="DD/MM/YYYY" {{ ($settings['date_format'] ?? 'DD/MM/YYYY') === 'DD/MM/YYYY' ? 'selected' : '' }}>DD/MM/YYYY (16/08/2026)</option>
                                        <option value="MM/DD/YYYY" {{ ($settings['date_format'] ?? '') === 'MM/DD/YYYY' ? 'selected' : '' }}>MM/DD/YYYY (08/16/2026)</option>
                                        <option value="YYYY-MM-DD" {{ ($settings['date_format'] ?? '') === 'YYYY-MM-DD' ? 'selected' : '' }}>YYYY-MM-DD (2026-08-16)</option>
                                    </x-select>
                                </div>
                            </div>
                        </div>

                        <!-- Single Save button, below all cards -->
                        <div class="sp-card-actions profile-save-row">
                            <span id="business_save_status" class="profile-business-save-status"></span>
                            @if($canManage)
                                <button type="submit" class="storage-sync-btn profile-save-btn" id="btn_save_business">
                                    <svg class="btn-spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display: none; animation: spin 0.8s linear infinite;">
                                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="32" stroke-linecap="round" fill="none"/>
                                    </svg>
                                    <svg class="btn-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                    <span class="btn-text">Save Changes</span>
                                </button>
                            @endif
                        </div>
                    </form>
</div>
</section>

                <!-- ----------------------------------------------------------
                     4. NOTIFICATIONS PANEL
                     ---------------------------------------------------------- -->
                <section class="apple-panel" id="panel_notifications" role="tabpanel" aria-labelledby="nav_notifications">
<div class="profile-business-container storage-page profile-page">
                    <div class="storage-page-header">
                            <div class="storage-page-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            </div>
                            <div class="storage-page-heading">
                                <h2 class="storage-page-title">Notifications & Alerts</h2>
                                <span class="storage-page-subtitle">Alert sounds, low stock and expiry warnings.</span>
                            </div>
                        </div>


                    <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form sp-form" id="form_notifications">
                        @csrf

                        <!-- Group 1: Audio & Banner Alerts -->
                        <div class="storage-section-head">
                            <h3>System Alert Preferences</h3>
                            <span>Choose which alerts you receive</span>
                        </div>
                        <div class="sp-card">
                            <div class="apple-row">
                                <div class="apple-row-label-wrap">
                                    <span class="apple-row-title" data-lang-key="system_notifications">System Notifications</span>
                                    <span class="apple-row-desc">Sound and banner alerts for new orders and payments</span>
                                </div>
                                <label class="apple-switch">
                                    <input type="checkbox" name="notifications_enabled" value="1" id="notifications_toggle" {{ ($settings['notifications_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                                    <span class="apple-switch-slider"></span>
                                </label>
                            </div>

                            <div class="apple-row">
                                <div class="apple-row-label-wrap">
                                    <span class="apple-row-title" data-lang-key="low_stock_alerts">Low Stock Alerts</span>
                                    <span class="apple-row-desc">Warn me when stock runs low</span>
                                </div>
                                <label class="apple-switch">
                                    <input type="checkbox" name="low_stock_alerts_enabled" value="1" id="low_stock_toggle" {{ ($settings['low_stock_alerts_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                                    <span class="apple-switch-slider"></span>
                                </label>
                            </div>

                            <div class="apple-row">
                                <div class="apple-row-label-wrap">
                                    <span class="apple-row-title" data-lang-key="expiry_alerts">Expiry Date Alerts</span>
                                    <span class="apple-row-desc">Remind me before ingredients expire</span>
                                </div>
                                <label class="apple-switch">
                                    <input type="checkbox" name="expiry_alerts_enabled" value="1" id="expiry_alerts_toggle" {{ ($settings['expiry_alerts_enabled'] ?? '1') == '1' ? 'checked' : '' }} {{ !$canManage ? 'disabled' : '' }}>
                                    <span class="apple-switch-slider"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Group 2: Warning Thresholds -->
                        <div class="storage-section-head">
                            <h3>Threshold Warning Levels</h3>
                            <span>When alerts should trigger</span>
                        </div>
                        <div class="sp-card">
                            <div class="apple-row-stacked">
                                <div class="apple-input-grid">
                                    <x-input type="number" name="low_stock_threshold" id="input_low_stock_threshold" label="Default Low-Stock Threshold (Units)" step="0.5" min="0" max="10000" :value="$settings['low_stock_threshold'] ?? '5'" required :disabled="!$canManage" />
                                    <x-input type="number" name="expiry_warning_days" id="input_expiry_warning_days" label="Expiry Advance Notice (Days)" min="1" max="90" :value="$settings['expiry_warning_days'] ?? '3'" required :disabled="!$canManage" />
                                </div>
                            </div>
                        </div>
                    </form>
</div>
</section>

                <!-- ----------------------------------------------------------
                     5. APPEARANCE PANEL
                     ---------------------------------------------------------- -->
                <section class="apple-panel" id="panel_appearance" role="tabpanel" aria-labelledby="nav_appearance">
<div class="profile-business-container storage-page profile-page">
                    <div class="storage-page-header">
                            <div class="storage-page-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                            </div>
                            <div class="storage-page-heading">
                                <h2 class="storage-page-title" data-lang-key="appearance_display">Appearance & Display</h2>
                                <span class="storage-page-subtitle" data-lang-key="appearance_display_sub">Choose your theme and see the fonts in use.</span>
                            </div>
                        </div>


                    <form action="{{ route('admin.settings.update') }}" method="POST" class="settings-form sp-form" id="form_appearance">
                        @csrf
                        <input type="hidden" name="theme_mode" id="theme_mode_hidden_input" value="{{ $settings['theme_mode'] ?? 'light' }}">

                        <!-- Group 1: Theme Mode Switcher -->
                        <div class="storage-section-head">
                            <h3 data-lang-key="theme_mode">Theme Mode</h3>
                            <span data-lang-key="how_system_looks">How the system looks</span>
                        </div>
                        <div class="sp-card">
                            <div class="apple-row">
                                <div class="apple-row-label-wrap">
                                    <span class="apple-row-title" data-lang-key="interface_style">Interface Style</span>
                                    <span class="apple-row-desc" data-lang-key="interface_style_sub">Light or dark colors</span>
                                </div>
                                <div class="ios-segmented-control">
                                    <button type="button" class="segmented-option {{ ($settings['theme_mode'] ?? 'light') === 'light' ? 'active' : '' }}" data-theme-value="light">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                                        <span data-lang-key="light">Light</span>
                                    </button>
                                    <button type="button" class="segmented-option {{ ($settings['theme_mode'] ?? '') === 'dark' ? 'active' : '' }}" data-theme-value="dark">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                                        <span data-lang-key="dark">Dark</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Group 2: System Language (moved here from Business Settings) -->
                        <div class="storage-section-head">
                            <h3 data-lang-key="system_language">System Language</h3>
                            <span data-lang-key="system_language_sub">Language of the interface</span>
                        </div>
                        <div class="sp-card">
                            <div style="max-width: 50%;">
                                <x-select name="language" id="language_select" data-styled-select label="System Language" label-key="system_language" :disabled="!$canManage">
                                    <option value="EN" {{ strtoupper($settings['language'] ?? 'EN') === 'EN' ? 'selected' : '' }}>English</option>
                                    <option value="KM" {{ strtoupper($settings['language'] ?? '') === 'KM' ? 'selected' : '' }}>Khmer (ភាសាខ្មែរ)</option>
                                </x-select>
                            </div>
                        </div>
                    </form>
</div>
</section>



                <!-- ----------------------------------------------------------
                     7. STORAGE & FILES PANEL (Simplified + Collapsible Advanced)
                     ---------------------------------------------------------- -->
                <section class="apple-panel" id="panel_storage" role="tabpanel" aria-labelledby="nav_storage">
                    @php
                        $storageDiag = $storageDiagnostics ?? null;
                        $prov = $storageDiag['provider'] ?? [];
                        $conn = $storageDiag['connection'] ?? [];
                        $usage = $storageDiag['usage'] ?? [];
                        $disp = $storageDiag['display'] ?? ['state' => 'unavailable', 'badge' => 'Data unavailable', 'headline' => 'Data unavailable', 'subtitle' => 'Data unavailable', 'detail' => null, 'usage_available' => false];
                        $recentFiles = array_slice($usage['recent_files'] ?? [], 0, 3);
                        $usageOk = !empty($disp['usage_available']);
                        $totalFilesText = $usageOk ? number_format($usage['total_files'] ?? 0) : 'Data unavailable';
                        $totalBytesText = $usageOk ? ($usage['total_bytes_formatted'] ?? 'Data unavailable') : 'Data unavailable';
                        $latency = $conn['latency_ms'] ?? null;
                        $syncedAt = $storageDiag['synced_at_formatted'] ?? null;
                    @endphp

                    <div class="profile-business-container storage-page">

                        <!-- A. Page header -->
                        <div class="storage-page-header">
                            <div class="storage-page-icon">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/><polyline points="12 16 12 11"/><polyline points="9.5 13 12 10.5 14.5 13"/></svg>
                            </div>
                            <div class="storage-page-heading">
                                <h2 class="storage-page-title">Storage &amp; Files</h2>
                                <span class="storage-page-subtitle">Your bakery's uploaded files.</span>
                            </div>
                            <button type="button" class="storage-sync-btn" id="btn_refresh_storage" title="Re-check the storage connection and reload file information">
                                <svg id="storage_refresh_spinner" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                    <path d="M3 3v5h5"/>
                                    <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                                    <path d="M16 21h5v-5"/>
                                </svg>
                                <span id="btn_refresh_storage_text">Sync Now</span>
                            </button>
                        </div>

                        <!-- C. Overview: two cards -->
                        <div class="storage-stat-grid">
                            <div class="storage-stat-card">
                                <div class="storage-stat-icon is-files">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                </div>
                                <div class="storage-stat-body">
                                    <span class="storage-stat-label">Total Files</span>
                                    <strong class="storage-stat-value" id="kpi_total_files">{{ $totalFilesText }}</strong>
                                </div>
                            </div>
                            <div class="storage-stat-card">
                                <div class="storage-stat-icon is-used">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                                </div>
                                <div class="storage-stat-body">
                                    <span class="storage-stat-label">Storage Used</span>
                                    <strong class="storage-stat-value" id="kpi_total_bytes">{{ $totalBytesText }}</strong>
                                </div>
                            </div>
                        </div>

                        <!-- D. Recent uploads -->
                        <div class="storage-section-head">
                            <h3>Recent Uploads</h3>
                            <span id="storage_last_synced_text">{{ $syncedAt ? 'Updated ' . $syncedAt : '' }}</span>
                        </div>

                        <div class="storage-recent-list" id="storage_recent_list">
                            @forelse($recentFiles as $file)
                                @php
                                    $isImage = str_starts_with((string) ($file['mime_type'] ?? ''), 'image/') && !empty($file['url']);
                                @endphp
                                <div class="storage-file-row">
                                    <div class="storage-file-thumb">
                                        @if($isImage)
                                            <img src="{{ $file['url'] }}" alt="" loading="lazy">
                                        @else
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        @endif
                                    </div>
                                    <div class="storage-file-main">
                                        <span class="storage-file-name" title="{{ $file['path'] }}">{{ $file['name'] }}</span>
                                        <span class="storage-file-tag">{{ $file['category'] }}</span>
                                    </div>
                                    <span class="storage-file-size">{{ $file['size_formatted'] }}</span>
                                    <span class="storage-file-date">{{ $file['last_modified_formatted'] }}</span>
                                    @if(!empty($file['url']))
                                        <a href="{{ $file['url'] }}" target="_blank" rel="noopener noreferrer" class="storage-open-btn">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                            <span>Open</span>
                                        </a>
                                    @else
                                        <span class="storage-file-private">Private</span>
                                    @endif
                                </div>
                            @empty
                                <div class="storage-empty">
                                    {{ $usageOk ? 'No files have been uploaded yet.' : 'Data unavailable' }}
                                </div>
                            @endforelse
                        </div>

                        <!-- E. Advanced details (collapsed by default) -->
                        <details class="storage-advanced" id="storage_advanced_details">
                            <summary class="storage-advanced-summary">
                                <span class="storage-advanced-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h0a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h0a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                </span>
                                <span class="storage-advanced-text">
                                    <strong>Advanced Details</strong>
                                    <span>Storage driver, bucket, region and connection information.</span>
                                </span>
                                <svg class="storage-advanced-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </summary>
                            <dl class="storage-advanced-list">
                                <div><dt>Storage Provider</dt><dd id="storage_info_provider">{{ $prov['provider'] ?? 'Data unavailable' }}</dd></div>
                                <div><dt>Filesystem Driver</dt><dd id="storage_info_driver"><code>{{ $prov['driver'] ?? 'Data unavailable' }}</code>@if(!empty($prov['disk_name'])) <span class="storage-adv-muted">(disk: {{ $prov['disk_name'] }})</span>@endif</dd></div>
                                <div><dt>Target Bucket</dt><dd id="storage_info_bucket"><code>{{ $prov['bucket'] ?? 'Data unavailable' }}</code></dd></div>
                                <div><dt>Storage Region</dt><dd id="storage_info_region"><code>{{ $prov['region'] ?? 'Data unavailable' }}</code></dd></div>
                                <div><dt>Endpoint Host</dt><dd id="storage_info_endpoint"><code>{{ $prov['endpoint_host'] ?? 'Data unavailable' }}</code></dd></div>
                                <div><dt>Connection Diagnostics</dt><dd id="storage_info_diag">{{ ($conn['status'] ?? 'Data unavailable') . (isset($conn['latency_ms']) ? ' · ' . $conn['latency_ms'] . ' ms' : '') }}@if(!empty($conn['message']))<span class="storage-adv-note">{{ $conn['message'] }}</span>@endif</dd></div>
                            </dl>
                        </details>

                    </div>
                </section>

                <!-- ----------------------------------------------------------
                     8. SYSTEM BACKUP & RESTORE PANEL (ADMIN ONLY)
                     ---------------------------------------------------------- -->
                @if($isAdmin)
                    <section class="apple-panel" id="panel_backup" role="tabpanel" aria-labelledby="nav_backup" data-panel-title="System Backup & Restore">
                        <div class="profile-business-container storage-page profile-page" id="full_backup_root"
                             data-url-index="{{ route('admin.backups.index') }}"
                             data-url-store="{{ route('admin.backups.store') }}"
                             data-url-verify="{{ route('admin.backups.verify') }}"
                             data-url-gate="{{ route('admin.backups.productionGate') }}"
                             data-url-status="{{ url('/admin/backups/__ID__/status') }}"
                             data-url-sandbox="{{ url('/admin/backups/__ID__/sandbox-restore') }}">

                            <div class="bkx-header">
                                <div class="bkx-header-icon">
                                    <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                </div>
                                <div class="bkx-header-text">
                                    <h2 class="bkx-title" data-lang-key="system_backup">Backup &amp; Restore</h2>
                                    <p class="bkx-subtitle">Manage your database backups and restore when needed.</p>
                                </div>
                            </div>

                            <!-- Overview cards -->
                            <div class="bkx-stats">
                                <div class="bkx-stat">
                                    <span class="bkx-stat-icon is-blue"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg></span>
                                    <div><span class="bkx-stat-label">Database Size</span><strong class="bkx-stat-value" id="bkx_stat_size">&mdash;</strong></div>
                                </div>
                                <div class="bkx-stat">
                                    <span class="bkx-stat-icon is-amber"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg></span>
                                    <div><span class="bkx-stat-label">Total Backups</span><strong class="bkx-stat-value" id="bkx_stat_total">&mdash;</strong></div>
                                </div>
                                <div class="bkx-stat">
                                    <span class="bkx-stat-icon is-violet"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
                                    <div><span class="bkx-stat-label">Last Backup</span><strong class="bkx-stat-value is-small" id="bkx_stat_last">&mdash;</strong></div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="bkx-actions" id="bk_create_card">
                                <div class="bkx-action">
                                    <span class="bkx-action-icon is-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.66 3.58 3 8 3s8-1.34 8-3V5"/><path d="M4 11v6c0 1.66 3.58 3 8 3s8-1.34 8-3v-6"/></svg></span>
                                    <div class="bkx-action-body">
                                        <strong>Create Backup</strong>
                                        <span>Create a new full backup of your database.</span>
                                        <button type="button" class="bkx-btn-primary" id="btn_create_full_backup" title="Saved to {{ app(\App\Services\Backup\BackupHistoryService::class)->destinationLabel() }}. Encrypted and verified automatically.">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            <span>Create Full Backup</span>
                                        </button>
                                    </div>
                                    <ul class="bk-components" id="bk_components" hidden>
                                        <li data-component="database"><span>Database</span><em class="bk-pill is-pending">Waiting</em></li>
                                        <li data-component="media"><span>Media files</span><em class="bk-pill is-pending">Waiting</em></li>
                                        <li data-component="settings"><span>Settings</span><em class="bk-pill is-pending">Waiting</em></li>
                                        <li data-component="package"><span>Package &amp; encryption</span><em class="bk-pill is-pending">Waiting</em></li>
                                        <li data-component="verification"><span>Verification</span><em class="bk-pill is-pending">Waiting</em></li>
                                    </ul>
                                    <p class="bk-message" id="bk_create_message" hidden></p>
                                </div>

                                <div class="bkx-action">
                                    <span class="bkx-action-icon is-rust"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/><polyline points="12 7 12 12 15 14"/></svg></span>
                                    <div class="bkx-action-body">
                                        <strong>Restore Backup</strong>
                                        <span>Restore your database from a backup file.</span>
                                        <button type="button" class="bkx-btn-secondary" id="bkx_open_restore">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            <span>Restore from Backup</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Backup History -->
                            <div class="bkx-section-head">
                                <h3>Backup History</h3>
                                <button type="button" class="bkx-viewall" id="bkx_view_all" hidden>
                                    <span id="bk_history_destination">View All Backups</span>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                </button>
                            </div>
                            <div class="bkx-table-card">
                                <div class="bkx-table-head" aria-hidden="true">
                                    <span>Backup Name</span><span>Type</span><span>Size</span><span>Date &amp; Time</span><span>Status</span><span>Actions</span>
                                </div>
                                <div id="bk_history_list">
                                    <div class="storage-empty">Loading...</div>
                                </div>
                            </div>

                            <!-- Advanced Details -->
                            <details class="bkx-advanced" id="bkx_advanced">
                                <summary>
                                    <span class="bkx-advanced-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
                                    <span class="bkx-advanced-text"><strong>Advanced Details</strong><span>Backup settings, production restore authorization, and system information.</span></span>
                                    <span class="bkx-advanced-chevron"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg></span>
                                </summary>
                                <div class="bkx-advanced-body" id="bk_restore_section">


                            <!-- 3. Restore System -->
                            <div class="storage-section-head">
                                <h3>Restore System</h3>
                                <span>Verify first, rehearse in a sandbox, never overwrite by accident</span>
                            </div>
                            <div class="sp-card bk-steps">

                                <div class="bk-step" data-step="1">
                                    <div class="bk-step-head"><span class="bk-step-no">1</span><strong>Select Backup</strong></div>
                                    <div class="bk-step-body">
                                        <div class="apple-field-wrap">
                                            <label class="apple-field-label" for="bk_select_existing">From history</label>
                                            <select id="bk_select_existing" class="apple-input"><option value="">Choose a saved backup</option></select>
                                        </div>
                                        <div class="apple-field-wrap">
                                            <label class="apple-field-label" for="bk_upload_file">Or upload a package (.bkp)</label>
                                            <input type="file" id="bk_upload_file" class="apple-input" accept=".bkp">
                                        </div>
                                    </div>
                                </div>

                                <div class="bk-step" data-step="2">
                                    <div class="bk-step-head"><span class="bk-step-no">2</span><strong>Verify Package</strong></div>
                                    <div class="bk-step-body">
                                        <button type="button" class="btn-apple-secondary" id="btn_bk_verify">Verify Package</button>
                                        <ul class="bk-checks" id="bk_verify_steps" hidden></ul>
                                    </div>
                                </div>

                                <div class="bk-step" data-step="3">
                                    <div class="bk-step-head"><span class="bk-step-no">3</span><strong>Preview</strong></div>
                                    <div class="bk-step-body">
                                        <p class="bk-muted" id="bk_preview_empty">Verify a package to see what it contains. Nothing is restored in this step.</p>
                                        <dl class="storage-advanced-list bk-preview" id="bk_preview" hidden></dl>
                                    </div>
                                </div>

                                <div class="bk-step" data-step="4">
                                    <div class="bk-step-head"><span class="bk-step-no">4</span><strong>Restore to Sandbox</strong></div>
                                    <div class="bk-step-body">
                                        <p class="bk-muted">A rehearsal in a separate test database and test folder. Your live data and files are not touched.</p>
                                        <button type="button" class="btn-apple-secondary" id="btn_bk_sandbox" disabled>Restore to Sandbox</button>
                                        <ul class="bk-checks" id="bk_sandbox_steps" hidden></ul>
                                    </div>
                                </div>

                                <div class="bk-step is-locked" data-step="5">
                                    <div class="bk-step-head"><span class="bk-step-no">5</span><strong>Production Restore Authorization</strong><em class="bk-pill is-fail">Disabled</em></div>
                                    <div class="bk-step-body">
                                        <p class="bk-muted">Restoring into the live system is turned off. It needs separate, explicit approval from the system owner.</p>
                                        <ul class="bk-checks" id="bk_gate_list"></ul>
                                        <button type="button" class="btn-apple-danger" id="btn_bk_production" disabled>Restore to Production</button>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. Settings Backup -->
                            <div class="storage-section-head">
                                <h3>Settings Backup</h3>
                                <span>A small JSON file with your settings only</span>
                            </div>
                            <div class="sui-action-row">
                                <div class="sui-action-text">
                                    <strong>Settings backup file</strong>
                                    <span>Store details, tax rates and preferences. No passwords or secrets.</span>
                                </div>
                                <form action="{{ route('admin.settings.backup') }}" method="POST" id="form_backup" style="margin: 0;">
                                    @csrf
                                    <button type="submit" class="btn-apple-accent" id="btn_create_backup">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        <span data-lang-key="backup_download">Download Backup</span>
                                    </button>
                                </form>
                            </div>

                            <form action="{{ route('admin.settings.restore') }}" method="POST" enctype="multipart/form-data" id="form_restore" class="sui-restore-box">
                                @csrf
                                <div class="sui-warning">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                    <div>
                                        <strong>Restoring settings replaces your current settings.</strong>
                                        <span>Store details, tax, receipt and alert settings will be overwritten by the file. This cannot be undone.</span>
                                    </div>
                                </div>

                                <div class="apple-field-wrap">
                                    <label class="apple-field-label" for="input_backup_file" data-lang-key="restore_file">Backup file (.json)</label>
                                    <input type="file" name="backup_file" id="input_backup_file" accept=".json,application/json" class="apple-input" required>
                                </div>

                                <label class="sui-confirm-check" for="checkbox_confirm_restore">
                                    <input type="checkbox" name="confirm_restore" id="checkbox_confirm_restore" value="1" required>
                                    <span data-lang-key="restore_warning">I understand my current settings will be replaced.</span>
                                </label>

                                <div class="sui-restore-actions">
                                    <button type="submit" class="btn-apple-danger" id="btn_restore_settings">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        <span data-lang-key="restore_now">Restore Settings</span>
                                    </button>
                                </div>
                            </form>
                                </div>
                            </details>
                        </div>

                        <!-- Restore confirmation dialog -->
                        <div class="sui-modal" id="restore_confirm_modal" hidden>
                            <div class="sui-modal-backdrop" data-close="1"></div>
                            <div class="sui-modal-card" role="alertdialog" aria-modal="true" aria-labelledby="restore_confirm_title">
                                <div class="sui-modal-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                </div>
                                <h3 id="restore_confirm_title">Restore these settings?</h3>
                                <p>Your current settings will be replaced with the contents of <strong id="restore_confirm_filename">the selected file</strong>. This cannot be undone.</p>
                                <div class="sui-modal-actions">
                                    <button type="button" class="btn-apple-secondary" id="btn_restore_cancel">Cancel</button>
                                    <button type="button" class="btn-apple-danger" id="btn_restore_confirm">Yes, restore</button>
                                </div>
                            </div>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/backup.js') }}?v={{ @filemtime(public_path('js/backup.js')) ?: '1.0' }}"></script>
    <script src="{{ asset('js/settings.js') }}?v={{ @filemtime(public_path('js/settings.js')) ?: '1.0' }}"></script>
@endpush