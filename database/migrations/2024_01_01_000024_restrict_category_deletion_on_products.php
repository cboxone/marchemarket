<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `products.category_id` était en ON DELETE CASCADE : supprimer une
     * catégorie effaçait tous ses produits. On passe en RESTRICT côté base
     * (en plus du garde-fou applicatif de Category::booted()).
     *
     * Ignoré sous SQLite (tests) : modifier une clé étrangère y impose de
     * reconstruire la table ; la protection y est assurée par le modèle.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
