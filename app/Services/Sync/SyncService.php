<?php

namespace App\Services\Sync;

use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Collection;

class SyncService
{
    /**
     * Get pending sync logs for a user.
     */
    public function getPendingForUser(int $userId): Collection
    {
        return SyncLog::where('user_id', $userId)
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Log a sync action.
     */
    public function logAction(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        string $status = 'pending',
    ): SyncLog {
        return SyncLog::create([
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status' => $status,
            'synced_at' => $status === 'completed' ? now() : null,
        ]);
    }

    /**
     * Mark a sync log as completed.
     */
    public function markCompleted(SyncLog $syncLog): SyncLog
    {
        $syncLog->update([
            'status' => 'completed',
            'synced_at' => now(),
        ]);

        return $syncLog->fresh();
    }

    /**
     * Mark a sync log as failed.
     */
    public function markFailed(SyncLog $syncLog, string $errorMessage): SyncLog
    {
        $syncLog->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);

        return $syncLog->fresh();
    }
}
