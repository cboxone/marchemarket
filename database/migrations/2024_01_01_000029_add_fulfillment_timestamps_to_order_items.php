<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date d'expédition et de livraison de chaque ligne, utilisées pour
     * calculer quand le solde correspondant devient retirable (voir
     * Vendor::revenueBreakdown()) et pour la fenêtre de dépôt d'un litige.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('shipped_at')->nullable()->after('fulfillment_status');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });

        // Rattrapage : les lignes déjà expédiées/livrées avant ce correctif
        // reçoivent la date de leur dernière modification comme meilleure
        // estimation disponible (updated_at a changé au moment du passage de
        // fulfillment_status, en l'absence d'un journal plus précis).
        DB::table('order_items')->where('fulfillment_status', 'shipped')->whereNull('shipped_at')
            ->update(['shipped_at' => DB::raw('updated_at')]);
        DB::table('order_items')->where('fulfillment_status', 'delivered')->whereNull('delivered_at')
            ->update(['delivered_at' => DB::raw('updated_at'), 'shipped_at' => DB::raw('COALESCE(shipped_at, updated_at)')]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['shipped_at', 'delivered_at']);
        });
    }
};
