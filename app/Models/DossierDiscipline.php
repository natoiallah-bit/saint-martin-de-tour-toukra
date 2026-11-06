<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Élève traduit au conseil de discipline : motif, convocation, décision
class DossierDiscipline extends Model
{
    protected $table = 'dossiers_discipline';

    protected $fillable = [
        'eleve_id', 'motif', 'faits', 'date_faits', 'date_conseil', 'lieu',
        'decision', 'decision_details', 'date_decision', 'cree_par', 'decide_par',
        'convocation_lue_le', 'decision_lue_le',
    ];

    protected $casts = [
        'date_faits' => 'date',
        'date_conseil' => 'datetime',
        'date_decision' => 'date',
        'convocation_lue_le' => 'datetime',
        'decision_lue_le' => 'datetime',
    ];

    public const DECISIONS = [
        'aucune' => 'Aucune sanction (relaxé)',
        'avertissement' => 'Avertissement',
        'blame' => 'Blâme',
        'travaux' => "Travaux d'intérêt scolaire",
        'exclusion_temporaire' => 'Exclusion temporaire',
        'exclusion_definitive' => 'Exclusion définitive',
        'autre' => 'Autre décision',
    ];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decide_par');
    }

    public function estDecide(): bool
    {
        return $this->decision !== null;
    }

    public function getLibelleDecisionAttribute(): ?string
    {
        return $this->decision ? (self::DECISIONS[$this->decision] ?? $this->decision) : null;
    }

    // Ce que le parent n'a pas encore lu : la convocation, ou la décision une fois prise
    public function estNonLuParParent(): bool
    {
        return ! $this->convocation_lue_le || ($this->estDecide() && ! $this->decision_lue_le);
    }
}
