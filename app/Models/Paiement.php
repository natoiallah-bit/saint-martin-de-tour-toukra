<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $fillable = [
        'eleve_id',
        'annee_scolaire',
        'types',
        'montant',
        'mode_paiement',
        'date_paiement',
        'numero_recu',
        'description',
        'piece_justificative',
        'saisi_par',
    ];

    protected $casts = [
        'date_paiement' => 'date',
        'types' => 'array',
    ];

    // Comptable qui a encaissé le paiement (signataire du reçu)
    public function encaissePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    /**
     * Génère un numéro de reçu unique du type SMT-2026-0001
     */
    // Prochain numéro de reçu de l'année, ex. SMT-2026-0042.
    // On part du plus grand numéro existant (et non du nombre de paiements),
    // sinon la suppression d'un paiement ferait réutiliser un numéro déjà attribué.
    // Numéro de reçu suivant pour une année scolaire : SMT-2026-0001 pour 2026-2027.
    // La numérotation suit l'année scolaire : elle ne repart pas à zéro au 1er janvier.
    public static function genererNumeroRecu(?string $anneeScolaire = null): string
    {
        $anneeScolaire ??= ConfigurationEcole::anneeActive();
        $debut = preg_match('/^(\d{4})/', (string) $anneeScolaire, $m) ? $m[1] : now()->format('Y');
        $prefixe = self::codeRecu() . '-' . $debut . '-';

        $dernier = self::where('numero_recu', 'like', $prefixe . '%')
            ->pluck('numero_recu')
            ->map(fn ($numero) => (int) substr($numero, strlen($prefixe)))
            ->max() ?? 0;

        return sprintf('%s%04d', $prefixe, $dernier + 1);
    }

    // Début des numéros de reçu : le sigle de l'école réglé dans Paramètres
    public static function codeRecu(): string
    {
        return rtrim(Eleve::prefixeMatricule(), '-');
    }

    protected static function libelles(): array
    {
        return [
            'inscription' => 'Inscription / Réinscription',
            'tranche1' => '1ère tranche',
            'tranche2' => '2e tranche',
            'tranche3' => '3e tranche',
        ];
    }

    public static function libellesDisponibles(): array
    {
        return self::libelles();
    }

    public function getLibelleTypesAttribute(): string
    {
        $libelles = self::libelles();

        return collect($this->types ?? [])
            ->map(fn ($t) => $libelles[$t] ?? $t)
            ->implode(' + ');
    }

    public static function libelleModePaiement(?string $mode): string
    {
        return match ($mode) {
            'especes' => 'Espèces',
            'cheque' => 'Chèque',
            'virement' => 'Virement bancaire',
            'mobile_money' => 'Mobile Money',
            default => $mode ?? '—',
        };
    }

    public function getLibelleModePaiementAttribute(): string
    {
        return self::libelleModePaiement($this->mode_paiement);
    }

    /**
     * Total versé par un élève pour une année scolaire donnée
     */
    public static function totalVerse(int $eleveId, string $anneeScolaire): float
    {
        return (float) self::where('eleve_id', $eleveId)
            ->where('annee_scolaire', $anneeScolaire)
            ->sum('montant');
    }
}
