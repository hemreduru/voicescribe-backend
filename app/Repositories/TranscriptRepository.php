<?php

namespace App\Repositories;

use App\Models\Transcript;
use App\Repositories\Contracts\TranscriptRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TranscriptRepository implements TranscriptRepositoryInterface
{
    public function getAllForUser(int $userId): Collection
    {
        return Transcript::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function findForUser(int $id, int $userId): ?Transcript
    {
        return Transcript::where('id', $id)
            ->where('user_id', $userId)
            ->first();
    }

    public function findByLocalIdForUser(string $localId, int $userId): ?Transcript
    {
        return Transcript::where('local_id', $localId)
            ->where('user_id', $userId)
            ->first();
    }

    public function create(array $data): Transcript
    {
        return Transcript::create($data);
    }

    public function update(Transcript $transcript, array $data): Transcript
    {
        $transcript->update($data);

        return $transcript->fresh();
    }

    public function delete(Transcript $transcript): bool
    {
        return $transcript->delete();
    }
}
