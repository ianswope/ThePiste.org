<?php

namespace Tests\Feature;

use App\Livewire\ResultsTracker;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResultsTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function makeFencer(string $rating = 'D'): User
    {
        $user = User::factory()->create();
        $fencer = $user->fencers()->create([
            'name' => 'Kid', 'weapon' => 'foil', 'age_group' => 'Junior',
            'rating' => $rating, 'drive_radius_miles' => 450,
        ]);
        $fencer->weapons()->create(['weapon' => 'foil', 'rating' => $rating, 'is_primary' => true]);
        $fencer->goals()->create(['type' => 'rating', 'weapon' => 'foil', 'params' => ['target_rating' => 'B']]);

        return $user;
    }

    public function test_page_requires_auth_and_renders_for_a_fencer(): void
    {
        $this->get('/season/results')->assertRedirect('/login');

        $this->actingAs($this->makeFencer())
            ->get('/season/results')
            ->assertOk()
            ->assertSee('results');
    }

    public function test_logging_a_result_persists_and_counts_in_stats(): void
    {
        $user = $this->makeFencer();
        $this->actingAs($user);

        Livewire::test(ResultsTracker::class)
            ->set('event_name', 'Junior Women\'s Foil')
            ->set('category', 'JNR')
            ->set('weapon', 'foil')
            ->set('fenced_on', '2026-11-07')
            ->set('place', 3)
            ->set('field_size', 42)
            ->set('points', 12.5)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Junior Women')
            ->assertSee('12.5');

        $fencer = $user->fencers()->first();
        $this->assertSame(1, $fencer->results()->count());
        $this->assertTrue($fencer->results->first()->isPodium());
    }

    public function test_earning_a_rating_upgrades_the_weapon_and_headline_rating(): void
    {
        $user = $this->makeFencer('D');
        $this->actingAs($user);

        Livewire::test(ResultsTracker::class)
            ->set('event_name', 'Big ROC')
            ->set('weapon', 'foil')
            ->set('fenced_on', '2026-12-19')
            ->set('place', 1)
            ->set('rating_earned', 'C26')
            ->call('save')
            ->assertHasNoErrors();

        $fencer = $user->fencers()->with('weapons')->first();
        $this->assertSame('C26', $fencer->rating);
        $this->assertSame('C26', $fencer->weapons->firstWhere('weapon', 'foil')->rating);
    }

    public function test_a_lower_rating_does_not_downgrade(): void
    {
        $user = $this->makeFencer('C');
        $this->actingAs($user);

        Livewire::test(ResultsTracker::class)
            ->set('event_name', 'Small local')
            ->set('weapon', 'foil')
            ->set('fenced_on', '2026-10-03')
            ->set('place', 1)
            ->set('rating_earned', 'E26')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('C', $user->fencers()->first()->rating);
    }

    public function test_rating_progress_meter(): void
    {
        $user = $this->makeFencer('C');
        $fencer = $user->fencers()->first();

        // U(0) E(1) D(2) C(3) B(4): C toward B = 3/4
        $this->assertEqualsWithDelta(0.75, $fencer->ratingProgress(), 0.001);
        $this->assertSame('B', $fencer->targetRating());

        // No rating goal, no ladder target.
        $fencer->goals()->where('type', 'rating')->delete();
        $fencer->goals()->create(['type' => 'standing', 'weapon' => 'foil', 'params' => ['category' => null]]);
        $this->assertNull($fencer->fresh()->ratingProgress());
    }

    public function test_deleting_a_result_keeps_the_earned_rating(): void
    {
        $user = $this->makeFencer('D');
        $this->actingAs($user);

        $component = Livewire::test(ResultsTracker::class)
            ->set('event_name', 'Big ROC')
            ->set('weapon', 'foil')
            ->set('fenced_on', '2026-12-19')
            ->set('place', 1)
            ->set('rating_earned', 'C26')
            ->call('save');

        $fencer = $user->fencers()->first();
        $component->call('delete', $fencer->results()->first()->id);

        $this->assertSame(0, $fencer->results()->count());
        $this->assertSame('C26', $fencer->fresh()->rating); // ratings are earned, not revoked
    }
}
