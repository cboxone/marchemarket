<?php

namespace App\Console\Commands;

use App\Models\Dispute;
use Illuminate\Console\Command;

class EscalateOverdueDisputes extends Command
{
    protected $signature = 'disputes:escalate-overdue';

    protected $description = "Escalade vers l'arbitrage admin les litiges auxquels le fournisseur n'a pas répondu dans le délai imparti";

    public function handle(): int
    {
        $count = 0;

        Dispute::query()
            ->where('status', Dispute::STATUS_OPEN)
            ->where('vendor_deadline_at', '<', now())
            ->chunkById(100, function ($disputes) use (&$count) {
                foreach ($disputes as $dispute) {
                    $dispute->update([
                        'status' => Dispute::STATUS_ESCALATED,
                        'escalated_at' => now(),
                        'admin_target_at' => now()->addDays(config('marketplace.disputes.admin_target_days')),
                    ]);
                    $count++;
                }
            });

        $this->info("{$count} litige(s) escaladé(s) vers l'arbitrage admin.");

        return self::SUCCESS;
    }
}
