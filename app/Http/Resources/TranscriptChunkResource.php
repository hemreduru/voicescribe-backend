<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptChunkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'chunk_index' => $this->chunk_index,
            'text' => $this->text,
            'speaker_label' => $this->speaker_label,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'confidence' => $this->confidence,
        ];
    }
}
