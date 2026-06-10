<?php

namespace Tests\Feature;

use App\Livewire\SeasonBuilder;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeasonBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function makeFencer(): User
    {
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => 'C', 'drive_radius_miles' => 450,
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => 'C', 'is_primary' => true]);

        return $user;
    }

    public function test_builder_seeds_the_recommended_anchors(): void
    {
        $user = $this->makeFencer();
        $this->actingAs($user);

        Livewire::test(SeasonBuilder::class)
            ->assertSee('Build Kid')
            ->assertSee('Anchors');

        $plan = $user->fencers()->first()->seasonPlans()->first();

        // The four NACs are always anchors and get pre-selected.
        $this->assertGreaterThanOrEqual(4, $plan->items()->count());
        $nacIds = Tournament::where('is_nac', true)->pluck('id');
        $this->assertTrue($plan->items()->whereIn('tournament_id', $nacIds)->count() === 4);
    }

    public function test_toggle_adds_and_removes_an_event(): void
    {
        $user = $this->makeFencer();
        $this->actingAs($user);

        $airForce = Tournament::where('name', 'like', 'Air Force%')->firstOrFail();
        $plan = $user->fencers()->first()->seasonPlans()->firstOrCreate([
            'season_id' => Tournament::first()->season_id,
        ]);

        $component = Livewire::test(SeasonBuilder::class);

        // Air Force is a fly trip (not a pre-selected anchor).
        $this->assertFalse($plan->items()->where('tournament_id', $airForce->id)->exists());

        $component->call('toggle', $airForce->id);
        $this->assertTrue($plan->items()->where('tournament_id', $airForce->id)->exists());

        $component->call('toggle', $airForce->id);
        $this->assertFalse($plan->items()->where('tournament_id', $airForce->id)->exists());
    }

    public function test_inline_cost_is_ignored_once_event_is_itemized(): void
    {
        $user = $this->makeFencer();
        $this->actingAs($user);

        $nac = Tournament::where('name', 'October NAC')->firstOrFail();
        Livewire::test(SeasonBuilder::class); // seeds anchors (incl. the NAC)
        $item = $user->fencers()->first()->seasonPlans()->first()
            ->items()->where('tournament_id', $nac->id)->firstOrFail();

        // Itemize the trip on the budget side.
        $item->expenses()->create(['category' => 'fees', 'est_amount' => 300]);

        // The builder's inline ballpark must not overwrite the itemized trip.
        Livewire::test(SeasonBuilder::class)
            ->set("costs.{$nac->id}", 9999)
            ->assertSee('~$300');   // read-only itemized total, not an input

        $this->assertNull($item->fresh()->est_cost);
        $this->assertSame(300.0, $item->fresh()->load('expenses')->effectiveTotal());
    }

    public function test_builder_tally_excludes_skipped_items(): void
    {
        $user = $this->makeFencer();
        $this->actingAs($user);

        $nac = Tournament::where('name', 'October NAC')->firstOrFail();
        Livewire::test(SeasonBuilder::class)->set("costs.{$nac->id}", 1200);

        $plan = $user->fencers()->first()->seasonPlans()->first();
        $item = $plan->items()->where('tournament_id', $nac->id)->firstOrFail();

        // Counts while planned...
        Livewire::test(SeasonBuilder::class)->assertSee('1,200');

        // ...drops out of the Budget tile once skipped.
        $item->update(['status' => 'skipped']);
        $this->assertSame(0.0, $plan->fresh()->projectedTotal());
        Livewire::test(SeasonBuilder::class)->assertDontSee('1,200');
    }
}
