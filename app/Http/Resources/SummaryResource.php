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
            'provider' => $this->provider,
            'model' => $this->model,
            'summary_text' => $this->summary_text,
            'token_count' => $this->token_count,
            'processing_time_ms' => $this->processing_time_ms,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
