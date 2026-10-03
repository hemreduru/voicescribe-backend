<?php

namespace Tests\Feature;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Summary provider labeling on a migrate-only deploy (no db:seed).
 *
 * Deliberately does NOT seed LookupSeeder: RefreshDatabase runs the data-seeding
 * migrations only, mirroring a production `migrate`-only deploy. On-device
 * summarization was dropped, so the 'local' provider is removed by migration.
 */
class SyncSummaryProviderTest extends TestCase
{
    use RefreshDatabase;

    private const REMOVAL_MIGRATION = '2026_10_03_000001_remove_local_llm_provider.php';

    public function test_local_provider_is_removed_by_migrations(): void
    {
        $this->assertDatabaseMissing('llm_providers', ['key' => 'local']);
    }

    public function test_removal_migration_deletes_an_unreferenced_local_provider(): void
    {
        $this->insertLocalProvider();

        $this->removalMigration()->up();

        $this->assertDatabaseMissing('llm_providers', ['key' => 'local']);
    }

    public function test_removal_migration_keeps_local_provider_still_referenced_by_summaries(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/sync/push', $this->payloadWithSummary('cloud'))->assertOk();
        $localId = $this->insertLocalProvider();
        DB::table('summaries')->update(['provider_id' => $localId]);

        $this->removalMigration()->up();

        // summaries.provider_id is ON DELETE RESTRICT: the row stays, retired.
        $this->assertDatabaseHas('llm_providers', ['key' => 'local', 'is_active' => false]);
        $this->assertSame($localId, Summary::query()->firstOrFail()->provider_id);
    }

    public function test_removal_migration_down_restores_the_local_provider(): void
    {
        $this->removalMigration()->down();

        $this->assertDatabaseHas('llm_providers', ['key' => 'local', 'is_active' => true]);
    }

    public function test_pushed_cloud_summary_maps_to_default_provider(): void
    {
        config(['llm.default_provider' => 'gemini']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/push', $this->payloadWithSummary('cloud'))->assertOk();

        $summary = Summary::query()->firstOrFail();
        $this->assertSame('gemini', $summary->provider->key);
    }

    private function removalMigration(): Migration
    {
        return require database_path('migrations/'.self::REMOVAL_MIGRATION);
    }

    private function insertLocalProvider(): int
    {
        return DB::table('llm_providers')->insertGetId([
            'key' => 'local',
            'name_en' => 'On-device',
            'name_tr' => 'Cihazda',
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadWithSummary(string $providerKey): array
    {
        return [
            'transcripts' => [[
                'client_local_id' => 'tr-local-1',
                'local_id' => 'tr-local-1',
                'title' => 'Sprint Planning',
                'duration_seconds' => 120,
                'status_key' => 'completed',
                'recorded_at' => '2026-06-15T10:00:00Z',
                'updated_at' => '2026-06-15T10:02:00Z',
            ]],
            'summaries' => [[
                'client_local_id' => 'sum-local-1',
                'transcript_client_local_id' => 'tr-local-1',
                'provider_key' => $providerKey,
                'model' => 'gemini-2.5-flash',
                'summary_text' => 'Summary body.',
                'updated_at' => '2026-06-15T10:02:00Z',
            ]],
        ];
    }
}
