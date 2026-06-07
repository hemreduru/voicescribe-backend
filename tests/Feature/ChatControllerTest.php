<?php

namespace Tests\Feature;

use App\Models\ChatSession;
use App\Models\Lookup\TranscriptStatus;
use App\Models\Transcript;
use App\Models\TranscriptChunk;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LookupSeeder::class);
        config([
            'llm.default_provider' => 'gemini',
            'llm.providers.gemini.api_key' => 'test-key',
        ]);
    }

    private function seedTranscript(User $user): Transcript
    {
        $transcript = Transcript::create([
            'user_id' => $user->id,
            'client_local_id' => 'tr-1',
            'local_id' => 'tr-1',
            'title' => 'Bütçe toplantısı',
            'duration_seconds' => 120,
            'status_id' => TranscriptStatus::getIdByKey('completed'),
            'recorded_at' => now()->subDay(),
            'sync_status' => 'synced',
        ]);
        TranscriptChunk::create([
            'transcript_id' => $transcript->id,
            'chunk_index' => 0,
            'text' => 'Pazarlama bütçesi yüzde on artırıldı ve Ayşe sorumlu oldu.',
            'start_time' => 0,
            'end_time' => 60,
            'sync_status' => 'synced',
        ]);

        return $transcript;
    }

    private function fakeGemini(string $answer): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $answer]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['totalTokenCount' => 42],
            ], 200),
        ]);
    }

    public function test_send_message_creates_session_and_persists_both_messages(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedTranscript($user);
        $this->fakeGemini('Pazarlama bütçesi %10 artırıldı (Kaynak: "Bütçe toplantısı").');

        $response = $this->postJson('/api/v1/chat/messages', [
            'content' => 'Pazarlama bütçesine ne oldu?',
        ]);

        $response->assertOk();
        $this->assertSame(
            'assistant',
            data_get($response->json(), 'data.assistant_message.role'),
        );
        $this->assertStringContainsString(
            'Bütçe toplantısı',
            data_get($response->json(), 'data.assistant_message.sources.0.title'),
        );
        $this->assertDatabaseCount('chat_sessions', 1);
        $this->assertDatabaseCount('chat_messages', 2);
    }

    public function test_continue_existing_session_keeps_history(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedTranscript($user);
        $this->fakeGemini('Yanıt.');

        $first = $this->postJson('/api/v1/chat/messages', ['content' => 'Soru 1?'])->json();
        $sessionId = data_get($first, 'data.session.id');

        $this->postJson('/api/v1/chat/messages', [
            'session_id' => $sessionId,
            'content' => 'Soru 2?',
        ])->assertOk();

        $this->assertDatabaseCount('chat_sessions', 1);
        $this->assertDatabaseCount('chat_messages', 4);

        $show = $this->getJson("/api/v1/chat/sessions/{$sessionId}");
        $show->assertOk();
        $this->assertCount(4, data_get($show->json(), 'data.messages'));
    }

    public function test_sessions_are_scoped_to_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $session = ChatSession::create(['user_id' => $owner->id, 'title' => 'x']);

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/chat/sessions/{$session->id}")->assertNotFound();
        $this->getJson('/api/v1/chat/sessions')->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_provider_failure_returns_502_but_keeps_user_message(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->seedTranscript($user);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);

        $this->postJson('/api/v1/chat/messages', ['content' => 'Soru?'])
            ->assertStatus(502);

        // The user's message is preserved even though the answer failed.
        $this->assertDatabaseHas('chat_messages', ['role' => 'user', 'content' => 'Soru?']);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/chat/messages', ['content' => 'x'])->assertUnauthorized();
        $this->getJson('/api/v1/chat/sessions')->assertUnauthorized();
    }

    public function test_validates_content(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/chat/messages', ['content' => ''])->assertStatus(422);
    }
}
