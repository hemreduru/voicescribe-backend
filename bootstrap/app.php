<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind Traefik (Dokploy): trust the proxy so the app sees the real
        // client IP (X-Forwarded-For) — required for per-client rate limiting —
        // and detects https from X-Forwarded-Proto so generated URLs use https.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
