<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_payment_page_redirects_for_an_already_cancelled_order(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $user->id, 'status' => 'cancelled']);

        $response = $this->actingAs($user)->get(route('payment.show', $order));

        $response->assertRedirect(route('orders.show', $order));
        $response->assertSessionHas('status');
    }

    public function test_verify_never_reopens_a_cancelled_order_even_for_an_unrelated_transaction(): void
    {
        // Transaction réussie mais d'un autre montant : aucun effet, aucun remboursement.
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->andReturn([
                'status' => 'SUCCESS', 'amount' => 1.0, 'transactionId' => 'txn_abc', 'reference' => 'CMD-CANCELLED1',
            ]);
            $mock->shouldNotReceive('refundTransaction');
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'pending',
            'total_amount' => 10000, 'reference' => 'CMD-CANCELLED1',
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_a_late_payment_on_a_cancelled_order_is_refunded_and_never_reopens_it(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_late', 'reference' => 'CMD-LATE0001',
            ]);
            $mock->shouldReceive('refundTransaction')->once()->with('txn_late')->andReturn(true);
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'pending',
            'total_amount' => 10000, 'reference' => 'CMD-LATE0001',
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_late'])->assertStatus(422);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);          // jamais rouverte
        $this->assertSame('paid', $fresh->payment_status);       // l'argent est bien tracé
        $this->assertSame('txn_late', $fresh->payment_reference);
        $this->assertNotNull($fresh->refunded_at);               // et remboursé
    }

    public function test_a_late_payment_whose_refund_fails_is_kept_for_manual_handling(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_late', 'reference' => 'CMD-LATE0002',
            ]);
            $mock->shouldReceive('refundTransaction')->once()->andThrow(new \RuntimeException('API indisponible'));
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'pending',
            'total_amount' => 10000, 'reference' => 'CMD-LATE0002',
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_late'])->assertStatus(422);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertNull($fresh->refunded_at); // repérable : payée + annulée + non remboursée
    }

    public function test_the_webhook_refunds_a_late_payment_on_a_cancelled_order(): void
    {
        config(['services.kkiapay.webhook_secret' => 'test-secret']);

        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_late', 'reference' => 'CMD-TEST123',
            ]);
            $mock->shouldReceive('refundTransaction')->once()->andReturn(true);
        });

        $order = Order::factory()->create([
            'status' => 'cancelled', 'payment_status' => 'pending', 'total_amount' => 10000, 'reference' => 'CMD-TEST123',
        ]);

        $this->postJson(route('payment.webhook'), ['data' => 'CMD-TEST123', 'transactionId' => 'txn_late'], ['x-kkiapay-secret' => 'test-secret'])
            ->assertNoContent();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->refunded_at);
    }

    public function test_a_late_payment_is_only_refunded_once(): void
    {
        config(['services.kkiapay.webhook_secret' => 'test-secret']);

        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andReturn([
                'status' => 'SUCCESS', 'amount' => 10000.0, 'transactionId' => 'txn_late', 'reference' => 'CMD-TEST123',
            ]);
            $mock->shouldReceive('refundTransaction')->once()->andReturn(true);
        });

        Order::factory()->create(['status' => 'cancelled', 'payment_status' => 'pending', 'total_amount' => 10000, 'reference' => 'CMD-TEST123']);

        $payload = ['data' => 'CMD-TEST123', 'transactionId' => 'txn_late'];
        $headers = ['x-kkiapay-secret' => 'test-secret'];

        // KkiaPay rejoue parfois un webhook : le second appel ne doit ni
        // revérifier ni rembourser une seconde fois (payment_status = paid).
        $this->postJson(route('payment.webhook'), $payload, $headers)->assertNoContent();
        $this->postJson(route('payment.webhook'), $payload, $headers)->assertNoContent();
    }

    public function test_verify_marks_a_pending_order_as_paid_on_success(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldReceive('verifyTransaction')->once()->andReturn([
                'status' => 'SUCCESS',
                'amount' => 10000.0,
                'transactionId' => 'txn_abc',
                'reference' => 'CMD-OK000001',
            ]);
        });

        $user = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $user->id, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 10000, 'reference' => 'CMD-OK000001']);

        $response = $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc']);

        $response->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('txn_abc', $order->fresh()->payment_reference);
    }

    private function mockTransaction(array $overrides = []): void
    {
        $this->mock(KkiapayService::class, function ($mock) use ($overrides) {
            $mock->shouldReceive('verifyTransaction')->andReturn(array_merge([
                'status' => 'SUCCESS',
                'amount' => 10000.0,
                'transactionId' => 'txn_abc',
                'reference' => 'CMD-TARGET01',
            ], $overrides));
        });
    }

    private function pendingOrderFor(User $user): Order
    {
        return Order::factory()->create([
            'buyer_id' => $user->id,
            'status' => 'pending',
            'payment_status' => 'pending',
            'total_amount' => 10000,
            'reference' => 'CMD-TARGET01',
        ]);
    }

    public function test_verify_refuses_a_transaction_of_a_different_amount(): void
    {
        $this->mockTransaction(['amount' => 500.0]);
        $user = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_verify_refuses_a_transaction_without_an_amount(): void
    {
        $this->mockTransaction(['amount' => null]);
        $user = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_verify_refuses_a_transaction_made_for_another_order(): void
    {
        $this->mockTransaction(['reference' => 'CMD-SOMEONE-ELSE']);
        $user = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_verify_refuses_a_transaction_without_a_reference(): void
    {
        $this->mockTransaction(['reference' => null]);
        $user = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_a_transaction_cannot_pay_two_orders(): void
    {
        $this->mockTransaction();
        $user = User::factory()->create();

        Order::factory()->create(['payment_status' => 'paid', 'status' => 'paid', 'payment_reference' => 'txn_abc']);
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertNull($order->fresh()->payment_reference);
    }

    public function test_verify_never_downgrades_an_already_paid_order(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('verifyTransaction');
        });

        $user = User::factory()->create();
        $order = Order::factory()->create([
            'buyer_id' => $user->id,
            'status' => 'paid',
            'payment_status' => 'paid',
            'payment_reference' => 'txn_real',
            'total_amount' => 10000,
        ]);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'garbage'])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('txn_real', $order->fresh()->payment_reference);
    }

    public function test_verify_marks_the_order_failed_when_kkiapay_reports_a_failure(): void
    {
        $this->mockTransaction(['status' => 'FAILED']);
        $user = User::factory()->create();
        $order = $this->pendingOrderFor($user);

        $this->actingAs($user)->postJson(route('payment.verify', $order), ['transaction_id' => 'txn_abc'])->assertStatus(422);

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_the_webhook_is_closed_when_no_secret_is_configured(): void
    {
        config(['services.kkiapay.webhook_secret' => null]);

        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('verifyTransaction');
        });

        $order = $this->pendingOrderFor(User::factory()->create());

        // Aucun en-tête ET aucun secret configuré : ne doit surtout pas passer.
        $this->postJson(route('payment.webhook'), ['data' => 'CMD-TARGET01', 'transactionId' => 'txn_abc'])
            ->assertUnauthorized();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_the_webhook_rejects_a_wrong_secret(): void
    {
        config(['services.kkiapay.webhook_secret' => 'test-secret']);

        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('verifyTransaction');
        });

        $this->postJson(route('payment.webhook'), ['data' => 'CMD-TARGET01', 'transactionId' => 'txn_abc'], ['x-kkiapay-secret' => 'wrong'])
            ->assertUnauthorized();
    }

    public function test_the_webhook_marks_a_matching_order_as_paid(): void
    {
        config(['services.kkiapay.webhook_secret' => 'test-secret']);
        $this->mockTransaction();
        $order = $this->pendingOrderFor(User::factory()->create());

        $this->postJson(route('payment.webhook'), ['data' => 'CMD-TARGET01', 'transactionId' => 'txn_abc'], ['x-kkiapay-secret' => 'test-secret'])
            ->assertNoContent();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_the_webhook_ignores_a_transaction_with_the_wrong_amount(): void
    {
        config(['services.kkiapay.webhook_secret' => 'test-secret']);
        $this->mockTransaction(['amount' => 1.0]);
        $order = $this->pendingOrderFor(User::factory()->create());

        $this->postJson(route('payment.webhook'), ['data' => 'CMD-TARGET01', 'transactionId' => 'txn_abc'], ['x-kkiapay-secret' => 'test-secret'])
            ->assertNoContent();

        $this->assertSame('pending', $order->fresh()->payment_status);
    }
}
