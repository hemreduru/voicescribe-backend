<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcessingJob extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'transcript_id',
        'client_local_id',
        'remote_id',
        'type',
        'status',
        'last_processed_chunk_index',
        'retry_count',
        'error',
        'meta',
        'sync_status',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'last_processed_chunk_index' => 'integer',
            'retry_count' => 'integer',
            'meta' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class);
    }
}

