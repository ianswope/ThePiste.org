<?php

namespace Tests\Feature;

use App\Livewire\BudgetTracker;
use App\Models\PlanItem;
use App\Models\SeasonPlan;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BudgetTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    /** @return array{0: User, 1: SeasonPlan, 2: PlanItem} */
    private function makePlanWithItem(): array
    {
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'drive_radius_miles' => 450,
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        $tournament = Tournament::orderBy('starts_on')->firstOrFail();
        $plan = $fencer->seasonPlans()->create(['season_id' => $tournament->season_id]);
        $item = $plan->items()->create(['tournament_id' => $tournament->id]);

        return [$user, $plan, $item];
    }

    public function test_estimates_and_actuals_are_separate_layers(): void
    {
        [$user, , $item] = $this->makePlanWithItem();
        $this->actingAs($user);

        Livewire::test(BudgetTracker::class)
            ->set("amounts.{$item->id}.fees", '200')
            ->set("amounts.{$item->id}.hotel", '350.50')
            ->call('setLayer', 'actual')
            ->set("amounts.{$item->id}.fees", '212.40');

        $item->refresh()->load('expenses');
        $this->assertSame(200.0, $item->expenses->firstWhere('category', 'fees')->est_amount);
        $this->assertSame(212.4, $item->expenses->firstWhere('category', 'fees')->actual_amount);
        $this->assertSame(350.5, $item->expenses->firstWhere('category', 'hotel')->est_amount);

        // Actuals replace estimates in the trip total; hotel still uses the estimate.
        $this->assertSame(562.9, $item->effectiveTotal());
    }

    public function test_clearing_both_layers_deletes_the_expense_row(): void
    {
        [$user, , $item] = $this->makePlanWithItem();
        $this->actingAs($user);

        Livewire::test(BudgetTracker::class)
            ->set("amounts.{$item->id}.travel", '40')
            ->set("amounts.{$item->id}.travel", '');

        $this->assertSame(0, $item->expenses()->count());
    }

    public function test_summary_rolls_up_like_the_spreadsheet(): void
    {
        [$user, $plan, $item] = $this->makePlanWithItem();
        $this->actingAs($user);

        $second = Tournament::orderBy('starts_on')->skip(1)->firstOrFail();
        $paidItem = $plan->items()->create(['tournament_id' => $second->id, 'paid' => 'yes']);
        $paidItem->expenses()->create(['category' => 'fees', 'est_amount' => 100]);

        $third = Tournament::orderBy('starts_on')->skip(2)->firstOrFail();
        $skipped = $plan->items()->create(['tournament_id' => $third->id, 'status' => 'skipped']);
        $skipped->expenses()->create(['category' => 'fees', 'est_amount' => 500]);

        $item->expenses()->create(['category' => 'fees', 'est_amount' => 150]);
        $item->expenses()->create(['category' => 'hotel', 'est_amount' => 250]);
        $plan->update(['budget' => 1000]);

        $summary = $plan->fresh()->load('items.expenses')->costSummary();

        $this->assertSame(500.0, $summary['projected']); // skipped 500 excluded
        $this->assertSame(100.0, $summary['paid']);
        $this->assertSame(400.0, $summary['to_pay']);
        $this->assertSame(250.0, $summary['avg']);
        $this->assertSame(250.0, $summary['by_category']['fees']);
        $this->assertSame(250.0, $summary['by_category']['hotel']);
        $this->assertSame(500.0, $summary['surplus']);
        $this->assertSame(3, $summary['total']);
    }

    public function test_status_and_paid_update_from_the_page(): void
    {
        [$user, , $item] = $this->makePlanWithItem();
        $this->actingAs($user);

        Livewire::test(BudgetTracker::class)
            ->set("statuses.{$item->id}", 'registered')
            ->set("paids.{$item->id}", 'partial');

        $item->refresh();
        $this->assertSame('registered', $item->status);
        $this->assertSame('partial', $item->paid);
    }

    public function test_legacy_ballpark_is_the_fallback_until_itemized(): void
    {
        [, , $item] = $this->makePlanWithItem();

        $item->update(['est_cost' => 900]);
        $this->assertSame(900.0, $item->load('expenses')->effectiveTotal());

        $item->expenses()->create(['category' => 'fees', 'est_amount' => 150]);
        $this->assertSame(150.0, $item->load('expenses')->effectiveTotal());
    }

    public function test_shared_plan_shows_effective_totals(): void
    {
        [, $plan, $item] = $this->makePlanWithItem();
        $plan->update(['share_slug' => str_repeat('a', 24)]);
        $item->expenses()->create(['category' => 'fees', 'est_amount' => 150, 'actual_amount' => 175]);

        $this->get('/p/'.str_repeat('a', 24))
            ->assertOk()
            ->assertSee('$175');
    }
}
