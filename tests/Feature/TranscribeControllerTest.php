<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TranscribeControllerTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'gsk_test_secret_key';

    private const URL = 'https://groq.test/openai/v1/audio/transcriptions';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'llm.providers.groq.api_key' => self::SECRET,
            'llm.providers.groq.base_url' => 'https://groq.test/openai/v1',
            'voicescribe.transcribe.model' => 'whisper-large-v3-turbo',
            'voicescribe.transcribe.max_size_kb' => 10240,
        ]);

        Sanctum::actingAs(User::factory()->create());
    }

    public function test_transcribe_returns_text_and_sends_expected_request_to_groq(): void
    {
        Http::fake([self::URL => Http::response(['text' => 'Merhaba dünya'], 200)]);

        $response = $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
            'language' => 'en',
            'prompt' => 'Sprint planning',
        ], ['Accept' => 'application/json']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.text', 'Merhaba dünya');

        Http::assertSent(function (Request $request): bool {
            $data = collect($request->data())->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

            return $request->url() === self::URL
                && $request->hasHeader('Authorization', 'Bearer '.self::SECRET)
                && $data['model'] === 'whisper-large-v3-turbo'
                && $data['language'] === 'en'
                && $data['response_format'] === 'json'
                && (string) $data['temperature'] === '0'
                && $data['prompt'] === 'Sprint planning'
                && $request->hasFile('file', null, 'audio.wav');
        });
    }

    public function test_language_defaults_to_turkish_and_prompt_is_omitted(): void
    {
        Http::fake([self::URL => Http::response(['text' => 'ok'], 200)]);

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.mp3', 100, 'audio/mpeg'),
        ], ['Accept' => 'application/json'])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $names = collect($request->data())->pluck('name', 'name');

            return collect($request->data())->firstWhere('name', 'language')['contents'] === 'tr'
                && ! $names->has('prompt');
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->app['auth']->forgetGuards();
        Http::fake();

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json'])->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_missing_audio_is_rejected(): void
    {
        Http::fake();

        $this->postJson('/api/v1/transcribe', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');

        Http::assertNothingSent();
    }

    public function test_unsupported_file_type_is_rejected(): void
    {
        Http::fake();

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');

        Http::assertNothingSent();
    }

    public function test_oversized_file_is_rejected(): void
    {
        Http::fake();

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('big.wav', 10241, 'audio/wav'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('audio');

        Http::assertNothingSent();
    }

    public function test_invalid_language_and_prompt_are_rejected(): void
    {
        Http::fake();

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
            'language' => 'turkish',
            'prompt' => str_repeat('a', 501),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['language', 'prompt']);

        Http::assertNothingSent();
    }

    public function test_groq_rate_limit_is_passed_through_with_retry_after(): void
    {
        Http::fake([self::URL => Http::response(['error' => ['message' => 'slow down']], 429, ['Retry-After' => '7'])]);

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json'])
            ->assertStatus(429)
            ->assertHeader('Retry-After', '7')
            ->assertJsonPath('success', false);
    }

    public function test_groq_server_error_becomes_generic_502_without_leaking_upstream_body(): void
    {
        Http::fake([self::URL => Http::response('upstream blew up, key '.self::SECRET, 500)]);

        $response = $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(502)->assertJsonPath('success', false);
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
        $this->assertStringNotContainsString('blew up', $response->getContent());
    }

    public function test_groq_response_without_text_becomes_502(): void
    {
        Http::fake([self::URL => Http::response(['unexpected' => true], 200)]);

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json'])->assertStatus(502);
    }

    public function test_missing_api_key_returns_503_without_calling_groq(): void
    {
        config(['llm.providers.groq.api_key' => null]);
        Http::fake();

        $response = $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(503)->assertJsonPath('success', false);
        Http::assertNothingSent();
    }

    public function test_successful_response_never_contains_the_api_key(): void
    {
        Http::fake([self::URL => Http::response(['text' => 'hello'], 200)]);

        $response = $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json']);

        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
    }

    public function test_transcribe_is_throttled_per_user(): void
    {
        config(['voicescribe.rate_limits.transcribe' => 2]);
        Http::fake([self::URL => Http::response(['text' => 'ok'], 200)]);

        for ($i = 0; $i < 2; $i++) {
            $this->post('/api/v1/transcribe', [
                'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
            ], ['Accept' => 'application/json'])->assertOk();
        }

        $this->post('/api/v1/transcribe', [
            'audio' => UploadedFile::fake()->create('chunk.wav', 100, 'audio/wav'),
        ], ['Accept' => 'application/json'])->assertStatus(429);
    }
}
