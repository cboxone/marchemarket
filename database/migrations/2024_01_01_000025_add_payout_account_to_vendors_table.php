<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compte Mobile Money enregistré par le vendeur pour ses retraits
     * (opérateur + numéro), à la place d'un champ texte libre ressaisi à
     * chaque demande de reversement.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('payout_provider', 20)->nullable()->after('city');
            $table->string('payout_phone', 20)->nullable()->after('payout_provider');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['payout_provider', 'payout_phone']);
        });
    }
};
