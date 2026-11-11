<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('shipping_fee', 12, 2)->default(0)->after('total_amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Statut d'exécution propre à chaque vendeur, indépendant du statut
            // global de la commande (qui peut regrouper plusieurs vendeurs).
            $table->enum('fulfillment_status', ['pending', 'shipped', 'delivered'])
                ->default('pending')
                ->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipping_fee');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('fulfillment_status');
        });
    }
};
