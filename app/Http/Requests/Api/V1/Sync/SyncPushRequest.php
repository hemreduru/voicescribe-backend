<?php

namespace App\Http\Requests\Api\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Each table batch is capped at `sync.max_batch_size` rows, every row must
     * be an object, and free-text fields are capped to their column capacity.
     * Rows are not otherwise restricted (the app also sends camelCase aliases).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $max = 'max:'.(int) config('sync.max_batch_size');

        $rules = [];
        foreach (['transcripts', 'transcript_chunks', 'speakers', 'summaries', 'processing_jobs', 'sync_logs'] as $table) {
            $rules[$table] = ['sometimes', 'array', $max];
            $rules[$table.'.*'] = ['array'];
        }

        return $rules + [
            'transcripts.*.title' => ['nullable', 'string', 'max:255'],
            'transcript_chunks.*.text' => ['nullable', 'string', 'max:16000'],
            'transcript_chunks.*.transcription_error' => ['nullable', 'string', 'max:5000'],
            'summaries.*.summary_text' => ['nullable', 'string', 'max:16000'],
            'summaries.*.summaryText' => ['nullable', 'string', 'max:16000'],
        ];
    }
}
