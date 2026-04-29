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
            'client_local_id' => $this->client_local_id,
            'remote_id' => (string) $this->id,
            'title' => $this->title,
            'duration_seconds' => $this->duration_seconds,
            'status' => $this->status,
            'status_key' => $this->status?->key,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'sync_status' => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'sync_error' => $this->sync_error,
            'chunks' => TranscriptChunkResource::collection($this->whenLoaded('chunks')),
            'summaries' => SummaryResource::collection($this->whenLoaded('summaries')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
