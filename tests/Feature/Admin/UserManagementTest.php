<?php

namespace Tests\Feature\Admin;

use App\Mail\AccountSuspendedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_suspend_a_user_and_an_email_is_sent(): void
    {
        Mail::fake();

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $customer));

        $response->assertRedirect();
        $this->assertTrue($customer->fresh()->is_banned);
        Mail::assertSent(AccountSuspendedMail::class);
    }

    public function test_an_admin_can_reactivate_a_suspended_user(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->banned()->create();

        $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $customer));

        $this->assertFalse($customer->fresh()->is_banned);
    }

    public function test_an_admin_cannot_suspend_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $admin));

        $response->assertForbidden();
        $this->assertFalse($admin->fresh()->is_banned);
    }

    public function test_an_admin_cannot_suspend_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->patch(route('admin.users.toggle-ban', $otherAdmin));

        $response->assertForbidden();
    }

    public function test_a_non_admin_cannot_access_user_management(): void
    {
        $customer = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($customer)->patch(route('admin.users.toggle-ban', $target));

        $response->assertForbidden();
    }
}
