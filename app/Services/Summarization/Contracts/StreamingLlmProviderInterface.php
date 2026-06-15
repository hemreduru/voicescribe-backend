<?php

namespace App\Services\Summarization\Contracts;

/**
 * Optional capability: a provider that can stream a free-form completion
 * token-by-token. The chat controller checks `instanceof` and falls back to the
 * buffered {@see LlmProviderInterface::complete()} when a provider does not
 * implement this, so adding it never breaks an existing provider.
 */
interface StreamingLlmProviderInterface
{
    /**
     * Yields answer text deltas as they are generated. [$systemPrompt] sets
     * behavior; [$userText] is the input. Implementations should throw before
     * yielding their first delta when generation cannot start, so the caller can
     * cleanly fall back to a buffered completion.
     *
     * @return iterable<string>
     */
    public function stream(string $systemPrompt, string $userText): iterable;
}
