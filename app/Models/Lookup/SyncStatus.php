<?php

namespace App\Models\Lookup;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncStatus extends BaseLookup
{
    protected $table = 'sync_statuses';

    public const KEY_PENDING = 'pending';

    public const KEY_COMPLETED = 'completed';

    public const KEY_FAILED = 'failed';

    public const KEY_CONFLICT = 'conflict';

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'status_id');
    }
}
