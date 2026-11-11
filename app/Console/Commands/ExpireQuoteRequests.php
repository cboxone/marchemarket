<?php

namespace App\Console\Commands;

use App\Models\QuoteRequest;
use Illuminate\Console\Command;

class ExpireQuoteRequests extends Command
{
    protected $signature = 'quotes:expire';

    protected $description = 'Marque comme expirées les demandes de devis et les devis dont l\'échéance est dépassée';

    public function handle(): int
    {
        $count = QuoteRequest::query()
            ->whereIn('status', [QuoteRequest::STATUS_PENDING, QuoteRequest::STATUS_QUOTED])
            ->where('expires_at', '<', now())
            ->update(['status' => QuoteRequest::STATUS_EXPIRED]);

        $this->info("{$count} demande(s) de devis expirée(s).");

        return self::SUCCESS;
    }
}
