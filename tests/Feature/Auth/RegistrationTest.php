<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_buyer_can_register(): void
    {
        Notification::fake();

        $response = $this->post('/inscription', [
            'name' => 'Amina Client',
            'email' => 'amina@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'customer',
            'terms' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('home'));

        $user = User::whereEmail('amina@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->isCustomer());

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_vendor_can_register_and_gets_a_vendor_profile(): void
    {
        $response = $this->post('/inscription', [
            'name' => 'Jean Fournisseur',
            'email' => 'jean@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'vendor',
            'store_name' => 'Jean Textiles',
            'terms' => '1',
        ]);

        $user = User::whereEmail('jean@example.com')->first();
        $this->assertTrue($user->isVendor());
        $this->assertDatabaseHas('vendors', ['user_id' => $user->id, 'store_name' => 'Jean Textiles']);
        $response->assertRedirect(route('vendor.dashboard'));
    }

    public function test_store_name_is_required_when_registering_as_a_vendor(): void
    {
        $response = $this->post('/inscription', [
            'name' => 'Sans Boutique',
            'email' => 'sansboutique@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'vendor',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('store_name');
        $this->assertGuest();
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'existe@example.com']);

        $response = $this->post('/inscription', [
            'name' => 'Doublon',
            'email' => 'existe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'customer',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_the_terms_must_be_accepted(): void
    {
        $response = $this->post('/inscription', [
            'name' => 'Sans CGU',
            'email' => 'sanscgu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'customer',
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertGuest();
    }
}
