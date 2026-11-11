<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CookieNoticeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_cookie_notice_appears_on_the_homepage(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(__('Compris'))
            ->assertSee(route('legal.privacy') . '#cookies', false);
    }

    public function test_the_cookie_notice_also_appears_on_the_login_page_for_guests(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('cookie-notice', false);
    }

    public function test_the_privacy_policy_lists_each_cookie_and_its_purpose(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee(__('Cookies'))
            ->assertSee(__('Un cookie de session, qui vous garde connecté pendant votre visite.'))
            ->assertSee(__("Un cookie de sécurité (jeton anti-falsification), qui protège les formulaires du site contre les attaques."))
            ->assertSee(__('Un cookie « se souvenir de moi », déposé uniquement si vous cochez cette option à la connexion, pour éviter de vous reconnecter à chaque visite.'));
    }

    public function test_the_privacy_policy_confirms_no_advertising_or_analytics_cookies(): void
    {
        $this->get(route('legal.privacy'))
            ->assertSee(__("MarcheMarket n'utilise aucun cookie publicitaire ni traceur de mesure d'audience. Seuls des cookies strictement nécessaires au fonctionnement du site sont déposés :"));
    }
}
