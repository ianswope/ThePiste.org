<?php

namespace App\Console\Concerns;

use App\Models\User;
use Illuminate\Notifications\Notification;

trait SendsDigests
{
    /**
     * Send one digest, swallowing a delivery failure so a single bad recipient
     * can't abort the run (and re-spam everyone already sent on the next pass).
     * Returns whether it went out; failures are logged and reported. Callers
     * decide what to mark based on the result.
     */
    protected function notifyOrLog(User $user, Notification $notification): bool
    {
        try {
            $user->notify($notification);

            return true;
        } catch (\Throwable $e) {
            $this->warn("  ! digest to {$user->email} failed: {$e->getMessage()}");
            logger()->error('Digest send failed', [
                'user_id' => $user->id,
                'notification' => $notification::class,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
