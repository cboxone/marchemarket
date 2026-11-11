<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Paramètres généraux
        Setting::set('site_name', 'MarcheMarket', 'string', 'general', 'Nom du site');
        Setting::set('site_description', 'La marketplace de gros qui relie les fournisseurs vérifiés d\'Afrique de l\'Ouest aux acheteurs professionnels.', 'string', 'general', 'Description du site');
        Setting::set('site_slogan', 'Achetez en gros, directement auprès des fournisseurs.', 'string', 'general', 'Slogan du site');
        Setting::set('footer_text', 'Projet de démonstration.', 'string', 'general', 'Texte du pied de page');
        Setting::set('cookie_notice_text', 'Nous utilisons des cookies essentiels pour améliorer votre expérience.', 'string', 'general', 'Texte de notice de cookies');
        Setting::set('enable_cookie_notice', true, 'boolean', 'general', 'Afficher la notice de cookies');

        // Paramètres de la plateforme
        Setting::set('commission_percent', 10, 'integer', 'platform', 'Commission de la plateforme en %');
        Setting::set('unpaid_order_ttl_minutes', 60, 'integer', 'platform', 'Délai avant annulation commande impayée (minutes)');
        Setting::set('quote_request_days', 14, 'integer', 'platform', 'Délai réponse devis (jours)');

        // Délais de reversement
        Setting::set('payout_release_after_delivery_days', 7, 'integer', 'platform', 'Délai reversement après livraison (jours)');
        Setting::set('payout_release_after_shipping_days', 21, 'integer', 'platform', 'Délai reversement après expédition (jours)');
        Setting::set('payout_release_after_payment_days', 45, 'integer', 'platform', 'Délai reversement après paiement (jours)');

        // Paramètres de litiges
        Setting::set('dispute_window_after_delivery_days', 7, 'integer', 'platform', 'Fenêtre litige après livraison (jours)');
        Setting::set('dispute_window_after_payment_days', 15, 'integer', 'platform', 'Fenêtre litige après paiement (jours)');
        Setting::set('dispute_vendor_response_hours', 72, 'integer', 'platform', 'Délai réponse vendeur litige (heures)');
        Setting::set('dispute_admin_target_days', 5, 'integer', 'platform', 'Cible arbitrage admin (jours)');

        // Paramètres de virement bancaire
        Setting::set('bank_transfer_enabled', false, 'boolean', 'payment', 'Activer le virement bancaire');
        Setting::set('bank_transfer_bank_name', '', 'string', 'payment', 'Nom de la banque');
        Setting::set('bank_transfer_account_name', '', 'string', 'payment', 'Nom du titulaire du compte');
        Setting::set('bank_transfer_account_number', '', 'string', 'payment', 'Numéro de compte');
        Setting::set('bank_transfer_iban', '', 'string', 'payment', 'IBAN');
        Setting::set('bank_transfer_swift', '', 'string', 'payment', 'Code SWIFT/BIC');
        Setting::set('bank_transfer_window_hours', 72, 'integer', 'payment', 'Fenêtre virement (heures)');

        // Paramètres KkiaPay
        Setting::set('kkiapay_enabled', false, 'boolean', 'payment', 'Activer KkiaPay');
        Setting::set('kkiapay_public_key', '', 'string', 'payment', 'Clé publique KkiaPay');
        Setting::set('kkiapay_private_key', '', 'string', 'payment', 'Clé privée KkiaPay');
        Setting::set('kkiapay_secret_key', '', 'string', 'payment', 'Clé secrète KkiaPay');
        Setting::set('kkiapay_api_key', '', 'string', 'payment', 'Clé API KkiaPay');

        // Informations légales de l'entreprise
        Setting::set('company_name', '', 'string', 'legal', 'Nom de l\'entreprise');
        Setting::set('company_address', '', 'string', 'legal', 'Adresse de l\'entreprise');
        Setting::set('company_city', '', 'string', 'legal', 'Ville de l\'entreprise');
        Setting::set('company_country', '', 'string', 'legal', 'Pays de l\'entreprise');
        Setting::set('company_phone', '', 'string', 'legal', 'Téléphone de l\'entreprise');
        Setting::set('company_email', '', 'string', 'legal', 'Email de l\'entreprise');
        Setting::set('company_tax_id', '', 'string', 'legal', 'Identifiant fiscal (IFU)');
        Setting::set('company_registration_number', '', 'string', 'legal', 'Numéro d\'enregistrement (RCCM)');

        // Footer liens
        Setting::set('footer_buyer_links', json_encode([
            ['label' => 'Parcourir le catalogue', 'route' => 'products.index'],
            ['label' => 'Créer un compte', 'route' => 'register'],
        ]), 'json', 'footer', 'Liens acheteurs');

        Setting::set('footer_vendor_links', json_encode([
            ['label' => 'Devenir fournisseur', 'route' => 'register'],
            ['label' => 'Garantie commerciale disponible', 'route' => null],
        ]), 'json', 'footer', 'Liens vendeurs');

        Setting::set('footer_trust_links', json_encode([
            ['label' => 'Fournisseurs vérifiés', 'route' => null],
            ['label' => 'Garantie commerciale (Trade Assurance)', 'route' => null],
        ]), 'json', 'footer', 'Liens confiance');

        Setting::set('footer_legal_links', json_encode([
            ['label' => 'Conditions générales', 'route' => 'legal.terms'],
            ['label' => 'Confidentialité', 'route' => 'legal.privacy'],
            ['label' => 'Mentions légales', 'route' => 'legal.notice'],
        ]), 'json', 'footer', 'Liens légaux');

        // Paramètres de contact
        Setting::set('contact_email', 'contact@marchemarket.test', 'string', 'contact', 'Email de contact');
        Setting::set('contact_phone', '', 'string', 'contact', 'Téléphone de contact');
        Setting::set('contact_address', '', 'string', 'contact', 'Adresse de contact');

        // Réseaux sociaux
        Setting::set('social_facebook', '', 'string', 'social', 'URL Facebook');
        Setting::set('social_twitter', '', 'string', 'social', 'URL Twitter/X');
        Setting::set('social_linkedin', '', 'string', 'social', 'URL LinkedIn');
        Setting::set('social_instagram', '', 'string', 'social', 'URL Instagram');

        // Maintenance
        Setting::set('maintenance_mode', false, 'boolean', 'system', 'Mode maintenance');
        Setting::set('maintenance_message', 'Le site est en maintenance. Veuillez revenir plus tard.', 'string', 'system', 'Message de maintenance');

        $this->command->info('Paramètres configurés avec succès !');
    }
}
