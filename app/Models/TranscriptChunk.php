<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TranscriptChunk extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'transcript_id',
        'chunk_index',
        'text',
        'speaker_label',
        'speaker_id',
        'speaker_confidence',
        'speaker_analysis_status',
        'start_time',
        'end_time',
        'confidence',
        'client_local_id',
        'remote_id',
        'sync_status',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'start_time' => 'decimal:3',
            'end_time' => 'decimal:3',
            'confidence' => 'decimal:2',
            'speaker_confidence' => 'decimal:4',
            'last_synced_at' => 'datetime',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }
}
