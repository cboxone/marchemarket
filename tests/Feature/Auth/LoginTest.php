<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/connexion', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('home'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/connexion', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_a_banned_user_cannot_log_in(): void
    {
        $user = User::factory()->banned()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/connexion', [
            'email' => $user->email,
            'password' => 'secret123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_admin_is_redirected_to_the_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

        $response = $this->post('/connexion', [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
    }

    public function test_repeated_failed_attempts_get_rate_limited(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        // La 6e tentative est bloquée avant même de vérifier le mot de
        // passe — y compris si, cette fois, on tape le bon.
        $response = $this->post('/connexion', ['email' => $user->email, 'password' => 'secret123']);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}
