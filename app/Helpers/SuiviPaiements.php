<?php

namespace App\Helpers;

use App\Models\Eleve;
use App\Models\FraisScolarite;
use App\Models\Paiement;
use App\Models\RelancePaiement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Retards de paiement : ce que chaque élève devait avoir payé à ce jour selon
 * l'échéancier de son cycle, ce qu'il a versé, et regroupement par famille
 * pour les lettres de relance.
 */
class SuiviPaiements
{
    // Situation de chaque élève non exonéré, indexée par id d'élève
    public static function situations(Collection $eleves, string $annee, ?CarbonInterface $date = null): Collection
    {
        $date ??= now();
        $eleves = $eleves->reject(fn (Eleve $e) => $e->exonere)->values();
        $ids = $eleves->pluck('id');

        $fraisParCycle = FraisScolarite::where('annee_scolaire', $annee)->get()->keyBy('cycle_id');

        $paiements = Paiement::where('annee_scolaire', $annee)->whereIn('eleve_id', $ids)
            ->groupBy('eleve_id')
            ->select('eleve_id', DB::raw('SUM(montant) as total'), DB::raw('MAX(date_paiement) as dernier'))
            ->get()->keyBy('eleve_id');

        $relances = RelancePaiement::where('annee_scolaire', $annee)->whereIn('eleve_id', $ids)
            ->orderBy('date_relance')->orderBy('id')
            ->get()->groupBy('eleve_id');

        return $eleves->mapWithKeys(function (Eleve $eleve) use ($fraisParCycle, $paiements, $relances, $date) {
            $frais = $fraisParCycle->get($eleve->classe->cycle_id ?? 0);
            $exigible = $frais ? $frais->montantExigible($date) : 0.0;
            $verse = (float) ($paiements->get($eleve->id)->total ?? 0);
            $dernierPaiement = $paiements->get($eleve->id)->dernier ?? null;

            return [$eleve->id => [
                'eleve' => $eleve,
                'frais' => $frais,
                'annuel' => $frais ? (float) $frais->montant_annuel : 0.0,
                'exigible' => $exigible,
                'verse' => $verse,
                'retard' => max($exigible - $verse, 0),
                'dernier_paiement' => $dernierPaiement ? \Carbon\Carbon::parse($dernierPaiement) : null,
                'relances' => $relances->get($eleve->id, collect()),
            ]];
        });
    }

    // Familles ayant au moins un enfant en retard, du plus gros retard au plus petit
    public static function famillesEnRetard(Collection $situations): Collection
    {
        return self::regrouperParFamille($situations->pluck('eleve'))
            ->map(fn (array $ids) => self::famille($situations->only($ids)->filter(fn ($s) => $s['retard'] > 0)))
            ->filter()
            ->sortByDesc('retard')
            ->values();
    }

    // Une famille à partir des situations de ses enfants en retard
    public static function famille(Collection $situations): ?array
    {
        if ($situations->isEmpty()) {
            return null;
        }

        $premier = $situations->first()['eleve'];
        $relances = $situations->flatMap(fn ($s) => $s['relances'])->sortBy(fn ($r) => [$r->date_relance->timestamp, $r->id])->values();
        $derniere = $relances->last();
        $dernierPaiement = $situations->pluck('dernier_paiement')->filter()->max();

        // Le niveau monte à chaque relance restée sans paiement ; un paiement fait repartir à la 1re relance
        $niveauSuivant = 1;
        if ($derniere && (! $dernierPaiement || $dernierPaiement->lt($derniere->date_relance))) {
            $niveauSuivant = min($derniere->niveau + 1, 3);
        }

        return [
            'cle' => $situations->keys()->sort()->implode('-'),
            'enfants' => $situations->values(),
            'destinataire' => self::destinataire($premier),
            'telephone' => collect([$premier->pere_telephone, $premier->mere_telephone, $premier->tuteur_telephone])->filter()->first(),
            'exigible' => $situations->sum('exigible'),
            'verse' => $situations->sum('verse'),
            'retard' => $situations->sum('retard'),
            'relances' => $relances,
            'derniere_relance' => $derniere,
            'niveau_suivant' => $niveauSuivant,
        ];
    }

    public static function destinataire(Eleve $eleve): string
    {
        $parents = collect([$eleve->pere_nom, $eleve->mere_nom])->filter()->implode(' et ');

        return $parents ?: ($eleve->tuteur_nom ?: 'Parent / Tuteur');
    }

    /**
     * Regroupe les élèves par famille : frères et sœurs liés par une même carte de
     * retrait, un même compte parent ou un même numéro de téléphone de parent.
     * Retourne une liste de groupes d'ids d'élèves.
     */
    public static function regrouperParFamille(Collection $eleves): Collection
    {
        $ids = $eleves->pluck('id')->all();
        $parent = array_combine($ids, $ids);
        $trouver = function ($id) use (&$parent, &$trouver) {
            return $parent[$id] === $id ? $id : ($parent[$id] = $trouver($parent[$id]));
        };

        $liens = [];
        foreach (DB::table('carte_retrait_eleve')->whereIn('eleve_id', $ids)->get() as $l) {
            $liens['carte' . $l->carte_retrait_id][] = $l->eleve_id;
        }
        foreach (DB::table('parent_eleve')->whereIn('eleve_id', $ids)->get() as $l) {
            $liens['compte' . $l->user_id][] = $l->eleve_id;
        }
        foreach ($eleves as $eleve) {
            foreach ([$eleve->pere_telephone, $eleve->mere_telephone, $eleve->tuteur_telephone] as $tel) {
                $chiffres = preg_replace('/\D/', '', (string) $tel);
                if (strlen($chiffres) >= 6) {
                    $liens['tel' . substr($chiffres, -8)][] = $eleve->id;
                }
            }
        }

        foreach ($liens as $groupe) {
            $racine = $trouver($groupe[0]);
            foreach ($groupe as $id) {
                $parent[$trouver($id)] = $racine;
            }
        }

        return collect($ids)->groupBy(fn ($id) => $trouver($id))->map(fn ($g) => $g->values()->all())->values();
    }
}
