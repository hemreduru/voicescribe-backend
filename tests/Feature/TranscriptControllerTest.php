<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranscriptControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LookupSeeder::class);
    }

    public function test_transcript_response_does_not_include_legacy_speaker_chunk_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transcripts', [
            'client_local_id' => 'transcript-api-local-1',
            'local_id' => 'transcript-api-local-1',
            'title' => 'Transcript API speaker cleanup regression',
            'duration_seconds' => 12,
            'status_key' => 'completed',
            'recorded_at' => now()->subMinute()->toIso8601String(),
            'chunks' => [[
                'client_local_id' => 'chunk-api-local-1',
                'chunk_index' => 0,
                'text' => 'Speaker fields should not appear in the API resource.',
                'speaker_id' => 55,
                'speaker_label' => 'Legacy Speaker',
                'speaker_confidence' => 0.88,
                'speaker_analysis_status' => 'completed',
                'start_time' => 0,
                'end_time' => 12,
                'confidence' => 0.9,
            ]],
        ]);

        $response->assertCreated();

        $chunk = data_get($response->json(), 'data.chunks.0');
        $this->assertIsArray($chunk);
        $this->assertArrayNotHasKey('speaker_label', $chunk);
        $this->assertArrayNotHasKey('speaker_id', $chunk);
        $this->assertArrayNotHasKey('speaker_confidence', $chunk);
        $this->assertArrayNotHasKey('speaker_analysis_status', $chunk);
    }
}
