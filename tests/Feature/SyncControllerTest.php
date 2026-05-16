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
}
