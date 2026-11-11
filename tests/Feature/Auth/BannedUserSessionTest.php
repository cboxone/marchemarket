<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannedUserSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_banned_during_an_open_session_is_logged_out_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('orders.index'))->assertOk();

        // L'admin suspend le compte alors que la session est encore ouverte.
        $user->update(['is_banned' => true]);

        $this->actingAs($user->fresh())->get(route('orders.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_a_banned_user_is_also_logged_out_on_a_public_page(): void
    {
        $user = User::factory()->create(['is_banned' => true]);

        $this->actingAs($user)->get(route('home'))->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_banned_user_making_a_json_request_gets_a_403(): void
    {
        $user = User::factory()->create(['is_banned' => true]);

        $this->actingAs($user)->getJson(route('orders.index'))->assertForbidden();

        $this->assertGuest();
    }

    public function test_an_active_user_is_not_affected(): void
    {
        $user = User::factory()->create(['is_banned' => false]);

        $this->actingAs($user)->get(route('orders.index'))->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_guests_are_not_affected(): void
    {
        $this->get(route('home'))->assertOk();
    }
}
