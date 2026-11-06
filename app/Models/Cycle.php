<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cycle extends Model
{
    protected $fillable = ['ecole_id', 'nom', 'couleur'];

    // Couleur des cartes quand le cycle n'en a pas encore : violet (maternelle),
    // vert (primaire), bleu (collège), rouge (lycée), bleu nuit pour les autres
    public const COULEURS = [
        'maternelle' => '#6a2c91',
        'primaire' => '#1b7a3a',
        'college' => '#1d4ea8',
        'lycee' => '#b0182e',
    ];
    public const COULEUR_NEUTRE = '#1b2b5c';

    protected static function booted(): void
    {
        // Un cycle renommé change la place de ses classes dans les listes
        static::saved(function (Cycle $cycle) {
            if ($cycle->wasChanged('nom')) {
                $cycle->classes->each->save();
            }
        });
    }

    public static function couleurParDefaut(?string $nom): string
    {
        $nom = \Illuminate\Support\Str::of((string) $nom)->ascii()->lower();
        foreach (self::COULEURS as $motCle => $couleur) {
            if ($nom->contains($motCle)) {
                return $couleur;
            }
        }

        return self::COULEUR_NEUTRE;
    }

    public function getCouleurCarteAttribute(): string
    {
        return $this->couleur ?: self::couleurParDefaut($this->nom);
    }

    public function ecole(): BelongsTo
    {
        return $this->belongsTo(Ecole::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClasseScolaire::class, 'cycle_id');
    }
}
