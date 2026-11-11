<?php

namespace Tests\Feature\Vendor;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_cannot_access_the_verification_page(): void
    {
        $this->actingAs(User::factory()->create())->get(route('vendor.verification.edit'))->assertForbidden();
    }

    public function test_a_vendor_can_submit_a_kyc_file(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-123456',
            'registration_number' => 'RCCM-RB-2024-B-001',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
            'business_document' => UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('IFU-123456', $fresh->tax_id);
        $this->assertSame('RCCM-RB-2024-B-001', $fresh->registration_number);
        $this->assertSame('pending', $fresh->kyc_status);
        $this->assertNotNull($fresh->kyc_submitted_at);
        Storage::disk('local')->assertExists($fresh->kyc_id_document_path);
        Storage::disk('local')->assertExists($fresh->kyc_business_document_path);
        $this->assertFalse($fresh->is_verified);
    }

    public function test_the_business_document_is_optional(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-123456',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
        ])->assertSessionHasNoErrors();

        $this->assertNull($vendor->fresh()->kyc_business_document_path);
    }

    public function test_the_tax_id_and_id_document_are_required(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [])
            ->assertSessionHasErrors(['tax_id', 'id_document']);
    }

    public function test_a_vendor_cannot_resubmit_while_pending(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();
        $vendor->forceFill(['kyc_status' => 'pending', 'kyc_submitted_at' => now()])->save();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-999999',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
        ])->assertSessionHasErrors('kyc');

        $this->assertNotSame('IFU-999999', $vendor->fresh()->tax_id);
    }

    public function test_a_vendor_cannot_resubmit_once_approved(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();
        $vendor->forceFill(['kyc_status' => 'approved', 'is_verified' => true])->save();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-999999',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
        ])->assertSessionHasErrors('kyc');
    }

    public function test_a_rejected_vendor_can_resubmit_and_it_clears_the_previous_rejection(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();
        $vendor->forceFill([
            'kyc_status' => 'rejected', 'kyc_rejection_reason' => 'Photo illisible',
            'kyc_reviewed_at' => now()->subDay(), 'kyc_reviewed_by' => User::factory()->admin()->create()->id,
        ])->save();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-123456',
            'id_document' => UploadedFile::fake()->image('cni-nette.jpg'),
        ])->assertSessionHasNoErrors();

        $fresh = $vendor->fresh();
        $this->assertSame('pending', $fresh->kyc_status);
        $this->assertNull($fresh->kyc_rejection_reason);
        $this->assertNull($fresh->kyc_reviewed_at);
        $this->assertNull($fresh->kyc_reviewed_by);
    }

    public function test_only_images_and_pdfs_are_accepted(): void
    {
        $vendor = Vendor::factory()->create();

        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-123456',
            'id_document' => UploadedFile::fake()->create('cni.exe', 100, 'application/x-msdownload'),
        ])->assertSessionHasErrors('id_document');
    }

    public function test_submissions_are_rate_limited(): void
    {
        Storage::fake('local');
        $vendor = Vendor::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $vendor->forceFill(['kyc_status' => 'none'])->save();
            $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
                'tax_id' => "IFU-{$i}",
                'id_document' => UploadedFile::fake()->image('cni.jpg'),
            ])->assertSessionHasNoErrors();
        }

        $vendor->forceFill(['kyc_status' => 'none'])->save();
        $this->actingAs($vendor->user)->post(route('vendor.verification.store'), [
            'tax_id' => 'IFU-final',
            'id_document' => UploadedFile::fake()->image('cni.jpg'),
        ])->assertStatus(429);
    }
}
