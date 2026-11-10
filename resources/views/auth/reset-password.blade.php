<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Bakery Management System</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Dedicated Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ @filemtime(public_path('css/login.css')) ?: '1.0' }}">
</head>

<body>
    @php
        $shopName = \App\Services\SettingsService::getString('shop_name', 'Moon Cake');
        $shopLogo = \App\Services\SettingsService::getString('shop_logo', '');
        $logoUrl = '';
        if (!empty($shopLogo)) {
            $logoUrl = (str_starts_with($shopLogo, 'http://') || str_starts_with($shopLogo, 'https://'))
                ? $shopLogo
                : asset(ltrim($shopLogo, '/'));
        }
    @endphp

    <!-- Background Blur Overlay -->
    <div class="login-bg-overlay"></div>

    <!-- Glassmorphic Card -->
    <div class="login-card-container">
        <!-- Logo Badge -->
        <div class="login-logo-badge {{ !empty($logoUrl) ? 'has-custom-img' : '' }}">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="login-logo-custom-img">
            @else
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            @endif
        </div>

        <!-- System Branding -->
        <h1 class="login-title">Choose New Password</h1>
        <p class="login-subtitle">Please enter your account email and specify your new secure password</p>

        <!-- Error Notifications -->
        @if ($errors->any())
            <div class="login-alert login-alert-error" role="alert" style="margin-bottom: 1.25rem;">
                <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <!-- Reset Password Form -->
        <form action="{{ route('password.update') }}" method="POST" class="login-form">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Email Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="email_input">Account Email</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="4" width="20" height="16" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </span>
                    <input type="email" name="email" id="email_input" class="pill-input" required
                        value="{{ old('email', $email ?? '') }}" autocomplete="email"
                        placeholder="your-email@bakery.com">
                </div>
            </div>

            <!-- New Password Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="password_input">New Password</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input type="password" name="password" id="password_input" class="pill-input has-right-icon"
                        required autocomplete="new-password" placeholder="Min. 6 characters">
                    <button type="button" class="input-icon-right" id="toggle_password_btn"
                        title="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Confirm Password Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="password_confirmation_input">Confirm New Password</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </span>
                    <input type="password" name="password_confirmation" id="password_confirmation_input" class="pill-input has-right-icon"
                        required autocomplete="new-password" placeholder="Repeat new password">
                    <button type="button" class="input-icon-right" id="toggle_confirm_password_btn"
                        title="Toggle confirm password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-pill-btn" id="submit_reset_btn" style="margin-top: 0.5rem;">
                Set New Password
            </button>

            <!-- Back to Login Link -->
            <a href="{{ route('login') }}" class="btn-pill-secondary">
                &larr; Back to Sign In
            </a>
        </form>
    </div>

    <!-- Toggle Password Visibility Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            function bindToggle(btnId, inputId) {
                const btn = document.getElementById(btnId);
                const input = document.getElementById(inputId);
                if (btn && input) {
                    btn.addEventListener('click', function () {
                        const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                        input.setAttribute('type', type);
                    });
                }
            }
            bindToggle('toggle_password_btn', 'password_input');
            bindToggle('toggle_confirm_password_btn', 'password_confirmation_input');
        });
    </script>
</body>

</html>

