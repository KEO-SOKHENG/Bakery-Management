<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Bakery Management System</title>

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
        <h1 class="login-title">Forgot Password?</h1>
        <p class="login-subtitle">Enter your username or registered email to reset your account password</p>

        <!-- Status & Error Notifications -->
        @if (session('status'))
            <div class="login-alert" style="background: rgba(34, 197, 94, 0.15); border: 1px solid rgba(34, 197, 94, 0.35); color: #15803d; margin-bottom: 1.25rem;" role="alert">
                <svg class="login-alert-icon" style="color: #15803d;" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('status') }}</span>
            </div>

            @if(session('reset_url'))
                <div class="reset-url-box" style="width: 100%; margin-bottom: 1.25rem;">
                    <strong>Direct Recovery Link:</strong>
                    <div style="font-size: 0.75rem; margin-top: 4px; word-break: break-all; opacity: 0.85;">A reset link has been prepared for your session.</div>
                    <a href="{{ session('reset_url') }}" class="reset-url-link-btn">Proceed to Set New Password &rarr;</a>
                </div>
            @endif
        @endif

        @if ($errors->any())
            <div class="login-alert login-alert-error" role="alert" style="margin-bottom: 1.25rem;">
                <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <!-- Forgot Password Form -->
        <form action="{{ route('password.email') }}" method="POST" class="login-form">
            @csrf

            <!-- Username / Email Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="username_input">Username or Registered Email</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </span>
                    <input type="text" name="username" id="username_input" class="pill-input" required
                        value="{{ old('username') }}" autocomplete="username" autofocus
                        placeholder="Enter username or email address">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-pill-btn" id="submit_forgot_btn">
                Send Reset Link
            </button>

            <!-- Back to Login Link -->
            <a href="{{ route('login') }}" class="btn-pill-secondary">
                &larr; Back to Sign In
            </a>
        </form>
    </div>
</body>

</html>
