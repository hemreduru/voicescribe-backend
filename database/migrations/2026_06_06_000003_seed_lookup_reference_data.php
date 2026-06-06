<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the lookup/reference data that the API requires to function.
 *
 * transcript_statuses is *required*: SyncController maps an incoming
 * status_key to a status_id, and the transcripts table has a NOT NULL
 * status_id — so with this table empty, every pushed transcript fails to
 * insert and sync silently applies nothing. This data previously lived only in
 * LookupSeeder (db:seed), which is easy to skip on deploy and left production
 * unable to sync. Inserting it from a migration guarantees `php artisan
 * migrate` alone leaves the app in a working state. Idempotent (updateOrInsert),
 * so it's safe to run on databases that were already seeded.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $statuses = [
            ['key' => 'recording', 'name_en' => 'Recording', 'name_tr' => 'Kaydediliyor', 'sort_order' => 1],
            ['key' => 'processing', 'name_en' => 'Processing', 'name_tr' => 'İşleniyor', 'sort_order' => 2],
            ['key' => 'completed', 'name_en' => 'Completed', 'name_tr' => 'Tamamlandı', 'sort_order' => 3],
            ['key' => 'failed', 'name_en' => 'Failed', 'name_tr' => 'Başarısız', 'sort_order' => 4],
        ];
        foreach ($statuses as $status) {
            DB::table('transcript_statuses')->updateOrInsert(
                ['key' => $status['key']],
                array_merge($status, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
            );
        }

        $providers = [
            ['key' => 'openai', 'name_en' => 'OpenAI', 'name_tr' => 'OpenAI', 'sort_order' => 1],
            ['key' => 'claude', 'name_en' => 'Claude (Anthropic)', 'name_tr' => 'Claude (Anthropic)', 'sort_order' => 2],
            ['key' => 'gemini', 'name_en' => 'Gemini (Google)', 'name_tr' => 'Gemini (Google)', 'sort_order' => 3],
        ];
        foreach ($providers as $provider) {
            DB::table('llm_providers')->updateOrInsert(
                ['key' => $provider['key']],
                array_merge($provider, ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]),
            );
        }
    }

    public function down(): void
    {
        // No-op: this is required reference data. Removing it would break the
        // API (transcripts can't be inserted without a status_id), so a
        // rollback deliberately leaves it in place.
    }
};
