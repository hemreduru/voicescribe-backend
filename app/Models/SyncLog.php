<?php

namespace App\Models;

use App\Models\Lookup\SyncAction;
use App\Models\Lookup\SyncStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_id',
        'entity_type',
        'entity_id',
        'status_id',
        'error_message',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'synced_at' => 'datetime',
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
