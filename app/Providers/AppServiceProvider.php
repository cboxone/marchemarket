<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();

        // Envoie automatiquement l'e-mail de vérification à chaque inscription
        // (RegisteredUserController déclenche déjà l'événement Registered).
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        // Personnalise le contenu de l'e-mail de vérification (texte en
        // français par défaut, traduit automatiquement en anglais si
        // l'utilisateur navigue en EN grâce à lang/en.json).
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject(__('Vérifiez votre adresse e-mail MarcheMarket'))
                ->greeting(__('Bonjour :name,', ['name' => $notifiable->name]))
                ->line(__('Merci de confirmer votre adresse e-mail en cliquant sur le bouton ci-dessous.'))
                ->action(__('Vérifier mon adresse e-mail'), $url)
                ->line(__("Si vous n'êtes pas à l'origine de cette inscription, aucune action n'est requise."));
        });

        // Partage la liste des identifiants de produits favoris de
        // l'utilisateur connecté à chaque carte produit affichée, pour
        // savoir si le cœur doit être plein ou vide — sans requête N+1 :
        // la variable statique mémorise le résultat pour toute la durée de
        // la requête HTTP, même si la carte produit est affichée 20 fois
        // sur la même page.
        View::composer('components.product-card', function ($view) {
            static $ids = null;

            if ($ids === null) {
                $ids = Auth::check() ? Auth::user()->wishlistedProducts()->pluck('products.id')->all() : [];
            }

            $view->with('wishlistedIds', $ids);
        });
    }

    /**
     * Limites de débit nommées, utilisées via `throttle:<nom>` dans
     * routes/web.php. La connexion a son propre compteur (voir
     * AuthenticatedSessionController). Les clés sont l'utilisateur connecté,
     * sinon l'adresse IP.
     *
     * Derrière un proxy ou un CDN (Nginx, Cloudflare…), pensez à déclarer les
     * proxys de confiance (`trustProxies`) : sinon tous les visiteurs
     * partagent la même adresse IP et la même limite.
     */
    private function configureRateLimiting(): void
    {
        $key = fn (Request $request) => $request->user()?->id ?: $request->ip();

        // Création de comptes en masse.
        RateLimiter::for('register', fn (Request $request) => Limit::perHour(10)->by($request->ip()));

        // Demandes de réinitialisation de mot de passe (envoi d'e-mails).
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        // Chaque message déclenche un e-mail vers le vendeur : plafond court ET horaire.
        RateLimiter::for('messages', fn (Request $request) => [
            Limit::perMinute(10)->by($key($request)),
            Limit::perHour(60)->by($key($request)),
        ]);

        // Recherche interactive : généreux, mais pas illimité.
        RateLimiter::for('autocomplete', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        // Devinette de codes promo par force brute.
        RateLimiter::for('coupon', fn (Request $request) => Limit::perMinute(10)->by($key($request)));

        // Validation de commande : verrouille le stock, à ne pas marteler.
        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(10)->by($key($request)));

        // Demandes de devis : chacune notifie un fournisseur par e-mail.
        RateLimiter::for('quotes', fn (Request $request) => Limit::perHour(10)->by($key($request)));

        // Dépôt d'un dossier de vérification (KYC) vendeur.
        RateLimiter::for('kyc', fn (Request $request) => Limit::perHour(5)->by($key($request)));

        // Dépôt d'une preuve de virement bancaire (upload de fichier).
        RateLimiter::for('bank-transfer', fn (Request $request) => Limit::perHour(10)->by($key($request)));

        // Ouverture de litiges : un e-mail part vers le fournisseur à chaque ouverture.
        RateLimiter::for('disputes', fn (Request $request) => Limit::perHour(10)->by($key($request)));

        // Vérification de paiement (appel API KkiaPay à chaque requête).
        RateLimiter::for('payment', fn (Request $request) => Limit::perMinute(10)->by($key($request)));
    }
}
