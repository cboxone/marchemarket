<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taux de commission (en %) figé sur chaque article à la commande.
     * Les articles déjà existants reçoivent 0 : la commission n'est jamais
     * appliquée rétroactivement à des ventes passées.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(0)->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('commission_rate');
        });
    }
};
