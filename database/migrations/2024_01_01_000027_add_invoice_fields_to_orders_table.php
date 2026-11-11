<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numérotation des factures : séquentielle, sans trou, par année
     * (FAC-2026-000001…). Le compteur est dans sa propre table pour pouvoir
     * être verrouillé le temps d'attribuer un numéro.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->unique()->after('payment_reference');
            $table->timestamp('invoiced_at')->nullable()->after('invoice_number');
        });

        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['invoice_number', 'invoiced_at']);
        });
    }
};
