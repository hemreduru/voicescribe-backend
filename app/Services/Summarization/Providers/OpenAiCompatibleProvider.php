<?php

namespace App\Services\Summarization\Providers;

use App\Services\Summarization\Contracts\LlmProviderInterface;
use App\Services\Summarization\Exceptions\SummarizationException;
use App\Services\Summarization\MeetingMinutesPrompt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Generic provider for any OpenAI-compatible /chat/completions endpoint
 * (OpenAI, Groq, OpenRouter, Together, ...). Adding such a provider needs only
 * config (api_key + base_url + model + driver "openai"), no new code.
 */
class OpenAiCompatibleProvider implements LlmProviderInterface
{
    private ?int $lastTokenCount = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $name = 'openai',
    ) {}

    public function summarize(string $text, ?string $prompt = null): string
    {
        return $this->chat(
            systemPrompt: $prompt ?? MeetingMinutesPrompt::system(),
            userText: $text,
            jsonMode: true,
        );
    }

    public function complete(string $systemPrompt, string $userText): string
    {
        return $this->chat(systemPrompt: $systemPrompt, userText: $userText, jsonMode: false);
    }

    private function chat(string $systemPrompt, string $userText, bool $jsonMode): string
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new SummarizationException("Missing API key for provider [{$this->name}].");
        }

        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/');

        $body = [
            'model' => $this->getModelName(),
            'temperature' => $jsonMode ? 0.2 : 0.3,
            'max_tokens' => (int) ($this->config['max_tokens'] ?? 2048),
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userText],
            ],
        ];
        if ($jsonMode) {
            $body['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::timeout((int) config('llm.request_timeout', 60))
                ->retry(2, 500, throw: false)
                ->withToken($apiKey)
                ->acceptJson()
                ->post("{$baseUrl}/chat/completions", $body);
        } catch (Throwable $e) {
            throw new SummarizationException("{$this->name} request failed: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new SummarizationException(
                "{$this->name} returned HTTP {$response->status()}: ".$response->body(),
            );
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new SummarizationException("{$this->name} returned an empty completion.");
        }

        $this->lastTokenCount = ($total = data_get($response->json(), 'usage.total_tokens')) !== null
            ? (int) $total
            : null;

        return trim($content);
    }

    public function getProviderName(): string
    {
        return $this->name;
    }

    public function getModelName(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-4o-mini');
    }

    public function lastTokenCount(): ?int
    {
        return $this->lastTokenCount;
    }
}
