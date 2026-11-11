<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Autorise l'accès uniquement aux utilisateurs possédant l'un des rôles indiqués.
     * Usage : ->middleware('role:vendor,admin')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, __("Vous n'avez pas accès à cette page."));
        }

        return $next($request);
    }
}
