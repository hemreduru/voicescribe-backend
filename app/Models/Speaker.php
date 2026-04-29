<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Speaker extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_local_id',
        'remote_id',
        'name',
        'embedding',
        'recordings',
        'has_voice_sample',
        'is_user_named',
        'sync_status',
        'last_synced_at',
        'sync_error',
    ];

    protected function casts(): array
    {
        return [
            'recordings' => 'integer',
            'has_voice_sample' => 'boolean',
            'is_user_named' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(TranscriptChunk::class);
    }
}

