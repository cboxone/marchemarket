<?php

namespace Tests\Feature\Vendor;

use App\Mail\PayoutAccountChangedMail;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private function vendorWithPassword(array $vendorAttributes = []): Vendor
    {
        $user = User::factory()->vendor()->create(['password' => bcrypt('secret123')]);

        return Vendor::factory()->create($vendorAttributes + ['user_id' => $user->id]);
    }

    private function payload(Vendor $vendor, array $overrides = []): array
    {
        return array_merge([
            'store_name' => $vendor->store_name,
            'description' => $vendor->description,
            'country' => $vendor->country,
            'city' => $vendor->city,
            'payout_provider' => $vendor->payout_provider,
            'payout_phone' => $vendor->payout_phone,
        ], $overrides);
    }

    public function test_a_vendor_can_view_the_shop_page(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->get(route('vendor.shop.edit'))
            ->assertOk()
            ->assertSee($vendor->store_name);
    }

    public function test_a_customer_cannot_access_the_shop_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('vendor.shop.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('vendor.shop.update'), [])->assertForbidden();
    }

    public function test_a_vendor_can_update_the_public_profile_and_the_slug_stays_stable(): void
    {
        $vendor = Vendor::factory()->create();
        $slug = $vendor->slug;

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'store_name' => 'Nouvelle Enseigne',
            'description' => 'Grossiste de riz local.',
            'city' => 'Parakou',
        ]))->assertRedirect(route('vendor.shop.edit'))->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('Nouvelle Enseigne', $fresh->store_name);
        $this->assertSame('Grossiste de riz local.', $fresh->description);
        $this->assertSame('Parakou', $fresh->city);
        $this->assertSame($slug, $fresh->slug); // les liens partagés continuent de fonctionner
    }

    public function test_the_store_name_is_required(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, ['store_name' => '']))
            ->assertSessionHasErrors('store_name');
    }

    public function test_a_verified_vendor_who_renames_the_shop_loses_the_verified_badge(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, ['store_name' => 'Autre Nom SARL']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($vendor->fresh()->is_verified);
    }

    public function test_a_verified_vendor_keeping_the_same_name_stays_verified(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, ['description' => 'Texte modifié']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($vendor->fresh()->is_verified);
    }

    public function test_uploading_a_logo_stores_it_and_replaces_the_previous_one(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'logo' => UploadedFile::fake()->image('logo.png', 600, 600),
        ]))->assertSessionHasNoErrors();

        $first = $vendor->fresh()->logo_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor->fresh(), [
            'logo' => UploadedFile::fake()->image('logo2.png', 600, 600),
        ]))->assertSessionHasNoErrors();

        $second = $vendor->fresh()->logo_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first); // pas de fichier orphelin
    }

    public function test_the_logo_can_be_removed(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ]));
        $path = $vendor->fresh()->logo_path;

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor->fresh(), ['remove_logo' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertNull($vendor->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_logo_must_be_an_image(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasErrors('logo');

        $this->assertNull($vendor->fresh()->logo_path);
    }

    public function test_the_logo_is_shown_on_the_public_vendor_page(): void
    {
        Storage::fake('public');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ]));

        $this->get(route('vendors.show', $vendor->fresh()))
            ->assertOk()
            ->assertSee($vendor->fresh()->logo_path);
    }

    public function test_changing_the_payout_account_requires_the_current_password(): void
    {
        Mail::fake();
        $vendor = $this->vendorWithPassword();
        $before = $vendor->payout_phone;

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, ['payout_phone' => '+22997111111']))
            ->assertSessionHasErrors('current_password');

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_phone' => '+22997111111',
            'current_password' => 'mauvais',
        ]))->assertSessionHasErrors('current_password');

        $this->assertSame($before, $vendor->fresh()->payout_phone);
        Mail::assertNothingQueued();
    }

    public function test_the_payout_account_is_saved_normalised_and_an_alert_is_sent(): void
    {
        Mail::fake();
        $vendor = $this->vendorWithPassword();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_provider' => 'moov',
            'payout_phone' => '+229 97 11-11.11',
            'current_password' => 'secret123',
        ]))->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('moov', $fresh->payout_provider);
        $this->assertSame('+22997111111', $fresh->payout_phone);
        $this->assertSame('Moov Money · +22997111111', $fresh->payoutAccountLabel());

        Mail::assertQueued(PayoutAccountChangedMail::class, fn ($mail) => $mail->hasTo($vendor->user->email));
    }

    public function test_the_password_is_not_required_when_the_payout_account_is_unchanged(): void
    {
        Mail::fake();
        $vendor = $this->vendorWithPassword();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, ['city' => 'Ouidah']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Ouidah', $vendor->fresh()->city);
        Mail::assertNothingQueued();
    }

    public function test_an_invalid_phone_number_is_rejected(): void
    {
        $vendor = $this->vendorWithPassword();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_phone' => 'abc123',
            'current_password' => 'secret123',
        ]))->assertSessionHasErrors('payout_phone');

        $this->assertSame('+22996000000', $vendor->fresh()->payout_phone);
    }

    public function test_a_provider_without_a_number_is_rejected(): void
    {
        $vendor = $this->vendorWithPassword(['payout_provider' => null, 'payout_phone' => null]);

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_provider' => 'mtn',
            'payout_phone' => '',
            'current_password' => 'secret123',
        ]))->assertSessionHasErrors('payout_phone');
    }

    public function test_an_unknown_provider_is_rejected(): void
    {
        $vendor = $this->vendorWithPassword();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_provider' => 'paypal',
            'current_password' => 'secret123',
        ]))->assertSessionHasErrors('payout_provider');
    }

    public function test_the_payout_account_can_be_removed_with_the_password(): void
    {
        Mail::fake();
        $vendor = $this->vendorWithPassword();

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_provider' => '',
            'payout_phone' => '',
            'current_password' => 'secret123',
        ]))->assertSessionHasNoErrors();

        $this->assertFalse($vendor->fresh()->hasPayoutAccount());
        Mail::assertQueued(PayoutAccountChangedMail::class);
    }

    private function giveBalance(Vendor $vendor, float $amount = 10000): void
    {
        $order = Order::factory()->create(['payment_status' => 'paid', 'status' => 'paid', 'shipping_fee' => 0, 'total_amount' => $amount]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => $amount]);
    }

    public function test_a_withdrawal_needs_a_saved_payout_account(): void
    {
        $vendor = Vendor::factory()->withoutPayoutAccount()->create();
        $this->giveBalance($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 5000])
            ->assertSessionHasErrors('payout_account');

        $this->assertDatabaseCount('payouts', 0);
    }

    public function test_a_withdrawal_always_goes_to_the_saved_account_whatever_the_request_says(): void
    {
        $vendor = Vendor::factory()->create(['payout_provider' => 'mtn', 'payout_phone' => '+22996000000']);
        $this->giveBalance($vendor);

        // Un champ libre forgé dans la requête ne doit avoir aucun effet.
        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 5000, 'payout_method' => 'Compte pirate 0000'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payouts', ['vendor_id' => $vendor->id, 'payout_method' => 'MTN Mobile Money · +22996000000']);
        $this->assertDatabaseMissing('payouts', ['payout_method' => 'Compte pirate 0000']);
    }

    public function test_changing_the_account_later_does_not_redirect_a_pending_withdrawal(): void
    {
        Mail::fake();
        $vendor = $this->vendorWithPassword();
        $this->giveBalance($vendor);

        $this->actingAs($vendor->user)->post(route('vendor.payouts.store'), ['amount' => 5000]);

        $this->actingAs($vendor->user)->put(route('vendor.shop.update'), $this->payload($vendor, [
            'payout_phone' => '+22997999999',
            'current_password' => 'secret123',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('payouts', ['vendor_id' => $vendor->id, 'payout_method' => 'MTN Mobile Money · +22996000000']);
    }

    public function test_the_payouts_page_shows_the_saved_account_and_a_prompt_when_missing(): void
    {
        $with = Vendor::factory()->create();
        $without = Vendor::factory()->withoutPayoutAccount()->create();

        $this->actingAs($with->user)->get(route('vendor.payouts.index'))->assertOk()->assertSee('MTN Mobile Money · +22996000000');
        $this->actingAs($without->user)->get(route('vendor.payouts.index'))->assertOk()->assertSee(route('vendor.shop.edit'), false);
    }
}
