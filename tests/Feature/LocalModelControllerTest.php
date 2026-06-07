<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocalModelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_model_config_for_authenticated_user(): void
    {
        config([
            'llm.local_model.url' => 'https://example.com/gemma3-1b-it-int4.task',
            'llm.local_model.auth_token' => 'hf_test_token',
            'llm.local_model.file_name' => 'gemma3-1b-it-int4.task',
            'llm.local_model.size_bytes' => 123456,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/local-summary-model');

        $response->assertOk();
        $this->assertSame(
            'https://example.com/gemma3-1b-it-int4.task',
            data_get($response->json(), 'data.url'),
        );
        $this->assertSame('hf_test_token', data_get($response->json(), 'data.token'));
        $this->assertSame('gemma3-1b-it-int4.task', data_get($response->json(), 'data.file_name'));
        $this->assertSame(123456, data_get($response->json(), 'data.size_bytes'));
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/local-summary-model')->assertUnauthorized();
    }
}
