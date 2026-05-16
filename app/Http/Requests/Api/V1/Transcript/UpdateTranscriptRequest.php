<?php

namespace App\Http\Requests\Api\V1\Transcript;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTranscriptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'local_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'client_local_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'status_key' => ['sometimes', 'nullable', 'string', 'max:50'],
            'recorded_at' => ['sometimes', 'nullable', 'date'],
            'updated_at' => ['sometimes', 'nullable', 'date'],
            'deleted_at' => ['sometimes', 'nullable', 'date'],
            'chunks' => ['sometimes', 'array'],
            'summaries' => ['sometimes', 'array'],
        ];
    }
}
