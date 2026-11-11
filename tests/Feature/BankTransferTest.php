<?php

namespace Tests\Feature;

use App\Mail\BankTransferStatusMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'marketplace.bank_transfer.bank_name' => 'Banque Atlantique',
            'marketplace.bank_transfer.account_name' => 'MarcheMarket SARL',
            'marketplace.bank_transfer.account_number' => '01234567890',
            'marketplace.bank_transfer.iban' => null,
            'marketplace.bank_transfer.window_hours' => 72,
        ]);
    }

    private function pendingOrder(User $buyer, array $attributes = []): Order
    {
        return Order::factory()->create($attributes + [
            'buyer_id' => $buyer->id, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 10000,
        ]);
    }

    // ---------- Visibilité de l'option ----------

    public function test_the_option_is_hidden_when_no_bank_account_is_configured(): void
    {
        config(['marketplace.bank_transfer.account_number' => null, 'marketplace.bank_transfer.iban' => null]);
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()->assertDontSee(__('Payer par virement bancaire'));
    }

    public function test_the_option_is_shown_when_an_account_is_configured(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->actingAs($user)->get(route('payment.show', $order))
            ->assertOk()->assertSee(__('Payer par virement bancaire'));
    }

    // ---------- Étape 1 : choisir ----------

    public function test_another_customer_cannot_choose_bank_transfer_on_my_order(): void
    {
        $order = $this->pendingOrder(User::factory()->create());

        $this->actingAs(User::factory()->create())->post(route('payment.bank-transfer.choose', $order))->assertForbidden();
    }

    public function test_choosing_bank_transfer_records_the_method_and_the_timestamp(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->actingAs($user)->post(route('payment.bank-transfer.choose', $order))->assertRedirect(route('payment.show', $order));

        $fresh = $order->fresh();
        $this->assertSame('bank_transfer', $fresh->payment_method);
        $this->assertNotNull($fresh->bank_transfer_chosen_at);
        $this->assertSame('pending', $fresh->payment_status); // jamais marquée payée par ce seul choix
    }

    public function test_choosing_twice_does_not_push_the_deadline_back(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->actingAs($user)->post(route('payment.bank-transfer.choose', $order));
        $firstChosenAt = $order->fresh()->bank_transfer_chosen_at;

        $this->travel(1)->hour();
        $this->actingAs($user)->post(route('payment.bank-transfer.choose', $order));

        $this->assertTrue($firstChosenAt->equalTo($order->fresh()->bank_transfer_chosen_at));
    }

    public function test_bank_transfer_extends_the_grace_period_well_past_the_short_default(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        Order::whereKey($order->id)->update(['created_at' => now()->subMinutes(90)]); // au-delà des 60 minutes par défaut

        $this->actingAs($user)->post(route('payment.bank-transfer.choose', $order));

        $deadline = $order->fresh()->unpaidCancelDeadline();
        $this->assertTrue($deadline->isFuture());
    }

    // ---------- Étape 2 : déclarer ----------

    public function test_declaring_without_choosing_first_is_refused(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertSessionHasErrors('bank_transfer');

        $this->assertNull($order->fresh()->bank_transfer_declared_at);
    }

    public function test_a_proof_is_required(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [])
            ->assertSessionHasErrors('proof');
    }

    public function test_declaring_stores_the_proof_and_the_reference(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'reference' => 'Virement Jean Dupont',
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('Virement Jean Dupont', $fresh->bank_transfer_reference);
        $this->assertNotNull($fresh->bank_transfer_declared_at);
        $this->assertNotNull($fresh->bank_transfer_proof_path);
        Storage::disk('local')->assertExists($fresh->bank_transfer_proof_path);
        $this->assertSame('pending', $fresh->payment_status); // toujours pas payée : ça attend un admin
    }

    public function test_a_pdf_receipt_is_accepted(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->create('recu.pdf', 500, 'application/pdf'),
        ])->assertSessionHasNoErrors();
    }

    public function test_declaring_twice_while_pending_review_is_refused(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now(), 'bank_transfer_declared_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('recu2.jpg'),
        ])->assertSessionHasErrors('bank_transfer');
    }

    public function test_declaring_after_the_window_has_passed_is_refused(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()->subHours(80)])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertSessionHasErrors('bank_transfer');

        $this->assertNull($order->fresh()->bank_transfer_declared_at);
    }

    public function test_a_cancelled_order_cannot_receive_a_declaration(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user, ['status' => 'cancelled']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ])->assertRedirect(route('payment.show', $order));

        $this->assertNull($order->fresh()->bank_transfer_declared_at);
    }

    public function test_declarations_are_rate_limited(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        for ($i = 0; $i < 10; $i++) {
            $order = $this->pendingOrder($user);
            $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();
            $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), ['proof' => UploadedFile::fake()->image('r.jpg')])
                ->assertSessionHasNoErrors();
        }

        $order = $this->pendingOrder($user);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();
        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), ['proof' => UploadedFile::fake()->image('r.jpg')])
            ->assertStatus(429);
    }

    // ---------- Annulation automatique ----------

    public function test_a_bank_transfer_never_declared_is_cancelled_after_its_own_window_not_the_short_default(): void
    {
        $user = User::factory()->create();

        $tooEarly = $this->pendingOrder($user);
        $tooEarly->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()->subHours(2)])->save();
        Order::whereKey($tooEarly->id)->update(['created_at' => now()->subHours(2)]);

        $overdue = $this->pendingOrder($user);
        $overdue->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()->subHours(80)])->save();
        Order::whereKey($overdue->id)->update(['created_at' => now()->subHours(80)]);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        // 2h < 72h : pas encore annulée, même si l'ancien délai KkiaPay de 60 min est largement dépassé.
        $this->assertSame('pending', $tooEarly->fresh()->status);
        $this->assertSame('cancelled', $overdue->fresh()->status);
    }

    public function test_a_declared_transfer_is_never_auto_cancelled_however_old(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        $order->forceFill([
            'payment_method' => 'bank_transfer',
            'bank_transfer_chosen_at' => now()->subDays(30),
            'bank_transfer_declared_at' => now()->subDays(30),
        ])->save();

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_a_regular_kkiapay_order_still_cancels_on_the_short_default_delay(): void
    {
        $user = User::factory()->create();
        $order = $this->pendingOrder($user);
        Order::whereKey($order->id)->update(['created_at' => now()->subHours(2)]);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    // ---------- Fusion avec KkiaPay ----------

    public function test_paying_via_kkiapay_after_choosing_bank_transfer_still_works(): void
    {
        $this->mock(\App\Services\KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_abc', 'reference' => 'CMD-SWITCH01',
            ]);
        });

        $user = User::factory()->create();
        $order = $this->pendingOrder($user, ['reference' => 'CMD-SWITCH01']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertOk();

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame('kkiapay', $fresh->payment_method); // écrase le choix initial de virement
    }
}
