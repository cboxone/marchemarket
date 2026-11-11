<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Obtenir la valeur d'un paramètre
     */
    public static function get(string $key, $default = null)
    {
        return Cache::rememberForever("settings.{$key}", function () use ($key, $default) {
            $setting = static::where('key', $key)->first();
            if (!$setting) {
                return $default;
            }

            return match ($setting->type) {
                'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $setting->value,
                'float' => (float) $setting->value,
                'json' => json_decode($setting->value, true),
                default => $setting->value,
            };
        });
    }

    /**
     * Définir la valeur d'un paramètre
     */
    public static function set(string $key, $value, string $type = 'string', string $group = 'general', string $description = null): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : $value,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );

        Cache::forget("settings.{$key}");
    }

    /**
     * Obtenir tous les paramètres d'un groupe
     */
    public static function getByGroup(string $group): array
    {
        return Cache::rememberForever("settings.group.{$group}", function () use ($group) {
            return static::where('group', $group)
                ->get()
                ->mapWithKeys(function ($setting) {
                    return [$setting->key => match ($setting->type) {
                        'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                        'integer' => (int) $setting->value,
                        'float' => (float) $setting->value,
                        'json' => json_decode($setting->value, true),
                        default => $setting->value,
                    }];
                })
                ->toArray();
        });
    }

    /**
     * Vider le cache des paramètres
     */
    public static function clearCache(): void
    {
        Cache::forgetMatching('settings.*');
    }
}
