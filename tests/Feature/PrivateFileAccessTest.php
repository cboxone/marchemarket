<?php

namespace Tests\Feature;

use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Les pièces d'identité KYC, les preuves de virement et les photos de
 * litige vivent sur le disque `local` (privé, sans lien symbolique public) :
 * seule une route authentifiée et autorisée peut les servir. Ces tests
 * vérifient à la fois que le disque public ne les contient jamais et que
 * les routes de service appliquent la bonne autorisation.
 */
class PrivateFileAccessTest extends TestCase
{
    use RefreshDatabase;

    // ---------- KYC ----------

    public function test_a_submitted_kyc_document_is_not_stored_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-1',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
        ]);

        $path = $vendor->fresh()->kyc_id_document_path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_an_admin_can_open_a_kyc_document(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();
        $vendor->forceFill(['kyc_status' => 'pending', 'kyc_id_document_path' => 'kyc/cni.jpg'])->save();
        Storage::disk('local')->put('kyc/cni.jpg', 'contenu');

        $this->get(route('admin.verifications.document', [$vendor, 'id']))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.verifications.document', [$vendor, 'id']))->assertForbidden();
        $this->actingAs($vendor->user)->get(route('admin.verifications.document', [$vendor, 'id']))->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.verifications.document', [$vendor, 'id']))
            ->assertOk();
    }

    public function test_a_missing_kyc_document_returns_404(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();
        $vendor->forceFill(['kyc_status' => 'pending'])->save(); // pas de business_document

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.verifications.document', [$vendor, 'business']))
            ->assertNotFound();
    }

    // ---------- Virement bancaire ----------

    public function test_a_declared_bank_transfer_proof_is_not_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $user->id, 'status' => 'pending', 'payment_status' => 'pending']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now()])->save();

        $this->actingAs($user)->post(route('payment.bank-transfer.declare', $order), [
            'proof' => UploadedFile::fake()->image('recu.jpg'),
        ]);

        $path = $order->fresh()->bank_transfer_proof_path;
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_only_an_admin_can_open_a_bank_transfer_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bank-transfers/proof.jpg', 'contenu');
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'pending']);
        $order->forceFill(['payment_method' => 'bank_transfer', 'bank_transfer_chosen_at' => now(), 'bank_transfer_proof_path' => 'bank-transfers/proof.jpg'])->save();

        $this->get(route('admin.bank-transfers.proof', $order))->assertRedirect(route('login'));
        $this->actingAs($order->buyer)->get(route('admin.bank-transfers.proof', $order))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.bank-transfers.proof', $order))->assertOk();
    }

    // ---------- Litiges ----------

    private function disputeWithEvidence(): Dispute
    {
        Storage::fake('local');
        Storage::disk('local')->put('disputes/photo.webp', 'contenu');

        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 1000]);

        return Dispute::factory()->create([
            'order_id' => $order->id, 'vendor_id' => $vendor->id,
            'evidence_paths' => ['disputes/photo.webp'],
        ]);
    }

    public function test_the_buyer_the_vendor_and_an_admin_can_each_open_the_evidence(): void
    {
        $dispute = $this->disputeWithEvidence();

        $this->actingAs($dispute->buyer)->get(route('disputes.evidence', [$dispute, 0]))->assertOk();
        $this->actingAs($dispute->vendor->user)->get(route('vendor.disputes.evidence', [$dispute, 0]))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.disputes.evidence', [$dispute, 0]))->assertOk();
    }

    public function test_another_customer_or_another_vendor_cannot_open_the_evidence(): void
    {
        $dispute = $this->disputeWithEvidence();
        $otherVendor = Vendor::factory()->create();

        $this->get(route('disputes.evidence', [$dispute, 0]))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('disputes.evidence', [$dispute, 0]))->assertForbidden();
        $this->actingAs($otherVendor->user)->get(route('vendor.disputes.evidence', [$dispute, 0]))->assertNotFound();
    }

    public function test_an_out_of_range_evidence_index_returns_404(): void
    {
        $dispute = $this->disputeWithEvidence();

        $this->actingAs($dispute->buyer)->get(route('disputes.evidence', [$dispute, 5]))->assertNotFound();
    }

    public function test_dispute_evidence_is_not_on_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $vendor = Vendor::factory()->create();
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => 'paid', 'payment_status' => 'paid']);
        $order->items()->create(['vendor_id' => $vendor->id, 'product_name' => 'X', 'quantity' => 1, 'unit_price' => 10000]);

        $this->actingAs($buyer)->post(route('disputes.store', $order), [
            'vendor_id' => $vendor->id, 'reason' => 'damaged', 'description' => 'Cassé.',
            'evidence' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $path = Dispute::first()->evidence_paths[0];
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }
}
