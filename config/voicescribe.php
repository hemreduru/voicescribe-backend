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

    /*
    |--------------------------------------------------------------------------
    | Rate limits (requests per minute)
    |--------------------------------------------------------------------------
    |
    | Named limiters registered in AppServiceProvider and attached in
    | routes/api.php. `auth` guards the public login/register endpoints against
    | brute force (keyed by IP). `llm` guards the synchronous LLM endpoints
    | (summarization + chat) and `transcribe` the speech-to-text relay against
    | quota exhaustion/abuse (keyed by user).
    |
    */
    'rate_limits' => [
        'auth' => (int) env('RATE_LIMIT_AUTH', 10),
        'llm' => (int) env('RATE_LIMIT_LLM', 20),
        'transcribe' => (int) env('RATE_LIMIT_TRANSCRIBE', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech-to-text relay (POST /api/v1/transcribe)
    |--------------------------------------------------------------------------
    |
    | Audio chunks are relayed to Groq Whisper. The API key and base URL are
    | shared with the `groq` provider in config/llm.php (GROQ_API_KEY,
    | GROQ_BASE_URL). `max_size_kb` must stay below the PHP/nginx 16M body
    | limit set in the Dockerfile (Groq's own free-tier limit is 25 MB).
    |
    */
    'transcribe' => [
        'model' => env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'),
        'max_size_kb' => (int) env('TRANSCRIBE_MAX_SIZE_KB', 10240),
    ],
];
