<?php

namespace Tests\Feature;

use App\Livewire\SeasonBuilder;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function makeUserWithPlan(): array
    {
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'goal' => 'earn_b', 'drive_radius_miles' => 450,
            'home_lat' => 41.808, 'home_lng' => -88.011,
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        $this->actingAs($user);
        Livewire::test(SeasonBuilder::class); // seeds anchors + share slug

        return [$user, $fencer->fresh(), $fencer->seasonPlans()->first()->fresh()];
    }

    public function test_builder_generates_a_share_slug(): void
    {
        [, , $plan] = $this->makeUserWithPlan();

        $this->assertNotNull($plan->share_slug);
        $this->assertGreaterThanOrEqual(20, strlen($plan->share_slug));
    }

    public function test_share_page_is_public_and_shows_only_plan_events(): void
    {
        [, , $plan] = $this->makeUserWithPlan();

        auth()->logout();

        $this->get("/p/{$plan->share_slug}")
            ->assertOk()
            ->assertSee('fencing season')
            ->assertSee('Kid')
            ->assertSee('October NAC')               // anchor, auto-planned
            ->assertDontSee('Trick or Retreat');     // skip-tier event, not in plan
    }

    public function test_bad_slug_404s(): void
    {
        $this->get('/p/not-a-real-slug')->assertNotFound();
    }

    public function test_ics_feed_has_one_vevent_per_plan_item_with_exclusive_end(): void
    {
        [, , $plan] = $this->makeUserWithPlan();
        auth()->logout();

        $ics = $this->get("/p/{$plan->share_slug}.ics")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')
            ->getContent();

        $this->assertSame($plan->items()->count(), substr_count($ics, 'BEGIN:VEVENT'));
        // October NAC is Oct 9-12 -> DTEND must be the 13th (exclusive).
        $this->assertStringContainsString('DTSTART;VALUE=DATE:20261009', $ics);
        $this->assertStringContainsString('DTEND;VALUE=DATE:20261013', $ics);
        $this->assertStringContainsString('SUMMARY:October NAC', $ics);
    }

    public function test_costs_persist_and_roll_up(): void
    {
        [$user, , $plan] = $this->makeUserWithPlan();

        $nac = Tournament::where('name', 'October NAC')->firstOrFail();

        Livewire::actingAs($user);
        Livewire::test(SeasonBuilder::class)
            ->set("costs.{$nac->id}", 1450)
            ->assertSee('1,450');

        $this->assertEquals(1450.0, $plan->items()->where('tournament_id', $nac->id)->value('est_cost'));

        // Share page shows the budget too.
        auth()->logout();
        $this->get("/p/{$plan->share_slug}")->assertSee('1,450');
    }

    public function test_builder_flags_clashes_when_both_sides_are_planned(): void
    {
        [$user] = $this->makeUserWithPlan();

        // Air Force + Nittany Lion share the Sep 12 weekend.
        $airForce = Tournament::where('name', 'like', 'Air Force%')->firstOrFail();
        $nittany = Tournament::where('name', 'like', 'Nittany%')->firstOrFail();

        Livewire::actingAs($user);
        Livewire::test(SeasonBuilder::class)
            ->call('toggle', $airForce->id)
            ->call('toggle', $nittany->id)
            ->assertSee('same weekend, pick one')
            ->assertSee('Clashes');
    }

    public function test_calendar_marks_in_plan_events(): void
    {
        [$user] = $this->makeUserWithPlan();

        $this->actingAs($user)->get('/season')
            ->assertOk()
            ->assertSee('✓ IN PLAN')
            ->assertSee('data-f="plan"', false);
    }
}
