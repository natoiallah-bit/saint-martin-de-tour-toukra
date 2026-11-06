<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Materiel extends Model
{
    protected $fillable = [
        'nom', 'categorie', 'quantite', 'etat', 'valeur_estimee',
        'localisation', 'date_acquisition', 'remarque', 'cycle_id',
    ];

    // Cycle du matériel (vide = matériel commun, géré par la Direction)
    public function cycle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    protected $casts = [
        'date_acquisition' => 'date',
    ];

    public static function categories(): array
    {
        return ['Mobilier', 'Informatique', 'Pédagogique', 'Entretien', 'Sécurité', 'Cuisine / Cantine', 'Autre'];
    }

    public function getLibelleEtatAttribute(): string
    {
        return match ($this->etat) {
            'bon' => 'Bon état',
            'moyen' => 'État moyen',
            'mauvais' => 'Mauvais état',
            'hors_service' => 'Hors service',
            default => $this->etat,
        };
    }

    public function getValeurTotaleAttribute(): float
    {
        return $this->quantite * $this->valeur_estimee;
    }
}
