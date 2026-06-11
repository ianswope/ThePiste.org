<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Friendly, on-brand password reset email (replaces Laravel's default).
        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expires = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Reset your ThePiste password')
                ->greeting('Hi '.($notifiable->name ?: 'there').',')
                ->line('Someone (hopefully you) asked to reset the password for your ThePiste account. Tap the button and pick a new one.')
                ->action('Choose a new password', $url)
                ->line("This link is good for {$expires} minutes.")
                ->line("Didn't ask for this? Just ignore it; your password stays as it is, and your season plan isn't going anywhere.")
                ->salutation('ThePiste');
        });
    }
}
