<?php

namespace Tests\Feature;

use App\Mcp\ThePisteServer;
use App\Mcp\Tools\GetPlan;
use App\Mcp\Tools\GetProgress;
use App\Mcp\Tools\GetSeasonOutlook;
use App\Mcp\Tools\ListFencers;
use App\Mcp\Tools\LogResult;
use App\Mcp\Tools\ManagePlan;
use App\Mcp\Tools\SetGoal;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpConnectorTest extends TestCase
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
            'rating' => 'C', 'goal' => 'earn_b', 'drive_radius_miles' => 450,
            'home_lat' => 41.808, 'home_lng' => -88.011,
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        return $user;
    }

    public function test_mcp_endpoint_requires_a_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping'])
            ->assertUnauthorized();
    }

    public function test_list_fencers_returns_profile(): void
    {
        ThePisteServer::actingAs($this->makeUser())
            ->tool(ListFencers::class)
            ->assertOk()
            ->assertSee('Kid')
            ->assertSee('Earn a B rating');
    }

    public function test_set_goal_updates_the_fencer(): void
    {
        $user = $this->makeUser();

        ThePisteServer::actingAs($user)
            ->tool(SetGoal::class, ['goal' => 'qualify_jo'])
            ->assertOk()
            ->assertSee('Qualify for Junior Olympics');

        $this->assertSame('qualify_jo', $user->fencers()->first()->goal);
    }

    public function test_outlook_returns_tiers_and_plan_membership(): void
    {
        ThePisteServer::actingAs($this->makeUser())
            ->tool(GetSeasonOutlook::class, ['tier' => 'nac'])
            ->assertOk()
            ->assertSee('October NAC')
            ->assertSee('non_negotiable');
    }

    public function test_manage_plan_adds_and_removes_by_name(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();

        ThePisteServer::actingAs($user)
            ->tool(ManagePlan::class, ['action' => 'add', 'tournament' => 'October NAC'])
            ->assertOk()
            ->assertSee('Added October NAC');

        $plan = $fencer->seasonPlans()->first();
        $nac = Tournament::where('name', 'October NAC')->first();
        $this->assertTrue($plan->items()->where('tournament_id', $nac->id)->exists());

        ThePisteServer::actingAs($user)
            ->tool(ManagePlan::class, ['action' => 'remove', 'tournament' => (string) $nac->id])
            ->assertOk()
            ->assertSee('Removed October NAC');

        $this->assertFalse($plan->items()->where('tournament_id', $nac->id)->exists());
    }

    public function test_manage_plan_reports_ambiguity(): void
    {
        // "NAC" matches several tournaments.
        ThePisteServer::actingAs($this->makeUser())
            ->tool(ManagePlan::class, ['action' => 'add', 'tournament' => 'NAC'])
            ->assertHasErrors();
    }

    public function test_log_result_records_and_upgrades_rating(): void
    {
        $user = $this->makeUser();

        ThePisteServer::actingAs($user)
            ->tool(LogResult::class, [
                'event_name' => "Junior Women's Foil",
                'fenced_on' => '2026-12-19',
                'place' => 1,
                'field_size' => 38,
                'rating_earned' => 'B26',
                'tournament' => 'Midwest ROC',
            ])
            ->assertOk()
            ->assertSee('finished 1/38')
            ->assertSee('rating updated to B26');

        $fencer = $user->fencers()->first();
        $this->assertSame('B26', $fencer->rating);
        $this->assertNotNull($fencer->results()->first()->tournament_id);
    }

    public function test_get_plan_and_progress_summaries(): void
    {
        $user = $this->makeUser();

        ThePisteServer::actingAs($user)
            ->tool(ManagePlan::class, ['action' => 'add', 'tournament' => 'October NAC']);

        ThePisteServer::actingAs($user)
            ->tool(GetPlan::class)
            ->assertOk()
            ->assertSee('October NAC')
            ->assertSee('tallies');

        ThePisteServer::actingAs($user)
            ->tool(GetProgress::class)
            ->assertOk()
            ->assertSee('target_rating')
            ->assertSee('season_stats');
    }
}
