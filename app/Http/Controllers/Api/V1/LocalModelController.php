<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Serves the on-device summarization model download config (URL + gated-model
 * auth token) to authenticated clients, so the mobile app never bundles the
 * HuggingFace token in its binary.
 */
class LocalModelController extends Controller
{
    public function summaryModel(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $config = (array) config('llm.local_model');

        return $this->successResponse(
            data: [
                'url' => $config['url'] ?? null,
                'token' => $config['auth_token'] ?? null,
                'file_name' => $config['file_name'] ?? null,
                'size_bytes' => $config['size_bytes'] ?? null,
                'model_type' => $config['model_type'] ?? null,
                'file_type' => $config['file_type'] ?? null,
                'label' => $config['label'] ?? null,
            ],
            message: 'Local summary model config',
        );
    }
}
