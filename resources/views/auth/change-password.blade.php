<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password - Bakery Management System</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Dedicated Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <style>
        .password-notice-banner {
            background: rgba(217, 119, 6, 0.12);
            border: 1px solid rgba(217, 119, 6, 0.3);
            color: #d97706;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            font-weight: 600;
            line-height: 1.4;
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            text-align: left;
        }
        .user-greeting-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(86, 48, 32, 0.08);
            border: 1px solid rgba(86, 48, 32, 0.15);
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 700;
            color: #563020;
            margin-bottom: 1.25rem;
        }
    </style>
</head>

<body>
    <!-- Background Blur Overlay -->
    <div class="login-bg-overlay"></div>

    <!-- Glassmorphic Container -->
    <div class="login-card-container">
        <!-- Logo Badge -->
        <div class="login-logo-badge">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
        </div>

        <!-- Title -->
        <h1 class="login-title">Set New Password</h1>
        
        <div class="user-greeting-chip">
            <span>👤</span>
            <span>Logged in as: <strong>{{ $user->name }}</strong> ({{ ucfirst($user->role) }})</span>
        </div>

        <div class="password-notice-banner">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="flex-shrink:0;">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <div>
                <strong>Action Required:</strong> An administrator has issued a temporary password for your account. Please choose a permanent, secure password to continue.
            </div>
        </div>

        @if(session('warning'))
            <div style="background: rgba(234, 88, 12, 0.12); color: #ea580c; border: 1px solid rgba(234, 88, 12, 0.3); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600;">
                {{ session('warning') }}
            </div>
        @endif

        @if($errors->any())
            <div style="background: rgba(239, 68, 68, 0.12); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 12px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; font-weight: 600; text-align: left;">
                <ul style="margin: 0; padding-left: 1.2rem;">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Form -->
        <form action="{{ route('password.change.update') }}" method="POST" class="login-form">
            @csrf

            <!-- Current / Temporary Password -->
            <div class="form-group-item">
                <label class="form-field-label">Current Temporary Password</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input type="password" name="current_password" id="current_password" class="pill-input"
                        required placeholder="Enter temporary password">
                </div>
            </div>

            <!-- New Password -->
            <div class="form-group-item">
                <label class="form-field-label">New Secure Password (min 6 characters)</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                        </svg>
                    </span>
                    <input type="password" name="password" id="new_password" class="pill-input"
                        required minlength="6" placeholder="Choose a new password">
                </div>
            </div>

            <!-- Confirm New Password -->
            <div class="form-group-item">
                <label class="form-field-label">Confirm New Password</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12" />
                        </svg>
                    </span>
                    <input type="password" name="password_confirmation" id="new_password_confirmation" class="pill-input"
                        required minlength="6" placeholder="Re-enter new password">
                </div>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-pill-btn" id="btn_submit_change_password" style="margin-top: 1rem;">
                Save Password & Enter System &rarr;
            </button>

            <!-- Logout option -->
            <div style="margin-top: 1rem; text-align: center;">
                <button type="submit" form="change_password_logout_form" style="background: none; border: 0; cursor: pointer; font-family: inherit; color: #64748b; font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                    &larr; Log out and return later
                </button>
            </div>
        </form>
        <form id="change_password_logout_form" method="POST" action="{{ route('logout.post') }}" hidden>
            @csrf
        </form>
    </div>
</body>
</html>
