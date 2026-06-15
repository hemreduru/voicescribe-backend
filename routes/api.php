<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\LocalModelController;
use App\Http\Controllers\Api\V1\SummarizationController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TranscriptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| VoiceScribe API v1 routes.
| All routes are prefixed with /api/v1/
|
*/

Route::prefix('v1')->group(function () {
    // Health check (public)
    Route::get('/health', HealthController::class)->name('api.v1.health');

    // Authentication (public) — throttled against brute force.
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    });

    // Authenticated routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

        // Transcripts
        Route::apiResource('transcripts', TranscriptController::class);

        // Summarization — throttled (synchronous LLM call, protects provider quota).
        Route::post('/transcripts/{id}/summaries', [SummarizationController::class, 'summarize'])
            ->middleware('throttle:llm')
            ->name('api.v1.transcripts.summarize');

        // On-device summary model download config (URL + gated-model token)
        Route::get('/local-summary-model', [LocalModelController::class, 'summaryModel'])
            ->name('api.v1.local-summary-model');

        // AI chat over the user's own transcripts (RAG) + persisted history
        Route::get('/chat/sessions', [ChatController::class, 'index'])->name('api.v1.chat.sessions');
        Route::get('/chat/sessions/{id}', [ChatController::class, 'show'])->name('api.v1.chat.session');
        Route::delete('/chat/sessions/{id}', [ChatController::class, 'destroy'])->name('api.v1.chat.session.delete');
        Route::post('/chat/messages', [ChatController::class, 'sendMessage'])
            ->middleware('throttle:llm')
            ->name('api.v1.chat.send');
        // Streaming (SSE) variant of the above; the client falls back to the
        // buffered endpoint when streaming isn't available.
        Route::post('/chat/messages/stream', [ChatController::class, 'streamMessage'])
            ->middleware('throttle:llm')
            ->name('api.v1.chat.stream');

        // Sync
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
            Route::post('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
            Route::get('/status', [SyncController::class, 'status'])->name('api.v1.sync.status');
        });
    });
});
