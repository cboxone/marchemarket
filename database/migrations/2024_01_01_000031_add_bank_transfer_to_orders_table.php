<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Second moyen de paiement : virement bancaire manuel. Contrairement à
     * KkiaPay, rien n'est vérifié automatiquement — ces colonnes tracent le
     * choix de l'acheteur, sa preuve de dépôt, puis la décision de l'admin.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Posé dès que l'acheteur choisit ce moyen de paiement, avant
            // même qu'il dépose une preuve : sert de point de départ au
            // délai de grâce (voir Order::unpaidCancelDeadline()).
            $table->timestamp('bank_transfer_chosen_at')->nullable()->after('payment_method');
            // Référence libre donnée par l'acheteur (nom de l'expéditeur,
            // numéro de virement...) pour aider l'admin à le retrouver sur
            // son relevé — jamais utilisée comme preuve à elle seule.
            $table->string('bank_transfer_reference')->nullable()->after('bank_transfer_chosen_at');
            $table->string('bank_transfer_proof_path')->nullable()->after('bank_transfer_reference');
            $table->timestamp('bank_transfer_declared_at')->nullable()->after('bank_transfer_proof_path');
            $table->foreignId('bank_transfer_confirmed_by')->nullable()->after('bank_transfer_declared_at')->constrained('users')->nullOnDelete();
            $table->string('bank_transfer_rejected_reason')->nullable()->after('bank_transfer_confirmed_by');
            $table->timestamp('bank_transfer_rejected_at')->nullable()->after('bank_transfer_rejected_reason');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_transfer_confirmed_by');
            $table->dropColumn([
                'bank_transfer_chosen_at', 'bank_transfer_reference', 'bank_transfer_proof_path',
                'bank_transfer_declared_at', 'bank_transfer_rejected_reason', 'bank_transfer_rejected_at',
            ]);
        });
    }
};
