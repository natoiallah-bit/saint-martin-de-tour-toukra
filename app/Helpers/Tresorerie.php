<?php

namespace App\Helpers;

use App\Models\MouvementTresorerie;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use App\Models\SoldeOuverture;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Caisse, banque et Mobile Money : chaque encaissement ou dépense est rangé
 * dans un compte selon son mode de paiement (espèces → caisse, chèque ou
 * virement → banque, Mobile Money → Mobile Money). Les versements entre
 * comptes (dépôt de la caisse à la banque...) sont saisis à part.
 *
 * Portée : un cycle (id), le commun ('commun', opérations sans cycle) ou
 * toute l'école (null).
 */
class Tresorerie
{
    public const COMPTES = [
        'caisse' => 'Caisse (espèces)',
        'banque' => 'Banque',
        'mobile' => 'Mobile Money',
    ];

    // Compte touché par un mode de paiement (scolarité ou comptabilité)
    public static function compteDuMode(?string $mode): string
    {
        $mode = mb_strtolower((string) $mode);

        return match (true) {
            str_contains($mode, 'mobile') => 'mobile',
            str_contains($mode, 'cheque'), str_contains($mode, 'chèque'), str_contains($mode, 'virement') => 'banque',
            default => 'caisse',
        };
    }

    /**
     * Tous les mouvements de l'année pour la portée, du plus ancien au plus récent :
     * [date, libelle, compte, entree, sortie, origine]
     */
    public static function mouvements(string $annee, int|string|null $portee): Collection
    {
        $lignes = collect();

        if ($portee !== 'commun') {
            Paiement::with('eleve')->where('annee_scolaire', $annee)
                ->when($portee, fn ($q) => $q->whereHas('eleve.classe', fn ($c) => $c->where('cycle_id', $portee)))
                ->get()
                ->each(fn ($p) => $lignes->push([
                    'date' => $p->date_paiement->copy(),
                    'libelle' => 'Scolarité ' . $p->numero_recu . ' — ' . trim(($p->eleve->nom ?? '') . ' ' . ($p->eleve->prenom ?? '')),
                    'compte' => self::compteDuMode($p->mode_paiement),
                    'entree' => (float) $p->montant, 'sortie' => 0.0, 'origine' => 'scolarite',
                ]));
        }

        OperationFinanciere::where('annee_scolaire', $annee)->where(fn ($q) => self::filtrerPortee($q, $portee))->get()
            ->each(fn ($o) => $lignes->push([
                'date' => $o->date_operation->copy(),
                'libelle' => $o->categorie . ($o->description ? ' — ' . $o->description : ''),
                'compte' => self::compteDuMode($o->mode_paiement),
                'entree' => $o->type === 'recette' ? (float) $o->montant : 0.0,
                'sortie' => $o->type === 'depense' ? (float) $o->montant : 0.0,
                'origine' => $o->paie_id ? 'paie' : 'operation',
            ]));

        // Un versement ne change rien au total : il sort d'un compte et entre dans l'autre
        MouvementTresorerie::where('annee_scolaire', $annee)->where(fn ($q) => self::filtrerPortee($q, $portee))->get()
            ->each(function ($m) use ($lignes) {
                $libelle = 'Versement ' . $m->libelle . ($m->description ? ' — ' . $m->description : '');
                $lignes->push(['date' => $m->date_mouvement->copy(), 'libelle' => $libelle, 'compte' => $m->compte_source, 'entree' => 0.0, 'sortie' => (float) $m->montant, 'origine' => 'mouvement']);
                $lignes->push(['date' => $m->date_mouvement->copy(), 'libelle' => $libelle, 'compte' => $m->compte_destination, 'entree' => (float) $m->montant, 'sortie' => 0.0, 'origine' => 'mouvement']);
            });

        return $lignes->sortBy(fn ($l) => $l['date']->format('Y-m-d'))->values();
    }

    // Soldes de départ de l'année, par compte
    public static function soldesOuverture(string $annee, int|string|null $portee): array
    {
        $soldes = SoldeOuverture::where('annee_scolaire', $annee)->where(fn ($q) => self::filtrerPortee($q, $portee))->get();

        return collect(array_keys(self::COMPTES))->mapWithKeys(fn ($c) => [$c => (float) $soldes->where('compte', $c)->sum('montant')])->all();
    }

    /**
     * Situation à une date : solde de départ de la journée, mouvements du jour et solde de fin,
     * pour chaque compte.
     */
    public static function situation(string $annee, int|string|null $portee, Carbon $jour): array
    {
        $ouverture = self::soldesOuverture($annee, $portee);
        $mouvements = self::mouvements($annee, $portee);
        $avant = $mouvements->filter(fn ($l) => $l['date']->lt($jour->copy()->startOfDay()));
        $duJour = $mouvements->filter(fn ($l) => $l['date']->isSameDay($jour))->values();

        $comptes = [];
        foreach (array_keys(self::COMPTES) as $compte) {
            $debut = $ouverture[$compte] + $avant->where('compte', $compte)->sum('entree') - $avant->where('compte', $compte)->sum('sortie');
            $jourCompte = $duJour->where('compte', $compte);
            $comptes[$compte] = [
                'ouverture_annee' => $ouverture[$compte],
                'debut' => $debut,
                'entrees' => $jourCompte->sum('entree'),
                'sorties' => $jourCompte->sum('sortie'),
                'fin' => $debut + $jourCompte->sum('entree') - $jourCompte->sum('sortie'),
            ];
        }

        return ['comptes' => $comptes, 'jour' => $duJour];
    }

    public static function filtrerPortee($q, int|string|null $portee)
    {
        if ($portee === 'commun') {
            return $q->whereNull('cycle_id');
        }

        return $q->when($portee, fn ($qq) => $qq->where('cycle_id', $portee));
    }
}
