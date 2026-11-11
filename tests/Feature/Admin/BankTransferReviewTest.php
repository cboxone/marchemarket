<?php

namespace Tests\Feature\Admin;

use App\Mail\BankTransferStatusMail;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankTransferReviewTest extends TestCase
{
    use RefreshDatabase;

    private function declaredOrder(array $attributes = []): Order
    {
        Storage::fake('local');
        $order = Order::factory()->create($attributes + ['status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 10000]);
        $order->forceFill([
            'payment_method' => 'bank_transfer',
            'bank_transfer_chosen_at' => now()->subDay(),
            'bank_transfer_declared_at' => now(),
            'bank_transfer_proof_path' => 'bank-transfers/proof.jpg',
        ])->save();

        return $order;
    }

    // ---------- Accès ----------

    public function test_only_admins_can_access_bank_transfer_review(): void
    {
        $order = $this->declaredOrder();

        $this->get(route('admin.bank-transfers.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.bank-transfers.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('admin.bank-transfers.confirm', $order))->assertForbidden();
    }

    // ---------- Liste ----------

    public function test_the_index_only_lists_declared_and_still_pending_transfers(): void
    {
        $admin = User::factory()->admin()->create();
        $declared = $this->declaredOrder(['reference' => 'CMD-DECLARED1']);

        $chosenOnly = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending', 'reference' => 'CMD-CHOSEN01']);
        $chosenOnly->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $alreadyPaid = $this->declaredOrder(['reference' => 'CMD-PAID0001']);
        $alreadyPaid->update(['payment_status' => 'paid', 'status' => 'paid']);

        $this->actingAs($admin)->get(route('admin.bank-transfers.index'))
            ->assertSee('CMD-DECLARED1')->assertDontSee('CMD-CHOSEN01')->assertDontSee('CMD-PAID0001');
    }

    // ---------- Confirmation ----------

    public function test_confirming_marks_the_order_paid_assigns_an_invoice_and_notifies_the_buyer(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = $this->declaredOrder();

        $this->actingAs($admin)->post(route('admin.bank-transfers.confirm', $order))->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame('paid', $fresh->status);
        $this->assertSame($admin->id, $fresh->bank_transfer_confirmed_by);
        $this->assertNotNull($fresh->invoice_number);

        $this->assertSame(1, AuditLog::where('action', 'bank_transfer.confirmed')->count());
        $this->assertMailed(BankTransferStatusMail::class, fn ($m) => $m->hasTo($order->buyer->email) && $m->event === 'confirmed');
    }

    public function test_a_declaration_cannot_be_confirmed_twice(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = $this->declaredOrder();

        $this->actingAs($admin)->post(route('admin.bank-transfers.confirm', $order));
        $this->actingAs($admin)->post(route('admin.bank-transfers.confirm', $order))->assertSessionHasErrors('bank_transfer');

        $this->assertSame(1, AuditLog::where('action', 'bank_transfer.confirmed')->count());
    }

    public function test_confirming_an_undeclared_order_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($admin)->post(route('admin.bank-transfers.confirm', $order))->assertSessionHasErrors('bank_transfer');

        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    // ---------- Rejet ----------

    public function test_rejecting_requires_a_reason_and_notifies_the_buyer(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = $this->declaredOrder();

        $this->actingAs($admin)->post(route('admin.bank-transfers.reject', $order), [])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.bank-transfers.reject', $order), ['reason' => 'Montant introuvable sur le relevé'])
            ->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertSame('pending', $fresh->payment_status);
        $this->assertNull($fresh->bank_transfer_declared_at); // reste choisi, mais plus déclaré
        $this->assertSame('Montant introuvable sur le relevé', $fresh->bank_transfer_rejected_reason);
        $this->assertNotNull($fresh->bank_transfer_rejected_at);

        $this->assertSame(1, AuditLog::where('action', 'bank_transfer.rejected')->count());
        $this->assertMailed(BankTransferStatusMail::class, fn ($m) => $m->event === 'rejected');
    }

    public function test_after_rejection_the_buyer_can_redeclare_within_the_window(): void
    {
        Mail::fake();
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $order = $this->declaredOrder();
        $order->forceFill(['bank_transfer_chosen_at' => now()])->save(); // fenêtre encore largement ouverte

        $this->actingAs($admin)->post(route('admin.bank-transfers.reject', $order), ['reason' => 'x']);

        $this->actingAs($order->buyer)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('nouveau-recu.jpg'),
        ])->assertSessionHasNoErrors();

        $fresh = $order->fresh();
        $this->assertNotNull($fresh->bank_transfer_declared_at);
        $this->assertNull($fresh->bank_transfer_rejected_reason); // le rejet précédent est effacé par la nouvelle déclaration
    }

    public function test_a_rejected_transfer_can_still_be_auto_cancelled_once_its_window_is_over(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $order = $this->declaredOrder();
        $order->forceFill(['bank_transfer_chosen_at' => now()->subHours(80)])->save(); // fenêtre déjà dépassée

        $this->actingAs($admin)->post(route('admin.bank-transfers.reject', $order), ['reason' => 'x']);

        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_rejecting_an_undeclared_order_is_refused(): void
    {
        $admin = User::factory()->admin()->create();
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($admin)->post(route('admin.bank-transfers.reject', $order), ['reason' => 'x'])
            ->assertSessionHasErrors('bank_transfer');
    }

    public function test_the_admin_dashboard_counts_pending_declarations(): void
    {
        $admin = User::factory()->admin()->create();
        $this->declaredOrder();
        $this->declaredOrder();
        $confirmed = $this->declaredOrder();
        $confirmed->update(['payment_status' => 'paid', 'status' => 'paid']);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats['pending_bank_transfers'] === 2);
    }
}
