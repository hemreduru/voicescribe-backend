<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transcript\StoreTranscriptRequest;
use App\Http\Requests\Api\V1\Transcript\UpdateTranscriptRequest;
use App\Http\Resources\TranscriptResource;
use App\Models\Lookup\LlmProvider;
use App\Models\Lookup\TranscriptStatus;
use App\Models\Summary;
use App\Models\Transcript;
use App\Models\TranscriptChunk;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TranscriptController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/transcripts",
     *     tags={"Transcripts"},
     *     summary="List transcripts",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Transcript list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $transcripts = Transcript::query()
            ->where('user_id', $user->id)
            ->with(['status', 'chunks', 'summaries.provider'])
            ->latest('updated_at')
            ->get();

        return $this->successResponse(
            data: TranscriptResource::collection($transcripts),
            message: 'Transcripts fetched successfully',
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $transcript = Transcript::query()
            ->where('user_id', $user->id)
            ->with(['status', 'chunks', 'summaries.provider'])
            ->find($id);

        if ($transcript === null) {
            return $this->notFoundResponse('Transcript not found.');
        }

        return $this->successResponse(
            data: new TranscriptResource($transcript),
            message: 'Transcript fetched successfully',
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/transcripts",
     *     tags={"Transcripts"},
     *     summary="Create or upsert transcript",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=201, description="Transcript stored")
     * )
     */
    public function store(StoreTranscriptRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $payload = $request->all();

        $transcript = DB::transaction(function () use ($payload, $user): Transcript {
            $transcript = $this->upsertTranscript($payload, $user);
            $this->upsertChunks($transcript, (array) ($payload['chunks'] ?? []));
            $this->upsertSummaries($transcript, (array) ($payload['summaries'] ?? []));

            return $transcript->refresh()->load(['status', 'chunks', 'summaries.provider']);
        });

        return $this->createdResponse(
            data: new TranscriptResource($transcript),
            message: 'Transcript stored successfully',
        );
    }

    public function update(UpdateTranscriptRequest $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $transcript = Transcript::query()
            ->where('user_id', $user->id)
            ->find($id);

        if ($transcript === null) {
            return $this->notFoundResponse('Transcript not found.');
        }

        $payload = $request->all();

        $updated = DB::transaction(function () use ($payload, $transcript): Transcript {
            $transcript = $this->upsertTranscript($payload, $transcript->user, $transcript);
            $this->upsertChunks($transcript, (array) ($payload['chunks'] ?? []));
            $this->upsertSummaries($transcript, (array) ($payload['summaries'] ?? []));

            return $transcript->refresh()->load(['status', 'chunks', 'summaries.provider']);
        });

        return $this->successResponse(
            data: new TranscriptResource($updated),
            message: 'Transcript updated successfully',
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $transcript = Transcript::query()
            ->where('user_id', $user->id)
            ->find($id);

        if ($transcript === null) {
            return $this->notFoundResponse('Transcript not found.');
        }

        $transcript->sync_status = 'pending';
        $transcript->sync_error = null;
        $transcript->save();
        $transcript->delete();

        return $this->successResponse(
            message: 'Transcript soft deleted successfully',
            statusCode: Response::HTTP_OK,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function upsertTranscript(array $payload, User $user, ?Transcript $existing = null): Transcript
    {
        $clientLocalId = $this->stringValue($payload, ['client_local_id', 'clientLocalId', 'local_id', 'localId']);
        $localId = $this->stringValue($payload, ['local_id', 'localId']) ?? $clientLocalId;

        $transcript = $existing;
        if ($transcript === null) {
            $transcript = Transcript::query()
                ->where('user_id', $user->id)
                ->when(
                    $clientLocalId !== null,
                    fn ($query) => $query->where('client_local_id', $clientLocalId),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->first();
        }

        if ($transcript === null && $localId !== null) {
            $transcript = Transcript::query()
                ->where('user_id', $user->id)
                ->where('local_id', $localId)
                ->first();
        }

        $statusKey = $this->stringValue($payload, ['status_key', 'statusKey']) ?? TranscriptStatus::KEY_COMPLETED;
        $statusId = TranscriptStatus::getIdByKey($statusKey) ?? TranscriptStatus::getIdByKey(TranscriptStatus::KEY_COMPLETED);

        $attributes = array_filter([
            'user_id' => $user->id,
            'local_id' => $localId,
            'client_local_id' => $clientLocalId ?? $localId,
            'title' => $this->stringValue($payload, ['title']),
            'duration_seconds' => $this->intValue($payload, ['duration_seconds', 'durationSeconds']) ?? 0,
            'status_id' => $statusId,
            'recorded_at' => $this->dateValue($payload, ['recorded_at', 'recordedAt']),
            'sync_status' => $this->stringValue($payload, ['sync_status', 'syncStatus']) ?? 'synced',
            'last_synced_at' => now(),
            'sync_error' => null,
        ], static fn ($value) => $value !== null);

        if ($transcript === null) {
            $transcript = Transcript::create($attributes);
        } else {
            $transcript->fill($attributes);
            if ($this->dateValue($payload, ['deleted_at', 'deletedAt']) !== null) {
                $transcript->delete();
            }
            $transcript->save();
        }

        return $transcript;
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunks
     */
    private function upsertChunks(Transcript $transcript, array $chunks): void
    {
        foreach ($chunks as $item) {
            $clientLocalId = $this->stringValue($item, ['client_local_id', 'clientLocalId']);
            $chunkIndex = $this->intValue($item, ['chunk_index', 'chunkIndex']);

            $chunk = TranscriptChunk::query()
                ->where('transcript_id', $transcript->id)
                ->when(
                    $clientLocalId !== null,
                    fn ($query) => $query->where('client_local_id', $clientLocalId),
                    fn ($query) => $chunkIndex !== null
                        ? $query->where('chunk_index', $chunkIndex)
                        : $query->whereRaw('1 = 0'),
                )
                ->first();

            $attributes = array_filter([
                'transcript_id' => $transcript->id,
                'chunk_index' => $chunkIndex,
                'text' => $this->stringValue($item, ['text']) ?? '',
                'start_time' => $this->floatValue($item, ['start_time', 'startTime']) ?? 0,
                'end_time' => $this->floatValue($item, ['end_time', 'endTime']) ?? 0,
                'confidence' => $this->floatValue($item, ['confidence']),
                'client_local_id' => $clientLocalId,
                'sync_status' => $this->stringValue($item, ['sync_status', 'syncStatus']) ?? 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ], static fn ($value) => $value !== null);

            if ($chunk === null) {
                $chunk = TranscriptChunk::create($attributes);
            } else {
                $chunk->fill($attributes);
                $chunk->save();
            }

            if ($this->dateValue($item, ['deleted_at', 'deletedAt']) !== null) {
                $chunk->delete();
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $summaries
     */
    private function upsertSummaries(Transcript $transcript, array $summaries): void
    {
        foreach ($summaries as $item) {
            $clientLocalId = $this->stringValue($item, ['client_local_id', 'clientLocalId']);

            $summary = Summary::query()
                ->where('transcript_id', $transcript->id)
                ->when(
                    $clientLocalId !== null,
                    fn ($query) => $query->where('client_local_id', $clientLocalId),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->first();

            $providerKey = $this->stringValue($item, ['provider_key', 'providerKey']) ?? 'openai';
            $providerId = LlmProvider::getIdByKey($providerKey) ?? LlmProvider::getIdByKey('openai') ?? 1;

            $attributes = array_filter([
                'transcript_id' => $transcript->id,
                'client_local_id' => $clientLocalId,
                'provider_id' => $providerId,
                'model' => $this->stringValue($item, ['model']) ?? 'local-default',
                'summary_text' => $this->stringValue($item, ['summary_text', 'summaryText']) ?? '',
                'token_count' => $this->intValue($item, ['token_count', 'tokenCount']),
                'processing_time_ms' => $this->intValue($item, ['processing_time_ms', 'processingTimeMs']),
                'sync_status' => $this->stringValue($item, ['sync_status', 'syncStatus']) ?? 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ], static fn ($value) => $value !== null);

            if ($summary === null) {
                $summary = Summary::create($attributes);
            } else {
                $summary->fill($attributes);
                $summary->save();
            }

            if ($this->dateValue($item, ['deleted_at', 'deletedAt']) !== null) {
                $summary->delete();
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function stringValue(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function intValue(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function floatValue(array $payload, array $keys): ?float
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     */
    private function dateValue(array $payload, array $keys): ?Carbon
    {
        foreach ($keys as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return Carbon::parse($value);
            }
        }

        return null;
    }
}
