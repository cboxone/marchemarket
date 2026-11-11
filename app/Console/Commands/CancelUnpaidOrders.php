<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;

class CancelUnpaidOrders extends Command
{
    protected $signature = 'orders:cancel-unpaid {--minutes= : Délai en minutes pour les commandes hors virement bancaire (défaut : config marketplace.unpaid_order_ttl_minutes)}';

    protected $description = 'Annule les commandes jamais payées après le délai imparti et restitue leur stock et leur code promo';

    public function handle(): int
    {
        if ($this->option('minutes') !== null) {
            config(['marketplace.unpaid_order_ttl_minutes' => (int) $this->option('minutes')]);
        }

        // Pré-filtre SQL large : la plus COURTE des deux fenêtres possibles
        // (délai KkiaPay habituel, ou fenêtre virement bancaire) — c'est elle
        // qui détermine l'âge minimal des commandes potentiellement déjà
        // hors délai, quel que soit leur moyen de paiement. La date exacte,
        // propre à chaque commande, est ensuite recalculée en PHP via
        // Order::unpaidCancelDeadline() (voir sa docblock : elle dépend du
        // moyen de paiement choisi et de l'état de la déclaration). Prendre
        // la plus longue des deux aurait au contraire retardé la détection
        // des commandes KkiaPay abandonnées jusqu'à ce qu'elles atteignent,
        // elles aussi, l'âge de la fenêtre virement.
        $earliestWindowMinutes = min(
            (int) config('marketplace.unpaid_order_ttl_minutes'),
            (int) config('marketplace.bank_transfer.window_hours') * 60,
        );
        $cutoff = now()->subMinutes($earliestWindowMinutes);
        $cancelled = 0;

        Order::query()
            ->where('status', Order::STATUS_PENDING)
            ->where('payment_status', '!=', 'paid')
            ->where(function ($query) use ($cutoff) {
                $query->where('created_at', '<', $cutoff)
                    // Une commande peut avoir Ã©tÃ© crÃ©Ã©e rÃ©cemment mais
                    // basculÃ©e vers le virement plus tÃ´t. Dans ce cas, la
                    // fenÃªtre d'annulation se calcule depuis ce choix, pas
                    // depuis sa crÃ©ation.
                    ->orWhere('bank_transfer_chosen_at', '<', $cutoff);
            })
            ->chunkById(100, function ($orders) use (&$cancelled) {
                foreach ($orders as $order) {
                    $deadline = $order->unpaidCancelDeadline();

                    if ($deadline === null || ! $deadline->isPast()) {
                        continue;
                    }

                    // onlyIfUnpaid : le statut est relu sous verrou, donc une
                    // commande payée entre la sélection et l'annulation
                    // (webhook tardif) n'est jamais annulée par erreur.
                    if ($order->cancel(__('Commande non payée dans le délai imparti'), onlyIfUnpaid: true)) {
                        $cancelled++;
                    }
                }
            });

        $this->info("{$cancelled} commande(s) impayée(s) annulée(s).");

        return self::SUCCESS;
    }
}
