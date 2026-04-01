<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Summary extends Model
{
    use HasFactory;

    protected $fillable = [
        'transcript_id',
        'provider',
        'model',
        'summary_text',
        'token_count',
        'processing_time_ms',
    ];

    protected function casts(): array
    {
        return [
            'token_count' => 'integer',
            'processing_time_ms' => 'integer',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
