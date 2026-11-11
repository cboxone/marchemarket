<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Product;
use App\Models\QuoteRequest;
use App\Models\Review;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDataTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Export ----------

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('profile.export'))->assertRedirect(route('login'));
        $this->delete(route('profile.destroy'))->assertRedirect(route('login'));
    }

    public function test_the_export_is_a_downloadable_json_file_with_the_users_data(): void
    {
        $user = User::factory()->create(['name' => 'Jean Dupont']);
        Address::factory()->create(['user_id' => $user->id, 'city' => 'Cotonou']);
        $order = Order::factory()->create(['buyer_id' => $user->id, 'reference' => 'CMD-EXPORT1']);
        $order->items()->create(['vendor_id' => Vendor::factory()->create()->id, 'product_name' => 'Sac de riz', 'quantity' => 2, 'unit_price' => 5000]);

        $response = $this->actingAs($user)->get(route('profile.export'));

        $response->assertOk();
        $this->assertStringContainsString('application/json', $response->headers->get('content-type'));

        $data = json_decode($response->streamedContent(), true);
        $this->assertSame('Jean Dupont', $data['profile']['name']);
        $this->assertSame('Cotonou', $data['addresses'][0]['city']);
        $this->assertSame('CMD-EXPORT1', $data['orders'][0]['reference']);
        $this->assertSame('Sac de riz', $data['orders'][0]['items'][0]['product_name']);
    }

    public function test_the_export_only_contains_my_own_data(): void
    {
        $me = User::factory()->create();
        Order::factory()->create(['buyer_id' => $me->id, 'reference' => 'CMD-MINE0001']);
        Order::factory()->create(['reference' => 'CMD-SOMEONE1']); // autre acheteur

        $data = json_decode($this->actingAs($me)->get(route('profile.export'))->streamedContent(), true);

        $refs = array_column($data['orders'], 'reference');
        $this->assertContains('CMD-MINE0001', $refs);
        $this->assertNotContains('CMD-SOMEONE1', $refs);
    }

    public function test_a_vendor_export_includes_the_shop_and_payouts(): void
    {
        $vendor = Vendor::factory()->create(['store_name' => 'Boutique Test']);
        $vendor->payouts()->create(['amount' => 5000, 'status' => 'pending', 'payout_method' => 'MTN 90000000']);

        $data = json_decode($this->actingAs($vendor->user)->get(route('profile.export'))->streamedContent(), true);

        $this->assertSame('Boutique Test', $data['vendor_shop']['store_name']);
        $this->assertEqualsWithDelta(5000.0, $data['payouts'][0]['amount'], 0.01);
    }

    public function test_a_customer_export_has_no_vendor_section(): void
    {
        $user = User::factory()->create();

        $data = json_decode($this->actingAs($user)->get(route('profile.export'))->streamedContent(), true);

        $this->assertArrayNotHasKey('vendor_shop', $data);
        $this->assertArrayNotHasKey('payouts', $data);
    }

    // ---------- Suppression : garde-fous ----------

    public function test_a_vendor_cannot_delete_their_own_account(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionHasErrors('account');

        $this->assertSame($vendor->user->email, $vendor->user->fresh()->email);
    }

    public function test_an_account_with_a_pending_order_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['buyer_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionHasErrors('account');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_an_account_with_a_shipped_but_not_delivered_order_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['buyer_id' => $user->id, 'status' => 'shipped']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionHasErrors('account');
    }

    public function test_an_account_with_an_open_quote_request_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        QuoteRequest::factory()->create(['buyer_id' => $user->id, 'status' => 'pending']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionHasErrors('account');
    }

    public function test_an_account_with_an_open_dispute_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        Dispute::factory()->create(['buyer_id' => $user->id, 'status' => 'escalated']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionHasErrors('account');
    }

    public function test_an_account_with_only_delivered_or_cancelled_orders_can_be_deleted(): void
    {
        $user = User::factory()->create();
        Order::factory()->create(['buyer_id' => $user->id, 'status' => 'delivered']);
        Order::factory()->create(['buyer_id' => $user->id, 'status' => 'cancelled']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password'])
            ->assertSessionDoesntHaveErrors();
    }

    public function test_the_correct_password_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'wrong-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertNotSame('Compte supprimé', $user->fresh()->name);
    }

    // ---------- Suppression : effet ----------

    public function test_deleting_anonymises_the_account_and_logs_out(): void
    {
        $user = User::factory()->create(['name' => 'Jean Dupont', 'email' => 'jean@example.test', 'phone' => '90000000']);

        $response = $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password']);

        $response->assertRedirect(route('home'));
        $this->assertGuest();

        $fresh = $user->fresh();
        $this->assertSame('Compte supprimé', $fresh->name);
        $this->assertNotSame('jean@example.test', $fresh->email);
        $this->assertNull($fresh->phone);
        $this->assertNull($fresh->email_verified_at);
    }

    public function test_deleting_removes_addresses_and_wishlist_but_keeps_orders(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id]);
        Wishlist::create(['user_id' => $user->id, 'product_id' => Product::factory()->create()->id]);
        $order = Order::factory()->create(['buyer_id' => $user->id, 'status' => 'delivered', 'reference' => 'CMD-KEEP0001']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password']);

        $this->assertDatabaseCount('addresses', 0);
        $this->assertDatabaseCount('wishlists', 0);
        $this->assertModelExists($order);
        $this->assertSame('CMD-KEEP0001', $order->fresh()->reference);
    }

    public function test_a_review_by_a_deleted_user_still_displays_but_anonymously(): void
    {
        $user = User::factory()->create(['name' => 'Jean Dupont']);
        $product = Product::factory()->create();
        Review::create(['user_id' => $user->id, 'product_id' => $product->id, 'rating' => 5, 'comment' => 'Très bon produit.']);

        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password']);

        $this->get(route('products.show', $product))
            ->assertSee(__('Compte supprimé'))
            ->assertDontSee('Jean Dupont')
            ->assertSee('Très bon produit.');
    }

    public function test_deleted_users_email_can_be_reused_by_someone_else(): void
    {
        $user = User::factory()->create(['email' => 'jean@example.test']);
        $this->actingAs($user)->delete(route('profile.destroy'), ['current_password' => 'password']);

        $this->post(route('register'), [
            'name' => 'Nouvelle Personne',
            'email' => 'jean@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'account_type' => 'customer',
            'terms' => '1',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, User::where('email', 'like', '%jean%')->count());
    }
}
