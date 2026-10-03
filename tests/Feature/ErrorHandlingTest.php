<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_unhandled_exception_returns_generic_json_500_without_trace(): void
    {
        config(['app.debug' => false]);
        Route::middleware('api')->get('/api/v1/__boom', function () {
            throw new RuntimeException('secret-internal-detail');
        });

        $response = $this->getJson('/api/v1/__boom');

        $response->assertStatus(500)->assertJsonMissingPath('trace');
        $body = $response->getContent();
        $this->assertStringNotContainsString('secret-internal-detail', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
        $this->assertStringNotContainsString('vendor/laravel', $body);
        $this->assertStringNotContainsString(base_path(), $body);
    }
}
