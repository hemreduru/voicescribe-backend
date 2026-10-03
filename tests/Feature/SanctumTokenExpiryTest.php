<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumTokenExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_expiration_defaults_to_thirty_days(): void
    {
        $this->assertSame(43200, config('sanctum.expiration'));
    }

    public function test_expired_token_pruning_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");

        $this->assertStringContainsString('sanctum:prune-expired', $commands);
    }

    public function test_valid_token_is_accepted_before_expiry(): void
    {
        config(['sanctum.expiration' => 60]);
        $token = User::factory()->create()->createToken('mobile')->plainTextToken;

        $this->travel(30)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_expired_token_gets_401(): void
    {
        config(['sanctum.expiration' => 60]);
        $token = User::factory()->create()->createToken('mobile')->plainTextToken;

        $this->travel(61)->minutes();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
