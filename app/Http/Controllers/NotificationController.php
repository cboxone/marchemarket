<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Retourne le nombre de messages non lus de l'utilisateur connecté.
     * Interrogé toutes les 30 secondes par le gabarit (voir layouts/app.blade.php)
     * pour rafraîchir le badge de la barre de navigation sans recharger la page.
     *
     * Ce n'est pas du temps réel poussé par le serveur (pas de WebSocket) :
     * juste un sondage régulier, largement suffisant pour ce volume d'usage
     * et ne nécessitant aucune infrastructure supplémentaire (Reverb/Pusher).
     */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'count' => $request->user()->unreadMessagesCount(),
        ]);
    }
}
