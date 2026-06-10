<?php

namespace App\Console\Commands;

use App\Models\PlanItem;
use App\Models\Tournament;
use App\Notifications\RegistrationReminderDigest;
use Illuminate\Console\Command;

class SendRegistrationReminders extends Command
{
    protected $signature = 'thepiste:send-registration-reminders
        {--dry-run : Report who would be emailed without sending or marking}';

    protected $description = 'Nudge users about planned events entering their registration window';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $due = PlanItem::whereNull('reminded_at')
            ->where('status', 'planned')
            ->whereHas('tournament', fn ($q) => $q->whereDate('starts_on', '>=', now()))
            ->with(['tournament', 'plan.fencer.user'])
            ->get()
            ->filter(fn (PlanItem $item) => $item->tournament->starts_on
                ->lte(now()->addDays($this->leadDays($item->tournament))->endOfDay()));

        if ($due->isEmpty()) {
            $this->info('No planned events inside their registration window.');

            return self::SUCCESS;
        }

        $sent = 0;
        foreach ($due->groupBy(fn (PlanItem $i) => $i->plan->fencer->user_id) as $items) {
            $user = $items->first()->plan->fencer->user;

            $groups = $items
                ->groupBy(fn (PlanItem $i) => $i->plan->fencer_id)
                ->map(fn ($g) => [
                    'fencer' => $g->first()->plan->fencer,
                    'items' => $g->sortBy(fn (PlanItem $i) => $i->tournament->starts_on)->values()->all(),
                ])
                ->values()
                ->all();

            if ($dry) {
                $names = $items->map(fn (PlanItem $i) => $i->tournament->name)->implode(' · ');
                $this->line("  would email {$user->email}: {$names}");
            } else {
                $user->notify(new RegistrationReminderDigest($groups));
            }
            $sent++;
        }

        if (! $dry) {
            PlanItem::whereIn('id', $due->pluck('id'))->update(['reminded_at' => now()]);
        }

        $this->info(sprintf('%s: %d plan item(s) due, %d digest(s) %s.',
            $dry ? 'Dry run' : 'Done', $due->count(), $sent, $dry ? 'would be sent' : 'sent'));

        return self::SUCCESS;
    }

    /** Nationals and internationals get the long lead; everything else the short one. */
    private function leadDays(Tournament $t): int
    {
        $leads = config('fencing.reminder_lead_days');

        return $t->is_nac || $t->level === 'national' || str_starts_with((string) $t->level, 'fie')
            ? $leads['national']
            : $leads['default'];
    }
}
