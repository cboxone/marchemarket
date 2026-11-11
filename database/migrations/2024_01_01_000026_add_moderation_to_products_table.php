<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suspension d'une annonce par l'administration. Distincte de
     * `is_active` (choix du vendeur) : un produit suspendu a aussi
     * is_active = false — donc il disparaît partout où le catalogue filtre
     * déjà sur is_active — mais le vendeur ne peut pas le remettre en ligne
     * lui-même.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->timestamp('admin_suspended_at')->nullable()->after('is_active');
            $table->string('admin_suspension_reason')->nullable()->after('admin_suspended_at');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['admin_suspended_at', 'admin_suspension_reason']);
        });
    }
};
