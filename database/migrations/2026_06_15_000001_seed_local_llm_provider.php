<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the 'local' LLM provider.
 *
 * On-device summaries sync up tagged with provider_key 'local'. The earlier seed
 * migration (2026_06_06_000003) only inserted openai/claude/gemini, so on a
 * migrate-only deploy (no db:seed) the 'local' row is missing and
 * LlmProvider::resolveId('local') falls back to another provider — silently
 * mislabeling every on-device summary. On-device is the default summarization
 * path, so this guarantees `php artisan migrate` alone leaves a correct DB.
 *
 * Mirrors database/seeders/LookupSeeder.php. Idempotent (updateOrInsert), so it's
 * safe on databases that were already seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('llm_providers')->updateOrInsert(
            ['key' => 'local'],
            [
                'name_en' => 'On-device',
                'name_tr' => 'Cihazda',
                'sort_order' => 0,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        // No-op: required reference data. Removing it would re-introduce the
        // on-device summary mislabeling bug, so a rollback leaves it in place.
    }
};
