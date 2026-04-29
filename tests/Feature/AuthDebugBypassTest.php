<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthDebugBypassTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_bypasses_supabase_in_debug_mode(): void
    {
        config(['app.debug' => true]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Debug User',
            'email' => 'debug-register@example.com',
            'password' => 'any-password',
            'password_confirmation' => 'any-password',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'debug-register@example.com');

        $this->assertNotNull(data_get($response->json(), 'data.session.accessToken'));
        $this->assertDatabaseHas('users', [
            'email' => 'debug-register@example.com',
        ]);
    }

    public function test_login_accepts_any_password_in_debug_mode_and_authenticates_me(): void
    {
        config(['app.debug' => true]);

        $user = User::factory()->create([
            'email' => 'debug-login@example.com',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'totally-wrong-password',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email);

        $accessToken = (string) data_get($login->json(), 'data.session.accessToken');
        $this->assertNotSame('', $accessToken);

        $me = $this->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
        ])->getJson('/api/v1/auth/me');

        $me
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', $user->email);
    }
}

