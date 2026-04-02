<?php

return [
    'default_provider' => env('LLM_DEFAULT_PROVIDER', 'openai'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2048),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('CLAUDE_MODEL', 'claude-3-haiku-20240307'),
            'max_tokens' => (int) env('CLAUDE_MAX_TOKENS', 2048),
            'base_url' => env('CLAUDE_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
            'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 2048),
        ],
    ],

    'chunk_size' => (int) env('LLM_CHUNK_SIZE', 4000),
    'cache_ttl' => (int) env('LLM_CACHE_TTL', 3600),
];
