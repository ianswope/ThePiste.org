<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_email_is_friendly_and_branded(): void
    {
        Notification::fake();

        $user = User::factory()->create(['name' => 'Paul', 'email' => 'paul@example.com']);

        Password::sendResetLink(['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);

            $this->assertSame('Reset your ThePiste password', $mail->subject);
            $this->assertSame('Hi Paul,', $mail->greeting);
            $this->assertSame('Choose a new password', $mail->actionText);
            $this->assertStringContainsString($n->token, $mail->actionUrl);
            $this->assertStringContainsString('email=paul%40example.com', $mail->actionUrl);

            $html = (string) $mail->render();
            $this->assertStringContainsString('good for 60 minutes', $html);
            $this->assertStringContainsString('your password stays as it is', $html);

            return true;
        });
    }
}
