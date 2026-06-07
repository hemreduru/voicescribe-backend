<?php

namespace App\Services\Summarization;

use App\Services\Summarization\Contracts\LlmProviderInterface;
use App\Services\Summarization\Exceptions\SummarizationException;
use App\Services\Summarization\Providers\GeminiProvider;
use App\Services\Summarization\Providers\OpenAiCompatibleProvider;

/**
 * Resolves an {@see LlmProviderInterface} purely from config/llm.php, so adding
 * or switching providers is a config/.env change, not a code change.
 */
class LlmProviderFactory
{
    /**
     * @param  string|null  $providerKey  Provider key from config; null → llm.default_provider.
     */
    public function make(?string $providerKey = null): LlmProviderInterface
    {
        $key = $providerKey ?: (string) config('llm.default_provider');

        /** @var array<string, mixed>|null $config */
        $config = config("llm.providers.{$key}");

        if (! is_array($config)) {
            throw new SummarizationException("Unknown LLM provider [{$key}].");
        }

        $driver = (string) ($config['driver'] ?? 'openai');

        return match ($driver) {
            'gemini' => new GeminiProvider($config, $key),
            'openai' => new OpenAiCompatibleProvider($config, $key),
            default => throw new SummarizationException("Unsupported LLM driver [{$driver}] for provider [{$key}]."),
        };
    }
}
