<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Auth password bypass (DEV/TEST ONLY)
    |--------------------------------------------------------------------------
    |
    | When true, the login endpoint skips password verification so the mobile
    | app's test build can authenticate with a throwaway password. This is
    | intended ONLY for local development and automated tests.
    |
    | It is decoupled from APP_DEBUG on purpose: a production deploy that
    | accidentally ships with APP_DEBUG=true must NOT fall open. The controller
    | additionally hard-gates this behind `app()->isProduction()`, so even if
    | this flag is set in a production environment the bypass stays disabled.
    |
    */
    'auth_password_bypass' => (bool) env('AUTH_PASSWORD_BYPASS', false),
];
