<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eleve extends Model
{
    protected $fillable = [
        'classe_id', 'matricule', 'nom', 'prenom', 'date_naissance', 'lieu_naissance',
        'sexe', 'quartier', 'tuteur_nom', 'pere_nom', 'pere_telephone', 'pere_email',
        'mere_nom', 'mere_telephone', 'tuteur_telephone', 'photo', 'exonere',
        'date_entree', 'ancienne_ecole', 'aptitude_physique', 'aptitude_remarque', 'aptitude_jusqu_au',
    ];

    // Aptitude physique (éducation physique et sportive)
    public const APTITUDES = [
        'apte' => 'Apte',
        'inapte_partiel' => 'Inapte partiel',
        'inapte_total' => 'Inapte total',
    ];

    protected $casts = [
        'date_entree' => 'date',
        'aptitude_jusqu_au' => 'date',
        'date_naissance' => 'date',
        'exonere' => 'boolean',
    ];

    public function classe(): BelongsTo
    {
        return $this->belongsTo(ClasseScolaire::class, 'classe_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentEleve::class);
    }

    // Cartes de retrait de la famille (personnes autorisées à venir chercher l'enfant)
    public function cartesRetrait(): BelongsToMany
    {
        return $this->belongsToMany(CarteRetrait::class, 'carte_retrait_eleve');
    }

    // Comptes parents rattachés à cet élève
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'parent_eleve', 'eleve_id', 'user_id');
    }

    public function getLibelleAptitudeAttribute(): string
    {
        return self::APTITUDES[$this->aptitude_physique ?? 'apte'] ?? 'Apte';
    }

    // Début des matricules : le sigle de l'école réglé dans Paramètres (ex. « SMT- »)
    public static function prefixeMatricule(): string
    {
        $sigle = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ConfigurationEcole::actuelle()->sigle_ecole));

        if ($sigle === '') {
            $sigle = \App\Support\Etablissement::initiales();
        }

        return $sigle . '-';
    }

    // Matricule automatique SMT-0001 (avec un suffixe si ce numéro est déjà pris)
    public function attribuerMatricule(): void
    {
        $base = self::prefixeMatricule() . str_pad($this->id, 4, '0', STR_PAD_LEFT);
        $matricule = $base;
        $suffixe = 1;
        while (self::where('matricule', $matricule)->where('id', '!=', $this->id)->exists()) {
            $matricule = $base . '-' . ++$suffixe;
        }

        $this->matricule = $matricule;
        $this->save();
    }
}
