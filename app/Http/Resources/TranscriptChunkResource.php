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
            'client_local_id' => $this->client_local_id,
            'remote_id' => (string) $this->id,
            'transcript_id' => $this->transcript_id,
            'chunk_index' => $this->chunk_index,
            'text' => $this->text,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'confidence' => $this->confidence,
            'sync_status' => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
