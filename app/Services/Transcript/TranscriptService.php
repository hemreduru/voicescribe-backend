<?php

namespace App\Services\Transcript;

use App\Models\Transcript;
use App\Repositories\Contracts\TranscriptRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TranscriptService
{
    public function __construct(
        private readonly TranscriptRepositoryInterface $transcriptRepository,
    ) {}

    /**
     * Get all transcripts for the given user.
     */
    public function getAllForUser(int $userId): Collection
    {
        return $this->transcriptRepository->getAllForUser($userId);
    }

    /**
     * Find a transcript by ID for the given user.
     */
    public function findForUser(int $id, int $userId): ?Transcript
    {
        return $this->transcriptRepository->findForUser($id, $userId);
    }

    /**
     * Create a new transcript.
     */
    public function create(array $data): Transcript
    {
        return $this->transcriptRepository->create($data);
    }

    /**
     * Update an existing transcript.
     */
    public function update(Transcript $transcript, array $data): Transcript
    {
        return $this->transcriptRepository->update($transcript, $data);
    }

    /**
     * Delete a transcript.
     */
    public function delete(Transcript $transcript): bool
    {
        return $this->transcriptRepository->delete($transcript);
    }
}
