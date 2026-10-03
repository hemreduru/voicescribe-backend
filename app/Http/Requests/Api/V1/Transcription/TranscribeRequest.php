<?php

namespace App\Http\Requests\Api\V1\Transcription;

use Illuminate\Foundation\Http\FormRequest;

class TranscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Audio is checked by extension and by sniffed MIME type (wav, m4a/mp4,
     * mp3, ogg/opus, webm, flac) and capped by `voicescribe.transcribe.max_size_kb`.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'extensions:wav,m4a,mp4,mp3,ogg,opus,webm,flac',
                'mimetypes:audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/mp4,audio/x-m4a,video/mp4,audio/mpeg,audio/mp3,audio/ogg,application/ogg,audio/opus,audio/webm,video/webm,audio/flac,audio/x-flac',
                'max:'.(int) config('voicescribe.transcribe.max_size_kb'),
            ],
            'language' => ['nullable', 'string', 'regex:/^[A-Za-z]{2}$/'],
            'prompt' => ['nullable', 'string', 'max:500'],
        ];
    }
}
