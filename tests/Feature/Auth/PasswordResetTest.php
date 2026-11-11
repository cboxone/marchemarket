<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reset_link_can_be_requested(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post('/mot-de-passe-oublie', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_does_not_error(): void
    {
        Notification::fake();

        $response = $this->post('/mot-de-passe-oublie', ['email' => 'inconnu@example.com']);

        // Volontairement le même message que pour un e-mail existant,
        // pour ne pas laisser deviner quels comptes existent.
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }

    public function test_the_password_can_be_reset_with_a_valid_token(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $token = Password::createToken($user);

        $response = $this->post('/reinitialiser-mot-de-passe', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->fresh()->password));
    }
}
