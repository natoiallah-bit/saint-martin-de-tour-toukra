<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends Model
{
    protected $fillable = ['cycle_id', 'enseignant_id', 'nom', 'coefficient', 'categorie'];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    // Le coefficient est fixé par la Direction, ou par le Principal du cycle de la matière
    // (ex. le directeur du primaire pour les matières du primaire)
    public static function peutFixerCoefficient(User $user, ?int $cycleId): bool
    {
        if ($user->role === 'direction') {
            return true;
        }

        return $user->aRole('principal') && (! $user->cycle_id || (int) $user->cycle_id === (int) $cycleId);
    }
}
