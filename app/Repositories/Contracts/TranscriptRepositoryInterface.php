<?php

namespace App\Repositories\Contracts;

use App\Models\Transcript;
use Illuminate\Database\Eloquent\Collection;

interface TranscriptRepositoryInterface
{
    /**
     * Get all transcripts for a user.
     */
    public function getAllForUser(int $userId): Collection;

    /**
     * Find a transcript by ID for a user.
     */
    public function findForUser(int $id, int $userId): ?Transcript;

    /**
     * Find a transcript by local ID for a user.
     */
    public function findByLocalIdForUser(string $localId, int $userId): ?Transcript;

    /**
     * Create a new transcript.
     */
    public function create(array $data): Transcript;

    /**
     * Update a transcript.
     */
    public function update(Transcript $transcript, array $data): Transcript;

    /**
     * Delete a transcript (soft delete).
     */
    public function delete(Transcript $transcript): bool;
}
