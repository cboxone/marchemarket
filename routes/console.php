<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Libère le stock bloqué par les commandes jamais payées.
// Nécessite l'entrée cron habituelle de Laravel : voir README (« Tâches planifiées »).
Schedule::command('orders:cancel-unpaid')->everyFiveMinutes()->withoutOverlapping();

// Les échéances sont déjà respectées à l'affichage et à l'acceptation ; cette
// commande ne fait que mettre les statuts en base en cohérence.
Schedule::command('quotes:expire')->hourly()->withoutOverlapping();

// Sans réponse du fournisseur dans le délai imparti, le litige passe à
// l'arbitrage admin plutôt que de rester bloqué indéfiniment.
Schedule::command('disputes:escalate-overdue')->everyFifteenMinutes()->withoutOverlapping();
