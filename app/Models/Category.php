<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Category extends Model
{
    use HasFactory;

    private const TOP_LEVEL_CACHE_KEY = 'categories:top-level';

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'icon',
    ];

    protected static function booted(): void
    {
        // Filet de sécurité au niveau du modèle : quel que soit le chemin
        // (back-office, tinker, script), une catégorie qui contient encore
        // des produits ou des sous-catégories ne peut pas être supprimée.
        // Sans ce garde-fou, la clé étrangère `products.category_id` en
        // cascade effaçait silencieusement tous les produits des vendeurs.
        static::deleting(function (Category $category) {
            return $category->products()->doesntExist() && $category->children()->doesntExist();
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Catégories de premier niveau, triées par nom — utilisées sur quasiment
     * toutes les pages du site (barre de navigation du layout, accueil,
     * catalogue, sitemap). Sans cache, c'est une requête en base à chaque
     * affichage de chaque page, pour des données qui changent rarement.
     *
     * Mise en cache 6h, invalidée immédiatement à la création ou la
     * suppression d'une catégorie (voir Admin\CategoryController et
     * flushTopLevelCache() ci-dessous) — jamais plus de 6h de décalage dans
     * le pire des cas, et à jour tout de suite dans le cas courant.
     */
    public static function topLevel(): Collection
    {
        return Cache::remember(
            self::TOP_LEVEL_CACHE_KEY,
            now()->addHours(6),
            fn () => self::whereNull('parent_id')->orderBy('name')->get()
        );
    }

    public static function flushTopLevelCache(): void
    {
        Cache::forget(self::TOP_LEVEL_CACHE_KEY);
    }
}
