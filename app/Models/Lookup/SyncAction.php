<?php

namespace App\Models\Lookup;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncAction extends BaseLookup
{
    protected $table = 'sync_actions';

    public const KEY_PUSH = 'push';
    public const KEY_PULL = 'pull';

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'action_id');
    }
}
