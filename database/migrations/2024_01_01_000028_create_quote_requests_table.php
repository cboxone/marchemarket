<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Demandes de devis (RFQ) : un acheteur demande un prix pour une quantité
     * à un fournisseur, qui répond par un devis chiffré. Accepté, le devis
     * devient une commande ordinaire (paiement KkiaPay, commission, facture).
     *
     * `expires_at` est l'échéance de l'état courant : la date limite de
     * réponse du fournisseur tant que la demande est « pending », puis la
     * date de validité du devis une fois « quoted ».
     */
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Instantané : la demande reste lisible si le produit est renommé ou supprimé.
            $table->string('product_name');
            $table->string('unit', 40)->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('target_price', 12, 2)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending')->index();

            // Réponse du fournisseur
            $table->decimal('quoted_unit_price', 12, 2)->nullable();
            $table->unsignedInteger('quoted_quantity')->nullable();
            $table->decimal('quoted_shipping_fee', 12, 2)->nullable();
            $table->text('vendor_note')->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->string('decline_reason')->nullable();

            $table->timestamp('expires_at')->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
