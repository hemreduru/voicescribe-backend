<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('api')->get('/api/v1/__ip', fn (Request $request) => response()->json(['ip' => $request->ip()]));
    }

    public function test_forwarded_for_is_honoured_from_a_private_network_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->getJson('/api/v1/__ip')
            ->assertJsonPath('ip', '203.0.113.9');
    }

    public function test_forwarded_for_is_ignored_from_a_public_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->getJson('/api/v1/__ip')
            ->assertJsonPath('ip', '198.51.100.7');
    }
}
