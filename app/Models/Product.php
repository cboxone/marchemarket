<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'category_id',
        'name',
        'slug',
        'description',
        'unit',
        'min_order_qty',
        'max_order_qty',
        'stock_qty',
        'base_price',
        'discount_percent',
        'promo_ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'min_order_qty' => 'integer',
            'max_order_qty' => 'integer',
            'stock_qty' => 'integer',
            'discount_percent' => 'integer',
            'promo_ends_at' => 'datetime',
            'is_active' => 'boolean',
            'admin_suspended_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(PriceTier::class)->orderBy('min_qty');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Détermine le prix unitaire applicable pour une quantité donnée,
     * en se basant sur les paliers de prix dégressifs (comme sur Alibaba).
     */
    public function priceForQty(int $qty): float
    {
        $tier = $this->priceTiers
            ->filter(fn ($t) => $qty >= $t->min_qty && ($t->max_qty === null || $qty <= $t->max_qty))
            ->sortByDesc('min_qty')
            ->first();

        $price = (float) ($tier->unit_price ?? $this->base_price);

        return $this->hasActivePromo() ? round($price * (1 - $this->discount_percent / 100), 2) : $price;
    }

    public function hasActivePromo(): bool
    {
        if (! $this->discount_percent) {
            return false;
        }

        return $this->promo_ends_at === null || $this->promo_ends_at->isFuture();
    }

    public function promoPrice(): float
    {
        return round((float) $this->base_price * (1 - $this->discount_percent / 100), 2);
    }

    public function averageRating(): float
    {
        return round($this->reviews->avg('rating') ?? 0, 1);
    }

    /** Annonce suspendue par l'administration (le vendeur ne peut pas la remettre en ligne). */
    public function isSuspended(): bool
    {
        return $this->admin_suspended_at !== null;
    }

    /**
     * Supprime le produit ET ses fichiers photo sur le disque : une simple
     * suppression en base laissait les images orphelines dans storage/.
     */
    public function purge(): void
    {
        foreach ($this->images as $image) {
            Storage::disk('public')->delete($image->path);
        }

        $this->delete();
    }
}
