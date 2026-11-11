<?php

namespace Tests\Feature\Admin;

use App\Mail\VendorKycStatusMail;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function pendingVendor(array $attributes = []): Vendor
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create($attributes);
        $vendor->forceFill([
            'tax_id' => 'IFU-123456',
            'kyc_status' => 'pending',
            'kyc_submitted_at' => now(),
            'kyc_id_document_path' => 'kyc/cni.jpg',
        ])->save();

        return $vendor;
    }

    // ---------- Accès ----------

    public function test_only_admins_can_access_kyc_review(): void
    {
        $vendor = $this->pendingVendor();

        $this->get(route('admin.verifications.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('admin.verifications.index'))->assertForbidden();
        $this->actingAs($vendor->user)->post(route('admin.verifications.approve', $vendor))->assertForbidden();
    }

    public function test_a_vendor_who_never_submitted_anything_returns_404(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create(); // kyc_status = none par défaut

        $this->actingAs($admin)->get(route('admin.verifications.show', $vendor))->assertNotFound();
    }

    // ---------- Liste ----------

    public function test_the_index_only_lists_pending_files(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = $this->pendingVendor(['store_name' => 'Boutique En Attente']);

        $approved = Vendor::factory()->create(['store_name' => 'Boutique Approuvee']);
        $approved->forceFill(['kyc_status' => 'approved'])->save();

        $none = Vendor::factory()->create(['store_name' => 'Boutique Jamais Soumise']);

        $this->actingAs($admin)->get(route('admin.verifications.index'))
            ->assertSee('Boutique En Attente')->assertDontSee('Boutique Approuvee')->assertDontSee('Boutique Jamais Soumise');
    }

    // ---------- Approbation ----------

    public function test_approving_verifies_the_vendor_and_notifies_by_email(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = $this->pendingVendor();

        $this->actingAs($admin)->post(route('admin.verifications.approve', $vendor))->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('approved', $fresh->kyc_status);
        $this->assertTrue($fresh->is_verified);
        $this->assertSame($admin->id, $fresh->kyc_reviewed_by);
        $this->assertNotNull($fresh->kyc_reviewed_at);

        $this->assertSame(1, AuditLog::where('action', 'vendor.kyc_approved')->count());
        $this->assertMailed(VendorKycStatusMail::class, fn ($m) => $m->hasTo($vendor->user->email) && $m->event === 'approved');
    }

    public function test_a_file_cannot_be_approved_twice(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = $this->pendingVendor();

        $this->actingAs($admin)->post(route('admin.verifications.approve', $vendor));
        $this->actingAs($admin)->post(route('admin.verifications.approve', $vendor))->assertSessionHasErrors('kyc');

        $this->assertSame(1, AuditLog::where('action', 'vendor.kyc_approved')->count());
    }

    // ---------- Rejet ----------

    public function test_rejecting_requires_a_reason_and_notifies_the_vendor(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = $this->pendingVendor();

        $this->actingAs($admin)->post(route('admin.verifications.reject', $vendor), [])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->post(route('admin.verifications.reject', $vendor), ['reason' => 'Photo illisible'])
            ->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('rejected', $fresh->kyc_status);
        $this->assertFalse($fresh->is_verified);
        $this->assertSame('Photo illisible', $fresh->kyc_rejection_reason);

        $this->assertSame(1, AuditLog::where('action', 'vendor.kyc_rejected')->count());
        $this->assertMailed(VendorKycStatusMail::class, fn ($m) => $m->event === 'rejected');
    }

    public function test_a_file_cannot_be_rejected_twice(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = $this->pendingVendor();

        $this->actingAs($admin)->post(route('admin.verifications.reject', $vendor), ['reason' => 'x']);
        $this->actingAs($admin)->post(route('admin.verifications.reject', $vendor), ['reason' => 'y'])->assertSessionHasErrors('kyc');

        $this->assertSame('x', $vendor->fresh()->kyc_rejection_reason);
    }

    // ---------- Interaction avec le renommage / bascule existante ----------

    public function test_renaming_after_approval_clears_the_badge_but_keeps_the_kyc_file_intact(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $vendor = $this->pendingVendor();
        $this->actingAs($admin)->post(route('admin.verifications.approve', $vendor));

        $this->actingAs($vendor->fresh()->user)->put(route('vendor.shop.update'), [
            'store_name' => 'Nouveau Nom SARL',
            'country' => $vendor->fresh()->country,
        ]);

        $fresh = $vendor->fresh();
        $this->assertFalse($fresh->is_verified);       // badge retiré (comportement déjà existant)
        $this->assertSame('approved', $fresh->kyc_status); // le dossier, lui, reste valide

        // L'admin peut réactiver le badge sans nouveau dossier, via la bascule existante.
        $this->actingAs($admin)->patch(route('admin.vendors.toggle-verification', $fresh));
        $this->assertTrue($fresh->fresh()->is_verified);
    }

    public function test_the_admin_dashboard_counts_only_actionable_pending_files(): void
    {
        $admin = User::factory()->admin()->create();
        $this->pendingVendor();
        $this->pendingVendor();
        Vendor::factory()->create(); // jamais soumis : ne doit pas compter

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertViewHas('stats', fn ($stats) => $stats['pending_verification_count'] === 2);
    }
}
