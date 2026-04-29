<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_push_returns_standardized_contract(): void
    {
        $user = User::factory()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000001',
        ]);

        $response = $this
            ->actingAs($user, 'supabase')
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
            ->assertJsonPath('data.applied.speakers', [])
            ->assertJsonPath('data.applied.summaries', [])
            ->assertJsonPath('data.applied.processing_jobs', [])
            ->assertJsonPath('data.applied.sync_logs', [])
            ->assertJsonPath('data.conflicts', [])
            ->assertJsonPath('data.errors', []);

        $this->assertIsString(data_get($response->json(), 'data.serverTime'));
    }

    public function test_pull_returns_server_time_and_collection_keys(): void
    {
        $user = User::factory()->create([
            'supabase_user_id' => '00000000-0000-0000-0000-000000000002',
        ]);

        $response = $this
            ->actingAs($user, 'supabase')
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
            ->assertJsonPath('data.speakers', [])
            ->assertJsonPath('data.summaries', [])
            ->assertJsonPath('data.processing_jobs', [])
            ->assertJsonPath('data.sync_logs', []);

        $this->assertIsString(data_get($response->json(), 'data.serverTime'));
    }
}

