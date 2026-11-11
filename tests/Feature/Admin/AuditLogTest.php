<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspending_a_user_is_recorded_in_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $customer));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'user.banned',
            'subject_type' => User::class,
            'subject_id' => $customer->id,
        ]);
    }

    public function test_verifying_a_vendor_is_recorded_in_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create();

        $this->actingAs($admin)->patch(route('admin.vendors.toggle-verification', $vendor));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'vendor.verified',
            'subject_type' => Vendor::class,
            'subject_id' => $vendor->id,
        ]);
    }

    public function test_deleting_a_category_is_recorded_in_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create(['name' => 'Catégorie à supprimer']);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category));

        $this->assertDatabaseHas('audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'category.deleted',
        ]);
    }

    public function test_the_audit_log_page_lists_entries(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $customer));

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));

        $response->assertOk();
        $response->assertSee($customer->name);
    }

    public function test_a_non_admin_cannot_view_the_audit_log(): void
    {
        $customer = User::factory()->create();

        $response = $this->actingAs($customer)->get(route('admin.audit-logs.index'));

        $response->assertForbidden();
    }
}
