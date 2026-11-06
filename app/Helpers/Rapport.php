<?php

namespace App\Helpers;

use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\FraisScolarite;
use App\Models\Note;
use App\Models\OperationFinanciere;
use App\Models\Paiement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Chiffres des rapports trimestriels et annuels.
 * - Volet pédagogique : effectifs, résultats, assiduité, matières, tableau d'honneur.
 * - Volet financier : scolarité attendue / encaissée, recouvrement, et pour toute
 *   l'école les autres recettes, dépenses et la masse salariale.
 * Les dates des trimestres viennent des Paramètres.
 */
class Rapport
{
    public const PERIODES = ['t1' => '1er trimestre', 't2' => '2e trimestre', 't3' => '3e trimestre', 'annee' => 'Année scolaire'];

    // Période choisie : libellé, trimestres concernés, dates de début et de fin (null si non réglées)
    public static function periode(string $code): array
    {
        $trimestres = $code === 'annee' ? [1, 2, 3] : [(int) substr($code, 1)];
        $debut = ConfigurationEcole::periodeTrimestre($trimestres[0])[0] ?? null;
        $fin = ConfigurationEcole::periodeTrimestre(end($trimestres))[1] ?? null;

        return [
            'code' => $code,
            'libelle' => self::PERIODES[$code],
            'trimestres' => $trimestres,
            'debut' => $debut,
            'fin' => $fin,
            'annuel' => $code === 'annee',
        ];
    }

    // Trimestre en cours d'après les dates des Paramètres (1er trimestre par défaut)
    public static function periodeEnCours(): string
    {
        foreach ([1, 2, 3] as $t) {
            $p = ConfigurationEcole::periodeTrimestre($t);
            if ($p && now()->between($p[0]->copy()->startOfDay(), $p[1]->copy()->endOfDay())) {
                return 't' . $t;
            }
        }

        return 't1';
    }

    public static function classes(?int $cycleId): Collection
    {
        return ClasseScolaire::with(['cycle', 'eleves'])
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();
    }

    // ---------------------------------------------------------------- Pédagogie

    public static function pedagogique(Collection $classes, array $periode): array
    {
        $lignes = [];
        $honneur = collect();

        foreach ($classes as $classe) {
            $eleves = $classe->eleves;

            // Moyenne de chaque élève : celle du trimestre, ou la moyenne des trimestres pour l'année
            $parTrimestre = collect($periode['trimestres'])->mapWithKeys(fn ($t) => [$t => BulletinCalcul::moyennesClasse($classe->id, $t)]);
            $moyennes = $eleves->mapWithKeys(function ($eleve) use ($parTrimestre) {
                $valeurs = $parTrimestre->map(fn ($m) => $m[$eleve->id] ?? null)->filter(fn ($v) => $v !== null);

                return [$eleve->id => $valeurs->isNotEmpty() ? round($valeurs->avg(), 2) : null];
            })->filter(fn ($m) => $m !== null);

            // Assiduité : somme des trimestres de la période
            $heures = 0;
            $heuresConnues = true;
            $retards = 0;
            $joursAbsence = 0;
            foreach ($periode['trimestres'] as $t) {
                $assiduite = Assiduite::pourClasse($classe->id, $t);
                if ($assiduite === null) {
                    $heuresConnues = false;
                    continue;
                }
                foreach ($assiduite as $a) {
                    if ($a['heures'] === null) {
                        $heuresConnues = false;
                    } else {
                        $heures += $a['heures'];
                    }
                    $retards += $a['retards'];
                    $joursAbsence += $a['jours'];
                }
            }

            foreach ($moyennes as $eleveId => $moyenne) {
                $honneur->push(['eleve' => $eleves->firstWhere('id', $eleveId), 'classe' => $classe, 'moyenne' => $moyenne]);
            }

            $lignes[] = [
                'classe' => $classe,
                'effectif' => $eleves->count(),
                'garcons' => $eleves->where('sexe', 'M')->count(),
                'filles' => $eleves->where('sexe', 'F')->count(),
                'classes' => $moyennes->count(),
                'moyenne' => $moyennes->isNotEmpty() ? round($moyennes->avg(), 2) : null,
                'max' => $moyennes->max(),
                'min' => $moyennes->min(),
                'reussite' => $moyennes->filter(fn ($m) => $m >= 10)->count(),
                'heures' => $heuresConnues ? round($heures, 1) : null,
                'jours' => $joursAbsence,
                'retards' => $retards,
            ];
        }

        $lignes = collect($lignes);
        $toutes = $honneur->pluck('moyenne');

        return [
            'lignes' => $lignes,
            'total' => [
                'effectif' => $lignes->sum('effectif'),
                'garcons' => $lignes->sum('garcons'),
                'filles' => $lignes->sum('filles'),
                'classes' => $lignes->sum('classes'),
                'moyenne' => $toutes->isNotEmpty() ? round($toutes->avg(), 2) : null,
                'max' => $toutes->max(),
                'min' => $toutes->min(),
                'reussite' => $lignes->sum('reussite'),
                'heures' => $lignes->contains(fn ($l) => $l['heures'] === null) ? null : round($lignes->sum('heures'), 1),
                'jours' => $lignes->sum('jours'),
                'retards' => $lignes->sum('retards'),
            ],
            'honneur' => $honneur->filter(fn ($h) => $h['moyenne'] >= 12)->sortByDesc('moyenne')->take(10)->values(),
            'matieres' => self::matieres($classes, $periode),
        ];
    }

    // Moyenne de chaque matière sur l'ensemble des élèves concernés
    protected static function matieres(Collection $classes, array $periode): Collection
    {
        $eleveIds = $classes->flatMap->eleves->pluck('id');

        return Note::with('matiere')
            ->whereIn('eleve_id', $eleveIds)
            ->whereIn('trimestre', $periode['trimestres'])
            ->get()
            ->filter(fn ($n) => $n->matiere && $n->moyenne_matiere !== null)
            ->groupBy(fn ($n) => $n->matiere->nom)
            ->map(fn ($notes, $nom) => [
                'matiere' => $nom,
                'moyenne' => round($notes->avg('moyenne_matiere'), 2),
                'reussite' => $notes->count() ? round($notes->filter(fn ($n) => $n->moyenne_matiere >= 10)->count() * 100 / $notes->count()) : null,
                'notes' => $notes->count(),
            ])
            ->sortBy('matiere')
            ->values();
    }

    // ---------------------------------------------------------------- Finances

    public static function financier(Collection $classes, array $periode, bool $touteLEcole, ?int $cycleId = null): array
    {
        $annee = ConfigurationEcole::anneeActive();
        $debut = $periode['debut'];
        $fin = $periode['fin'];
        $fraisParCycle = FraisScolarite::where('annee_scolaire', $annee)->get()->keyBy('cycle_id');
        $eleveIds = $classes->flatMap->eleves->pluck('id');

        $paiements = Paiement::where('annee_scolaire', $annee)->whereIn('eleve_id', $eleveIds)->get();
        $paiementsParEleve = $paiements->groupBy('eleve_id');
        // Rapport annuel : toute l'année scolaire, y compris avant la rentrée (inscriptions) et après le 3e trimestre
        if ($periode['annuel']) {
            $debut = $fin = null;
        }
        $dansPeriode = fn ($p) => (! $debut || $p->date_paiement->gte($debut->copy()->startOfDay())) && (! $fin || $p->date_paiement->lte($fin->copy()->endOfDay()));
        $avantFin = fn ($p) => ! $fin || $p->date_paiement->lte($fin->copy()->endOfDay());

        $lignes = $classes->map(function ($classe) use ($fraisParCycle, $paiementsParEleve, $periode, $fin, $dansPeriode, $avantFin) {
            $frais = $fraisParCycle->get($classe->cycle_id);
            $payants = $classe->eleves->reject(fn ($e) => $e->exonere);

            // Ce que chaque élève devait avoir payé à la fin de la période
            $du = $frais ? ($periode['annuel'] ? (float) $frais->montant_annuel : $frais->montantExigible($fin ?? now())) : 0.0;

            $encaisse = 0;
            $cumul = 0;
            $reste = 0;
            $enRetard = 0;
            // Encaissé : tous les élèves (un exonéré peut avoir payé l'inscription) ; reste dû : élèves payants
            foreach ($classe->eleves as $eleve) {
                $encaisse += $paiementsParEleve->get($eleve->id, collect())->filter($dansPeriode)->sum('montant');
            }
            foreach ($payants as $eleve) {
                $siens = $paiementsParEleve->get($eleve->id, collect());
                $verse = $siens->filter($avantFin)->sum('montant');
                $cumul += $verse;
                if ($du - $verse > 0) {
                    $reste += $du - $verse;
                    $enRetard++;
                }
            }
            $attendu = $du * $payants->count();

            return [
                'classe' => $classe,
                'payants' => $payants->count(),
                'exoneres' => $classe->eleves->count() - $payants->count(),
                'attendu' => $attendu,
                'encaisse' => $encaisse,
                'cumul' => $cumul,
                'reste' => $reste,
                'en_retard' => $enRetard,
                'taux' => $attendu > 0 ? min(100, round($cumul * 100 / $attendu)) : null,
            ];
        });

        $total = [
            'payants' => $lignes->sum('payants'),
            'exoneres' => $lignes->sum('exoneres'),
            'attendu' => $lignes->sum('attendu'),
            'encaisse' => $lignes->sum('encaisse'),
            'cumul' => $lignes->sum('cumul'),
            'reste' => $lignes->sum('reste'),
            'en_retard' => $lignes->sum('en_retard'),
        ];
        $total['taux'] = $total['attendu'] > 0 ? min(100, round($total['cumul'] * 100 / $total['attendu'])) : null;

        $parMode = $paiements->filter($dansPeriode)->groupBy('mode_paiement')
            ->map(fn ($p, $mode) => ['mode' => Paiement::libelleModePaiement($mode), 'montant' => $p->sum('montant'), 'nombre' => $p->count()])
            ->sortByDesc('montant')->values();

        $resultat = ['lignes' => $lignes, 'total' => $total, 'par_mode' => $parMode, 'ecole' => null];

        // Recettes, dépenses et paie : toute l'école, ou seulement celles du cycle
        if ($touteLEcole || $cycleId) {
            $operations = OperationFinanciere::where('annee_scolaire', $annee)
                ->when(! $touteLEcole, fn ($q) => $q->where('cycle_id', $cycleId))
                ->when($debut, fn ($q) => $q->whereDate('date_operation', '>=', $debut->toDateString()))
                ->when($fin, fn ($q) => $q->whereDate('date_operation', '<=', $fin->toDateString()))
                ->get();
            $parCategorie = fn ($type) => $operations->where('type', $type)->groupBy('categorie')
                ->map(fn ($o, $categorie) => ['categorie' => $categorie ?: '—', 'montant' => $o->sum('montant')])
                ->sortByDesc('montant')->values();

            // Salaires versés : dépenses créées automatiquement par la Paie (déjà comprises dans les dépenses)
            $salaires = $operations->where('type', 'depense')->whereNotNull('paie_id')->sum('montant');

            $recettes = $parCategorie('recette');
            $depenses = $parCategorie('depense');
            $resultat['ecole'] = [
                'recettes' => $recettes,
                'depenses' => $depenses,
                'total_recettes' => $recettes->sum('montant'),
                'total_depenses' => $depenses->sum('montant'),
                'salaires' => $salaires,
                'solde' => $total['encaisse'] + $recettes->sum('montant') - $depenses->sum('montant'),
            ];
        }

        return $resultat;
    }
}
