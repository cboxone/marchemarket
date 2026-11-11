{{--
    Bandeau d'information sur les cookies. Le site ne dépose que des cookies
    strictement nécessaires (session, sécurité, « se souvenir de moi ») —
    aucun cookie publicitaire ni de mesure d'audience — donc ce bandeau est
    une information, pas un mur de consentement à choix multiples : il n'y a
    rien à activer ou désactiver, juste à faire savoir.

    Le choix de fermeture est mémorisé en local (localStorage), pas en
    session ni en base : il ne concerne que ce navigateur, n'a aucune valeur
    contractuelle, et ne doit jamais nécessiter d'aller-retour serveur.
--}}
<div
    id="cookie-notice"
    class="fixed inset-x-0 bottom-0 z-50 hidden border-t border-line bg-white px-4 py-4 shadow-[0_-4px_12px_rgba(0,0,0,0.06)] sm:px-6"
    role="region"
    aria-label="{{ __('Information sur les cookies') }}"
>
    <div class="mx-auto flex max-w-5xl flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-navy-600">
            {{ __("Ce site utilise uniquement des cookies nécessaires à son fonctionnement (connexion, sécurité) — aucun cookie publicitaire ni de mesure d'audience.") }}
            <a href="{{ route('legal.privacy') }}#cookies" class="text-trade-green hover:underline">{{ __('En savoir plus') }}</a>
        </p>
        <button type="button" onclick="document.getElementById('cookie-notice').classList.add('hidden'); localStorage.setItem('cookie-notice-dismissed', '1');" class="btn-dark shrink-0 whitespace-nowrap">
            {{ __('Compris') }}
        </button>
    </div>
</div>

<script>
    if (! localStorage.getItem('cookie-notice-dismissed')) {
        document.getElementById('cookie-notice').classList.remove('hidden');
    }
</script>
