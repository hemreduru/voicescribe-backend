<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_per_ip(): void
    {
        config(['voicescribe.rate_limits.auth' => 3]);

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/login', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [])->assertStatus(429);
    }

    public function test_register_is_throttled_per_ip(): void
    {
        config(['voicescribe.rate_limits.auth' => 2]);

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/auth/register', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/register', [])->assertStatus(429);
    }

    public function test_chat_is_throttled_per_user(): void
    {
        config(['voicescribe.rate_limits.llm' => 2]);
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/chat/messages', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/chat/messages', [])->assertStatus(429);
    }

    public function test_summarize_is_throttled_per_user(): void
    {
        config(['voicescribe.rate_limits.llm' => 2]);
        Sanctum::actingAs(User::factory()->create());

        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/transcripts/999/summaries', [])->assertStatus(404);
        }

        $this->postJson('/api/v1/transcripts/999/summaries', [])->assertStatus(429);
    }
}
