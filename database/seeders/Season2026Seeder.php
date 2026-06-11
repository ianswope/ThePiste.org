<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\Fencer;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Season2026Seeder extends Seeder
{
    /** City => [lat, lng] centroids for distance/drive-fly calculation. */
    private array $cities = [
        'Downers Grove, IL' => [41.808, -88.011],
        'Edison, NJ' => [40.518, -74.412],
        'Libertyville, IL' => [42.283, -87.953],
        'Danvers, MA' => [42.575, -70.930],
        'Dallas, TX' => [32.777, -96.797],
        'State College, PA' => [40.793, -77.860],
        'Air Force Academy, CO' => [38.997, -104.862],
        'Virginia Beach, VA' => [36.853, -75.978],
        'Waterford, MI' => [42.693, -83.412],
        'Myrtle Beach, SC' => [33.689, -78.886],
        'Evanston, IL' => [42.045, -87.688],
        'Suffern, NY' => [41.115, -74.149],
        'Richmond, VA' => [37.541, -77.436],
        'Orlando, FL' => [28.538, -81.379],
        'Rochester, NY' => [43.161, -77.611],
        'Suwanee, GA' => [34.052, -84.071],
        'Twinsburg, OH' => [41.313, -81.440],
        'Nashville, TN' => [36.163, -86.781],
        'Hillsborough, NJ' => [40.500, -74.640],
        'Grand Rapids, MI' => [42.963, -85.668],
        'New Haven, CT' => [41.308, -72.928],
        'Columbus, OH' => [39.961, -82.999],
        'Secaucus, NJ' => [40.789, -74.056],
        'Fredericksburg, VA' => [38.303, -77.461],
        'Newtown, CT' => [41.414, -73.304],
        'Norton, MA' => [41.966, -71.187],
        'Oklahoma City, OK' => [35.468, -97.516],
        'San Diego, CA' => [32.716, -117.161],
        'Jacksonville, FL' => [30.332, -81.656],
        'Liberty Township, OH' => [39.405, -84.446],
        'Providence, RI' => [41.824, -71.413],
        'Hartford, CT' => [41.764, -72.685],
        'Atlantic City, NJ' => [39.364, -74.423],
        'Carrollton, TX' => [32.954, -96.890],
        'Cincinnati, OH' => [39.103, -84.512],
        'Saint Paul, MN' => [44.954, -93.090],
        'Anaheim, CA' => [33.836, -117.914],
        'La Jolla, CA' => [32.848, -117.274],
        'Tampa, FL' => [27.951, -82.457],
    ];

    public function run(): void
    {
        $season = Season::updateOrCreate(
            ['slug' => '2026-27'],
            [
                'name' => '2026-27',
                'starts_on' => '2026-08-01',
                'ends_on' => '2027-05-31',
                'is_active' => true,
            ]
        );

        $fcc = Club::updateOrCreate(
            ['slug' => 'fencing-center-of-chicago'],
            [
                'name' => 'Fencing Center of Chicago (FCC)',
                'city' => 'Libertyville',
                'state' => 'IL',
                'region' => 'R2',
                'lat' => 42.283,
                'lng' => -87.953,
            ]
        );

        // Site admin login for the Filament /admin panel.
        User::updateOrCreate(
            ['email' => 'ian@promoeqp.com'],
            [
                'name' => 'Ian Swope',
                'role' => User::ROLE_SUPER_ADMIN,
                'password' => Hash::make('changeme-piste'),
            ]
        );

        // Demo fencer (Farren) — drives the default calendar preview until a visitor builds their own.
        $farren = Fencer::updateOrCreate(
            ['name' => 'Farren'],
            [
                'home_club_id' => $fcc->id,
                'gender' => 'women',
                'handedness' => 'right',
                'weapon' => 'foil',
                'age_group' => 'Junior',
                'rating' => 'C',
                'home_zip' => '60515',
                'home_lat' => 41.808,
                'home_lng' => -88.011,
                'drive_radius_miles' => 450,
            ]
        );
        $farren->weapons()->updateOrCreate(['weapon' => 'foil'], ['rating' => 'C', 'is_primary' => true]);
        $farren->goals()->updateOrCreate(
            ['type' => 'rating', 'weapon' => 'foil'],
            ['params' => ['target_rating' => 'B'], 'status' => 'active']
        );

        foreach ($this->events() as $e) {
            [$lat, $lng] = $this->cities["{$e['city']}, {$e['state']}"] ?? [null, null];

            Tournament::updateOrCreate(
                ['slug' => Str::slug($e['name']).'-'.$e['start']],
                [
                    'season_id' => $season->id,
                    'host_club_id' => ($e['fcc'] ?? false) ? $fcc->id : null,
                    'name' => $e['name'],
                    'starts_on' => $e['start'],
                    'ends_on' => $e['end'],
                    'city' => $e['city'],
                    'state' => $e['state'],
                    'region' => $e['region'],
                    'lat' => $lat,
                    'lng' => $lng,
                    'is_nac' => $e['nac'] ?? false,
                    'circuits' => $this->circuitsFrom($e['name']),
                    'contested_events' => $e['ev'],
                    // curated_note stays null: the events() notes below are
                    // Region-2/Chicago-relative planning rationale, not objective
                    // catalog facts. TierService::generatedNote() produces the
                    // per-fencer guidance; curated_note is reserved for genuinely
                    // objective marquee copy entered by an admin.
                    'curated_note' => null,
                ]
            );
        }
    }

    /**
     * The 2026-27 Region 2-centric calendar, ported from the planning prototype.
     * The 'note' on each event is design rationale (the original Chicago-fencer
     * planning logic), not persisted: it is subjective and home-relative, so it
     * is not written to curated_note. The engine generates objective per-fencer
     * notes instead.
     */
    /** Circuit designators from the event name (same tokens the AskFRED sync uses). */
    private function circuitsFrom(string $name): ?array
    {
        preg_match_all('/\b(ROC|RJCC|RYC|SYC|RCC|RPC)\b/i', $name, $m);
        $circuits = array_values(array_unique(array_map('strtoupper', $m[1])));

        return $circuits ?: null;
    }

    private function events(): array
    {
        return [
            ['name' => 'Trick or Retreat ROC/RJCC', 'start' => '2026-08-22', 'end' => '2026-08-23', 'city' => 'Edison', 'state' => 'NJ', 'region' => 'R3', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Long haul to NJ with no Region 2 benefit. Better NJ/NY options later in the year.'],

            ['name' => 'FCC Chicago RYC & RJCC', 'start' => '2026-09-04', 'end' => '2026-09-06', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'fcc' => true, 'ev' => ['JNR', 'CDT'], 'note' => 'Home club event. 35 min from home, season opener, zero travel cost.'],
            ['name' => 'Star Cup RYC/RJCC', 'start' => '2026-09-05', 'end' => '2026-09-06', 'city' => 'Danvers', 'state' => 'MA', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with the home opener. Flight required.'],
            ['name' => 'North Texas Roundup SYC/RJCC', 'start' => '2026-09-05', 'end' => '2026-09-07', 'city' => 'Dallas', 'state' => 'TX', 'region' => 'R5', 'ev' => ['JNR', 'CDT'], 'note' => 'Cross-regional, conflicts with the home opener.'],
            ['name' => 'Nittany Lion Cup RYC/RJCC', 'start' => '2026-09-12', 'end' => '2026-09-13', 'city' => 'State College', 'state' => 'PA', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => '~9 hrs and conflicts with Air Force the same weekend.'],
            ['name' => 'Air Force ROC (D1A/DV2/VET) + RJCC', 'start' => '2026-09-12', 'end' => '2026-09-13', 'city' => 'Air Force Academy', 'state' => 'CO', 'region' => 'R4', 'nac' => false, 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'A family favorite and a legitimate mini-vacation. D1A + DV2 + JNR + CDT in one weekend. Fly into COS or DEN.'],
            ['name' => 'Miracle Fencing RYC/RJCC', 'start' => '2026-09-12', 'end' => '2026-09-13', 'city' => 'Virginia Beach', 'state' => 'VA', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Same weekend as Air Force. Flight to VA Beach vs. Colorado is no contest.'],
            ['name' => 'Motor City SYC/RCC', 'start' => '2026-09-18', 'end' => '2026-09-20', 'city' => 'Waterford', 'state' => 'MI', 'region' => 'R2', 'ev' => ['CDT'], 'note' => '3.5 hrs from home. Region 2 event, easy pre-NAC tune-up. Pack the car.'],
            ['name' => 'Lotus Cup ROC/RJCC/RYC', 'start' => '2026-09-18', 'end' => '2026-09-20', 'city' => 'Myrtle Beach', 'state' => 'SC', 'region' => 'R6', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Multi-circuit card in one trip. Strong fall fly candidate; conflicts with Motor City.'],
            ['name' => 'Remenyik ROC & RJCC', 'start' => '2026-09-26', 'end' => '2026-09-27', 'city' => 'Evanston', 'state' => 'IL', 'region' => 'R2', 'ev' => ['JNR', 'CDT', 'D1A'], 'note' => '45 min from home. D1A + JNR + CDT in your own backyard. Free points every year.'],
            ['name' => 'Premier Challenge ROC/RJCC/RYC', 'start' => '2026-09-25', 'end' => '2026-09-27', 'city' => 'Suffern', 'state' => 'NY', 'region' => 'R3', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Conflicts with Remenyik at home. Flight to NY.'],
            ['name' => 'River City Regional Rumble', 'start' => '2026-09-25', 'end' => '2026-09-27', 'city' => 'Richmond', 'state' => 'VA', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with Remenyik. Long haul.'],

            ['name' => 'October NAC', 'start' => '2026-10-09', 'end' => '2026-10-12', 'city' => 'Orlando', 'state' => 'FL', 'region' => 'NATIONAL', 'nac' => true, 'ev' => ['D1A', 'JNR', 'CDT'], 'note' => 'First NAC of the season sets the national points baseline. Fly to MCO. No regional conflicts.'],
            ['name' => 'Ben Gutenberg Memorial SYC/RJCC', 'start' => '2026-10-02', 'end' => '2026-10-04', 'city' => 'Rochester', 'state' => 'NY', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => '~9 hrs but driveable. Good pre-NAC tune-up if a 3-day weekend works.'],
            ['name' => 'Peachtree RYC/RJCC', 'start' => '2026-10-03', 'end' => '2026-10-04', 'city' => 'Suwanee', 'state' => 'GA', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Out-of-region, flight required, same weekend as Rochester.'],
            ['name' => "Escrime d'Halloween RYC/RJCC", 'start' => '2026-10-24', 'end' => '2026-10-25', 'city' => 'Twinsburg', 'state' => 'OH', 'region' => 'R2', 'ev' => ['JNR', 'CDT'], 'note' => '5 hrs from home, Region 2 points. First regional after the October NAC. Fun Halloween road trip.'],
            ['name' => 'Morris Cup RYC/RJCC', 'start' => '2026-10-24', 'end' => '2026-10-25', 'city' => 'Suffern', 'state' => 'NY', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with Twinsburg. Flight to NY.'],
            ['name' => 'Nashville Challenge RYC/RJCC', 'start' => '2026-10-24', 'end' => '2026-10-25', 'city' => 'Nashville', 'state' => 'TN', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with Twinsburg. Out-of-region.'],
            ['name' => 'Ultimate Cup RJCC', 'start' => '2026-10-31', 'end' => '2026-11-01', 'city' => 'Hillsborough', 'state' => 'NJ', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Flight to NJ for JNR/CDT only over a busy weekend.'],

            ['name' => 'GRAFA Third Coast Cup RYC/RJCC/ROC', 'start' => '2026-11-06', 'end' => '2026-11-08', 'city' => 'Grand Rapids', 'state' => 'MI', 'region' => 'R2', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => '3 hrs from home. Four categories in one weekend. Highest points-per-dollar on the calendar. Do not miss.'],
            ['name' => 'Pizza RYC/RJCC', 'start' => '2026-11-14', 'end' => '2026-11-15', 'city' => 'New Haven', 'state' => 'CT', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Flight to CT for JNR/CDT only. Weak as a standalone trip.'],
            ['name' => 'Kiefer Meinhardt Cup SYC/RJCC', 'start' => '2026-11-14', 'end' => '2026-11-15', 'city' => 'Richmond', 'state' => 'VA', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Prestige event named after a top US foilist. Reasonable flight to Richmond if budget allows.'],
            ['name' => 'November NAC', 'start' => '2026-11-20', 'end' => '2026-11-23', 'city' => 'Columbus', 'state' => 'OH', 'region' => 'NATIONAL', 'nac' => true, 'ev' => ['D1A', 'JNR', 'CDT'], 'note' => 'A drive NAC. Columbus is ~5 hrs, no flight. Thanksgiving-week timing; one of the friendliest NACs of the year.'],
            ['name' => 'Cobra Challenge SYC/RCC', 'start' => '2026-11-27', 'end' => '2026-11-29', 'city' => 'Secaucus', 'state' => 'NJ', 'region' => 'R3', 'ev' => ['CDT'], 'note' => 'CDT-only, Thanksgiving weekend, flight to NJ.'],

            ['name' => 'FCC Chicago RYC & RJC', 'start' => '2026-12-11', 'end' => '2026-12-13', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'fcc' => true, 'ev' => ['JNR'], 'note' => 'Home club event. JNR points before the holiday break, 35 min away, zero cost.'],
            ['name' => 'The Southern ROC/RJCC/RYC', 'start' => '2026-12-11', 'end' => '2026-12-13', 'city' => 'Myrtle Beach', 'state' => 'SC', 'region' => 'R6', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Massive multi-circuit event. Worth a flight for the D1A/DV2 upside if points are tight.'],
            ['name' => 'Midwest ROC/RJCC', 'start' => '2026-12-19', 'end' => '2026-12-20', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'D1A + DV2 + JNR + CDT, 35 min away. Best pre-holiday points event of the year.'],
            ['name' => 'Fairfax Challenge ROC/RJCC/RYC', 'start' => '2026-12-18', 'end' => '2026-12-20', 'city' => 'Fredericksburg', 'state' => 'VA', 'region' => 'R6', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Conflicts with Midwest ROC at home.'],
            ['name' => 'Z1 RYC/RJCC', 'start' => '2026-12-12', 'end' => '2026-12-13', 'city' => 'Newtown', 'state' => 'CT', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with the home weekend.'],
            ['name' => 'Boston Fencing Club RYC/RJCC', 'start' => '2026-12-19', 'end' => '2026-12-20', 'city' => 'Norton', 'state' => 'MA', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with Midwest ROC. Long flight.'],

            ['name' => 'January NAC + Junior Olympics', 'start' => '2027-01-08', 'end' => '2027-01-11', 'city' => 'Oklahoma City', 'state' => 'OK', 'region' => 'NATIONAL', 'nac' => true, 'ev' => ['D1A', 'JNR', 'CDT'], 'note' => 'The biggest event of the season: January NAC and the Junior Olympics. JO drives recruiting and national ranking. Plan a full 4-day trip.'],
            ['name' => 'Elite Cup RYC/RJCC', 'start' => '2027-01-16', 'end' => '2027-01-18', 'city' => 'San Diego', 'state' => 'CA', 'region' => 'R4', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'One week after JO. Excellent points momentum if recovered; skip if she needs rest.'],
            ['name' => 'Wildcat SYC/RCC', 'start' => '2027-01-30', 'end' => '2027-01-31', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'ev' => ['CDT'], 'note' => 'Home event, CDT-only, 35 min away. Keeps regional presence consistent late in January.'],
            ['name' => 'Battle at the Beach 5', 'start' => '2027-01-30', 'end' => '2027-02-01', 'city' => 'Jacksonville', 'state' => 'FL', 'region' => 'R6', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Four categories, usually affordable flights to JAX. Conflicts with Wildcat.'],

            ['name' => 'Queen City Cup Y8/RYC/RJCC', 'start' => '2027-02-06', 'end' => '2027-02-07', 'city' => 'Liberty Township', 'state' => 'OH', 'region' => 'R2', 'ev' => ['JNR', 'CDT'], 'note' => '5.5 hrs from home, Region 2 points. Solid low-cost road trip.'],
            ['name' => 'Miles Chamley-Watson Cup RYC/ROC/RJCC', 'start' => '2027-02-05', 'end' => '2027-02-07', 'city' => 'Providence', 'state' => 'RI', 'region' => 'R3', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Prestige event, strong field. The East Coast trip to make if chasing national profile.'],
            ['name' => 'Motor City RJCC/RYC', 'start' => '2027-02-20', 'end' => '2027-02-21', 'city' => 'Waterford', 'state' => 'MI', 'region' => 'R2', 'ev' => ['JNR', 'CDT'], 'note' => '3.5 hrs, Region 2 points. Keep regional standing strong into the spring stretch.'],

            ['name' => 'GRAFA Third Coast Cup RYC/RJCC/ROC', 'start' => '2027-03-12', 'end' => '2027-03-14', 'city' => 'Grand Rapids', 'state' => 'MI', 'region' => 'R2', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Second GRAFA: same four-category card, 3 hrs from home. Critical spring points event.'],
            ['name' => 'American Challenge SYC/RJCC', 'start' => '2027-03-19', 'end' => '2027-03-21', 'city' => 'Hartford', 'state' => 'CT', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'CT is 15+ hrs for JNR/CDT only. Low value for the cost.'],
            ['name' => 'FCC Chicago ROC & RJC', 'start' => '2027-03-26', 'end' => '2027-03-28', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'fcc' => true, 'ev' => ['JNR', 'D1A', 'DV2'], 'note' => 'Home club event. D1A + DV2 + JNR, 35 min away. Last major home D1A before the April NAC.'],
            ['name' => 'Absolute Foundation RYC/RJCC', 'start' => '2027-03-26', 'end' => '2027-03-28', 'city' => 'Atlantic City', 'state' => 'NJ', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Conflicts with the home weekend. NJ flight for JNR/CDT only.'],
            ['name' => 'Wang Memorial RYC/RJCC/ROC', 'start' => '2027-03-27', 'end' => '2027-03-28', 'city' => 'Dallas', 'state' => 'TX', 'region' => 'R5', 'ev' => ['JNR', 'CDT', 'D1A', 'DV2'], 'note' => 'Conflicts with the home weekend. Dallas flight vs. a free home event.'],

            ['name' => 'RedStar Fencing RYC/RJCC/Y8', 'start' => '2027-04-03', 'end' => '2027-04-04', 'city' => 'Libertyville', 'state' => 'IL', 'region' => 'R2', 'ev' => ['JNR', 'CDT'], 'note' => 'Home event, 35 min away. Last regional push before the April NAC.'],
            ['name' => 'Fairfax Challenge SYC/RJCC', 'start' => '2027-04-09', 'end' => '2027-04-11', 'city' => 'Virginia Beach', 'state' => 'VA', 'region' => 'R6', 'ev' => ['JNR', 'CDT'], 'note' => 'Sandwiched between RedStar and the NAC. Too close to NAC week for a VA Beach flight.'],
            ['name' => 'April NAC', 'start' => '2027-04-24', 'end' => '2027-04-27', 'city' => 'Cincinnati', 'state' => 'OH', 'region' => 'NATIONAL', 'nac' => true, 'ev' => ['D1A', 'JNR', 'CDT'], 'note' => 'Season-closing NAC. Cincinnati is a ~5-hr drive, no flight. Dates likely Apr 24-27 (confirm on usafencing.org). A strong finish cements the B push.'],
            ['name' => 'American Challenge RJCC/RYC', 'start' => '2027-04-24', 'end' => '2027-04-25', 'city' => 'Suffern', 'state' => 'NY', 'region' => 'R3', 'ev' => ['JNR', 'CDT'], 'note' => 'Likely conflicts with the April NAC. NAC takes precedence.'],
            ['name' => 'The Gauntlet', 'start' => '2027-04-24', 'end' => '2027-04-25', 'city' => 'Tampa', 'state' => 'FL', 'region' => 'R6', 'ev' => ['CDT'], 'note' => 'Likely conflicts with the April NAC. CDT-only.'],

            ['name' => 'North Star RYC/RJCC/ADLTC', 'start' => '2027-05-01', 'end' => '2027-05-02', 'city' => 'Saint Paul', 'state' => 'MN', 'region' => 'R2', 'ev' => ['JNR', 'CDT'], 'note' => '6.5 hrs but driveable. Region 2 end-of-season event. Worth it for a last points push if the B is in reach.'],
            ['name' => 'Fortune SYC/RJCC', 'start' => '2027-04-30', 'end' => '2027-05-02', 'city' => 'Anaheim', 'state' => 'CA', 'region' => 'R4', 'ev' => ['JNR', 'CDT'], 'note' => 'Cross-regional, flight to CA, season finale.'],
            ['name' => 'Duel in Dallas RJCC/VET/RPC', 'start' => '2027-05-01', 'end' => '2027-05-02', 'city' => 'Carrollton', 'state' => 'TX', 'region' => 'R5', 'ev' => ['JNR', 'CDT'], 'note' => 'Cross-regional, Dallas flight, end of season.'],
            ['name' => 'Collegiate Cup', 'start' => '2027-05-08', 'end' => '2027-05-09', 'city' => 'La Jolla', 'state' => 'CA', 'region' => 'R4', 'ev' => ['JNR', 'CDT'], 'note' => 'Cross-regional CA flight. A future college-visit opportunity, not a points priority this year.'],
        ];
    }
}
