<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceTier extends Model
{
    use HasFactory;

    protected $fillable = ['product_id', 'min_qty', 'max_qty', 'unit_price'];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'max_qty' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
