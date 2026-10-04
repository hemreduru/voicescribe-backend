<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthControllerTest extends TestCase
{
    public function test_health_reports_status_without_leaking_internals(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'healthy')
            ->assertJsonMissingPath('data.php_version')
            ->assertJsonMissingPath('data.laravel_version')
            ->assertJsonMissingPath('data.environment')
            ->assertJsonMissingPath('data.version');

        $this->assertSame(['status'], array_keys($response->json('data')));
        $this->assertStringNotContainsString(PHP_VERSION, $response->getContent());
        $this->assertStringNotContainsString(app()->version(), $response->getContent());
    }
}
