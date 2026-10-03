<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/v1/health', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
    }

    public function test_no_origin_is_allowed_by_default(): void
    {
        $this->assertSame([], config('cors.allowed_origins'));
        $this->assertNull($this->preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_configured_origin_is_allowed_and_others_are_not(): void
    {
        config(['cors.allowed_origins' => ['https://app.example.com']]);

        $this->assertSame('https://app.example.com', $this->preflight('https://app.example.com')->headers->get('Access-Control-Allow-Origin'));
        // A single configured origin is echoed as-is, so a foreign origin never matches it.
        $this->assertNotSame('https://evil.example', $this->preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_native_clients_without_origin_are_unaffected(): void
    {
        $this->getJson('/api/v1/health')->assertOk();
    }
}
