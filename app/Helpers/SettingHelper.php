<?php

namespace App\Helpers;

use App\Models\Setting;

class SettingHelper
{
    /**
     * Obtenir un paramètre
     */
    public static function get(string $key, $default = null)
    {
        return Setting::get($key, $default);
    }

    /**
     * Obtenir le nom du site
     */
    public static function siteName(): string
    {
        return self::get('site_name', 'MarcheMarket');
    }

    /**
     * Obtenir la description du site
     */
    public static function siteDescription(): string
    {
        return self::get('site_description', 'La marketplace de gros qui relie les fournisseurs vérifiés d\'Afrique de l\'Ouest aux acheteurs professionnels.');
    }

    /**
     * Obtenir le slogan du site
     */
    public static function siteSlogan(): string
    {
        return self::get('site_slogan', 'Achetez en gros, directement auprès des fournisseurs.');
    }

    /**
     * Obtenir le texte du pied de page
     */
    public static function footerText(): string
    {
        return self::get('footer_text', 'Projet de démonstration.');
    }

    /**
     * Obtenir les liens du footer par groupe
     */
    public static function footerLinks(string $group): array
    {
        return self::get("footer_{$group}_links", []);
    }

    /**
     * Obtenir les informations de l'entreprise
     */
    public static function companyInfo(): array
    {
        return [
            'name' => self::get('company_name', ''),
            'address' => self::get('company_address', ''),
            'city' => self::get('company_city', ''),
            'country' => self::get('company_country', ''),
            'phone' => self::get('company_phone', ''),
            'email' => self::get('company_email', ''),
            'tax_id' => self::get('company_tax_id', ''),
            'registration_number' => self::get('company_registration_number', ''),
        ];
    }

    /**
     * Obtenir les informations de contact
     */
    public static function contactInfo(): array
    {
        return [
            'email' => self::get('contact_email', ''),
            'phone' => self::get('contact_phone', ''),
            'address' => self::get('contact_address', ''),
        ];
    }

    /**
     * Obtenir les réseaux sociaux
     */
    public static function socialLinks(): array
    {
        return [
            'facebook' => self::get('social_facebook', ''),
            'twitter' => self::get('social_twitter', ''),
            'linkedin' => self::get('social_linkedin', ''),
            'instagram' => self::get('social_instagram', ''),
        ];
    }

    /**
     * Vérifier si le mode maintenance est activé
     */
    public static function isMaintenanceMode(): bool
    {
        return self::get('maintenance_mode', false);
    }

    /**
     * Obtenir le message de maintenance
     */
    public static function maintenanceMessage(): string
    {
        return self::get('maintenance_message', 'Le site est en maintenance. Veuillez revenir plus tard.');
    }
}
