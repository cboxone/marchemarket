<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\VendorKycStatusMail;
use App\Models\Vendor;
use Illuminate\Http\Request;

/**
 * Dossier de vérification (KYC) du vendeur : IFU, RCCM, pièce d'identité et
 * document professionnel. Le badge « Vérifié » affiché publiquement
 * (`Vendor::is_verified`) n'est activé automatiquement que lorsqu'un admin
 * approuve ce dossier — voir Admin\VerificationController.
 */
class VerificationController extends Controller
{
    public function edit(Request $request)
    {
        return view('vendor.verification.edit', ['vendor' => $request->user()->vendor]);
    }

    public function store(Request $request)
    {
        $vendor = $request->user()->vendor;

        if (! $vendor->canSubmitKyc()) {
            return back()->withErrors(['kyc' => __('Un dossier est déjà en cours d\'examen ou a déjà été approuvé.')]);
        }

        $data = $request->validate([
            'tax_id' => ['required', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            // Pièces scannées : photo ou PDF, jamais optimisées pour ne pas dégrader leur lisibilité.
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'business_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        $vendor->forceFill([
            'tax_id' => $data['tax_id'],
            'registration_number' => $data['registration_number'] ?? null,
            // Disque `local` : privé, jamais exposé publiquement — une pièce
            // d'identité n'a rien à faire derrière une URL devinable.
            'kyc_id_document_path' => $request->file('id_document')->store('kyc', 'local'),
            'kyc_business_document_path' => $request->hasFile('business_document')
                ? $request->file('business_document')->store('kyc', 'local')
                : null,
            'kyc_status' => Vendor::KYC_PENDING,
            'kyc_submitted_at' => now(),
            'kyc_reviewed_by' => null,
            'kyc_reviewed_at' => null,
            'kyc_rejection_reason' => null,
        ])->save();

        return redirect()->route('vendor.verification.edit')
            ->with('status', __('Dossier envoyé. Notre équipe le vérifie sous quelques jours ouvrés.'));
    }
}
