<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pack extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'name', 'slug', 'description', 'pack_price', 'is_active'];

    protected function casts(): array
    {
        return [
            'pack_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackItem::class);
    }

    /**
     * Prix total si chaque produit du pack était acheté séparément
     * (au prix de base) — sert à calculer l'économie mise en avant.
     */
    public function individualTotal(): float
    {
        return $this->items->sum(fn (PackItem $item) => $item->quantity * (float) $item->product->base_price);
    }

    public function savingsAmount(): float
    {
        return max(0, $this->individualTotal() - (float) $this->pack_price);
    }

    public function savingsPercent(): int
    {
        $total = $this->individualTotal();

        return $total > 0 ? (int) round(($this->savingsAmount() / $total) * 100) : 0;
    }
}
