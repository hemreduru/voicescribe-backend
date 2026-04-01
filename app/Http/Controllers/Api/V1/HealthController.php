<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     */
    public function __invoke(): JsonResponse
    {
        return $this->successResponse(
            data: [
                'status' => 'healthy',
                'service' => 'VoiceScribe API',
                'version' => '1.0.0',
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
            ],
            message: 'Service is running',
        );
    }
}
