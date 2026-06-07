<?php

namespace App\Services\Summarization\Providers;

use App\Services\Summarization\Contracts\LlmProviderInterface;
use App\Services\Summarization\Exceptions\SummarizationException;
use App\Services\Summarization\MeetingMinutesPrompt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google Gemini provider using the native generateContent API with structured
 * JSON output (responseMimeType + responseSchema).
 */
class GeminiProvider implements LlmProviderInterface
{
    private ?int $lastTokenCount = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly array $config,
        private readonly string $name = 'gemini',
    ) {}

    public function summarize(string $text, ?string $prompt = null): string
    {
        return $this->generate(
            systemPrompt: $prompt ?? MeetingMinutesPrompt::system(),
            userText: $text,
            // NOTE: we intentionally ask for JSON via responseMimeType + the
            // schema-in-prompt, but do NOT pass responseSchema. With the strict
            // responseSchema, gemini-2.5 models intermittently degenerate (a
            // runaway "\n\n\n…" loop in string fields) and drop content
            // (empty decisions/agenda). Free-form JSON + prompt is reliably
            // valid AND richer in practice. The client tolerantly parses anyway.
            generationConfig: [
                'temperature' => 0.2,
                'maxOutputTokens' => (int) ($this->config['max_tokens'] ?? 2048),
                'responseMimeType' => 'application/json',
            ],
        );
    }

    public function complete(string $systemPrompt, string $userText): string
    {
        return $this->generate(
            systemPrompt: $systemPrompt,
            userText: $userText,
            generationConfig: [
                'temperature' => 0.3,
                'maxOutputTokens' => (int) ($this->config['max_tokens'] ?? 2048),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $generationConfig
     */
    private function generate(string $systemPrompt, string $userText, array $generationConfig): string
    {
        $apiKey = (string) ($this->config['api_key'] ?? '');
        if ($apiKey === '') {
            throw new SummarizationException("Missing API key for provider [{$this->name}].");
        }

        $model = $this->getModelName();
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $endpoint = "{$baseUrl}/models/{$model}:generateContent";

        $body = [
            'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents' => [
                ['role' => 'user', 'parts' => [['text' => $userText]]],
            ],
            'generationConfig' => $generationConfig,
        ];

        try {
            $response = Http::timeout((int) config('llm.request_timeout', 60))
                ->retry(2, 500, throw: false)
                ->withQueryParameters(['key' => $apiKey])
                ->acceptJson()
                ->post($endpoint, $body);
        } catch (Throwable $e) {
            throw new SummarizationException("Gemini request failed: {$e->getMessage()}", previous: $e);
        }

        if (! $response->successful()) {
            throw new SummarizationException(
                "Gemini returned HTTP {$response->status()}: ".$response->body(),
            );
        }

        $json = $response->json();
        $content = data_get($json, 'candidates.0.content.parts.0.text');

        if (! is_string($content) || trim($content) === '') {
            $finish = data_get($json, 'candidates.0.finishReason', 'unknown');
            throw new SummarizationException("Gemini returned an empty completion (finishReason: {$finish}).");
        }

        $this->lastTokenCount = ($total = data_get($json, 'usageMetadata.totalTokenCount')) !== null
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
        return (string) ($this->config['model'] ?? 'gemini-2.0-flash');
    }

    public function lastTokenCount(): ?int
    {
        return $this->lastTokenCount;
    }
}
