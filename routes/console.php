<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Drop expired personal access tokens (keeps the table small; expiry itself is
// enforced by Sanctum on every request via config/sanctum.php).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
