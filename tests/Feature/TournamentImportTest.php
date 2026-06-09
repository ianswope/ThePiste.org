<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Season;
use App\Models\Tournament;
use App\Services\TournamentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class TournamentImportTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        $this->season = Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'is_active' => true,
        ]);
    }

    private function import(string $csv): array
    {
        return app(TournamentImporter::class)->importCsv($csv, $this->season);
    }

    public function test_imports_rows_with_explicit_coordinates(): void
    {
        $csv = implode("\n", [
            'name,starts_on,ends_on,city,state,region,is_nac,circuits,contested_events,host_club,curated_note,source_url,lat,lng',
            '"Cascade Clash ROC","2026-09-19","2026-09-20","Portland","OR","R1",,ROC|RJCC,JNR|CDT|D1A,,,"https://example.org",45.515,-122.679',
            '"Desert Duel SYC","2026-10-03","2026-10-04","Phoenix","AZ","R4",,SYC,CDT,,,,33.448,-112.074',
        ]);

        $summary = $this->import($csv);

        $this->assertSame(2, $summary['created']);
        $this->assertSame([], $summary['errors']);

        $t = Tournament::where('name', 'Cascade Clash ROC')->firstOrFail();
        $this->assertSame('R1', $t->region);
        $this->assertSame(['JNR', 'CDT', 'D1A'], $t->contested_events);
        $this->assertSame(['ROC', 'RJCC'], $t->circuits);
        $this->assertEqualsWithDelta(45.515, $t->lat, 0.001);
    }

    public function test_reimport_updates_in_place_instead_of_duplicating(): void
    {
        $csv = "name,starts_on,ends_on,city,state,region,contested_events,lat,lng\n"
            ."\"Cascade Clash ROC\",2026-09-19,2026-09-20,Portland,OR,R1,JNR,45.5,-122.6\n";
        $this->import($csv);

        $updated = "name,starts_on,ends_on,city,state,region,contested_events,lat,lng\n"
            ."\"Cascade Clash ROC\",2026-09-19,2026-09-21,Portland,OR,R1,JNR|CDT,45.5,-122.6\n";
        $summary = $this->import($updated);

        $this->assertSame(0, $summary['created']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, Tournament::where('name', 'Cascade Clash ROC')->count());
        $this->assertSame(['JNR', 'CDT'], Tournament::where('name', 'Cascade Clash ROC')->first()->contested_events);
    }

    public function test_geocodes_when_coordinates_missing(): void
    {
        Sleep::fake();

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '43.073', 'lon' => '-89.401'],
            ], 200),
        ]);

        $csv = "name,starts_on,ends_on,city,state,region,contested_events\n"
            ."\"Badger Open\",2026-11-07,2026-11-08,Madison,WI,R2,JNR|CDT\n";

        $summary = $this->import($csv);

        $this->assertSame(1, $summary['created']);
        $this->assertSame(1, $summary['geocoded']);
        $this->assertEqualsWithDelta(43.073, Tournament::where('name', 'Badger Open')->first()->lat, 0.001);

        // Cached: a second event in the same city must not re-hit the API.
        Http::fake(fn () => throw new \RuntimeException('should not be called'));
        $csv2 = "name,starts_on,ends_on,city,state,region,contested_events\n"
            ."\"Badger Open II\",2027-02-06,2027-02-07,Madison,WI,R2,JNR\n";
        $summary2 = $this->import($csv2);
        $this->assertSame(1, $summary2['created']);
    }

    public function test_bad_rows_are_reported_and_good_rows_still_import(): void
    {
        $csv = implode("\n", [
            'name,starts_on,ends_on,city,state,region,contested_events,lat,lng',
            '"Good Event",2026-09-19,2026-09-20,Portland,OR,R1,JNR,45.5,-122.6',
            '"No Dates Event",,2026-09-20,Portland,OR,R1,JNR,45.5,-122.6',
            '"Backwards Dates",2026-09-22,2026-09-20,Portland,OR,R1,JNR,45.5,-122.6',
            '"Salem Open",2026-09-26,2026-09-27,Salem,OR,R1,JNR,44.9,-123.0',
        ]);

        $summary = $this->import($csv);

        $this->assertSame(2, $summary['created']); // Good Event + Salem Open
        $this->assertCount(2, $summary['errors']);
        $this->assertStringContainsString('Line 3', $summary['errors'][0]);
        $this->assertStringContainsString('Line 4', $summary['errors'][1]);
    }

    public function test_host_club_must_exist(): void
    {
        Club::create(['name' => 'Real Club', 'slug' => 'real-club', 'region' => 'R1']);

        $csv = implode("\n", [
            'name,starts_on,ends_on,city,state,region,contested_events,host_club,lat,lng',
            '"Hosted Event",2026-09-19,2026-09-20,Portland,OR,R1,JNR,"Real Club",45.5,-122.6',
            '"Ghost Hosted",2026-10-19,2026-10-20,Portland,OR,R1,JNR,"Ghost Club",45.5,-122.6',
        ]);

        $summary = $this->import($csv);

        $this->assertSame(1, $summary['created']);
        $this->assertCount(1, $summary['errors']);
        $this->assertStringContainsString('Ghost Club', $summary['errors'][0]);
        $this->assertNotNull(Tournament::where('name', 'Hosted Event')->first()->host_club_id);
    }

    public function test_missing_required_columns_fail_fast(): void
    {
        $summary = $this->import("name,city\nFoo,Portland\n");

        $this->assertSame(0, $summary['created']);
        $this->assertStringContainsString('Missing required columns', $summary['errors'][0]);
    }
}
