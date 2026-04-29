<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
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

    // Authentication (public)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('api.v1.auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    });

    // Authenticated routes
    Route::middleware('auth:supabase')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

        // Transcripts
        Route::apiResource('transcripts', TranscriptController::class);

        // Summarization (Phase 6)
        // Route::post('/summarize', [SummarizationController::class, 'summarize']);
        // Route::post('/summarize/chunk', [SummarizationController::class, 'summarizeChunk']);

        // Sync
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push'])->name('api.v1.sync.push');
            Route::post('/pull', [SyncController::class, 'pull'])->name('api.v1.sync.pull');
            Route::get('/status', [SyncController::class, 'status'])->name('api.v1.sync.status');
        });
    });
});
