<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SummaryResource extends JsonResource
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
            'provider' => $this->provider,
            'provider_key' => $this->whenLoaded('provider', fn () => $this->provider?->key),
            'model' => $this->model,
            'summary_text' => $this->summary_text,
            'token_count' => $this->token_count,
            'processing_time_ms' => $this->processing_time_ms,
            'sync_status' => $this->sync_status,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
