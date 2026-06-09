<?php

namespace Tests\Feature;

use App\Models\Fencer;
use App\Models\Season;
use App\Models\Tournament;
use App\Services\TierService;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalEventTierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function fencer(): Fencer
    {
        // No home club on purpose: region must fall back to home_state.
        $f = Fencer::query()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'drive_radius_miles' => 450,
            'home_state' => 'IL',
            'home_lat' => 41.808, 'home_lng' => -88.011, // Downers Grove
        ]);
        $f->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        return $f;
    }

    private function makeLocal(string $name, float $lat, float $lng, string $region): Tournament
    {
        return Tournament::create([
            'season_id' => Season::first()->id,
            'name' => $name, 'slug' => Str::slug($name).'-test',
            'starts_on' => '2027-02-13', 'ends_on' => '2027-02-14',
            'city' => 'X', 'state' => 'IL', 'region' => $region, 'level' => 'local',
            'lat' => $lat, 'lng' => $lng,
            // 4 categories would normally rank "priority" in-region
            'contested_events' => ['JNR', 'CDT', 'D1A', 'DV2'],
        ]);
    }

    public function test_nearby_local_club_event_caps_at_drive_not_priority(): void
    {
        $this->makeLocal('NIFC Pierogi Piste', 41.6, -87.5, 'R2'); // ~30 mi, in-region

        $rows = app(TierService::class)->evaluate($this->fencer(), Tournament::all());
        $row = $rows->first(fn ($r) => $r['tournament']->name === 'NIFC Pierogi Piste');

        $this->assertSame('drive', $row['tier']);
        $this->assertFalse($row['non_negotiable']);
    }

    public function test_far_local_club_event_is_a_pass_not_a_fly_trip(): void
    {
        $this->makeLocal('Faraway Club Open', 33.4, -112.0, 'R4'); // Phoenix, 4 categories

        $rows = app(TierService::class)->evaluate($this->fencer(), Tournament::all());
        $row = $rows->first(fn ($r) => $r['tournament']->name === 'Faraway Club Open');

        $this->assertSame('skip', $row['tier']);
    }
}
