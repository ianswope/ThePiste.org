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
}
