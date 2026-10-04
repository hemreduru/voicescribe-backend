<?php

namespace App\Services\Transcription;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Relays one audio chunk to Groq's OpenAI-compatible speech-to-text endpoint.
 * The API key stays server-side (shared with the Groq summarization provider).
 */
class GroqTranscriber
{
    /**
     * @param  UploadedFile  $audio  Already validated audio chunk.
     * @param  string  $language  Two-letter ISO 639-1 code.
     * @param  string|null  $prompt  Optional vocabulary/context hint for the model.
     * @return string The transcribed text.
     *
     * @throws TranscriptionException
     */
    public function transcribe(UploadedFile $audio, string $language, ?string $prompt = null): string
    {
        $apiKey = (string) config('llm.providers.groq.api_key');
        if ($apiKey === '') {
            Log::error('transcribe.groq_api_key_missing');

            throw new TranscriptionException('Transcription service is not available.', 503);
        }

        $baseUrl = rtrim((string) config('llm.providers.groq.base_url'), '/');

        $fields = array_filter([
            'model' => (string) config('voicescribe.transcribe.model'),
            'language' => strtolower($language),
            'response_format' => 'json',
            'temperature' => '0',
            'prompt' => $prompt,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        $stream = fopen($audio->getRealPath(), 'r');

        try {
            // Groq infers the format from the filename; the extension was
            // whitelisted by the request validation.
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('llm.request_timeout', 60))
                ->connectTimeout(10)
                ->attach('file', $stream, 'audio.'.strtolower($audio->getClientOriginalExtension()))
                ->post("{$baseUrl}/audio/transcriptions", $fields);
        } catch (Throwable $e) {
            Log::warning('transcribe.upstream_unreachable', ['exception' => $e::class]);

            throw new TranscriptionException('Transcription failed. Please try again later.', 502);
        } finally {
            fclose($stream);
        }

        if ($response->status() === 429) {
            Log::warning('transcribe.upstream_rate_limited');

            throw new TranscriptionException(
                'Transcription service is busy. Please try again shortly.',
                429,
                $response->header('Retry-After') ?: null,
            );
        }

        $text = $response->json('text');
        if (! $response->successful() || ! is_string($text)) {
            Log::warning('transcribe.upstream_failed', ['status' => $response->status()]);

            throw new TranscriptionException('Transcription failed. Please try again later.', 502);
        }

        return trim($text);
    }
}
