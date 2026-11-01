@extends('layouts.app')

@section('title', 'My Profile - Bakery Management System')

@section('header_toolbar')
    <div class="period-pill-group">
        <a href="{{ route('profile') }}" class="period-pill active">
            <span>Account Profile</span>
        </a>
    </div>
@endsection

@section('content')
<div style="max-width: 960px; margin: 0 auto; padding-bottom: 2.5rem;">
    @if(session('success'))
        <x-alert type="success" :message="session('success')" />
    @endif

    @if($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; color: #dc2626; font-size: 0.9rem;">
            <div style="font-weight: 700; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span>Please correct the errors below:</span>
            </div>
            <ul style="margin: 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="display: grid; grid-template-columns: 320px 1fr; gap: 1.5rem; align-items: start;">
        <!-- Left Column: User Summary Card -->
        <div style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-subtle, #e4e4e7); border-radius: 16px; padding: 1.75rem 1.5rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            @php
                $profileAvatar = \App\Services\SettingsService::getString('user_avatar_' . $user->id, '')
                    ?: \App\Services\SettingsService::getString('admin_avatar', '')
                    ?: \App\Services\SettingsService::getString('shop_logo', '');
            @endphp
            @if(!empty($profileAvatar))
                <div style="width: 84px; height: 84px; border-radius: 50%; overflow: hidden; margin: 0 auto 1.25rem auto; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25); border: 2.5px solid rgba(217, 119, 6, 0.35);">
                    <img src="{{ asset($profileAvatar) }}" alt="{{ $user->name }}" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
            @else
                <div style="width: 84px; height: 84px; border-radius: 50%; background: linear-gradient(135deg, #d97706, #f59e0b); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800; margin: 0 auto 1.25rem auto; box-shadow: 0 4px 14px rgba(217, 119, 6, 0.25);">
                    {{ strtoupper(substr($user->name ?: $user->username, 0, 1)) }}
                </div>
            @endif

            <h2 style="font-size: 1.25rem; font-weight: 800; color: var(--text-title, #18181b); margin: 0 0 0.25rem 0;">{{ $user->name }}</h2>
            <div style="font-size: 0.85rem; color: var(--text-muted, #71717a); margin-bottom: 0.85rem;">{{ '@' . $user->username }}</div>

            <div style="display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1.5rem;">
                @php
                    $roleBadgeColors = match(strtolower($user->role)) {
                        'admin' => ['bg' => 'rgba(220, 38, 38, 0.1)', 'color' => '#dc2626', 'border' => 'rgba(220, 38, 38, 0.2)'],
                        'manager' => ['bg' => 'rgba(217, 119, 6, 0.1)', 'color' => '#d97706', 'border' => 'rgba(217, 119, 6, 0.2)'],
                        'baker' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'color' => '#059669', 'border' => 'rgba(16, 185, 129, 0.2)'],
                        'cashier' => ['bg' => 'rgba(37, 99, 235, 0.1)', 'color' => '#2563eb', 'border' => 'rgba(37, 99, 235, 0.2)'],
                        default => ['bg' => '#f4f4f5', 'color' => '#52525b', 'border' => '#e4e4e7'],
                    };
                @endphp
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; background: {{ $roleBadgeColors['bg'] }}; color: {{ $roleBadgeColors['color'] }}; border: 1px solid {{ $roleBadgeColors['border'] }};">
                    {{ ucfirst($user->role) }}
                </span>
                <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; padding: 4px 10px; border-radius: 9999px; background: {{ $user->status === 'active' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' }}; color: {{ $user->status === 'active' ? '#059669' : '#dc2626' }}; border: 1px solid {{ $user->status === 'active' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' }};">
                    {{ ucfirst($user->status ?? 'active') }}
                </span>
            </div>

            <div style="border-top: 1px solid var(--border-subtle, #f4f4f5); padding-top: 1.25rem; text-align: left; font-size: 0.825rem; display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #71717a;">Email:</span>
                    <strong style="color: var(--text-title, #27272a); font-weight: 600;">{{ $user->email }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #71717a;">Phone:</span>
                    <strong style="color: var(--text-title, #27272a); font-weight: 600;">{{ $user->phone ?: 'Not set' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #71717a;">Last Login:</span>
                    <strong style="color: var(--text-title, #27272a); font-weight: 600;">{{ $user->last_login_at ? $user->last_login_at->diffForHumans() : 'First Session' }}</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #71717a;">Joined:</span>
                    <strong style="color: var(--text-title, #27272a); font-weight: 600;">{{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}</strong>
                </div>
            </div>
        </div>

        <!-- Right Column: Edit Profile & Password Form -->
        <div style="background: var(--bg-card, #ffffff); border: 1px solid var(--border-subtle, #e4e4e7); border-radius: 16px; padding: 1.75rem 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
            <div style="margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-subtle, #f4f4f5);">
                <h3 style="font-size: 1.15rem; font-weight: 800; color: var(--text-title, #18181b); margin: 0 0 0.25rem 0;">Edit Personal Details</h3>
                <p style="font-size: 0.825rem; color: var(--text-muted, #71717a); margin: 0;">Update your account credentials, contact information, and password.</p>
            </div>

            <form action="{{ route('profile.update') }}" method="POST" id="form_update_profile">
                @csrf
                @method('PUT')

                <!-- Personal Information -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div>
                        <x-input name="name" label="Full Name" :value="old('name', $user->name)" :required="true" />
                    </div>
                    <div>
                        <label class="form-label" style="display: block; font-size: 0.825rem; font-weight: 600; color: #52525b; margin-bottom: 0.35rem;">Username</label>
                        <input type="text" class="form-control" value="{{ $user->username }}" disabled style="background: #f4f4f5; cursor: not-allowed; color: #71717a;">
                        <small style="color: #a1a1aa; font-size: 0.725rem;">Username cannot be modified.</small>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div>
                        <x-input type="email" name="email" label="Email Address" :value="old('email', $user->email)" :required="true" />
                    </div>
                    <div>
                        <x-input type="text" name="phone" label="Phone Number" :value="old('phone', $user->phone)" placeholder="e.g. +855 12 345 678" />
                    </div>
                </div>

                <!-- Password Change Section -->
                <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-subtle, #f4f4f5);">
                    <div style="margin-bottom: 1.25rem;">
                        <h4 style="font-size: 1rem; font-weight: 800; color: var(--text-title, #18181b); margin: 0 0 0.25rem 0; display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>Change Password</span>
                        </h4>
                        <p style="font-size: 0.8rem; color: var(--text-muted, #71717a); margin: 0;">Leave password fields empty if you do not want to change your current password.</p>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label class="form-label" for="current_password" style="display: block; font-size: 0.825rem; font-weight: 600; color: #52525b; margin-bottom: 0.35rem;">Current Password</label>
                        <input type="password" name="current_password" id="current_password" class="form-control" autocomplete="current-password" placeholder="Enter your current password">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div>
                            <label class="form-label" for="password" style="display: block; font-size: 0.825rem; font-weight: 600; color: #52525b; margin-bottom: 0.35rem;">New Password</label>
                            <input type="password" name="password" id="password" class="form-control" autocomplete="new-password" placeholder="Minimum 6 characters">
                        </div>
                        <div>
                            <label class="form-label" for="password_confirmation" style="display: block; font-size: 0.825rem; font-weight: 600; color: #52525b; margin-bottom: 0.35rem;">Confirm New Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" autocomplete="new-password" placeholder="Repeat new password">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle, #f4f4f5);">
                    <x-button variant="primary" type="submit" id="btn_save_profile">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span>Save Profile Changes</span>
                    </x-button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

