<?php

return [
    'enabled' => (bool) env('SYNC_ENABLED', true),
    'max_batch_size' => (int) env('SYNC_MAX_BATCH', 50),
    'conflict_strategy' => env('SYNC_CONFLICT', 'server_wins'),
];
