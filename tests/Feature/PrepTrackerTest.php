<?php

namespace Tests\Feature;

use App\Livewire\PrepTracker;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\Season2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrepTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Season2026Seeder::class);
    }

    private function makeUserWithPlannedEvent(): array
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

        return [$user, $item];
    }

    public function test_lists_planned_events_with_a_fresh_checklist(): void
    {
        [$user, $item] = $this->makeUserWithPlannedEvent();
        $this->actingAs($user);

        Livewire::test(PrepTracker::class)
            ->assertSee($item->tournament->name)
            ->assertSee('0/5 ready')
            ->assertSee('Mark registered');
    }

    public function test_toggle_registered_advances_status_and_stands_down_reminders(): void
    {
        [$user, $item] = $this->makeUserWithPlannedEvent();
        $this->actingAs($user);

        Livewire::test(PrepTracker::class)->call('toggleRegistered', $item->id);
        $this->assertSame('registered', $item->fresh()->status);

        // A registered item is no longer 'planned', so the reminder query skips it.
        Livewire::test(PrepTracker::class)->call('toggleRegistered', $item->id);
        $this->assertSame('planned', $item->fresh()->status);
    }

    public function test_set_field_updates_prep_and_validates(): void
    {
        [$user, $item] = $this->makeUserWithPlannedEvent();
        $this->actingAs($user);

        $component = Livewire::test(PrepTracker::class)
            ->call('setField', $item->id, 'travel_status', 'booked')
            ->call('setField', $item->id, 'coaching_status', 'none')
            ->call('setField', $item->id, 'paid', 'yes');

        $item->refresh();
        $this->assertSame('booked', $item->travel_status);
        $this->assertSame('none', $item->coaching_status);
        $this->assertSame('yes', $item->paid);

        // Bad field or value is ignored, not written.
        $component->call('setField', $item->id, 'coaching_status', 'bogus')
            ->call('setField', $item->id, 'status', 'attended');
        $item->refresh();
        $this->assertSame('none', $item->coaching_status);
        $this->assertSame('planned', $item->status); // status isn't a settable field here
    }

    public function test_set_note_saves_and_clears_the_personal_note(): void
    {
        [$user, $item] = $this->makeUserWithPlannedEvent();
        $this->actingAs($user);

        $component = Livewire::test(PrepTracker::class)
            ->call('setNote', $item->id, '  Carpool with the Lees.  ');
        // Trimmed on save.
        $this->assertSame('Carpool with the Lees.', $item->fresh()->notes);

        // Blanking it back out clears to null rather than an empty string.
        $component->call('setNote', $item->id, '   ');
        $this->assertNull($item->fresh()->notes);
    }

    public function test_progress_counts_completed_milestones(): void
    {
        [, $item] = $this->makeUserWithPlannedEvent();

        $this->assertSame(['done' => 0, 'total' => 5], $item->prepProgress());

        $item->update(['status' => 'registered', 'paid' => 'yes', 'travel_status' => 'na', 'coaching_status' => 'arranged']);
        // registered + fees + travel(na) + coaching = 4; lodging still pending.
        $this->assertSame(['done' => 4, 'total' => 5], $item->fresh()->prepProgress());
    }

    public function test_mutations_cannot_touch_another_users_plan_item(): void
    {
        [$userA] = $this->makeUserWithPlannedEvent();
        [, $itemB] = $this->makeUserWithPlannedEvent();
        $this->actingAs($userA);

        // Acting as A, aim every mutation at B's plan item id.
        Livewire::test(PrepTracker::class)
            ->call('setField', $itemB->id, 'coaching_status', 'arranged')
            ->call('toggleRegistered', $itemB->id)
            ->call('setNote', $itemB->id, 'injected by another user');

        $itemB->refresh();
        $this->assertSame('undecided', $itemB->coaching_status);
        $this->assertSame('planned', $itemB->status);
        $this->assertNull($itemB->notes);
    }

    public function test_skipped_events_are_not_shown(): void
    {
        [$user, $item] = $this->makeUserWithPlannedEvent();
        $item->update(['status' => 'skipped']);
        $this->actingAs($user);

        Livewire::test(PrepTracker::class)
            ->assertDontSee($item->tournament->name)
            ->assertSee('No events in your plan yet');
    }
}
