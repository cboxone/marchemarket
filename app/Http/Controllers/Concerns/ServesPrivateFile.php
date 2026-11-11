<?php

namespace App\Http\Controllers\Concerns;

/**
 * Sert un fichier stocké sur le disque privé `local` (storage/app, jamais
 * exposé par le lien symbolique public/storage) après que l'appelant a déjà
 * vérifié l'autorisation d'y accéder. Utilisé pour les pièces d'identité
 * KYC, les preuves de virement bancaire et les photos jointes à un litige :
 * des documents personnels qui ne doivent jamais être accessibles par une
 * simple URL devinée ou partagée, contrairement aux photos produit
 * (disque `public`, volontairement accessibles à tous).
 */
trait ServesPrivateFile
{
    private function servePrivateFile(?string $path)
    {
        abort_if(! $path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path);
    }
}
