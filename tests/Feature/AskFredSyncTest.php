<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\Tournament;
use App\Services\AskFredScraper;
use App\Services\TournamentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class AskFredSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return file_get_contents(base_path('tests/Fixtures/askfred-listing.html'));
    }

    public function test_parser_extracts_us_tournaments_and_skips_the_rest(): void
    {
        $parsed = app(AskFredScraper::class)->parseListing($this->fixture());

        $this->assertTrue($parsed['hasNext']);
        $this->assertCount(3, $parsed['rows']);
        $this->assertSame(1, $parsed['skipped']['non_us']);        // Richmond BC
        $this->assertSame(1, $parsed['skipped']['no_categories']); // Y8-only

        // Spelled-out state names ("Santa Clara, California 95054 US") are US events.
        $afm = $parsed['rows'][2];
        $this->assertSame('AFM RYC/RJCC/ROC Div1A/Div2/Vet', $afm['name']);
        $this->assertSame(['Santa Clara', 'CA'], [$afm['city'], $afm['state']]);
        $this->assertSame('R4', $afm['region']);
        $this->assertSame('regional', $afm['level']);
        $this->assertSame('2026-10-23', $afm['starts_on']);
        $this->assertSame('2026-10-26', $afm['ends_on']);

        // A division-level "Junior Olympic Qualifier" is NOT a national event,
        // and with no circuit designators it's a club-level (local) event.
        $qualifier = $parsed['rows'][1];
        $this->assertSame('CT Division Junior Olympic Qualifiers', $qualifier['name']);
        $this->assertSame('', $qualifier['is_nac']);
        $this->assertSame('R3', $qualifier['region']);
        $this->assertSame('local', $qualifier['level']);

        $row = $parsed['rows'][0];
        $this->assertSame('Badger Open ROC & RJCC', $row['name']);
        $this->assertSame('2026-11-07', $row['starts_on']);
        $this->assertSame('2026-11-08', $row['ends_on']); // gcal end date is exclusive
        $this->assertSame('Madison', $row['city']);
        $this->assertSame('WI', $row['state']);
        $this->assertSame('R2', $row['region']);
        $this->assertSame('ROC|RJCC', $row['circuits']);
        $this->assertSame('regional', $row['level']); // circuit designators = official
        $this->assertSame('JNR|CDT|Y12|OPEN', $row['contested_events']);
        $this->assertStringContainsString('aaaaaaaa-1111-2222-3333-444444444444', $row['source_url']);
    }

    public function test_sync_command_imports_into_the_active_season(): void
    {
        Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'is_active' => true,
        ]);

        // Page 2 has no cards -> loop also stops on missing rel=next.
        Http::fake([
            'www.askfred.net/tournaments?*page=1*' => Http::response($this->fixture()),
            'www.askfred.net/tournaments*' => Http::response('<html><body>empty</body></html>'),
            'nominatim.openstreetmap.org/*' => Http::response([['lat' => '43.073', 'lon' => '-89.401']]),
        ]);
        Sleep::fake();

        $this->artisan('thepiste:sync-askfred', ['--from' => '2026-08-01'])
            ->assertExitCode(0);

        $this->assertSame(3, Tournament::count());

        $t = Tournament::where('name', 'Badger Open ROC & RJCC')->firstOrFail();
        $this->assertSame('Badger Open ROC & RJCC', $t->name);
        $this->assertSame('R2', $t->region);
        $this->assertFalse($t->is_nac);
        $this->assertSame(['JNR', 'CDT', 'Y12', 'OPEN'], $t->contested_events);
        $this->assertEqualsWithDelta(43.073, $t->lat, 0.001); // geocoded
        $this->assertStringContainsString('askfred.net', $t->source_url);

        // Re-running must update in place, not duplicate.
        $this->artisan('thepiste:sync-askfred', ['--from' => '2026-08-01'])->assertExitCode(0);
        $this->assertSame(3, Tournament::count());
    }

    public function test_date_change_at_source_updates_in_place_instead_of_duplicating(): void
    {
        $season = Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'is_active' => true,
        ]);

        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '43.0', 'lon' => '-89.4']])]);
        Sleep::fake();

        $importer = app(TournamentImporter::class);
        $geocoded = 0;

        $row = [
            'name' => 'Badger Open', 'starts_on' => '2026-11-07', 'ends_on' => '2026-11-08',
            'city' => 'Madison', 'state' => 'WI', 'region' => 'R2',
            'is_nac' => '', 'circuits' => '', 'contested_events' => 'JNR|CDT',
            'host_club' => '', 'curated_note' => '', 'source_url' => 'https://www.askfred.net/tournaments/uuid-1',
            'lat' => '43.0', 'lng' => '-89.4', 'external_id' => 'uuid-1',
        ];
        $this->assertSame('created', $importer->upsertRow($row, $season, $geocoded));

        // The event gets rescheduled at the source: same UUID, new dates.
        $row['starts_on'] = '2026-11-21';
        $row['ends_on'] = '2026-11-22';
        $this->assertSame('updated', $importer->upsertRow($row, $season, $geocoded));

        $this->assertSame(1, Tournament::count());
        $t = Tournament::firstOrFail();
        $this->assertSame('2026-11-21', $t->starts_on->toDateString());
        $this->assertStringContainsString('2026-11-21', $t->slug); // slug follows the new date
        $this->assertNotNull($t->last_seen_at);
    }

    public function test_full_sweep_reports_vanished_events(): void
    {
        $season = Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'is_active' => true,
        ]);

        // A synced upcoming event that AskFRED no longer lists, unseen for 5 days.
        Tournament::create([
            'season_id' => $season->id, 'name' => 'Ghost Open', 'slug' => 'ghost-open-2026-12-05',
            'external_id' => 'uuid-ghost', 'starts_on' => '2026-12-05', 'ends_on' => '2026-12-06',
            'city' => 'Peoria', 'state' => 'IL', 'region' => 'R2',
            'contested_events' => ['JNR'], 'last_seen_at' => now()->subDays(5),
        ]);

        Http::fake([
            'www.askfred.net/*' => Http::response('<html><body>no cards</body></html>'),
        ]);
        Sleep::fake();

        $this->artisan('thepiste:sync-askfred', ['--full' => true])
            ->expectsOutputToContain('Ghost Open')
            ->expectsOutputToContain('no longer appear on AskFRED')
            ->assertExitCode(0);

        // Flagged, not deleted.
        $this->assertSame(1, Tournament::count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => '2026-08-01', 'ends_on' => '2027-05-31', 'is_active' => true,
        ]);

        Http::fake(['www.askfred.net/*' => Http::response($this->fixture())]);
        Sleep::fake();

        $this->artisan('thepiste:sync-askfred', ['--dry-run' => true, '--from' => '2026-08-01', '--max-pages' => 1])
            ->expectsOutputToContain('would import')
            ->assertExitCode(0);

        $this->assertSame(0, Tournament::count());
    }
}
