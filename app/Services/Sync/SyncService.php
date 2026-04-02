<?php

namespace App\Services\Sync;

use App\Models\Lookup\SyncAction;
use App\Models\Lookup\SyncStatus;
use App\Models\SyncLog;
use Illuminate\Database\Eloquent\Collection;

class SyncService
{
    /**
     * Get pending sync logs for a user.
     */
    public function getPendingForUser(int $userId): Collection
    {
        $pendingStatusId = SyncStatus::getIdByKey(SyncStatus::KEY_PENDING);

        return SyncLog::where('user_id', $userId)
            ->where('status_id', $pendingStatusId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Log a sync action.
     */
    public function logAction(
        int $userId,
        string $actionKey,
        string $entityType,
        int $entityId,
        string $statusKey = SyncStatus::KEY_PENDING,
    ): SyncLog {
        $actionId = SyncAction::getIdByKey($actionKey);
        $statusId = SyncStatus::getIdByKey($statusKey);
        $isCompleted = $statusKey === SyncStatus::KEY_COMPLETED;

        return SyncLog::create([
            'user_id' => $userId,
            'action_id' => $actionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'status_id' => $statusId,
            'synced_at' => $isCompleted ? now() : null,
        ]);
    }

    /**
     * Mark a sync log as completed.
     */
    public function markCompleted(SyncLog $syncLog): SyncLog
    {
        $completedStatusId = SyncStatus::getIdByKey(SyncStatus::KEY_COMPLETED);

        $syncLog->update([
            'status_id' => $completedStatusId,
            'synced_at' => now(),
        ]);

        return $syncLog->fresh();
    }

    /**
     * Mark a sync log as failed.
     */
    public function markFailed(SyncLog $syncLog, string $errorMessage): SyncLog
    {
        $failedStatusId = SyncStatus::getIdByKey(SyncStatus::KEY_FAILED);

        $syncLog->update([
            'status_id' => $failedStatusId,
            'error_message' => $errorMessage,
        ]);

        return $syncLog->fresh();
    }
}
