<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedTranscriptStatuses();
        $this->seedLlmProviders();
        $this->seedSyncActions();
        $this->seedSyncStatuses();
    }

    private function seedTranscriptStatuses(): void
    {
        $statuses = [
            ['key' => 'recording', 'name_en' => 'Recording', 'name_tr' => 'Kaydediliyor', 'sort_order' => 1],
            ['key' => 'processing', 'name_en' => 'Processing', 'name_tr' => 'İşleniyor', 'sort_order' => 2],
            ['key' => 'completed', 'name_en' => 'Completed', 'name_tr' => 'Tamamlandı', 'sort_order' => 3],
            ['key' => 'failed', 'name_en' => 'Failed', 'name_tr' => 'Başarısız', 'sort_order' => 4],
        ];

        foreach ($statuses as $status) {
            DB::table('transcript_statuses')->updateOrInsert(
                ['key' => $status['key']],
                array_merge($status, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }

    private function seedLlmProviders(): void
    {
        $providers = [
            ['key' => 'openai', 'name_en' => 'OpenAI', 'name_tr' => 'OpenAI', 'sort_order' => 1],
            ['key' => 'claude', 'name_en' => 'Claude (Anthropic)', 'name_tr' => 'Claude (Anthropic)', 'sort_order' => 2],
            ['key' => 'gemini', 'name_en' => 'Gemini (Google)', 'name_tr' => 'Gemini (Google)', 'sort_order' => 3],
        ];

        foreach ($providers as $provider) {
            DB::table('llm_providers')->updateOrInsert(
                ['key' => $provider['key']],
                array_merge($provider, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }

    private function seedSyncActions(): void
    {
        $actions = [
            ['key' => 'create', 'name_en' => 'Create', 'name_tr' => 'Oluştur', 'sort_order' => 1],
            ['key' => 'update', 'name_en' => 'Update', 'name_tr' => 'Güncelle', 'sort_order' => 2],
            ['key' => 'delete', 'name_en' => 'Delete', 'name_tr' => 'Sil', 'sort_order' => 3],
        ];

        foreach ($actions as $action) {
            DB::table('sync_actions')->updateOrInsert(
                ['key' => $action['key']],
                array_merge($action, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }

    private function seedSyncStatuses(): void
    {
        $statuses = [
            ['key' => 'pending', 'name_en' => 'Pending', 'name_tr' => 'Beklemede', 'sort_order' => 1],
            ['key' => 'completed', 'name_en' => 'Completed', 'name_tr' => 'Tamamlandı', 'sort_order' => 2],
            ['key' => 'failed', 'name_en' => 'Failed', 'name_tr' => 'Başarısız', 'sort_order' => 3],
            ['key' => 'conflict', 'name_en' => 'Conflict', 'name_tr' => 'Çakışma', 'sort_order' => 4],
        ];

        foreach ($statuses as $status) {
            DB::table('sync_statuses')->updateOrInsert(
                ['key' => $status['key']],
                array_merge($status, [
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
