<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsNotBanned;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            EnsureUserIsNotBanned::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Envoie les exceptions non gérées à Sentry pour un suivi en
        // production (voir config/sentry.php et la variable d'environnement
        // SENTRY_LARAVEL_DSN). Sans DSN configuré, cet appel ne fait
        // strictement rien : aucune donnée n'est envoyée nulle part tant que
        // la variable d'environnement reste vide, ce qui est le cas par
        // défaut en développement.
        Integration::handles($exceptions);
    })->create();
