<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carnet d'adresses de l'acheteur : évite de ressaisir l'adresse de
     * livraison à chaque commande ou devis accepté.
     */
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Nom libre pour s'y retrouver dans la liste (« Entrepôt », « Bureau »...).
            $table->string('label', 60);
            $table->string('recipient_name')->nullable();
            $table->string('address');
            $table->string('city', 120);
            $table->string('country', 120);
            $table->string('phone', 30);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
