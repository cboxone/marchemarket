<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vérification d'identité du vendeur (KYC) : IFU, RCCM, pièce d'identité
     * et document professionnel. Distincte de `is_verified` (le badge
     * affiché publiquement) : un dossier approuvé positionne `is_verified`
     * à true, mais l'admin garde par ailleurs la possibilité d'activer ou de
     * retirer le badge à la main (`vendors.toggle-verification`, déjà
     * existant) sans repasser par un nouveau dossier — utile par exemple
     * après un renommage de boutique, qui retire le badge sans invalider les
     * documents déjà vérifiés.
     */
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('tax_id')->nullable()->after('years_active');
            $table->string('registration_number')->nullable()->after('tax_id');
            // none : jamais soumis · pending : en attente d'examen ·
            // approved : dossier validé · rejected : refusé, à corriger.
            $table->string('kyc_status', 20)->default('none')->after('registration_number');
            $table->string('kyc_id_document_path')->nullable()->after('kyc_status');
            $table->string('kyc_business_document_path')->nullable()->after('kyc_id_document_path');
            $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_business_document_path');
            $table->foreignId('kyc_reviewed_by')->nullable()->after('kyc_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('kyc_reviewed_at')->nullable()->after('kyc_reviewed_by');
            $table->string('kyc_rejection_reason')->nullable()->after('kyc_reviewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kyc_reviewed_by');
            $table->dropColumn([
                'tax_id', 'registration_number', 'kyc_status', 'kyc_id_document_path',
                'kyc_business_document_path', 'kyc_submitted_at', 'kyc_reviewed_at', 'kyc_rejection_reason',
            ]);
        });
    }
};
