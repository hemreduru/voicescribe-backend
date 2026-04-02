<?php

namespace App\Services\Summarization;

use App\Services\Summarization\Contracts\LlmProviderInterface;

class SummarizationService
{
    private LlmProviderInterface $provider;

    public function __construct(LlmProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Summarize a full transcript text.
     * Uses chunking for long texts.
     */
    public function summarize(string $text): array
    {
        $chunkSize = config('llm.chunk_size', 4000);

        if (strlen($text) <= $chunkSize) {
            $summary = $this->provider->summarize($text);

            return [
                'summary' => $summary,
                'provider' => $this->provider->getProviderName(),
                'model' => $this->provider->getModelName(),
                'chunks_processed' => 1,
            ];
        }

        return $this->summarizeInChunks($text, $chunkSize);
    }

    /**
     * Summarize a single chunk of text.
     */
    public function summarizeChunk(string $text): string
    {
        return $this->provider->summarize($text);
    }

    /**
     * Split text into chunks and summarize hierarchically.
     */
    private function summarizeInChunks(string $text, int $chunkSize): array
    {
        $chunks = str_split($text, $chunkSize);
        $chunkSummaries = [];

        foreach ($chunks as $chunk) {
            $chunkSummaries[] = $this->provider->summarize(
                $chunk,
                'Summarize the following text concisely, preserving key information:'
            );
        }

        $mergedSummary = $this->provider->summarize(
            implode("\n\n", $chunkSummaries),
            'Merge the following partial summaries into a single coherent summary:'
        );

        return [
            'summary' => $mergedSummary,
            'provider' => $this->provider->getProviderName(),
            'model' => $this->provider->getModelName(),
            'chunks_processed' => count($chunks),
        ];
    }
}
