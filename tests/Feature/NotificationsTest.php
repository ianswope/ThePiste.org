<?php

namespace Tests\Feature;

use App\Models\Season;
use App\Models\SeasonPlan;
use App\Models\Tournament;
use App\Models\User;
use App\Notifications\NewEventsDigest;
use App\Notifications\RegistrationReminderDigest;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->season = Season::create([
            'name' => '2026-27', 'slug' => '2026-27',
            'starts_on' => now()->subMonth(), 'ends_on' => now()->addMonths(10),
            'is_active' => true,
        ]);
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

    private function makeTournament(array $attrs = []): Tournament
    {
        $starts = $attrs['starts_on'] ?? now()->addDays(60);
        $name = $attrs['name'] ?? 'Windy City RJCC';

        return Tournament::create(array_merge([
            'season_id' => $this->season->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.$starts->toDateString(),
            'starts_on' => $starts,
            'ends_on' => $starts,
            'city' => 'Chicago', 'state' => 'IL', 'region' => 'R2',
            'lat' => 41.878, 'lng' => -87.629,
            'level' => 'regional',
            'circuits' => ['RJCC'],
            'contested_events' => ['JNR', 'CDT', 'D1A'],
        ], $attrs));
    }

    public function test_new_event_digest_goes_to_matching_users_once(): void
    {
        $user = $this->makeUser();
        $event = $this->makeTournament();

        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        Notification::assertSentTo($user, NewEventsDigest::class, function (NewEventsDigest $n) use ($event) {
            $rows = $n->groups[0]['rows'];

            return count($n->groups) === 1
                && $n->groups[0]['fencer']->name === 'Kid'
                && collect($rows)->contains(fn ($r) => $r['tournament']->is($event));
        });
        $this->assertNotNull($event->fresh()->alerted_at);

        // A second run finds nothing new.
        $this->artisan('thepiste:notify-new-events')->assertSuccessful();
        Notification::assertSentToTimes($user, NewEventsDigest::class, 1);
    }

    public function test_irrelevant_events_are_marked_but_not_emailed(): void
    {
        $this->makeUser();
        $event = $this->makeTournament([
            'name' => 'Bakersfield Y10 Open',
            'city' => 'Bakersfield', 'state' => 'CA', 'region' => 'R4',
            'lat' => 35.373, 'lng' => -119.018,
            'circuits' => null, 'contested_events' => ['Y10'],
        ]);

        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNotNull($event->fresh()->alerted_at);
    }

    public function test_already_alerted_and_past_events_are_skipped(): void
    {
        $user = $this->makeUser();
        $this->makeTournament(['alerted_at' => now()]);
        $this->makeTournament(['name' => 'Last Month RJCC', 'starts_on' => now()->subDays(10)]);

        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        Notification::assertNothingSentTo($user);
    }

    public function test_registration_reminder_for_events_inside_their_window(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $plan = SeasonPlan::create(['fencer_id' => $fencer->id, 'season_id' => $this->season->id]);

        $soon = $this->makeTournament([
            'name' => 'Next Week RJCC',
            'starts_on' => now()->addDays(10),
            'source_url' => 'https://www.askfred.net/tournaments/abc',
        ]);
        $farRegional = $this->makeTournament(['name' => 'Far Off RJCC', 'starts_on' => now()->addDays(30)]);
        $farNac = $this->makeTournament([
            'name' => 'December NAC', 'starts_on' => now()->addDays(30),
            'is_nac' => true, 'level' => 'national', 'region' => 'NATIONAL',
        ]);

        $dueItem = $plan->items()->create(['tournament_id' => $soon->id]);
        $notDueItem = $plan->items()->create(['tournament_id' => $farRegional->id]);
        $nacItem = $plan->items()->create(['tournament_id' => $farNac->id]);

        $this->artisan('thepiste:send-registration-reminders')->assertSuccessful();

        // One digest: the 10-day regional and the 30-day NAC (6-week lead),
        // but not the 30-day regional (2-week lead).
        Notification::assertSentTo($user, RegistrationReminderDigest::class, function (RegistrationReminderDigest $n) {
            $names = collect($n->groups[0]['items'])->map(fn ($i) => $i->tournament->name);

            return $names->contains('Next Week RJCC')
                && $names->contains('December NAC')
                && ! $names->contains('Far Off RJCC');
        });
        Notification::assertSentToTimes($user, RegistrationReminderDigest::class, 1);

        $this->assertNotNull($dueItem->fresh()->reminded_at);
        $this->assertNotNull($nacItem->fresh()->reminded_at);
        $this->assertNull($notDueItem->fresh()->reminded_at);

        // Reminded items are not nudged again.
        $this->artisan('thepiste:send-registration-reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, RegistrationReminderDigest::class, 1);
    }

    public function test_no_reminder_for_registered_or_skipped_items(): void
    {
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $plan = SeasonPlan::create(['fencer_id' => $fencer->id, 'season_id' => $this->season->id]);

        $soon = $this->makeTournament(['starts_on' => now()->addDays(5)]);
        $plan->items()->create(['tournament_id' => $soon->id, 'status' => 'registered']);

        $this->artisan('thepiste:send-registration-reminders')->assertSuccessful();

        Notification::assertNothingSentTo($user);
    }

    public function test_new_event_alerts_are_scoped_to_the_active_season(): void
    {
        $user = $this->makeUser();

        // A future season's catalog imported early (e.g. before is_active flips).
        $next = Season::create([
            'name' => '2027-28', 'slug' => '2027-28',
            'starts_on' => now()->addYear(), 'ends_on' => now()->addYear()->addMonths(10),
            'is_active' => false,
        ]);
        $offSeason = $this->makeTournament([
            'name' => 'Next Season RJCC', 'starts_on' => now()->addYear()->addMonth(),
        ]);
        $offSeason->update(['season_id' => $next->id]);

        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        // The off-season event must not be alerted (the builder wouldn't list it)...
        Notification::assertNothingSentTo($user);
        $this->assertNull($offSeason->fresh()->alerted_at);
    }

    public function test_digest_subject_counts_distinct_tournaments_not_per_fencer_rows(): void
    {
        $user = $this->makeUser();
        // A second fencer on the same account, eligible for the same events.
        $sib = $user->fencers()->create([
            'name' => 'Sib', 'weapon' => 'foil', 'age_group' => 'Cadet',
            'rating' => 'D', 'drive_radius_miles' => 450,
            'home_lat' => 41.808, 'home_lng' => -88.011, 'home_state' => 'IL',
        ]);
        $sib->weapons()->create(['weapon' => 'foil', 'rating' => 'D', 'is_primary' => true]);

        $this->makeTournament(); // one new event, relevant to both fencers

        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        Notification::assertSentTo($user, NewEventsDigest::class, function (NewEventsDigest $n) use ($user) {
            // Two fencer groups, but one tournament — the subject must say "a", not "2".
            return count($n->groups) === 2
                && str_contains($n->toMail($user)->subject, 'A new tournament');
        });
    }

    public function test_location_has_no_dangling_comma_without_a_state(): void
    {
        $intl = $this->makeTournament([
            'name' => 'Paris World Cup', 'city' => 'Paris',
            'state' => null, 'country' => 'FR', 'region' => 'INTL',
        ]);
        $domestic = $this->makeTournament(['name' => 'Chicago RJCC', 'city' => 'Chicago']);

        $this->assertSame('Paris, FR', $intl->location());
        $this->assertSame('Chicago, IL', $domestic->location());
    }

    public function test_date_range_handles_single_same_month_and_cross_month(): void
    {
        $oneDay = $this->makeTournament(['starts_on' => Carbon::parse('2026-10-09'), 'ends_on' => '2026-10-09']);
        $sameMonth = $this->makeTournament(['starts_on' => Carbon::parse('2026-08-22'), 'ends_on' => '2026-08-23']);
        $crossMonth = $this->makeTournament(['starts_on' => Carbon::parse('2026-12-30'), 'ends_on' => '2027-01-02']);

        $this->assertSame('Oct 9', $oneDay->dateRange());
        $this->assertSame('Aug 22–23', $sameMonth->dateRange());
        $this->assertSame('Dec 30–Jan 2', $crossMonth->dateRange());
        $this->assertSame('Sat Aug 22–23', $sameMonth->dateRange(true));
    }

    public function test_queued_digests_survive_serialization(): void
    {
        // ShouldQueue means the payload (models nested in $groups) is serialized
        // to the queue and rebuilt in the worker — it must round-trip and render.
        $user = $this->makeUser();
        $fencer = $user->fencers()->first();
        $event = $this->makeTournament(['name' => 'Serialize Me RJCC']);

        $newEvents = new NewEventsDigest([
            ['fencer' => $fencer, 'rows' => [['tournament' => $event, 'note' => '120 mi drive']]],
        ]);

        $plan = SeasonPlan::create(['fencer_id' => $fencer->id, 'season_id' => $this->season->id]);
        $item = $plan->items()->create(['tournament_id' => $event->id])->load('tournament');
        $reminder = new RegistrationReminderDigest([
            ['fencer' => $fencer, 'items' => [$item]],
        ]);

        foreach ([$newEvents, $reminder] as $digest) {
            $restored = unserialize(serialize($digest));
            $this->assertStringContainsString('Serialize Me RJCC', $restored->toMail($user)->render());
        }
    }

    /** Replace the notifications dispatcher so notify() throws for chosen emails. */
    private function failNotificationsFor(array $emails): void
    {
        $mock = \Mockery::mock(Dispatcher::class);
        $mock->shouldReceive('send')->andReturnUsing(function ($notifiable, $instance) use ($emails) {
            if (in_array($notifiable->email, $emails, true)) {
                throw new \RuntimeException('mail down');
            }
        });
        $mock->shouldReceive('sendNow')->andReturnNull();
        $this->app->instance(ChannelManager::class, $mock);
        $this->app->instance(Dispatcher::class, $mock);
    }

    public function test_new_event_alert_survives_a_send_failure_and_does_not_mark(): void
    {
        $user = $this->makeUser();
        $event = $this->makeTournament();
        $this->failNotificationsFor([$user->email]);

        // The failing send must not abort the command...
        $this->artisan('thepiste:notify-new-events')->assertSuccessful();

        // ...and with every send failed the event stays unalerted, so the next
        // run retries instead of silently burying it.
        $this->assertNull($event->fresh()->alerted_at);
    }

    public function test_registration_reminder_failure_retries_only_the_failed_user(): void
    {
        $soon = $this->makeTournament(['name' => 'Next Week RJCC', 'starts_on' => now()->addDays(8)]);

        $failUser = $this->makeUser();
        $okUser = $this->makeUser();
        $failItem = SeasonPlan::create(['fencer_id' => $failUser->fencers()->first()->id, 'season_id' => $this->season->id])
            ->items()->create(['tournament_id' => $soon->id]);
        $okItem = SeasonPlan::create(['fencer_id' => $okUser->fencers()->first()->id, 'season_id' => $this->season->id])
            ->items()->create(['tournament_id' => $soon->id]);

        $this->failNotificationsFor([$failUser->email]);

        $this->artisan('thepiste:send-registration-reminders')->assertSuccessful();

        // The failed user's item is left unmarked (retried next run); the
        // successful user's item is marked so it is not nudged again.
        $this->assertNull($failItem->fresh()->reminded_at);
        $this->assertNotNull($okItem->fresh()->reminded_at);
    }
}
