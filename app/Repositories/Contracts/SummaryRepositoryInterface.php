<?php

namespace App\Repositories\Contracts;

use App\Models\Summary;
use Illuminate\Database\Eloquent\Collection;

interface SummaryRepositoryInterface
{
    /**
     * Get all summaries for a transcript.
     */
    public function getAllForTranscript(int $transcriptId): Collection;

    /**
     * Find a summary by ID.
     */
    public function find(int $id): ?Summary;

    /**
     * Create a new summary.
     */
    public function create(array $data): Summary;

    /**
     * Delete a summary.
     */
    public function delete(Summary $summary): bool;
}
