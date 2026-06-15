<?php

namespace Tests\Feature;

use App\Models\Summary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression guard for the on-device summary mislabeling bug.
 *
 * Deliberately does NOT seed LookupSeeder: RefreshDatabase runs the data-seeding
 * migrations only, mirroring a production `migrate`-only deploy (no `db:seed`).
 * That is exactly the scenario where the bug lived — the 'local' provider row was
 * absent and SyncController's fallback relabeled on-device summaries as 'openai'.
 */
class SyncSummaryProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_local_provider_is_present_from_migrations_alone(): void
    {
        $this->assertDatabaseHas('llm_providers', ['key' => 'local']);
    }

    public function test_pushed_local_summary_is_labeled_local(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/sync/push', $this->payloadWithSummary('local'))->assertOk();

        $summary = Summary::query()->firstOrFail();
        $this->assertSame('local', $summary->provider->key, 'On-device summary was mislabeled.');
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
                'model' => $providerKey === 'local' ? 'local-default' : 'gemini-2.5-flash',
                'summary_text' => 'Summary body.',
                'updated_at' => '2026-06-15T10:02:00Z',
            ]],
        ];
    }
}
