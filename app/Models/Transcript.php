<?php

namespace App\Models;

use App\Models\Lookup\TranscriptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transcript extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'local_id',
        'title',
        'duration_seconds',
        'status_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(TranscriptStatus::class, 'status_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(TranscriptChunk::class)->orderBy('chunk_index');
    }

    public function summaries(): HasMany
    {
        return $this->hasMany(Summary::class);
    }
}
