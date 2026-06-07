<?php

namespace Tests\Feature;

use App\Models\Lookup\LlmProvider;
use App\Models\Lookup\TranscriptStatus;
use App\Models\Summary;
use App\Models\Transcript;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SummarizationControllerTest extends TestCase
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

    private function createTranscript(User $user): Transcript
    {
        return Transcript::create([
            'user_id' => $user->id,
            'client_local_id' => 'tr-local-1',
            'local_id' => 'tr-local-1',
            'title' => 'Sprint planlama',
            'duration_seconds' => 600,
            'status_id' => TranscriptStatus::getIdByKey('completed'),
            'recorded_at' => now(),
            'sync_status' => 'synced',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function minutesJson(): array
    {
        return [
            'schema_version' => 1,
            'title' => 'Sprint Planlama Toplantısı',
            'subtitle' => 'Q3 yol haritası',
            'metadata' => [
                'topic' => 'Sprint planlama',
                'date' => '2026-06-06',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'location' => 'Zoom',
                'attendees' => ['Ahmet', 'Ayşe'],
                'absentees' => ['Mehmet'],
                'recorder' => 'Ayşe',
            ],
            'executive_summary' => ['Bütçe onaylandı.', 'Lansman Eylül ayına ertelendi.'],
            'agenda_items' => [[
                'title' => 'Bütçe',
                'discussion' => 'Ahmet yetersiz olduğunu savundu.',
                'conclusion' => 'Bütçe onaylandı.',
            ]],
            'decisions' => ['Lansman Eylül ayına ertelendi.'],
            'action_items' => [[
                'owner' => 'Ahmet',
                'task' => 'Mali tabloyu hazırla',
                'due_date' => '2026-06-15',
            ]],
            'open_questions' => ['Pazarlama bütçesi netleşmedi.'],
            'next_meeting' => '2026-06-13',
            'notes' => ['Kesin rakam konuşulmadı.'],
        ];
    }

    private function fakeGemini(array $minutes): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($minutes)]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['totalTokenCount' => 321],
            ], 200),
        ]);
    }

    public function test_generates_structured_summary_and_persists_it(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);
        $minutes = $this->minutesJson();
        $this->fakeGemini($minutes);

        $response = $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => 'Toplantıda bütçe ve lansman konuşuldu.',
            'provider' => 'gemini',
            'length' => 'medium',
            'client_local_id' => 'sum-local-1',
        ]);

        $response->assertCreated();
        $this->assertSame('gemini', data_get($response->json(), 'data.provider_key'));
        $this->assertSame(321, data_get($response->json(), 'data.token_count'));

        $stored = data_get($response->json(), 'data.summary_text');
        $this->assertSame($minutes, json_decode($stored, true));

        $this->assertDatabaseHas('summaries', [
            'transcript_id' => $transcript->id,
            'client_local_id' => 'sum-local-1',
            'provider_id' => LlmProvider::getIdByKey('gemini'),
        ]);
    }

    public function test_locale_drives_summary_output_language(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);
        $this->fakeGemini($this->minutesJson());

        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => 'This meeting was held entirely in English.',
            'provider' => 'gemini',
            'locale' => 'en_US',
        ])->assertCreated();

        // The system instruction sent to the model must request English output
        // regardless of the transcript language.
        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'in English');
        });
    }

    public function test_identical_request_is_cached(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);
        $this->fakeGemini($this->minutesJson());

        $payload = ['transcript_text' => 'Aynı metin.', 'provider' => 'gemini', 'length' => 'short'];
        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", $payload)->assertCreated();
        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", $payload)->assertCreated();

        Http::assertSentCount(1);
    }

    public function test_provider_failure_returns_502(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response('boom', 500)]);

        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => 'Hata senaryosu.',
        ])->assertStatus(502);
    }

    public function test_other_users_transcript_returns_404(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $transcript = $this->createTranscript($owner);
        Sanctum::actingAs($other);

        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => 'Yetkisiz erişim.',
        ])->assertNotFound();
    }

    public function test_invalid_provider_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);

        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => 'Geçersiz sağlayıcı.',
            'provider' => 'does-not-exist',
        ])->assertStatus(422);
    }

    public function test_empty_transcript_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $transcript = $this->createTranscript($user);

        $this->postJson("/api/v1/transcripts/{$transcript->id}/summaries", [
            'transcript_text' => '   ',
        ])->assertStatus(422);
    }
}
