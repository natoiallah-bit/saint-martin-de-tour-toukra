<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use Concerns\ParAnneeScolaire;

    protected $fillable = ['eleve_id', 'matiere_id', 'trimestre', 'annee_scolaire', 'devoir1', 'devoir2', 'moyenne_classe', 'moyenne_composition'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    // Moyenne de la matière = (moyenne classe + moyenne composition) / 2
    // (moyenne_classe est elle-même la moyenne des 2 devoirs, calculée à l'enregistrement)
    public function getMoyenneMatiereAttribute(): ?float
    {
        if ($this->moyenne_classe === null || $this->moyenne_composition === null) {
            return null;
        }
        return round(($this->moyenne_classe + $this->moyenne_composition) / 2, 2);
    }
}
