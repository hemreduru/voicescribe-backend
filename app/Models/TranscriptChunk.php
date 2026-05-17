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
        'start_time',
        'end_time',
        'confidence',
        'transcription_error',
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
            'last_synced_at' => 'datetime',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}
