<?php

use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Behind Traefik (Dokploy): trust the proxy so the app sees the real
        // client IP (X-Forwarded-For) — required for per-client rate limiting —
        // and detects https from X-Forwarded-Proto so generated URLs use https.
        // TRUSTED_PROXIES: comma-separated IPs/CIDRs, or "*" to trust every hop.
        // Default PRIVATE_SUBNETS trusts only proxies on private/loopback
        // addresses (the Traefik hop inside the Docker network) so a client on
        // a public IP cannot spoof X-Forwarded-For.
        $trusted = trim((string) env('TRUSTED_PROXIES', '')) ?: 'PRIVATE_SUBNETS';
        $middleware->trustProxies(
            at: $trusted === '*' ? '*' : array_map('trim', explode(',', $trusted)),
        );

        $middleware->api(prepend: [
            ForceJsonResponse::class,
        ]);

        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
