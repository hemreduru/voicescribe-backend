<?php

namespace App\Models;

use App\Models\Lookup\SyncAction;
use App\Models\Lookup\SyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'action_id',
        'entity_type',
        'entity_id',
        'client_local_id',
        'remote_id',
        'status_id',
        'retry_count',
        'error_message',
        'meta',
        'synced_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'retry_count' => 'integer',
            'meta' => 'array',
            'synced_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(SyncAction::class, 'action_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(SyncStatus::class, 'status_id');
    }
}
