<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paie extends Model
{
    protected $fillable = [
        'personnel_id', 'mois', 'salaire_base',
        'nombre_heures', 'taux_horaire',
        'primes', 'acompte', 'retenues', 'date_paiement', 'mode_paiement',
    ];

    protected $casts = ['date_paiement' => 'date'];

    // Modes de versement du salaire (déterminent la caisse ou la banque touchée)
    public const MODES = ['Espèces', 'Chèque', 'Virement bancaire', 'Mobile Money'];

    // Dépense « Salaires » créée automatiquement en comptabilité
    public function depense(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OperationFinanciere::class);
    }

    // Crée ou met à jour la dépense « Salaires » liée à ce bulletin (montant = net payé)
    public function synchroniserDepense(): void
    {
        $this->loadMissing('personnel');
        $net = $this->net_a_payer;
        if ($net <= 0) {
            $this->depense()->delete();

            return;
        }

        $date = $this->date_paiement ?? now();
        $depense = $this->depense()->first() ?? new OperationFinanciere([
            'paie_id' => $this->id,
            'type' => 'depense',
            'categorie' => 'Salaires',
            'annee_scolaire' => ConfigurationEcole::anneeActive(),
            'saisi_par' => auth()->id(),
        ]);
        $depense->fill([
            'montant' => $net,
            'mode_paiement' => $this->mode_paiement ?: 'Espèces',
            'date_operation' => $date->toDateString(),
            'description' => 'Salaire ' . $this->mois_libelle . ' — ' . trim(($this->personnel->nom ?? '') . ' ' . ($this->personnel->prenom ?? '')),
            'cycle_id' => $this->personnel?->cycle_id,
        ])->save();
    }

    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    // Net à payer = salaire de base (fixe ou calculé à l'heure) + primes - acompte déjà versé - retenues
    public function getNetAPayerAttribute(): float
    {
        return round($this->salaire_base + $this->primes - $this->acompte - $this->retenues, 2);
    }

    public function getMoisLibelleAttribute(): string
    {
        $mois = [
            '01' => 'Janvier', '02' => 'Février', '03' => 'Mars', '04' => 'Avril',
            '05' => 'Mai', '06' => 'Juin', '07' => 'Juillet', '08' => 'Août',
            '09' => 'Septembre', '10' => 'Octobre', '11' => 'Novembre', '12' => 'Décembre',
        ];
        [$annee, $moisNum] = explode('-', $this->mois);
        return ($mois[$moisNum] ?? $moisNum) . ' ' . $annee;
    }
}
