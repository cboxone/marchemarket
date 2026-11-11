<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un litige porte sur (commande, fournisseur) : une commande groupant
     * plusieurs fournisseurs peut donner lieu à un litige distinct par
     * fournisseur, ce qui correspond au découpage déjà utilisé pour la
     * facture et pour le solde vendeur.
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();

            $table->string('reason', 30);
            $table->text('description');
            $table->json('evidence_paths')->nullable();

            $table->string('status', 20)->default('open')->index();
            $table->timestamp('vendor_deadline_at');

            // Réponse du vendeur
            $table->string('vendor_response_type', 20)->nullable();
            $table->decimal('vendor_response_amount', 12, 2)->nullable();
            $table->text('vendor_response_note')->nullable();
            $table->timestamp('vendor_responded_at')->nullable();

            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('admin_target_at')->nullable();

            // Décision finale (vendeur, en acceptation totale immédiate ; ou admin après arbitrage)
            $table->string('decision', 20)->nullable();          // refund_full | refund_partial | reject
            $table->decimal('refund_amount', 12, 2)->nullable();
            $table->text('resolution_note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->string('refund_status', 20)->default('not_applicable');
            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            // Un seul litige ouvert (open/escalated) à la fois par commande et
            // par fournisseur — appliqué aussi en base, pas seulement côté
            // application, via un index partiel ci-dessous (MySQL/PostgreSQL).
            $table->index(['order_id', 'vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
