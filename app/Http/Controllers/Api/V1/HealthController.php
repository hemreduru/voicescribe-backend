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
            data: ['status' => 'healthy'],
            message: 'Service is running',
        );
    }
}
