<?php

return [

    // ── Feature switch ──
    'enabled' => (bool) env('LOGIN_APPROVAL_ENABLED', true),

    /*
    | Owner bypass: this email skips approval (password is still checked).
    | Server-side only; never render it in views or scripts.
    */
    'owner_email' => env('OWNER_EMAIL'),

    // ── Timing (seconds) ──
    'request_ttl' => (int) env('LOGIN_APPROVAL_TTL', 120),
    'active_window' => (int) env('LOGIN_APPROVAL_ACTIVE_WINDOW', 300),
    'touch_interval' => 30,

    // Roles whose active, trusted sessions may approve or reject requests.
    'approver_roles' => array_values(array_filter(array_map(
        'trim',
        explode(',', strtolower((string) env('LOGIN_APPROVAL_APPROVER_ROLES', 'admin,manager')))
    ))),
];
