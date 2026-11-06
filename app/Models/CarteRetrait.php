<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

// Carte de retrait d'une famille : parents, enfants en charge et personnes autorisées à venir les chercher
class CarteRetrait extends Model
{
    protected $table = 'cartes_retrait';

    protected $fillable = [
        'numero', 'pere_nom', 'pere_telephone', 'mere_nom', 'mere_telephone', 'quartier',
        'responsable_nom', 'responsable_lien', 'responsable_sexe', 'responsable_telephone', 'responsable_piece',
        'second_nom', 'second_lien', 'second_telephone', 'second_piece',
    ];

    // Liens possibles entre la personne autorisée et les enfants
    public const LIENS = ['Mère', 'Père', 'Tutrice', 'Tuteur', 'Grand-mère', 'Grand-père', 'Tante', 'Oncle', 'Sœur', 'Frère', 'Autre'];

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class, 'carte_retrait_eleve')->withTimestamps();
    }

    // Noms des parents réunis sur une ligne (père · mère)
    public function getParentsAttribute(): string
    {
        return collect([$this->pere_nom, $this->mere_nom])->filter()->implode(' · ');
    }

    public function getTelephonesParentsAttribute(): string
    {
        return collect([$this->pere_telephone, $this->mere_telephone])->filter()->unique()->implode(' · ');
    }

    // "Photo" de la carte : une femme ou un homme avec deux enfants, selon la personne autorisée principale
    public function getEmojiAttribute(): string
    {
        return $this->responsable_sexe === 'F' ? "\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}" : "\u{1F468}\u{200D}\u{1F467}\u{200D}\u{1F466}";
    }
}
