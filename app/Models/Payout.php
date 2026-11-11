<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'vendor_id',
        'amount',
        'status',
        'payout_method',
        'payout_reference',
        'admin_note',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PAID => __('Payé'),
            self::STATUS_REJECTED => __('Rejeté'),
            default => __('En attente'),
        };
    }
}
