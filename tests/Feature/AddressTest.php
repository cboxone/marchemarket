<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Entrepôt',
            'recipient_name' => 'Jean Dupont',
            'address' => '12 rue du Marché',
            'city' => 'Cotonou',
            'country' => 'Bénin',
            'phone' => '90000000',
        ], $overrides);
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_the_index_lists_only_my_addresses(): void
    {
        $me = User::factory()->create();
        Address::factory()->create(['user_id' => $me->id, 'label' => 'Mon entrepôt']);
        Address::factory()->create(['label' => 'Adresse étrangère']);

        $this->actingAs($me)->get(route('addresses.index'))
            ->assertOk()->assertSee('Mon entrepôt')->assertDontSee('Adresse étrangère');
    }

    // ---------- Création ----------

    public function test_creating_the_first_address_makes_it_the_default_even_unchecked(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), $this->payload())->assertSessionHasNoErrors();

        $this->assertTrue($user->addresses()->first()->is_default);
    }

    public function test_a_second_address_is_not_default_unless_checked(): void
    {
        $user = User::factory()->create();
        Address::factory()->default()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('addresses.store'), $this->payload());

        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_checking_default_on_a_new_address_unmarks_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->default()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('addresses.store'), $this->payload(['is_default' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertSame(1, $user->addresses()->where('is_default', true)->count());
    }

    public function test_required_fields_are_validated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('addresses.store'), [])
            ->assertSessionHasErrors(['label', 'address', 'city', 'country', 'phone']);

        $this->assertDatabaseCount('addresses', 0);
    }

    // ---------- Modification ----------

    public function test_updating_my_own_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->put(route('addresses.update', $address), $this->payload(['city' => 'Parakou']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Parakou', $address->fresh()->city);
    }

    public function test_a_customer_cannot_update_or_delete_someone_elses_address(): void
    {
        $address = Address::factory()->create();

        $this->actingAs(User::factory()->create())->put(route('addresses.update', $address), $this->payload())->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('addresses.destroy', $address))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('addresses.set-default', $address))->assertForbidden();
    }

    // ---------- Suppression et promotion automatique ----------

    public function test_deleting_a_non_default_address_leaves_the_default_untouched(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->default()->create(['user_id' => $user->id]);
        $other = Address::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('addresses.destroy', $other))->assertSessionHasNoErrors();

        $this->assertTrue($default->fresh()->is_default);
    }

    public function test_deleting_the_default_address_promotes_another_one(): void
    {
        $user = User::factory()->create();
        $default = Address::factory()->default()->create(['user_id' => $user->id]);
        $other = Address::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('addresses.destroy', $default))->assertSessionHasNoErrors();

        $this->assertTrue($other->fresh()->is_default);
    }

    public function test_deleting_the_only_address_leaves_none_default(): void
    {
        $user = User::factory()->create();
        $only = Address::factory()->default()->create(['user_id' => $user->id]);

        $this->actingAs($user)->delete(route('addresses.destroy', $only));

        $this->assertSame(0, $user->addresses()->count());
    }

    // ---------- Définir par défaut ----------

    public function test_setting_a_different_address_as_default_unmarks_the_previous_one(): void
    {
        $user = User::factory()->create();
        $first = Address::factory()->default()->create(['user_id' => $user->id]);
        $second = Address::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('addresses.set-default', $second))->assertSessionHasNoErrors();

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    // ---------- Helper modèle ----------

    public function test_default_address_helper_returns_the_flagged_one(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id]);
        $default = Address::factory()->default()->create(['user_id' => $user->id]);

        $this->assertSame($default->id, $user->fresh()->defaultAddress()->id);
    }
}
