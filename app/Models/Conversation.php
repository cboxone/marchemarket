<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['buyer_id', 'vendor_id', 'product_id', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function lastMessage(): ?Message
    {
        return $this->messages()->latest()->first();
    }

    /**
     * Nombre de messages non lus pour un utilisateur donné
     * (c'est-à-dire envoyés par l'autre partie et jamais ouverts).
     */
    public function unreadCountFor(int $userId): int
    {
        return $this->messages()->where('sender_id', '!=', $userId)->whereNull('read_at')->count();
    }
}
