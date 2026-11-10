<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bakery Management System</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Dedicated Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
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

    <!-- Glassmorphic Login Card -->
    <div class="login-card-container">
        <!-- Logo Badge -->
        <div class="login-logo-badge {{ !empty($logoUrl) ? 'has-custom-img' : '' }}">
            @if(!empty($logoUrl))
                <img src="{{ $logoUrl }}" alt="{{ $shopName }}" class="login-logo-custom-img">
            @else
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 13.8a4.5 4.5 0 1 1 2.6-8.3 5 5 0 0 1 6.8 0 4.5 4.5 0 1 1 2.6 8.3v4.2H6v-4.2z" />
                    <path d="M6 18h12v2H6z" />
                </svg>
            @endif
        </div>

        <!-- System Branding -->
        <h1 class="login-title">{{ $shopName }}</h1>
        <p class="login-subtitle">Bakery Management System</p>

        <!-- Status & Error Notifications -->
        @if ($errors->any())
            <div class="login-alert login-alert-error" role="alert">
                <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>{{ $errors->first() }}</span>
            </div>
        @elseif (session('info'))
            <div class="login-alert login-alert-info" role="alert">
                <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('info') }}</span>
            </div>
        @elseif (session('warning'))
            <div class="login-alert login-alert-warning" role="alert">
                <svg class="login-alert-icon" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <span>{{ session('warning') }}</span>
            </div>
        @endif

        <!-- Login Form -->
        <form action="{{ route('login.post') }}" method="POST" class="login-form">
            @csrf

            <!-- Username / Email Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="username_input">Username / Email</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                            <circle cx="12" cy="7" r="4" />
                        </svg>
                    </span>
                    <input type="text" name="username" id="username_input" class="pill-input" required
                        value="{{ old('username') }}" autocomplete="username" autofocus
                        placeholder="Enter your username or email">
                </div>
            </div>

            <!-- Password Field -->
            <div class="form-group-item">
                <label class="form-field-label" for="password_input">Password</label>
                <div class="input-pill-wrapper">
                    <span class="input-icon-left">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                        </svg>
                    </span>
                    <input type="password" name="password" id="password_input" class="pill-input has-right-icon"
                        required autocomplete="current-password" placeholder="••••••••••••">
                    <button type="button" class="input-icon-right" id="toggle_password_btn"
                        title="Toggle password visibility">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="form-options-row">
                <label class="remember-me-label">
                    <input type="checkbox" name="remember" class="remember-me-checkbox" checked>
                    <span>Remember Me</span>
                </label>
                <a href="{{ route('password.request') }}" class="forgot-password-link" id="btn_open_forgot_modal">Forgot Password?</a>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="submit-pill-btn" id="submit_btn">
                Sign In
            </button>
        </form>
    </div>

    <!-- In-Page Forgot Password Modal -->
    <div class="login-modal-backdrop" id="forgot_password_modal" role="dialog" aria-modal="true" aria-labelledby="forgot_modal_title">
        <div class="login-modal-card">
            <button type="button" class="login-modal-close" id="btn_close_forgot_modal" aria-label="Close modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <div class="login-modal-header" style="text-align: center; margin-bottom: 1.5rem;">
                <div class="brand-badge-container" style="display: inline-flex; margin-bottom: 0.75rem;">
                    <div class="brand-badge-square" style="width: 52px; height: 52px; border-radius: 16px; background: #4a2c20; display: flex; align-items: center; justify-content: center; color: #fff; overflow: hidden; box-shadow: 0 4px 14px rgba(74, 44, 32, 0.25);">
                        @if(!empty($logoUrl))
                            <img src="{{ $logoUrl }}" alt="{{ $shopName }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 13.87A4 4 0 0 1 7.41 6a5.11 5.11 0 0 1 1.05-1.54 5 5 0 0 1 7.08 0A5.11 5.11 0 0 1 16.59 6 4 4 0 0 1 18 13.87V21H6Z"/>
                                <line x1="6" y1="17" x2="18" y2="17"/>
                            </svg>
                        @endif
                    </div>
                </div>
                <h2 id="forgot_modal_title" style="font-size: 1.35rem; font-weight: 700; color: #3b2318; margin: 0 0 0.4rem 0;">Forgot Password?</h2>
                <p style="font-size: 0.825rem; color: #6b584d; margin: 0; line-height: 1.45;">
                    Enter your username or registered email and we'll send you a password reset link.
                </p>
            </div>

            <div id="modal_alert_container"></div>

            <form id="forgot_password_form" method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="pill-field-group">
                    <label for="modal_forgot_username" class="pill-label">USERNAME / EMAIL</label>
                    <div class="pill-input-container">
                        <span class="pill-left-icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="modal_forgot_username"
                            name="username"
                            class="pill-input"
                            placeholder="Admin or admin@bakery.com"
                            required
                            autocomplete="username"
                        >
                    </div>
                </div>

                <button type="submit" class="submit-pill-btn" id="btn_modal_send_reset" style="margin-top: 1.25rem;">
                    Send Reset Link
                </button>
                <button type="button" class="btn-pill-secondary" id="btn_modal_cancel">
                    Cancel
                </button>
            </form>
        </div>
    </div>

    <!-- Login Interactivity Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('password_input');
            const togglePasswordBtn = document.getElementById('toggle_password_btn');

            // Toggle password visibility
            if (togglePasswordBtn && passwordInput) {
                togglePasswordBtn.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                });
            }

            // Forgot Password Modal
            const openModalBtn = document.getElementById('btn_open_forgot_modal');
            const modal = document.getElementById('forgot_password_modal');
            const closeModalBtn = document.getElementById('btn_close_forgot_modal');
            const cancelModalBtn = document.getElementById('btn_modal_cancel');
            const forgotForm = document.getElementById('forgot_password_form');
            const forgotInput = document.getElementById('modal_forgot_username');
            const alertContainer = document.getElementById('modal_alert_container');
            const sendBtn = document.getElementById('btn_modal_send_reset');

            function openModal(e) {
                if (e) e.preventDefault();
                if (modal) {
                    modal.classList.add('is-active');
                    alertContainer.innerHTML = '';
                    const loginUsername = document.getElementById('username_input');
                    if (loginUsername && loginUsername.value.trim() && !forgotInput.value.trim()) {
                        forgotInput.value = loginUsername.value.trim();
                    }
                    setTimeout(() => forgotInput.focus(), 120);
                }
            }

            function closeModal() {
                if (modal) {
                    modal.classList.remove('is-active');
                }
            }

            if (openModalBtn) openModalBtn.addEventListener('click', openModal);
            if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
            if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

            if (modal) {
                modal.addEventListener('click', function (e) {
                    if (e.target === modal) closeModal();
                });
            }

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
                    closeModal();
                }
            });

            // AJAX form submission for instant reset feedback
            if (forgotForm) {
                forgotForm.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    alertContainer.innerHTML = '';
                    sendBtn.disabled = true;
                    const originalBtnText = sendBtn.innerHTML;
                    sendBtn.innerHTML = 'Sending reset link...';

                    try {
                        const formData = new FormData(forgotForm);
                        const response = await fetch(forgotForm.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            let successHtml = `
                                <div class="alert-glass-box success" style="margin-bottom: 1rem; text-align: left;">
                                    <div class="alert-icon-wrap" style="color: #16a34a;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                                    </div>
                                    <div class="alert-message-text" style="color: #15803d; font-size: 0.85rem; line-height: 1.4;">${data.message}</div>
                                </div>
                            `;

                            if (data.reset_url) {
                                successHtml += `
                                    <div class="reset-url-box">
                                        <strong style="display: block; margin-bottom: 0.35rem;">Direct Reset Link:</strong>
                                        <a href="${data.reset_url}" class="reset-url-link-btn">Proceed to Reset Password &rarr;</a>
                                    </div>
                                `;
                            }

                            alertContainer.innerHTML = successHtml;
                            forgotForm.style.display = 'none';
                            cancelModalBtn.style.display = 'none';
                        } else {
                            const errorMsg = data.message || (data.errors && data.errors.username ? data.errors.username[0] : 'Unable to send reset link. Please verify your username or email.');
                            alertContainer.innerHTML = `
                                <div class="alert-glass-box danger" style="margin-bottom: 1rem; text-align: left;">
                                    <div class="alert-icon-wrap" style="color: #dc2626;">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                    </div>
                                    <div class="alert-message-text" style="color: #991b1b; font-size: 0.85rem; line-height: 1.4;">${errorMsg}</div>
                                </div>
                            `;
                        }
                    } catch (err) {
                        alertContainer.innerHTML = `
                            <div class="alert-glass-box danger" style="margin-bottom: 1rem; text-align: left;">
                                <div class="alert-icon-wrap" style="color: #dc2626;">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                                </div>
                                <div class="alert-message-text" style="color: #991b1b; font-size: 0.85rem; line-height: 1.4;">An unexpected network error occurred. Please try again.</div>
                            </div>
                        `;
                    } finally {
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = originalBtnText;
                    }
                });
            }
        });
    </script>
</body>

</html>