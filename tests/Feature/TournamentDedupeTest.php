<?php

namespace Tests\Feature;

use App\Models\PlanItem;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TournamentImporter;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentDedupeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    /** The curated seed row for Air Force (no external id). */
    private function curatedAirForce(): Tournament
    {
        return Tournament::where('name', 'like', 'Air Force%')->firstOrFail();
    }

    /** An AskFRED-style row for the same real event, as the sync would build it. */
    private function syncedAirForceRow(): array
    {
        return [
            'name' => 'Air Force Academy ROC & RJCC', 'starts_on' => '2026-09-12', 'ends_on' => '2026-09-13',
            'city' => 'Colorado Springs', 'state' => 'CO', 'region' => 'R4', 'level' => 'regional',
            'is_nac' => '', 'circuits' => 'ROC|RJCC', 'contested_events' => 'JNR|CDT|D1A|DV2',
            'host_club' => '', 'curated_note' => '', 'source_url' => 'https://www.askfred.net/tournaments/uuid-af',
            'lat' => '38.99', 'lng' => '-104.86', 'external_id' => 'uuid-af',
        ];
    }

    public function test_sync_adopts_a_matching_curated_row_instead_of_duplicating(): void
    {
        $curated = $this->curatedAirForce();
        $originalNote = $curated->curated_note;
        $this->assertNotNull($originalNote);

        $geocoded = 0;
        $outcome = app(TournamentImporter::class)->upsertRow($this->syncedAirForceRow(), Season::first(), $geocoded);

        $this->assertSame('updated', $outcome);
        $this->assertSame(1, Tournament::where('name', 'like', '%Air Force%')->count());

        $merged = $this->curatedAirForce()->fresh();
        $this->assertSame('uuid-af', $merged->external_id);          // adopted source identity
        $this->assertSame($originalNote, $merged->curated_note);     // curation preserved
        $this->assertSame('Air Force Academy ROC & RJCC', $merged->name); // source name wins
    }

    public function test_distinct_events_same_weekend_and_state_are_not_merged(): void
    {
        $geocoded = 0;
        $row = $this->syncedAirForceRow();
        $row['name'] = 'Denver Open Foil Bash';
        $row['external_id'] = 'uuid-denver';
        $row['source_url'] = 'https://www.askfred.net/tournaments/uuid-denver';

        $outcome = app(TournamentImporter::class)->upsertRow($row, Season::first(), $geocoded);

        $this->assertSame('created', $outcome);
        $this->assertSame(1, Tournament::where('name', 'like', '%Air Force%')->count());
        $this->assertNotNull(Tournament::where('name', 'Denver Open Foil Bash')->first());
    }

    public function test_dedupe_command_merges_existing_duplicates_and_repoints_plans(): void
    {
        // Simulate the bug: the synced duplicate already exists.
        $curated = $this->curatedAirForce();
        $dupe = Tournament::create([
            'season_id' => Season::first()->id,
            'name' => 'Air Force Academy ROC & RJCC',
            'slug' => 'air-force-academy-roc-rjcc-2026-09-12',
            'external_id' => 'uuid-af', 'source_url' => 'https://www.askfred.net/tournaments/uuid-af',
            'starts_on' => '2026-09-12', 'ends_on' => '2026-09-13',
            'city' => 'Colorado Springs', 'state' => 'CO', 'region' => 'R4', 'level' => 'regional',
            'contested_events' => ['JNR', 'CDT', 'D1A', 'DV2'], 'last_seen_at' => now(),
        ]);

        // A user planned BOTH copies (the "clashes with itself" report).
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior', 'rating' => 'C', 'drive_radius_miles' => 450,
        ]);
        $plan = $fencer->seasonPlans()->create(['season_id' => Season::first()->id]);
        $plan->items()->create(['tournament_id' => $curated->id]);
        $plan->items()->create(['tournament_id' => $dupe->id, 'est_cost' => 900]);

        $this->artisan('thepiste:dedupe-tournaments', ['--dry-run' => true])
            ->expectsOutputToContain('merge:')
            ->assertExitCode(0);
        $this->assertSame(2, Tournament::where('name', 'like', '%Air Force%')->count()); // dry run wrote nothing

        $this->artisan('thepiste:dedupe-tournaments')->assertExitCode(0);

        $this->assertSame(1, Tournament::where('name', 'like', '%Air Force%')->count());
        $kept = $this->curatedAirForce()->fresh();
        $this->assertSame('uuid-af', $kept->external_id);
        $this->assertNotNull($kept->curated_note);

        // Plan now references the kept row exactly once.
        $this->assertSame(1, PlanItem::where('season_plan_id', $plan->id)->count());
        $this->assertSame($kept->id, (int) PlanItem::where('season_plan_id', $plan->id)->value('tournament_id'));
    }
}
