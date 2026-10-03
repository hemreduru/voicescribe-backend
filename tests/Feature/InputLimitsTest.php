<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InputLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LookupSeeder::class);
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_transcript_store_rejects_oversize_fields(): void
    {
        $this->postJson('/api/v1/transcripts', ['title' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors(['title']);

        $this->postJson('/api/v1/transcripts', ['chunks' => [['text' => str_repeat('a', 16001)]]])
            ->assertStatus(422)->assertJsonValidationErrors(['chunks.0.text']);

        $this->postJson('/api/v1/transcripts', ['chunks' => [['transcription_error' => str_repeat('a', 5001)]]])
            ->assertStatus(422)->assertJsonValidationErrors(['chunks.0.transcription_error']);

        $this->postJson('/api/v1/transcripts', ['summaries' => [['summary_text' => str_repeat('a', 16001)]]])
            ->assertStatus(422)->assertJsonValidationErrors(['summaries.0.summary_text']);
    }

    public function test_transcript_store_accepts_fields_at_the_limit(): void
    {
        $this->postJson('/api/v1/transcripts', [
            'title' => str_repeat('a', 255),
            'chunks' => [['chunk_index' => 0, 'text' => str_repeat('a', 16000)]],
        ])->assertSuccessful();
    }

    public function test_transcript_update_rejects_oversize_title(): void
    {
        $this->putJson('/api/v1/transcripts/1', ['title' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors(['title']);
    }

    public function test_transcript_update_bounds_nested_arrays(): void
    {
        $this->putJson('/api/v1/transcripts/1', ['chunks' => [['text' => str_repeat('a', 16001)]]])
            ->assertStatus(422)->assertJsonValidationErrors(['chunks.0.text']);
    }

    public function test_chat_message_rejects_oversize_content(): void
    {
        $this->postJson('/api/v1/chat/messages', ['content' => str_repeat('a', 8001)])
            ->assertStatus(422)->assertJsonValidationErrors(['content']);
    }

    public function test_summarize_rejects_oversize_transcript_text(): void
    {
        $this->postJson('/api/v1/transcripts/1/summaries', ['transcript_text' => str_repeat('a', 200001)])
            ->assertStatus(422)->assertJsonValidationErrors(['transcript_text']);
    }

    public function test_sync_push_rejects_oversize_nested_text(): void
    {
        $this->postJson('/api/v1/sync/push', [
            'transcripts' => [['client_local_id' => 'a', 'title' => str_repeat('a', 256)]],
            'transcript_chunks' => [['text' => str_repeat('a', 16001)]],
            'summaries' => [['summary_text' => str_repeat('a', 16001)]],
        ])->assertStatus(422)->assertJsonValidationErrors([
            'transcripts.0.title',
            'transcript_chunks.0.text',
            'summaries.0.summary_text',
        ]);
    }

    public function test_sync_push_rejects_non_array_rows(): void
    {
        $this->postJson('/api/v1/sync/push', ['transcripts' => ['not-a-row']])
            ->assertStatus(422)->assertJsonValidationErrors(['transcripts.0']);
    }

    public function test_sync_push_batch_above_max_is_rejected_and_max_is_accepted(): void
    {
        config(['sync.max_batch_size' => 3]);

        foreach (['transcripts', 'transcript_chunks', 'speakers', 'summaries', 'processing_jobs', 'sync_logs'] as $key) {
            $this->postJson('/api/v1/sync/push', [$key => array_fill(0, 4, [])])
                ->assertStatus(422)->assertJsonValidationErrors([$key]);
        }

        $this->postJson('/api/v1/sync/push', ['transcripts' => array_fill(0, 3, [])])
            ->assertOk();
    }

    public function test_sync_push_keeps_unvalidated_row_keys(): void
    {
        $this->postJson('/api/v1/sync/push', [
            'transcripts' => [['client_local_id' => 'keep-1', 'title' => 'T', 'durationSeconds' => 5]],
        ])->assertOk()->assertJsonPath('data.applied.transcripts.0.client_local_id', 'keep-1');
    }
}
