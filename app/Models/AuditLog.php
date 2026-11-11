<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    protected $fillable = ['admin_id', 'action', 'subject_type', 'subject_id', 'description'];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Enregistre une action admin. À appeler depuis les contrôleurs
     * Admin\* juste après chaque action sensible (suspension, suppression,
     * validation d'un reversement, etc.) — voir app/Http/Controllers/Admin.
     */
    public static function record(string $action, string $description, ?Model $subject = null): self
    {
        return self::create([
            'admin_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
        ]);
    }
}
