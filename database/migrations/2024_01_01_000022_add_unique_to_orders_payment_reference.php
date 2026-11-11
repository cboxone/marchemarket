<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une même transaction KkiaPay ne doit jamais pouvoir solder deux
     * commandes. Les valeurs NULL (commandes non payées) restent autorisées
     * en plusieurs exemplaires.
     *
     * Avant de migrer une base existante, vérifiez qu'il n'y a pas déjà de
     * doublons : SELECT payment_reference, COUNT(*) FROM orders
     * WHERE payment_reference IS NOT NULL GROUP BY payment_reference HAVING COUNT(*) > 1;
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['payment_reference']);
        });
    }
};
