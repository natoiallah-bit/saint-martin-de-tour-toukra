<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Relance de paiement envoyée à une famille, pour un enfant
class RelancePaiement extends Model
{
    protected $table = 'relances_paiement';

    protected $fillable = ['eleve_id', 'annee_scolaire', 'niveau', 'montant_du', 'date_relance', 'date_limite', 'envoyee_par'];

    protected $casts = [
        'date_relance' => 'date',
        'date_limite' => 'date',
        'montant_du' => 'float',
    ];

    public const NIVEAUX = [
        1 => '1re relance',
        2 => '2e relance',
        3 => 'Convocation',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function getLibelleNiveauAttribute(): string
    {
        return self::NIVEAUX[$this->niveau] ?? '—';
    }
}
