<?php

namespace App\Services\Summarization\Contracts;

interface LlmProviderInterface
{
    /**
     * Summarize a given text.
     */
    public function summarize(string $text, ?string $prompt = null): string;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;

    /**
     * Get the model name.
     */
    public function getModelName(): string;
}
