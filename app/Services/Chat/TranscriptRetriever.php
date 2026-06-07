<?php

namespace App\Services\Chat;

use App\Models\Transcript;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Retrieves the user's transcripts most relevant to a query, to ground chat
 * answers (the "queryable knowledge pool"). Uses MySQL FULLTEXT when available
 * and falls back to LIKE matching otherwise.
 */
class TranscriptRetriever
{
    /** Max transcripts to include as context. */
    private const MAX_SOURCES = 4;

    /** Max characters of each transcript's text included in the prompt. */
    private const PER_SOURCE_CHARS = 1800;

    /**
     * @return array<int, array{transcript_id:int,title:string,date:?string,text:string}>
     */
    public function retrieve(int $userId, string $query): array
    {
        $ids = $this->rankTranscriptIds($userId, $query);

        if ($ids === []) {
            // No keyword hits — fall back to the user's most recent transcripts so
            // the assistant still has something to reason over.
            $ids = Transcript::query()
                ->where('user_id', $userId)
                ->latest('recorded_at')
                ->limit(self::MAX_SOURCES)
                ->pluck('id')
                ->all();
        }

        if ($ids === []) {
            return [];
        }

        $transcripts = Transcript::query()
            ->where('user_id', $userId)
            ->whereIn('id', $ids)
            ->with(['chunks'])
            ->get();

        // Preserve ranking order.
        $byId = $transcripts->keyBy('id');
        $ordered = [];
        foreach ($ids as $id) {
            $transcript = $byId->get($id);
            if ($transcript === null) {
                continue;
            }
            $text = $transcript->chunks
                ->sortBy('chunk_index')
                ->pluck('text')
                ->filter(fn ($t) => is_string($t) && trim($t) !== '')
                ->implode(' ');
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            if (mb_strlen($text) > self::PER_SOURCE_CHARS) {
                $text = mb_substr($text, 0, self::PER_SOURCE_CHARS);
            }
            $ordered[] = [
                'transcript_id' => (int) $transcript->id,
                'title' => $transcript->title !== null && trim($transcript->title) !== ''
                    ? $transcript->title
                    : 'Adsız kayıt',
                'date' => $transcript->recorded_at?->toDateString(),
                'text' => $text,
            ];
        }

        return $ordered;
    }

    /**
     * @return array<int, int>
     */
    private function rankTranscriptIds(int $userId, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        try {
            $rows = DB::select(
                'SELECT t.id AS id, MAX(MATCH(c.text) AGAINST (? IN NATURAL LANGUAGE MODE)) AS score '
                .'FROM transcripts t JOIN transcript_chunks c ON c.transcript_id = t.id '
                .'WHERE t.user_id = ? AND t.deleted_at IS NULL AND c.deleted_at IS NULL '
                .'GROUP BY t.id HAVING score > 0 ORDER BY score DESC LIMIT ?',
                [$query, $userId, self::MAX_SOURCES],
            );

            return array_map(static fn ($r) => (int) $r->id, $rows);
        } catch (Throwable $e) {
            return $this->likeFallback($userId, $query);
        }
    }

    /**
     * @return array<int, int>
     */
    private function likeFallback(int $userId, string $query): array
    {
        $terms = collect(preg_split('/\s+/', $query))
            ->map(fn ($t) => trim($t))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->take(6)
            ->values();

        if ($terms->isEmpty()) {
            return [];
        }

        return Transcript::query()
            ->where('user_id', $userId)
            ->where(function ($q) use ($terms) {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $q->orWhere('title', 'like', $like)
                        ->orWhereHas('chunks', fn ($cq) => $cq->where('text', 'like', $like));
                }
            })
            ->latest('recorded_at')
            ->limit(self::MAX_SOURCES)
            ->pluck('id')
            ->all();
    }
}
