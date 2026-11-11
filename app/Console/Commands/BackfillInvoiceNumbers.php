<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class BackfillInvoiceNumbers extends Command
{
    protected $signature = 'invoices:backfill';

    protected $description = 'Numérote, dans l\'ordre de création, les commandes payées avant la mise en place des factures';

    public function handle(): int
    {
        $count = 0;

        // Ordre des identifiants = ordre de création : les numéros suivent
        // la chronologie des commandes. À lancer une fois après la migration.
        Order::query()
            ->where('payment_status', 'paid')
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->whereNull('invoice_number')
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $order->assignInvoiceNumber();
                    $count++;
                }
            });

        $this->info("{$count} commande(s) numérotée(s).");

        return self::SUCCESS;
    }
}
