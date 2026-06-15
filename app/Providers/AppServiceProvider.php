<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Register the named API rate limiters (see routes/api.php).
     */
    private function configureRateLimiting(): void
    {
        // Public auth endpoints: brute-force guard, keyed by IP.
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(
            (int) config('voicescribe.rate_limits.auth', 10)
        )->by($request->ip()));

        // Synchronous LLM endpoints (summarization + chat): quota/abuse guard,
        // keyed by authenticated user (falling back to IP).
        RateLimiter::for('llm', fn (Request $request) => Limit::perMinute(
            (int) config('voicescribe.rate_limits.llm', 20)
        )->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
