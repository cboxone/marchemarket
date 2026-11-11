<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'recipient_name',
        'address',
        'city',
        'country',
        'phone',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Une seule adresse par défaut par utilisateur : en marquer une
        // nouvelle retire le drapeau des autres, dans la même transaction
        // pour ne jamais laisser deux valeurs par défaut coexister le temps
        // de deux requêtes concurrentes.
        static::saving(function (Address $address) {
            if ($address->is_default && $address->user_id) {
                static::where('user_id', $address->user_id)
                    ->when($address->exists, fn ($q) => $q->whereKeyNot($address->id))
                    ->update(['is_default' => false]);
            }
        });

        // La suppression de l'adresse par défaut en promeut une autre
        // automatiquement, pour qu'il en reste toujours une désignée dès
        // qu'il en existe au moins une — évite un panier ou un devis sans
        // aucune proposition d'adresse pré-remplie.
        static::deleted(function (Address $address) {
            if (! $address->is_default) {
                return;
            }

            $next = static::where('user_id', $address->user_id)->first();
            $next?->update(['is_default' => true]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
