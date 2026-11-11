<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Out - Bakery Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
</head>

<body>
    <div class="login-bg-overlay"></div>

    <div class="login-card-container">
        <h1 class="login-title">Sign out?</h1>
        <p class="login-subtitle">Confirm that you want to sign out of the Bakery Management System.</p>

        <form method="POST" action="{{ route('logout.post') }}" class="login-form">
            @csrf
            <button type="submit" class="submit-pill-btn" id="logout_confirm_btn">Sign out</button>
        </form>
        <p style="text-align: center; margin-top: 1rem;">
            <a href="{{ url('/login') }}" class="forgot-password-link">Stay signed in</a>
        </p>
    </div>
</body>

</html>
