<?php

namespace App\Providers;

use App\Repositories\Contracts\SummaryRepositoryInterface;
use App\Repositories\Contracts\TranscriptRepositoryInterface;
use App\Repositories\SummaryRepository;
use App\Repositories\TranscriptRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        TranscriptRepositoryInterface::class => TranscriptRepository::class,
        SummaryRepositoryInterface::class => SummaryRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
