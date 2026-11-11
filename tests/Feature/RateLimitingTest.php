<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_are_limited_to_ten_per_minute(): void
    {
        Mail::fake();
        Notification::fake();

        $user = User::factory()->create();
        $product = Product::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('messages.store', $product), ['body' => "Bonjour {$i}"])
                ->assertStatus(302);
        }

        $this->actingAs($user)->post(route('messages.store', $product), ['body' => 'Trop'])
            ->assertStatus(429);
    }

    public function test_the_message_limit_is_per_user(): void
    {
        Mail::fake();
        Notification::fake();

        $spammer = User::factory()->create();
        $other = User::factory()->create();
        $product = Product::factory()->create();

        for ($i = 0; $i < 11; $i++) {
            $this->actingAs($spammer)->post(route('messages.store', $product), ['body' => 'Spam']);
        }

        $this->actingAs($other)->post(route('messages.store', $product), ['body' => 'Bonjour'])
            ->assertStatus(302);
    }

    public function test_coupon_guessing_is_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => "TEST{$i}"])
                ->assertStatus(302);
        }

        $this->actingAs($user)->post(route('checkout.apply-coupon'), ['code' => 'TEST11'])
            ->assertStatus(429);
    }

    public function test_registration_is_limited_per_ip(): void
    {
        for ($i = 0; $i < 10; $i++) {
            // Données invalides : on ne teste que le compteur, pas l'inscription.
            $this->post('/inscription', [])->assertStatus(302);
        }

        $this->post('/inscription', [])->assertStatus(429);
    }

    public function test_password_reset_requests_are_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertStatus(429);
    }

    public function test_checkout_submissions_are_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->post('/commande', [])->assertStatus(302);
        }

        $this->actingAs($user)->post('/commande', [])->assertStatus(429);
    }

    public function test_payment_verification_is_limited(): void
    {
        $user = User::factory()->create();
        $order = \App\Models\Order::factory()->create(['buyer_id' => $user->id, 'payment_status' => 'paid', 'status' => 'paid']);

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'x'])->assertOk();
        }

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'x'])->assertStatus(429);
    }

    public function test_autocomplete_allows_normal_typing_but_not_unbounded_scraping(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson(route('products.autocomplete', ['q' => 'a']))->assertOk();
        }

        $this->getJson(route('products.autocomplete', ['q' => 'a']))->assertStatus(429);
    }
}
