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
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
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
            // 429 (no free allowance). gemma-4-* have high quota but are reasoning
            // models that emit chain-of-thought and won't produce clean JSON.
            'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
            // Fallback chain: each model has its own daily free-tier quota, so the
            // provider rotates to the next on 429/5xx. Primary GEMINI_MODEL is
            // always tried first. gemma-4-* are excluded: they are reasoning
            // models that don't emit clean JSON.
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

    /*
    | On-device summarization model served to the mobile app. The app fetches
    | this (authenticated) instead of bundling the gated-model URL + HF token in
    | its binary, so the token stays server-side and the URL/model can be swapped
    | via .env without shipping an app update.
    */
    'local_model' => [
        // Default: Gemma 3 1B IT, int8 (.task, q8, ~1.0GB, 4096 ctx). Runs on the
        // GPU (OpenCL) and produces structured Turkish minutes on-device. The int4
        // q4_block128 variant SIGSEGVs in the OpenCL executor on some devices, so
        // int8 is the stable default. Gated (gated:auto) — the app fetches the URL
        // + HF token from /api/v1/local-summary-model so the token stays
        // server-side and never ships in the binary.
        'url' => env(
            'LOCAL_LLM_MODEL_URL',
            'https://huggingface.co/litert-community/Gemma3-1B-IT/resolve/main/Gemma3-1B-IT_multi-prefill-seq_q8_ekv4096.task',
        ),
        // Required for the gated Gemma repo; supply an HF token with read access.
        'auth_token' => env('LOCAL_LLM_MODEL_TOKEN'),
        'file_name' => env('LOCAL_LLM_MODEL_FILE', 'Gemma3-1B-IT_multi-prefill-seq_q8_ekv4096.task'),
        'size_bytes' => (int) env('LOCAL_LLM_MODEL_SIZE', 1054023846),
        // flutter_gemma ModelType (gemmaIt|qwen|qwen3|deepSeek|phi|llama|hammer|general).
        'model_type' => env('LOCAL_LLM_MODEL_TYPE', 'gemmaIt'),
        // flutter_gemma ModelFileType (task|litertlm|binary).
        'file_type' => env('LOCAL_LLM_MODEL_FILE_TYPE', 'task'),
        'label' => env('LOCAL_LLM_MODEL_LABEL', 'Gemma 3 1B'),
    ],

    'chunk_size' => (int) env('LLM_CHUNK_SIZE', 4000),
    'cache_ttl' => (int) env('LLM_CACHE_TTL', 3600),
];
