<?php

namespace App\Http\Requests\Api\V1\Sync;

use Illuminate\Foundation\Http\FormRequest;

class SyncPushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transcripts' => ['sometimes', 'array'],
            'transcript_chunks' => ['sometimes', 'array'],
            'speakers' => ['sometimes', 'array'],
            'summaries' => ['sometimes', 'array'],
            'processing_jobs' => ['sometimes', 'array'],
            'sync_logs' => ['sometimes', 'array'],
        ];
    }
}

