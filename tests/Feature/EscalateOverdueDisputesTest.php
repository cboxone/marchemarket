<?php

namespace Tests\Feature;

use App\Models\Dispute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EscalateOverdueDisputesTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_overdue_open_disputes_are_escalated(): void
    {
        $overdue = Dispute::factory()->create(['vendor_deadline_at' => now()->subHour()]);
        $notYet = Dispute::factory()->create(['vendor_deadline_at' => now()->addHour()]);
        $alreadyEscalated = Dispute::factory()->escalated()->create(['vendor_deadline_at' => now()->subHour()]);
        $resolved = Dispute::factory()->resolved('reject')->create(['vendor_deadline_at' => now()->subHour()]);

        $this->artisan('disputes:escalate-overdue')->expectsOutput("1 litige(s) escaladé(s) vers l'arbitrage admin.")->assertSuccessful();

        $fresh = $overdue->fresh();
        $this->assertSame('escalated', $fresh->status);
        $this->assertNotNull($fresh->escalated_at);
        $this->assertNotNull($fresh->admin_target_at);

        $this->assertSame('open', $notYet->fresh()->status);
        $this->assertSame('escalated', $alreadyEscalated->fresh()->status); // inchangé, pas recompté
        $this->assertSame('resolved', $resolved->fresh()->status);
    }

    public function test_the_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('disputes:escalate-overdue')->assertSuccessful();
    }
}
