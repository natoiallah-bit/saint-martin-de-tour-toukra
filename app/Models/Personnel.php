<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personnel extends Model
{
    protected $fillable = [
        'matricule', 'nom', 'prenom', 'type', 'fonction',
        'telephone', 'email', 'photo', 'salaire_base',
        'paiement_horaire', 'taux_horaire',
        'actif', 'cycle_id', 'date_embauche', 'statut',
    ];

    // Statut du contrat
    public const STATUTS = [
        'permanent' => 'Permanent',
        'contractuel' => 'Contractuel',
        'vacataire' => 'Vacataire',
        'stagiaire' => 'Stagiaire',
    ];

    protected $casts = [
        'actif' => 'boolean',
        'paiement_horaire' => 'boolean',
        'date_embauche' => 'date',
    ];

    // Cycle d'affectation (vide = personnel commun, géré par la Direction)
    public function cycle(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public function getLibelleStatutAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? '—';
    }

    // Ancienneté lisible : « 3 ans et 2 mois »
    public function getAncienneteAttribute(): ?string
    {
        if (! $this->date_embauche) {
            return null;
        }
        $diff = $this->date_embauche->diff(now());
        $parties = array_filter([
            $diff->y ? $diff->y . ' an' . ($diff->y > 1 ? 's' : '') : null,
            $diff->m ? $diff->m . ' mois' : null,
        ]);

        return $parties ? implode(' et ', $parties) : 'moins d\'un mois';
    }

    public function paies(): HasMany
    {
        return $this->hasMany(Paie::class);
    }

    // Documents du dossier (contrat, diplômes, pièce d'identité...)
    public function documentsPersonnel(): HasMany
    {
        return $this->hasMany(DocumentPersonnel::class);
    }

    public function pointages(): HasMany
    {
        return $this->hasMany(Pointage::class);
    }

    public function getLibelleTypeAttribute(): string
    {
        return match ($this->type) {
            'enseignant' => 'Enseignant',
            'administratif' => 'Administratif',
            'securite' => 'Sécurité',
            'entretien' => 'Entretien',
            default => 'Autre',
        };
    }
}
