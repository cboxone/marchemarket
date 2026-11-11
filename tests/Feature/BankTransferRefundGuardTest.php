<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Services\KkiapayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un virement bancaire n'a pas de transaction KkiaPay : ces tests vérifient
 * qu'aucun remboursement automatique n'est jamais tenté pour ce moyen de
 * paiement, ni via l'annulation d'une commande, ni via un litige.
 */
class BankTransferRefundGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_attempt_refund_never_calls_kkiapay_for_a_bank_transfer_order(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('refundTransaction');
        });

        $order = Order::factory()->create([
            'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer',
            'bank_transfer_confirmed_by' => User::factory()->admin()->create()->id,
        ]);

        $result = $order->attemptRefund();

        $this->assertFalse($result);
        $this->assertNull($order->fresh()->refunded_at);
    }

    public function test_cancelling_a_bank_transfer_order_from_the_back_office_leaves_the_refund_manual(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('refundTransaction');
        });

        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer']);

        $this->actingAs($admin)->patch(route('admin.orders.update-status', $order), ['status' => 'cancelled'])
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->refunded_at);
    }

    public function test_a_dispute_on_a_bank_transfer_order_is_never_eligible_for_an_automatic_refund(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('refundTransaction');
        });

        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create([
            'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer',
            'shipping_fee' => 0, 'total_amount' => 10000,
        ]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);

        $dispute = Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id, 'refund_amount' => 10000]);

        $this->assertFalse($dispute->isAutomaticRefundEligible());
    }

    public function test_accepting_a_dispute_in_full_on_a_bank_transfer_order_is_left_for_manual_processing(): void
    {
        $this->mock(KkiapayService::class, function ($mock) {
            $mock->shouldNotReceive('refundTransaction');
        });

        \Illuminate\Support\Facades\Mail::fake();
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create([
            'status' => 'paid', 'payment_status' => 'paid', 'payment_method' => 'bank_transfer',
            'shipping_fee' => 0, 'total_amount' => 10000,
        ]);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);
        $dispute = Dispute::factory()->create(['order_id' => $order->id, 'vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user)->post(route('vendor.disputes.respond', $dispute), ['response_type' => 'accept_full'])
            ->assertSessionHasNoErrors();

        $fresh = $dispute->fresh();
        $this->assertSame('pending_manual', $fresh->refund_status);
        $this->assertNull($fresh->refunded_at);
    }
}
