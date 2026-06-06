<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LookupSeeder::class);
    }

    public function test_push_returns_standardized_contract(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this
            ->postJson('/api/v1/sync/push', [
                'transcripts' => [],
                'transcript_chunks' => [],
                'speakers' => [],
                'summaries' => [],
                'processing_jobs' => [],
                'sync_logs' => [],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.applied.transcripts', [])
            ->assertJsonPath('data.applied.transcript_chunks', [])
            ->assertJsonPath('data.applied.summaries', [])
            ->assertJsonPath('data.applied.speakers', [])
            ->assertJsonPath('data.applied.processing_jobs', [])
            ->assertJsonPath('data.applied.sync_logs', [])
            ->assertJsonPath('data.conflicts', [])
            ->assertJsonPath('data.errors', []);

        $this->assertIsString(data_get($response->json(), 'data.serverTime'));
    }

    public function test_pull_returns_server_time_and_collection_keys(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this
            ->postJson('/api/v1/sync/pull', [
                'tables' => [
                    'transcripts',
                    'transcript_chunks',
                    'speakers',
                    'summaries',
                    'processing_jobs',
                    'sync_logs',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.conflicts', [])
            ->assertJsonPath('data.errors', [])
            ->assertJsonPath('data.transcripts', [])
            ->assertJsonPath('data.transcript_chunks', [])
            ->assertJsonPath('data.summaries', [])
            ->assertJsonPath('data.speakers', [])
            ->assertJsonPath('data.processing_jobs', [])
            ->assertJsonPath('data.sync_logs', []);

        $this->assertIsString(data_get($response->json(), 'data.serverTime'));
    }

    public function test_legacy_speaker_payload_is_ignored_without_persistence(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/sync/push', [
            'transcripts' => [[
                'client_local_id' => 'transcript-local-1',
                'local_id' => 'transcript-local-1',
                'title' => 'Legacy speaker cleanup regression',
                'duration_seconds' => 30,
                'status_key' => 'completed',
                'recorded_at' => now()->subMinute()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]],
            'transcript_chunks' => [[
                'client_local_id' => 'chunk-local-1',
                'transcript_client_local_id' => 'transcript-local-1',
                'chunk_index' => 0,
                'text' => 'Speaker fields should not be stored.',
                'speaker_id' => 123,
                'speaker_label' => 'Legacy Speaker',
                'speaker_confidence' => 0.91,
                'speaker_analysis_status' => 'completed',
                'start_time' => 0,
                'end_time' => 30,
                'confidence' => 0.95,
                'updated_at' => now()->toIso8601String(),
            ]],
            'speakers' => [[
                'client_local_id' => 'speaker-local-1',
                'name' => 'Legacy Speaker',
            ]],
            'processing_jobs' => [[
                'client_local_id' => 'job-local-1',
                'type' => 'speakerAnalysis',
                'status' => 'completed',
            ]],
            'sync_logs' => [[
                'client_local_id' => 'sync-log-local-1',
                'entity_type' => 'speaker',
            ]],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.applied.speakers', [])
            ->assertJsonPath('data.applied.processing_jobs', [])
            ->assertJsonPath('data.applied.sync_logs', [])
            ->assertJsonPath('data.errors', []);

        $this->assertFalse(Schema::hasTable('speakers'));
        $this->assertFalse(Schema::hasTable('processing_jobs'));
        $this->assertFalse(Schema::hasTable('sync_logs'));
        $this->assertFalse(Schema::hasColumn('transcript_chunks', 'speaker_label'));
        $this->assertFalse(Schema::hasColumn('transcript_chunks', 'speaker_id'));
        $this->assertFalse(Schema::hasColumn('transcript_chunks', 'speaker_confidence'));
        $this->assertFalse(Schema::hasColumn('transcript_chunks', 'speaker_analysis_status'));
        $this->assertDatabaseHas('transcript_chunks', [
            'client_local_id' => 'chunk-local-1',
            'text' => 'Speaker fields should not be stored.',
        ]);

        $pull = $this->postJson('/api/v1/sync/pull', [
            'tables' => ['transcript_chunks'],
        ]);

        $chunk = data_get($pull->json(), 'data.transcript_chunks.0');
        $this->assertIsArray($chunk);
        $this->assertArrayNotHasKey('speaker_label', $chunk);
        $this->assertArrayNotHasKey('speaker_id', $chunk);
        $this->assertArrayNotHasKey('speaker_confidence', $chunk);
        $this->assertArrayNotHasKey('speaker_analysis_status', $chunk);
    }

    public function test_push_does_not_cross_match_another_users_transcript(): void
    {
        // User A creates a transcript whose client_local_id (and derived
        // local_id) is 'shared-local'.
        $userA = User::factory()->create();
        Sanctum::actingAs($userA);
        $this->postJson('/api/v1/sync/push', [
            'transcripts' => [[
                'client_local_id' => 'shared-local',
                'title' => 'A owned',
                'duration_seconds' => 1,
                'status_key' => 'completed',
                'updated_at' => now()->subDay()->toIso8601String(),
            ]],
        ])->assertOk();

        // User B pushes a transcript reusing the same id. It must create B's
        // own row, never hijack A's (regression for the unscoped local_id
        // OR-match that ignored user_id).
        $userB = User::factory()->create();
        Sanctum::actingAs($userB);
        $this->postJson('/api/v1/sync/push', [
            'transcripts' => [[
                'client_local_id' => 'shared-local',
                'title' => 'B owned',
                'duration_seconds' => 2,
                'status_key' => 'completed',
                'updated_at' => now()->toIso8601String(),
            ]],
        ])->assertOk();

        $this->assertDatabaseCount('transcripts', 2);
        $this->assertDatabaseHas('transcripts', [
            'user_id' => $userA->id,
            'client_local_id' => 'shared-local',
            'title' => 'A owned',
        ]);
        $this->assertDatabaseHas('transcripts', [
            'user_id' => $userB->id,
            'client_local_id' => 'shared-local',
            'title' => 'B owned',
        ]);
    }

    public function test_push_is_idempotent_for_same_client_local_id(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $payload = [
            'transcripts' => [[
                'client_local_id' => 'dup-local',
                'title' => 'First',
                'duration_seconds' => 1,
                'status_key' => 'completed',
                'updated_at' => now()->toIso8601String(),
            ]],
        ];
        $this->postJson('/api/v1/sync/push', $payload)->assertOk();

        // Re-push the same client_local_id with a newer timestamp → updates the
        // same row, never a duplicate (the unique constraint backs this up).
        $payload['transcripts'][0]['title'] = 'Updated';
        $payload['transcripts'][0]['updated_at'] = now()->addMinute()->toIso8601String();
        $this->postJson('/api/v1/sync/push', $payload)->assertOk();

        $this->assertDatabaseCount('transcripts', 1);
        $this->assertDatabaseHas('transcripts', [
            'user_id' => $user->id,
            'client_local_id' => 'dup-local',
            'title' => 'Updated',
        ]);
    }
}
