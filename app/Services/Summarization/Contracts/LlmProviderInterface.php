<?php

namespace App\Services\Summarization\Contracts;

interface LlmProviderInterface
{
    /**
     * Summarize a given text.
     */
    public function summarize(string $text, ?string $prompt = null): string;

    /**
     * Free-form completion: returns plain text (no enforced JSON schema). Used by
     * the chat/RAG flow. [$systemPrompt] sets behavior; [$userText] is the input.
     */
    public function complete(string $systemPrompt, string $userText): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;

    /**
     * Get the model name.
     */
    public function getModelName(): string;

    /**
     * Total tokens reported by the provider for the most recent summarize() call,
     * or null when the provider does not expose usage metadata.
     */
    public function lastTokenCount(): ?int;
}
