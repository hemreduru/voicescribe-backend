<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Remove the 'local' (on-device) LLM provider seeded by
 * 2026_06_15_000001_seed_local_llm_provider: on-device summarization was
 * dropped, summaries are cloud-only.
 *
 * summaries.provider_id is ON DELETE RESTRICT (soft-deleted summaries included),
 * so a row that existing on-device summaries still point at cannot be deleted
 * without relabeling them. In that case it is only retired (is_active = false)
 * so this migration can never fail a production deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('llm_providers')->where('key', 'local')->value('id');

        if ($id === null) {
            return;
        }

        if (DB::table('summaries')->where('provider_id', $id)->exists()) {
            DB::table('llm_providers')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);

            return;
        }

        DB::table('llm_providers')->where('id', $id)->delete();
    }

    public function down(): void
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
};
