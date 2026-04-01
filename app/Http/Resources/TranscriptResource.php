<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TranscriptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'local_id' => $this->local_id,
            'title' => $this->title,
            'duration_seconds' => $this->duration_seconds,
            'status' => $this->status,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'chunks' => TranscriptChunkResource::collection($this->whenLoaded('chunks')),
            'summaries' => SummaryResource::collection($this->whenLoaded('summaries')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
