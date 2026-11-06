<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraisScolarite extends Model
{
    protected $table = 'frais_scolarites';

    protected $fillable = [
        'cycle_id',
        'annee_scolaire',
        'montant_annuel',
        'montant_inscription', 'date_inscription',
        'montant_tranche1', 'date_tranche1',
        'montant_tranche2', 'date_tranche2',
        'montant_tranche3', 'date_tranche3',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'date_tranche1' => 'date',
        'date_tranche2' => 'date',
        'date_tranche3' => 'date',
    ];

    public const ECHEANCES = [
        'inscription' => 'Inscription / Réinscription',
        'tranche1' => '1re tranche',
        'tranche2' => '2e tranche',
        'tranche3' => '3e tranche',
    ];

    // Échéances renseignées : [code, libellé, montant, date limite]
    public function echeances(): array
    {
        $liste = [];
        foreach (self::ECHEANCES as $code => $libelle) {
            if ($this->{"montant_{$code}"} !== null) {
                $liste[] = [$code, $libelle, (float) $this->{"montant_{$code}"}, $this->{"date_{$code}"}];
            }
        }

        return $liste;
    }

    // Montant qui devait être payé à cette date. Sans échéancier, tout le montant annuel est dû.
    public function montantExigible(?\Carbon\CarbonInterface $date = null): float
    {
        $date ??= now();
        $echeances = $this->echeances();

        if ($echeances === []) {
            return (float) $this->montant_annuel;
        }

        // Une échéance sans date limite (ex. inscription) est due tout de suite
        return (float) collect($echeances)
            ->filter(fn ($e) => $e[3] === null || $e[3]->lte($date))
            ->sum(fn ($e) => $e[2]);
    }

    // Dernière échéance déjà passée (pour la citer dans les lettres)
    public function derniereEcheanceEchue(?\Carbon\CarbonInterface $date = null): ?array
    {
        $date ??= now();

        return collect($this->echeances())->filter(fn ($e) => $e[3] !== null && $e[3]->lte($date))->last();
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Cycle::class);
    }

    public static function pourCycle(int $cycleId, string $anneeScolaire): ?self
    {
        return self::where('cycle_id', $cycleId)
            ->where('annee_scolaire', $anneeScolaire)
            ->first();
    }

    public static function montantPourCycle(int $cycleId, string $anneeScolaire): float
    {
        $frais = self::pourCycle($cycleId, $anneeScolaire);

        return $frais ? (float) $frais->montant_annuel : 0.0;
    }
}
