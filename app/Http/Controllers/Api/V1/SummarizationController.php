<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Summarization\SummarizeTranscriptRequest;
use App\Http\Resources\SummaryResource;
use App\Models\Lookup\LlmProvider;
use App\Models\Summary;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Summarization\Exceptions\SummarizationException;
use App\Services\Summarization\LlmProviderFactory;
use App\Services\Summarization\MeetingMinutesPrompt;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class SummarizationController extends Controller
{
    public function __construct(private readonly LlmProviderFactory $factory) {}

    /**
     * Generate a structured meeting-minutes summary for a transcript and persist it.
     */
    public function summarize(SummarizeTranscriptRequest $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $transcript = Transcript::query()
            ->where('user_id', $user->id)
            ->with('chunks')
            ->find($id);

        if ($transcript === null) {
            return $this->notFoundResponse('Transcript not found.');
        }

        $validated = $request->validated();
        $text = trim((string) ($validated['transcript_text'] ?? $this->mergeChunkText($transcript)));

        if ($text === '') {
            return $this->errorResponse('Transcript has no text to summarize.', 422);
        }

        $length = $validated['length'] ?? 'medium';
        // The summary must be written in the user's app language, not the
        // transcript's. Default to Turkish when the client sends no locale.
        $locale = $validated['locale'] ?? 'tr';
        $provider = $this->factory->make($validated['provider'] ?? null);

        $cacheKey = 'summary:'.sha1($provider->getProviderName().'|'.$provider->getModelName().'|'.$length.'|'.$locale.'|'.$text);

        try {
            $start = microtime(true);
            $cached = Cache::has($cacheKey);
            $summaryText = Cache::remember(
                $cacheKey,
                (int) config('llm.cache_ttl', 3600),
                fn (): string => $provider->summarize($text, MeetingMinutesPrompt::system($length, $locale)),
            );
            $processingMs = (int) round((microtime(true) - $start) * 1000);
        } catch (SummarizationException $e) {
            report($e);

            return $this->errorResponse('Summarization failed. Please try again later.', 502);
        }

        $providerName = $provider->getProviderName();
        $providerId = LlmProvider::getIdByKey($providerName)
            ?? LlmProvider::getIdByKey(LlmProvider::KEY_GEMINI)
            ?? LlmProvider::query()->value('id');

        $attributes = [
            'provider_id' => $providerId,
            'model' => $provider->getModelName(),
            'summary_text' => $summaryText,
            'token_count' => $cached ? null : $provider->lastTokenCount(),
            'processing_time_ms' => $processingMs,
            'sync_status' => 'synced',
            'last_synced_at' => now(),
            'sync_error' => null,
        ];

        $clientLocalId = $validated['client_local_id'] ?? null;
        $summary = Summary::updateOrCreate(
            $clientLocalId !== null
                ? ['transcript_id' => $transcript->id, 'client_local_id' => $clientLocalId]
                : ['transcript_id' => $transcript->id, 'provider_id' => $providerId],
            $attributes + ['client_local_id' => $clientLocalId],
        );

        return $this->createdResponse(
            data: new SummaryResource($summary->load('provider')),
            message: 'Summary generated successfully',
        );
    }

    private function mergeChunkText(Transcript $transcript): string
    {
        return $transcript->chunks
            ->sortBy('chunk_index')
            ->pluck('text')
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->implode("\n");
    }
}
