<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte immédiatement un utilisateur suspendu, même si sa session (ou
 * son cookie « se souvenir de moi ») était déjà ouverte au moment de la
 * suspension. Le contrôle fait à la connexion ne suffisait pas : un compte
 * banni gardait l'accès tant que sa session n'expirait pas.
 *
 * Le modèle utilisateur est relu en base à chaque requête par le guard, donc
 * la suspension prend effet dès la requête suivante.
 */
class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_banned) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = __("Ce compte a été suspendu. Contactez le support pour plus d'informations.");

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
