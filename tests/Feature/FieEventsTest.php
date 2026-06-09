<?php

namespace Tests\Feature;

use App\Models\Fencer;
use App\Models\Season;
use App\Models\Tournament;
use App\Services\TierService;
use App\Services\TournamentImporter;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);

        Tournament::create([
            'season_id' => Season::first()->id,
            'name' => 'Cadet World Cup Udine',
            'slug' => 'cadet-world-cup-udine-2026-11-14',
            'starts_on' => '2026-11-14', 'ends_on' => '2026-11-15',
            'city' => 'Udine', 'state' => null, 'country' => 'IT',
            'region' => 'FIE', 'level' => 'fie_cadet',
            'contested_events' => ['CDT'],
        ]);
    }

    private function fencer(bool $includeFie)
    {
        $f = Fencer::query()->create([
            'name' => 'Intl Kid', 'weapon' => 'foil', 'age_group' => 'Cadet',
            'rating' => 'C', 'include_fie' => $includeFie, 'drive_radius_miles' => 450,
            'home_lat' => 41.808, 'home_lng' => -88.011,
        ]);
        $f->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        return $f;
    }

    public function test_fie_events_are_hidden_unless_opted_in(): void
    {
        $rows = app(TierService::class)->evaluate($this->fencer(false), Tournament::all());

        $this->assertNull($rows->first(fn ($r) => $r['tournament']->name === 'Cadet World Cup Udine'));
    }

    public function test_fie_events_show_as_fly_for_opted_in_fencers(): void
    {
        $rows = app(TierService::class)->evaluate($this->fencer(true), Tournament::all());

        $row = $rows->first(fn ($r) => $r['tournament']->name === 'Cadet World Cup Udine');
        $this->assertNotNull($row);
        $this->assertSame('fly', $row['tier']);
        $this->assertFalse($row['non_negotiable']);
        $this->assertSame(['CDT'], $row['eligible']);
    }

    public function test_importer_accepts_international_rows_without_state(): void
    {
        $csv = implode("\n", [
            'name,starts_on,ends_on,city,state,country,region,level,contested_events,lat,lng',
            '"Junior World Cup Leszno",2027-01-23,2027-01-24,Leszno,,PL,FIE,fie_junior,JNR,51.84,16.57',
        ]);

        $summary = app(TournamentImporter::class)->importCsv($csv, Season::first());

        $this->assertSame(1, $summary['created']);
        $this->assertSame([], $summary['errors']);

        $t = Tournament::where('name', 'Junior World Cup Leszno')->firstOrFail();
        $this->assertSame('PL', $t->country);
        $this->assertSame('fie_junior', $t->level);
        $this->assertNull($t->state);
    }
}
