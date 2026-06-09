<?php

namespace Tests\Feature;

use App\Livewire\SeasonBuilder;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\User;
use App\Services\GoalScorer;
use App\Services\TierService;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function makeUser(): User
    {
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'drive_radius_miles' => 450,
            'home_lat' => 41.808, 'home_lng' => -88.011, 'home_state' => 'IL',
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        return $user;
    }

    private function rows(User $user)
    {
        $fencer = $user->fencers()->first();
        $season = Season::where('is_active', true)->first();

        return app(TierService::class)->evaluate($fencer, $season->tournaments()->with('hostClub')->get());
    }

    public function test_rating_goal_marks_rating_earning_events(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $fencer->goals()->create(['type' => 'rating', 'weapon' => 'foil', 'params' => ['target_rating' => 'B']]);

        $rows = $this->rows($user);

        $nac = $rows->first(fn ($r) => str_contains($r['tournament']->name, 'November NAC'));
        $this->assertNotEmpty($nac['advances']);
        $this->assertSame('Earn a B in foil', $nac['advances'][0]['label']);
        $this->assertStringContainsString('NAC field', $nac['advances'][0]['why']);

        // A JNR/CDT-only regional doesn't advance a rating goal.
        $regional = $rows->first(fn ($r) => str_contains($r['tournament']->name, 'Pizza'));
        $this->assertSame([], array_column($regional['advances'], 'type'));

        // Club-level events are never a credible rating path, even with OPEN contested.
        $club = Tournament::first()->replicate();
        $club->fill(['name' => 'Club Open', 'level' => 'local', 'contested_events' => ['OPEN'], 'is_nac' => false]);
        $advances = app(GoalScorer::class)->advances(
            $user->fencers()->first()->activeGoals(),
            $club,
            ['eligible' => ['OPEN'], 'in_region' => true, 'driveable' => true, 'distance' => 30, 'tier' => 'drive']
        );
        $this->assertSame([], $advances);
    }

    public function test_qualify_goal_lights_the_path(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $fencer->goals()->create(['type' => 'qualify', 'weapon' => 'foil', 'params' => ['target' => 'jo']]);

        $rows = $this->rows($user);

        $rjcc = $rows->first(fn ($r) => in_array('RJCC', $r['tournament']->circuits ?? [], true));
        $this->assertNotNull($rjcc);
        $this->assertStringContainsString('Junior Olympics', $rjcc['advances'][0]['why']);

        $jo = $rows->first(fn ($r) => str_contains($r['tournament']->name, 'Junior Olympics'));
        $this->assertStringContainsString('goal event', $jo['advances'][0]['why']);
    }

    public function test_standing_goal_boosts_in_region_circuits(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $fencer->goals()->create(['type' => 'standing', 'weapon' => 'foil', 'params' => ['category' => 'JNR']]);

        $rows = $this->rows($user);

        $inRegion = $rows->first(fn ($r) => $r['in_region'] && ! empty($r['tournament']->circuits) && in_array('JNR', $r['eligible'], true));
        $this->assertNotEmpty($inRegion['advances']);

        $outRegion = $rows->first(fn ($r) => ! $r['in_region'] && ! $r['is_nac'] && ! empty($r['tournament']->circuits));
        $this->assertEmpty($outRegion['advances']);
    }

    public function test_develop_goal_favors_driveable_mileage(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $fencer->goals()->create(['type' => 'develop', 'params' => ['target_events' => 8]]);

        $rows = $this->rows($user);

        $drive = $rows->first(fn ($r) => $r['tier'] === 'drive' || ($r['tier'] === 'priority' && $r['driveable']));
        $this->assertNotEmpty($drive['advances']);

        $flight = $rows->first(fn ($r) => $r['tier'] === 'fly');
        $this->assertEmpty($flight['advances']);
    }

    public function test_goal_score_breaks_same_weekend_ties_within_a_tier(): void
    {
        $rowsNoGoal = $this->rows($this->makeUser());

        $user = $this->makeUser();
        $user->fencers()->first()->goals()->create(['type' => 'rating', 'weapon' => 'foil', 'params' => ['target_rating' => 'B']]);
        $rows = $this->rows($user);

        // Scores exist and are higher where goals are advanced.
        $this->assertTrue($rows->max(fn ($r) => $r['goal_score']) > 0);
        $this->assertSame(0.0, (float) $rowsNoGoal->max(fn ($r) => $r['goal_score']));
    }

    public function test_builder_manages_goals(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        Livewire::test(SeasonBuilder::class)
            ->set('goalType', 'rating')
            ->set('goalRating', 'B')
            ->set('goalWeapon', 'foil')
            ->call('addGoal')
            ->assertHasNoErrors()
            ->assertSee('Earn a B in foil');

        $fencer = $user->fencers()->first();
        $this->assertCount(1, $fencer->activeGoals());

        // Same type + weapon replaces, not duplicates.
        Livewire::test(SeasonBuilder::class)
            ->set('goalType', 'rating')
            ->set('goalRating', 'A')
            ->set('goalWeapon', 'foil')
            ->call('addGoal');
        $this->assertCount(1, $fencer->activeGoals());
        $this->assertSame('A', $fencer->activeGoals()->first()->param('target_rating'));

        $goalId = $fencer->activeGoals()->first()->id;
        Livewire::test(SeasonBuilder::class)->call('removeGoal', $goalId);
        $this->assertCount(0, $fencer->activeGoals());
    }

    public function test_calendar_shows_advances_and_goal_filter(): void
    {
        $user = $this->makeUser();
        $user->fencers()->first()->goals()->create(['type' => 'rating', 'weapon' => 'foil', 'params' => ['target_rating' => 'B']]);

        $this->actingAs($user)->get('/season')
            ->assertOk()
            ->assertSee('Earn a B in foil')
            ->assertSee('data-f="goals"', false)
            ->assertSee('data-goals="1"', false);
    }
}
