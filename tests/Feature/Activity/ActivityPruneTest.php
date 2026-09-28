<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het opruimen van het activiteitenlogboek.
 *
 * Twee kanten, en de tweede is de belangrijkste: een opruimtaak die te veel
 * weghaalt merk je pas wanneer je iets terugzoekt dat er had moeten staan.
 */
class ActivityPruneTest extends TestCase
{
    use RefreshDatabase;

    private function regel(int $dagenGeleden): ActivityEntry
    {
        $entry = ActivityEntry::query()->create([
            'user_id' => null,
            'actor_name' => 'Systeem',
            'action' => 'created',
            'subject_type' => User::class,
            'subject_id' => 1,
            'subject_label' => 'Test',
        ]);

        // Rechtstreeks bijwerken: created_at wordt door het model gezet.
        ActivityEntry::query()
            ->whereKey($entry->id)
            ->update(['created_at' => now()->subDays($dagenGeleden)]);

        return $entry;
    }

    public function test_old_entries_are_removed(): void
    {
        $oud = $this->regel(400);

        $this->artisan('activity:prune', ['--days' => 365])
            ->assertSuccessful();

        $this->assertDatabaseMissing('activity_entries', ['id' => $oud->id]);
    }

    public function test_recent_entries_are_kept(): void
    {
        $vers = $this->regel(10);

        $this->artisan('activity:prune', ['--days' => 365])
            ->assertSuccessful();

        $this->assertDatabaseHas('activity_entries', ['id' => $vers->id]);
    }

    public function test_a_retention_of_zero_days_is_refused(): void
    {
        // Nul dagen zou het hele logboek wissen. Dat is nooit de bedoeling
        // van een onderhoudstaak, dus het moet stuklopen en niet slagen.
        $vers = $this->regel(1);

        $this->artisan('activity:prune', ['--days' => 0])
            ->assertFailed();

        $this->assertDatabaseHas('activity_entries', ['id' => $vers->id]);
    }
}
