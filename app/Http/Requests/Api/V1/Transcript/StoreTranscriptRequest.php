<?php

namespace App\Http\Requests\Api\V1\Transcript;

use Illuminate\Foundation\Http\FormRequest;

class StoreTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'local_id' => ['nullable', 'string', 'max:100'],
            'client_local_id' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'status_key' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
            'updated_at' => ['nullable', 'date'],
            'deleted_at' => ['nullable', 'date'],
            'chunks' => ['sometimes', 'array'],
            'chunks.*.client_local_id' => ['nullable', 'string', 'max:100'],
            'chunks.*.chunk_index' => ['nullable', 'integer', 'min:0'],
            'chunks.*.text' => ['nullable', 'string'],
            'chunks.*.start_time' => ['nullable', 'numeric'],
            'chunks.*.end_time' => ['nullable', 'numeric'],
            'chunks.*.confidence' => ['nullable', 'numeric'],
            'chunks.*.updated_at' => ['nullable', 'date'],
            'chunks.*.deleted_at' => ['nullable', 'date'],
            'summaries' => ['sometimes', 'array'],
            'summaries.*.client_local_id' => ['nullable', 'string', 'max:100'],
            'summaries.*.provider_key' => ['nullable', 'string', 'max:50'],
            'summaries.*.model' => ['nullable', 'string', 'max:100'],
            'summaries.*.summary_text' => ['nullable', 'string'],
            'summaries.*.token_count' => ['nullable', 'integer', 'min:0'],
            'summaries.*.processing_time_ms' => ['nullable', 'integer', 'min:0'],
            'summaries.*.updated_at' => ['nullable', 'date'],
            'summaries.*.deleted_at' => ['nullable', 'date'],
        ];
    }
}
