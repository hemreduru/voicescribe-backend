<?php

namespace App\Http\Requests\Api\V1\Summarization;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SummarizeTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional: client may send the transcript text directly so the call
            // works even before chunks have synced; otherwise we merge stored chunks.
            'transcript_text' => ['nullable', 'string'],
            // Provider key must exist in config/llm.php; null falls back to default.
            'provider' => ['nullable', 'string', Rule::in(array_keys((array) config('llm.providers')))],
            'length' => ['nullable', 'string', Rule::in(['short', 'medium', 'long'])],
            // Local row id the app will use, so a later sync push updates this row
            // instead of creating a duplicate.
            'client_local_id' => ['nullable', 'string', 'max:100'],
        ];
    }
}
