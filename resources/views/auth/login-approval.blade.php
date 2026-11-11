<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Waiting for Approval - Bakery Management System</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/login-approval.css') }}?v={{ @filemtime(public_path('css/login-approval.css')) ?: '1.0' }}">
</head>

<body>
    @php
        $shopName = \App\Services\SettingsService::getString('shop_name', 'Moon Cake');
        $isPending = $approval->status === \App\Models\LoginApprovalRequest::PENDING;
    @endphp

    <div class="login-bg-overlay"></div>

    <div class="login-card-container approval-wait-card">
        <div class="login-logo-badge">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            </svg>
        </div>

        <h1 class="login-title">{{ $shopName }}</h1>
        <p class="login-subtitle">Login approval required</p>

        <div id="approval_state" class="approval-state" data-state="{{ $approval->status }}" aria-live="polite">
            <div class="approval-state-pending">
                <div class="approval-spinner" aria-hidden="true"></div>
                <p class="approval-message">
                    Another user is signed in. An admin or manager must approve this login from their device.
                </p>
                <p class="approval-countdown">
                    Expires in <strong id="approval_countdown">{{ gmdate('i:s', (int) $secondsLeft) }}</strong>
                </p>
            </div>

            <div class="login-alert login-alert-info approval-state-approved" role="status">
                <span>Approved. Signing you in…</span>
            </div>
            <div class="login-alert login-alert-error approval-state-rejected" role="alert">
                <span>Your login request was rejected.</span>
            </div>
            <div class="login-alert login-alert-warning approval-state-expired" role="alert">
                <span>Your login request expired before it was approved.</span>
            </div>
        </div>

        <form id="approval_complete_form" method="POST" action="{{ route('login.approval.complete') }}" hidden>
            @csrf
        </form>

        <form method="POST" action="{{ route('login.approval.cancel') }}" class="approval-actions">
            @csrf
            <button type="submit" class="btn-pill-secondary approval-back-btn" id="approval_cancel_btn">
                {{ $isPending ? 'Cancel request' : 'Back to sign in' }}
            </button>
        </form>
    </div>

    <script>
        (function () {
            var stateBox = document.getElementById('approval_state');
            var countdown = document.getElementById('approval_countdown');
            var cancelBtn = document.getElementById('approval_cancel_btn');
            var statusUrl = @json(route('login.approval.status'));
            var secondsLeft = {{ (int) $secondsLeft }};
            var done = stateBox.dataset.state !== 'pending';
            var timer = null;

            function format(s) {
                s = Math.max(0, s);
                return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
            }

            function finish(state) {
                done = true;
                stateBox.dataset.state = state;
                clearInterval(timer);
                if (state === 'approved') {
                    document.getElementById('approval_complete_form').submit();
                } else {
                    cancelBtn.textContent = 'Back to sign in';
                }
            }

            function poll() {
                if (done) return;
                fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        if (!data || done) return;
                        if (typeof data.seconds_left === 'number') secondsLeft = data.seconds_left;
                        if (data.status === 'missing') return finish('expired');
                        if (data.status !== 'pending') finish(data.status === 'approved' ? 'approved' : (data.status === 'rejected' ? 'rejected' : 'expired'));
                    })
                    .catch(function () { /* transient network error: keep polling */ });
            }

            if (done) {
                if (stateBox.dataset.state === 'approved') finish('approved');
                return;
            }

            timer = setInterval(function () {
                secondsLeft -= 1;
                countdown.textContent = format(secondsLeft);
            }, 1000);
            setInterval(poll, 3000);
        })();
    </script>
</body>

</html>
