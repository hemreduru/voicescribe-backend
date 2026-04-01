<?php

namespace App\Repositories;

use App\Models\Summary;
use App\Repositories\Contracts\SummaryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class SummaryRepository implements SummaryRepositoryInterface
{
    public function getAllForTranscript(int $transcriptId): Collection
    {
        return Summary::where('transcript_id', $transcriptId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(int $id): ?Summary
    {
        return Summary::find($id);
    }

    public function create(array $data): Summary
    {
        return Summary::create($data);
    }

    public function delete(Summary $summary): bool
    {
        return $summary->delete();
    }
}
