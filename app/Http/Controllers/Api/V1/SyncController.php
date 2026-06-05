<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Sync\SyncPullRequest;
use App\Http\Requests\Api\V1\Sync\SyncPushRequest;
use App\Models\Lookup\LlmProvider;
use App\Models\Lookup\TranscriptStatus;
use App\Models\Summary;
use App\Models\Transcript;
use App\Models\TranscriptChunk;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    /**
     * @return array<string, array<int, mixed>>
     */
    private function emptyLegacyTables(): array
    {
        return [
            'speakers' => [],
            'processing_jobs' => [],
            'sync_logs' => [],
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/sync/push",
     *     tags={"Sync"},
     *     summary="Push local changes to server",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="transcripts", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="transcript_chunks", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="summaries", type="array", @OA\Items(type="object")),
     *             example={
     *                 "transcripts": {
     *                     {
     *                         "id": "local-1714400400000",
     *                         "local_id": "local-1714400400000",
     *                         "client_local_id": "local-1714400400000",
     *                         "title": "Sprint Planning",
     *                         "duration_seconds": 1320,
     *                         "status_key": "completed",
     *                         "recorded_at": "2026-04-29T10:00:00Z",
     *                         "updated_at": "2026-04-29T10:25:00Z"
     *                     }
     *                 },
     *                 "transcript_chunks": {
     *                     {
     *                         "id": "local-1714400400000-chunk-1",
     *                         "client_local_id": "local-1714400400000-chunk-1",
     *                         "transcript_client_local_id": "local-1714400400000",
     *                         "chunk_index": 1,
     *                         "text": "Sprint goals and blockers",
     *                         "start_time": 0.0,
     *                         "end_time": 4.2,
     *                         "updated_at": "2026-04-29T10:00:05Z"
     *                     }
     *                 },
     *                 "summaries": {}
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Push result",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sync push processed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="serverTime", type="string", format="date-time"),
     *                 @OA\Property(property="applied", type="object"),
     *                 @OA\Property(property="conflicts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function push(SyncPushRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $payload = $request->validated();
        $startedAt = microtime(true);

        $result = DB::transaction(function () use ($payload, $user): array {
            $report = [
                'applied' => [],
                'conflicts' => [],
                'errors' => [],
            ];

            $report['applied']['transcripts'] = $this->syncTranscripts($user, (array) ($payload['transcripts'] ?? []), $report['conflicts']);
            $report['applied']['transcript_chunks'] = $this->syncTranscriptChunks($user, (array) ($payload['transcript_chunks'] ?? []), $report['conflicts'], $report['errors']);
            $report['applied']['summaries'] = $this->syncSummaries($user, (array) ($payload['summaries'] ?? []), $report['conflicts'], $report['errors']);
            $report['applied'] = array_merge($report['applied'], $this->emptyLegacyTables());
            $report['serverTime'] = now()->toIso8601String();

            return $report;
        });

        $context = [
            'user_id' => $user->id,
            'received' => [
                'transcripts' => count((array) ($payload['transcripts'] ?? [])),
                'transcript_chunks' => count((array) ($payload['transcript_chunks'] ?? [])),
                'summaries' => count((array) ($payload['summaries'] ?? [])),
            ],
            'applied' => [
                'transcripts' => count($result['applied']['transcripts'] ?? []),
                'transcript_chunks' => count($result['applied']['transcript_chunks'] ?? []),
                'summaries' => count($result['applied']['summaries'] ?? []),
            ],
            'conflicts' => count($result['conflicts'] ?? []),
            'errors' => count($result['errors'] ?? []),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];

        // Errors/conflicts are the actionable signal — surface their details at a
        // higher level so they stand out; clean pushes stay at info.
        if ($context['errors'] > 0) {
            Log::channel('sync')->warning('sync.push completed with errors', $context + [
                'error_details' => $result['errors'] ?? [],
            ]);
        } else {
            Log::channel('sync')->info('sync.push', $context);
        }

        return $this->successResponse(
            data: $result,
            message: 'Sync push processed successfully',
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/sync/pull",
     *     tags={"Sync"},
     *     summary="Pull changed data from server",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=false,
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="since", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="tables",
     *                 type="array",
     *
     *                 @OA\Items(type="string", enum={"transcripts","transcript_chunks","summaries"})
     *             ),
     *
     *             @OA\Property(property="limit", type="integer", example=200),
     *             example={
     *                 "since": "2026-04-29T10:00:00Z",
     *                 "tables": {"transcripts", "transcript_chunks", "summaries"},
     *                 "limit": 200
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Pull result",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sync pull completed successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="since", type="string", format="date-time"),
     *                 @OA\Property(property="serverTime", type="string", format="date-time"),
     *                 @OA\Property(property="conflicts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="errors", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="transcripts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="transcript_chunks", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summaries", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     )
     * )
     */
    public function pull(SyncPullRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $since = $request->validated('since');
        $sinceTs = is_string($since) ? Carbon::parse($since) : Carbon::createFromTimestamp(0);
        $tables = collect((array) ($request->validated('tables') ?? []));
        $limit = (int) ($request->validated('limit') ?? 200);

        $shouldPull = static fn (string $table) => $tables->isEmpty() || $tables->contains($table);
        $startedAt = microtime(true);

        $data = array_merge([
            'transcripts' => $shouldPull('transcripts')
                ? $this->changedRows(
                    Transcript::query()->where('user_id', $user->id)->withTrashed(),
                    $sinceTs,
                    $limit,
                )
                : [],
            'transcript_chunks' => $shouldPull('transcript_chunks')
                ? $this->changedRows(
                    TranscriptChunk::query()
                        ->withTrashed()
                        ->whereIn(
                            'transcript_id',
                            Transcript::query()->where('user_id', $user->id)->select('id'),
                        ),
                    $sinceTs,
                    $limit,
                )
                : [],
            'summaries' => $shouldPull('summaries')
                ? $this->changedRows(
                    Summary::query()
                        ->withTrashed()
                        ->whereIn(
                            'transcript_id',
                            Transcript::query()->where('user_id', $user->id)->select('id'),
                        ),
                    $sinceTs,
                    $limit,
                )
                : [],
        ], $this->emptyLegacyTables());

        // Resumable cursor: when any table filled its page (a truncated result),
        // do NOT advance the cursor to "now" — that would skip the rows beyond
        // the limit. Instead resume from the earliest truncated table's last
        // `updated_at`, so the next pull re-scans from there. Re-pulling the
        // boundary timestamp is safe because the client merge is idempotent.
        $resumeFrom = $this->resumeCursor($data, $limit);
        $nextSince = $resumeFrom ?? now();

        Log::channel('sync')->info('sync.pull', [
            'user_id' => $user->id,
            'since' => $sinceTs->toIso8601String(),
            'returned' => [
                'transcripts' => count($data['transcripts'] ?? []),
                'transcript_chunks' => count($data['transcript_chunks'] ?? []),
                'summaries' => count($data['summaries'] ?? []),
            ],
            // True when a page was truncated at the limit, so the client must
            // pull again from `serverTime` to drain the remainder.
            'has_more' => $resumeFrom !== null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $this->successResponse(
            data: [
                'since' => $sinceTs->toIso8601String(),
                'serverTime' => $nextSince->toIso8601String(),
                'conflicts' => [],
                'errors' => [],
                ...$data,
            ],
            message: 'Sync pull completed successfully',
        );
    }

    /**
     * Returns the timestamp the next pull should resume from when at least one
     * table was truncated at the page limit, or null when everything drained.
     *
     * @param  array<string, mixed>  $data
     */
    private function resumeCursor(array $data, int $limit): ?Carbon
    {
        if ($limit <= 0) {
            return null;
        }

        $resume = null;
        foreach (['transcripts', 'transcript_chunks', 'summaries'] as $table) {
            $rows = $data[$table] ?? [];
            if (! is_array($rows) || count($rows) < $limit) {
                continue;
            }

            // Rows are ordered by updated_at asc, so the last one is this
            // table's highest processed timestamp.
            $last = $rows[array_key_last($rows)];
            $ts = is_array($last) ? ($last['updated_at'] ?? null) : null;
            if (! is_string($ts) || trim($ts) === '') {
                continue;
            }

            $candidate = Carbon::parse($ts);
            // Use the earliest truncated boundary so no table's overflow rows
            // are skipped by a single shared cursor.
            if ($resume === null || $candidate->lt($resume)) {
                $resume = $candidate;
            }
        }

        return $resume;
    }

    /**
     * @OA\Get(
     *     path="/api/v1/sync/status",
     *     tags={"Sync"},
     *     summary="Get sync status counts",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Status result",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 example={
     *                     "transcripts": {"pending": 2, "synced": 12},
     *                     "transcript_chunks": {"pending": 8, "synced": 170},
     *                     "summaries": {"pending": 0, "synced": 7}
     *                 }
     *             )
     *         )
     *     )
     * )
     */
    public function status(SyncPullRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $countByStatus = static fn (string $modelClass) => $modelClass::query()
            ->where('user_id', $user->id)
            ->selectRaw('sync_status, COUNT(*) as total')
            ->groupBy('sync_status')
            ->pluck('total', 'sync_status');

        return $this->successResponse(
            data: array_merge([
                'transcripts' => $countByStatus(Transcript::class),
                'transcript_chunks' => TranscriptChunk::query()
                    ->whereIn(
                        'transcript_id',
                        Transcript::query()->where('user_id', $user->id)->select('id'),
                    )
                    ->selectRaw('sync_status, COUNT(*) as total')
                    ->groupBy('sync_status')
                    ->pluck('total', 'sync_status'),
                'summaries' => Summary::query()
                    ->whereIn(
                        'transcript_id',
                        Transcript::query()->where('user_id', $user->id)->select('id'),
                    )
                    ->selectRaw('sync_status, COUNT(*) as total')
                    ->groupBy('sync_status')
                    ->pluck('total', 'sync_status'),
            ], $this->emptyLegacyTables()),
            message: 'Sync status fetched successfully',
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $conflicts
     * @return array<int, array<string, mixed>>
     */
    private function syncTranscripts(User $user, array $rows, array &$conflicts): array
    {
        $applied = [];

        foreach ($rows as $row) {
            $clientLocalId = $this->stringValue($row, ['client_local_id', 'clientLocalId', 'local_id', 'localId']);
            $existing = Transcript::query()->withTrashed()
                ->where('user_id', $user->id)
                ->when(
                    $clientLocalId !== null,
                    // Wrap the OR in a closure so it stays scoped to user_id.
                    // Without the closure, operator precedence yields
                    // `(user_id = ? AND client_local_id = ?) OR local_id = ?`,
                    // which can cross-match another user's row by local_id.
                    fn ($query) => $query->where(function ($q) use ($clientLocalId): void {
                        $q->where('client_local_id', $clientLocalId)
                            ->orWhere('local_id', $clientLocalId);
                    }),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->first();

            if ($this->isServerNewer($existing?->updated_at, $this->dateValue($row, ['updated_at', 'updatedAt']))) {
                $conflicts[] = [
                    'table' => 'transcripts',
                    'client_local_id' => $clientLocalId,
                    'reason' => 'server_newer',
                    'server' => $existing?->toArray(),
                ];

                continue;
            }

            $incomingStatusKey = $this->stringValue($row, ['status_key', 'statusKey']);
            $statusKey = $this->normalizeIncomingTranscriptStatusKey($incomingStatusKey);
            $statusId = TranscriptStatus::getIdByKey($statusKey) ?? TranscriptStatus::getIdByKey(TranscriptStatus::KEY_COMPLETED);

            $attributes = array_filter([
                'user_id' => $user->id,
                'local_id' => $this->stringValue($row, ['local_id', 'localId']) ?? $clientLocalId,
                'client_local_id' => $clientLocalId,
                'title' => $this->stringValue($row, ['title']),
                'duration_seconds' => $this->intValue($row, ['duration_seconds', 'durationSeconds']) ?? 0,
                'status_id' => $statusId,
                'recorded_at' => $this->dateValue($row, ['recorded_at', 'recordedAt']),
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ], static fn ($value) => $value !== null);

            if ($existing === null) {
                $existing = Transcript::create($attributes);
            } else {
                $existing->fill($attributes);
                $existing->save();
            }

            if ($this->dateValue($row, ['deleted_at', 'deletedAt']) !== null) {
                $existing->delete();
            }

            $applied[] = [
                'client_local_id' => $clientLocalId,
                'remote_id' => (string) $existing->id,
                'updated_at' => $existing->updated_at?->toIso8601String(),
            ];
        }

        return $applied;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function syncTranscriptChunks(User $user, array $rows, array &$conflicts, array &$errors): array
    {
        $applied = [];

        foreach ($rows as $row) {
            $transcript = $this->resolveTranscriptForRow($user, $row);
            if ($transcript === null) {
                $errors[] = [
                    'table' => 'transcript_chunks',
                    'reason' => 'transcript_not_found',
                    'row' => $row,
                ];

                continue;
            }

            $clientLocalId = $this->stringValue($row, ['client_local_id', 'clientLocalId', 'id']);
            $chunkIndex = $this->intValue($row, ['chunk_index', 'chunkIndex']);

            $existing = TranscriptChunk::query()->withTrashed()
                ->where('transcript_id', $transcript->id)
                ->when(
                    $clientLocalId !== null,
                    fn ($query) => $query->where('client_local_id', $clientLocalId),
                    fn ($query) => $chunkIndex !== null
                        ? $query->where('chunk_index', $chunkIndex)
                        : $query->whereRaw('1 = 0'),
                )
                ->first();

            if ($this->isServerNewer($existing?->updated_at, $this->dateValue($row, ['updated_at', 'updatedAt']))) {
                $conflicts[] = [
                    'table' => 'transcript_chunks',
                    'client_local_id' => $clientLocalId,
                    'reason' => 'server_newer',
                    'server' => $existing?->toArray(),
                ];

                continue;
            }

            $attributes = array_filter([
                'transcript_id' => $transcript->id,
                'client_local_id' => $clientLocalId,
                'chunk_index' => $chunkIndex ?? 0,
                'text' => $this->stringValue($row, ['text']) ?? '',
                'start_time' => $this->floatValue($row, ['start_time', 'startTime']) ?? 0,
                'end_time' => $this->floatValue($row, ['end_time', 'endTime']) ?? 0,
                'confidence' => $this->floatValue($row, ['confidence']),
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ], static fn ($value) => $value !== null);

            if ($existing === null) {
                $existing = TranscriptChunk::create($attributes);
            } else {
                $existing->fill($attributes);
                $existing->save();
            }

            if ($this->dateValue($row, ['deleted_at', 'deletedAt']) !== null) {
                $existing->delete();
            }

            $applied[] = [
                'client_local_id' => $clientLocalId,
                'remote_id' => (string) $existing->id,
                'updated_at' => $existing->updated_at?->toIso8601String(),
            ];
        }

        return $applied;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $conflicts
     * @param  array<int, array<string, mixed>>  $errors
     * @return array<int, array<string, mixed>>
     */
    private function syncSummaries(User $user, array $rows, array &$conflicts, array &$errors): array
    {
        $applied = [];

        foreach ($rows as $row) {
            $transcript = $this->resolveTranscriptForRow($user, $row);
            if ($transcript === null) {
                $errors[] = [
                    'table' => 'summaries',
                    'reason' => 'transcript_not_found',
                    'row' => $row,
                ];

                continue;
            }

            $clientLocalId = $this->stringValue($row, ['client_local_id', 'clientLocalId', 'id']);
            $existing = Summary::query()->withTrashed()
                ->where('transcript_id', $transcript->id)
                ->when(
                    $clientLocalId !== null,
                    fn ($query) => $query->where('client_local_id', $clientLocalId),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )
                ->first();

            if ($this->isServerNewer($existing?->updated_at, $this->dateValue($row, ['updated_at', 'updatedAt']))) {
                $conflicts[] = [
                    'table' => 'summaries',
                    'client_local_id' => $clientLocalId,
                    'reason' => 'server_newer',
                    'server' => $existing?->toArray(),
                ];

                continue;
            }

            $providerKey = $this->stringValue($row, ['provider_key', 'providerKey']) ?? 'openai';
            $providerId = LlmProvider::getIdByKey($providerKey) ?? LlmProvider::getIdByKey('openai') ?? 1;

            $attributes = array_filter([
                'transcript_id' => $transcript->id,
                'client_local_id' => $clientLocalId,
                'provider_id' => $providerId,
                'model' => $this->stringValue($row, ['model']) ?? 'local-default',
                'summary_text' => $this->stringValue($row, ['summary_text', 'summaryText']) ?? '',
                'token_count' => $this->intValue($row, ['token_count', 'tokenCount']),
                'processing_time_ms' => $this->intValue($row, ['processing_time_ms', 'processingTimeMs']),
                'sync_status' => 'synced',
                'last_synced_at' => now(),
                'sync_error' => null,
            ], static fn ($value) => $value !== null);

            if ($existing === null) {
                $existing = Summary::create($attributes);
            } else {
                $existing->fill($attributes);
                $existing->save();
            }

            if ($this->dateValue($row, ['deleted_at', 'deletedAt']) !== null) {
                $existing->delete();
            }

            $applied[] = [
                'client_local_id' => $clientLocalId,
                'remote_id' => (string) $existing->id,
                'updated_at' => $existing->updated_at?->toIso8601String(),
            ];
        }

        return $applied;
    }

    private function resolveTranscriptForRow(User $user, array $row): ?Transcript
    {
        $transcriptId = $this->intValue($row, ['transcript_id', 'transcriptId']);
        if ($transcriptId !== null) {
            return Transcript::query()->where('user_id', $user->id)->find($transcriptId);
        }

        $transcriptTextId = $this->stringValue($row, ['transcript_id', 'transcriptId']);
        if ($transcriptTextId !== null) {
            $byRemoteId = Transcript::query()
                ->where('user_id', $user->id)
                ->where('remote_id', $transcriptTextId)
                ->first();
            if ($byRemoteId !== null) {
                return $byRemoteId;
            }
        }

        $localId = $this->stringValue($row, ['transcript_client_local_id', 'transcriptClientLocalId', 'transcript_local_id', 'transcriptLocalId']);
        if ($localId === null) {
            return null;
        }

        return Transcript::query()
            ->where('user_id', $user->id)
            ->where(function ($query) use ($localId): void {
                $query->where('client_local_id', $localId)
                    ->orWhere('local_id', $localId);
            })
            ->first();
    }

    private function isServerNewer(?Carbon $serverUpdatedAt, ?Carbon $incomingUpdatedAt): bool
    {
        if ($serverUpdatedAt === null || $incomingUpdatedAt === null) {
            return false;
        }

        return $serverUpdatedAt->gt($incomingUpdatedAt);
    }

    private function changedRows($query, Carbon $sinceTs, int $limit): array
    {
        return $query
            ->where(function ($builder) use ($sinceTs): void {
                $builder->where('updated_at', '>=', $sinceTs)
                    ->orWhere('deleted_at', '>=', $sinceTs);
            })
            // Deterministic ordering so a truncated page is resumable and never
            // re-orders rows that share an `updated_at` timestamp.
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->map(function ($model): array {
                $row = $model->toArray();
                $row['remote_id'] = (string) $model->id;
                $row['updated_at'] = $model->updated_at?->toIso8601String();
                $row['deleted_at'] = $model->deleted_at?->toIso8601String();
                if ($model instanceof Transcript) {
                    $row['status_key'] = TranscriptStatus::query()
                        ->whereKey($model->status_id)
                        ->value('key');
                }

                return $row;
            })
            ->all();
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

    private function normalizeIncomingTranscriptStatusKey(?string $key): string
    {
        $normalized = strtolower((string) $key);

        return match ($normalized) {
            'recording' => TranscriptStatus::KEY_RECORDING,
            'processing',
            'transcribing',
            'transcription_completed',
            'empty' => TranscriptStatus::KEY_PROCESSING,
            'failed',
            'transcription_error' => TranscriptStatus::KEY_FAILED,
            'completed' => TranscriptStatus::KEY_COMPLETED,
            default => TranscriptStatus::KEY_COMPLETED,
        };
    }
}
