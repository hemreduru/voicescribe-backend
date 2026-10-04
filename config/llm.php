<?php

return [
    'default_provider' => env('LLM_DEFAULT_PROVIDER', 'gemini'),

    /*
    | Each provider declares a `driver` that selects the concrete client used to
    | talk to it. Adding or switching a provider therefore needs only config /
    | .env changes — no code:
    |   - driver "gemini": Google Gemini native generateContent API.
    |   - driver "openai": any OpenAI-compatible /chat/completions endpoint
    |                      (OpenAI, Groq, OpenRouter, Together, ...).
    | Switch the active provider with LLM_DEFAULT_PROVIDER.
    */
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2048),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'groq' => [
            'driver' => 'openai',
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'max_tokens' => (int) env('GROQ_MAX_TOKENS', 2048),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
        'claude' => [
            'driver' => 'openai',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-haiku-20240307'),
            'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 2048),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            // Swap the active model with one env var (GEMINI_MODEL). Verified on
            // this key: gemini-2.5-flash & gemini-2.5-flash-lite return clean JSON
            // (flash-lite free tier is only ~20 req/day). gemini-2.0-flash* return
            // 429 (no free allowance).
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            // Fallback chain: each model has its own daily free-tier quota, so the
            // provider rotates to the next on 429/5xx. Primary GEMINI_MODEL is
            // always tried first.
            'models' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'GEMINI_MODELS',
                    'gemini-2.5-flash,gemini-2.5-flash-lite,gemini-2.0-flash,gemini-2.0-flash-lite,gemini-2.5-pro',
                )),
            ))),
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 2048),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],
    ],

    'request_timeout' => (int) env('LLM_REQUEST_TIMEOUT', 60),

    'chunk_size' => (int) env('LLM_CHUNK_SIZE', 4000),
    'cache_ttl' => (int) env('LLM_CACHE_TTL', 3600),
];
