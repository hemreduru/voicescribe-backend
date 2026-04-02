<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptChunk extends Model
{
    use HasFactory;

    protected $fillable = [
        'transcript_id',
        'chunk_index',
        'text',
        'speaker_label',
        'start_time',
        'end_time',
        'confidence',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'start_time' => 'decimal:3',
            'end_time' => 'decimal:3',
            'confidence' => 'decimal:2',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
