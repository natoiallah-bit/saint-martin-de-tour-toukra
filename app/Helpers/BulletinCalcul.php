<?php

namespace App\Helpers;

use App\Models\ConfigurationEcole;
use App\Models\ConseilClasse;
use App\Models\Eleve;
use App\Models\EmploiTemps;
use App\Models\Note;
use Illuminate\Support\Collection;

/**
 * Logique de calcul du bulletin, réutilisée par l'affichage du bulletin
 * et par la page de vérification d'authenticité (QR code).
 */
class BulletinCalcul
{
    public static function calculer(Eleve $eleve, int $trimestre): array
    {
        // Toutes les notes de la classe pour ce trimestre, chargées en une seule
        // requête : elles servent au bulletin de l'élève, à son rang général et
        // à son rang dans chaque matière (au lieu d'une requête par camarade).
        $eleveIds = Eleve::where('classe_id', $eleve->classe_id)->pluck('id');
        $notesClasse = Note::with('matiere.enseignant')
            ->whereIn('eleve_id', $eleveIds)
            ->where('trimestre', $trimestre)
            ->get();

        $notes = $notesClasse->where('eleve_id', $eleve->id)->values();

        // Enseignant de chaque matière dans CETTE classe, d'après l'emploi du temps
        // (c'est là que les enseignants sont attribués) ; à défaut, celui de la fiche matière
        $enseignantsParMatiere = EmploiTemps::with('enseignant')
            ->where('classe_id', $eleve->classe_id)
            ->whereNotNull('enseignant_id')
            ->get()
            ->groupBy('matiere_id')
            ->map(fn ($creneaux) => $creneaux->pluck('enseignant')->filter()->unique('id')
                ->map(fn ($p) => trim($p->prenom . ' ' . $p->nom))->implode(', '));

        // Notes de conduite (saisies dans "Conseil de classe"), comptées dans la
        // moyenne générale avec le coefficient réglé dans Paramètres
        $annee = ConfigurationEcole::anneeActive();
        $coefConduite = ConfigurationEcole::coefficientConduite();
        $conseilsClasse = ConseilClasse::where('annee_scolaire', $annee)
            ->where('trimestre', $trimestre)
            ->whereIn('eleve_id', $eleveIds)
            ->get()
            ->keyBy('eleve_id');
        $conseilsPrecedents = $trimestre >= 2
            ? ConseilClasse::where('annee_scolaire', $annee)->where('eleve_id', $eleve->id)->where('trimestre', '<', $trimestre)->get()->keyBy('trimestre')
            : collect();

        // Notes de l'élève aux trimestres précédents (rappel par matière et cumul)
        $notesPrecedentes = $trimestre >= 2
            ? Note::with('matiere')->where('eleve_id', $eleve->id)->where('trimestre', '<', $trimestre)->get()->groupBy('trimestre')
            : collect();

        $totalPoints = 0;
        $totalCoefficients = 0;

        $lignes = $notes->map(function (Note $note) use (&$totalPoints, &$totalCoefficients, $eleve, $trimestre, $notesClasse, $notesPrecedentes, $enseignantsParMatiere) {
            $moyenneMatiere = $note->moyenne_matiere;
            $coefficient = $note->matiere->coefficient;

            if ($moyenneMatiere !== null) {
                $totalPoints += $moyenneMatiere * $coefficient;
                $totalCoefficients += $coefficient;
            }

            // Rappel : moyenne de la matière à chaque trimestre précédent ([1 => 12.5, 2 => 14])
            $rappel = [];
            for ($t = 1; $t < $trimestre; $t++) {
                $rappel[$t] = ($notesPrecedentes->get($t)?->firstWhere('matiere_id', $note->matiere_id))?->moyenne_matiere;
            }

            return [
                'matiere_id' => $note->matiere_id,
                'matiere' => $note->matiere->nom,
                'enseignant' => $enseignantsParMatiere->get($note->matiere_id)
                    ?: ($note->matiere->enseignant ? trim($note->matiere->enseignant->prenom . ' ' . $note->matiere->enseignant->nom) : null),
                'appreciation' => Appreciation::pour($moyenneMatiere),
                'categorie' => $note->matiere->categorie ?: 'Autre',
                'coefficient' => $coefficient,
                'moyenne_classe' => $note->moyenne_classe,
                'moyenne_composition' => $note->moyenne_composition,
                'moyenne_matiere' => $moyenneMatiere,
                'moyenne_coeff' => $moyenneMatiere !== null ? round($moyenneMatiere * $coefficient, 2) : null,
                'rang_matiere' => self::rangMatiere($notesClasse->where('matiere_id', $note->matiere_id), $eleve->id, $moyenneMatiere),
                'rappel' => $rappel,
            ];
        });

        $lignesParCategorie = $lignes->groupBy('categorie')->map(function ($groupe) {
            $pts = 0;
            $coefs = 0;
            foreach ($groupe as $l) {
                if ($l['moyenne_matiere'] !== null) {
                    $pts += $l['moyenne_matiere'] * $l['coefficient'];
                    $coefs += $l['coefficient'];
                }
            }
            return [
                'lignes' => $groupe,
                'bilan' => $coefs > 0 ? round($pts / $coefs, 2) : null,
                'total_coefficients' => $groupe->sum('coefficient'),
            ];
        });

        $ordreCategories = ['Scientifique', 'Littéraire', 'Accessoire', 'Autre'];
        $lignesParCategorie = collect($ordreCategories)
            ->filter(fn ($cat) => $lignesParCategorie->has($cat))
            ->mapWithKeys(fn ($cat) => [$cat => $lignesParCategorie->get($cat)]);

        // Ligne "Conduite" du bulletin ; son appréciation est le commentaire du conseil
        $conseil = $conseilsClasse->get($eleve->id);
        $noteConduite = $conseil?->note_conduite;
        $conduite = [
            'note' => $noteConduite,
            'coefficient' => $coefConduite,
            'moyenne_coeff' => $noteConduite !== null ? round($noteConduite * $coefConduite, 2) : null,
            'rang' => self::rangConduite($conseilsClasse, $eleve->id, $noteConduite),
            // Rappel des notes de conduite des trimestres précédents
            'rappel' => collect(range(1, $trimestre))->filter(fn ($t) => $t < $trimestre)
                ->mapWithKeys(fn ($t) => [$t => $conseilsPrecedents->get($t)?->note_conduite])->all(),
            // Commentaire du conseil s'il en a écrit un, sinon déduit de la note
            'appreciation' => $conseil?->commentaire_conduite ?: Appreciation::conduite($noteConduite),
        ];

        if ($totalCoefficients > 0 && $noteConduite !== null && $coefConduite > 0) {
            $totalPoints += $noteConduite * $coefConduite;
            $totalCoefficients += $coefConduite;
        }

        $moyenneGenerale = $totalCoefficients > 0 ? round($totalPoints / $totalCoefficients, 2) : null;

        $rang = null;
        $effectifClasse = null;
        if ($moyenneGenerale !== null) {
            $effectifClasse = $eleveIds->count();
            $notesParEleve = $notesClasse->groupBy('eleve_id');

            $moyennes = $eleveIds->map(fn ($id) => [
                'id' => (int) $id,
                'moyenne' => self::moyennePonderee($notesParEleve->get($id, collect()), $conseilsClasse->get($id)?->note_conduite, $coefConduite),
            ])->filter(fn ($m) => $m['moyenne'] !== null)->values();

            $rang = self::rangParmi($moyennes->pluck('moyenne', 'id')->all(), (int) $eleve->id);
        }

        // Moyennes des trimestres précédents, pour le cumul affiché en synthèse
        $moyenneT1 = $trimestre >= 2 ? self::moyennePonderee($notesPrecedentes->get(1, collect()), $conseilsPrecedents->get(1)?->note_conduite, $coefConduite) : null;
        $moyenneT2 = $trimestre >= 3 ? self::moyennePonderee($notesPrecedentes->get(2, collect()), $conseilsPrecedents->get(2)?->note_conduite, $coefConduite) : null;

        // Moyenne annuelle et décision de fin d'année, uniquement au 3e trimestre
        $moyenneAnnuelle = null;
        $decisionAnnuelle = null;
        $rangAnnuel = null;
        if ($trimestre == 3) {
            $rangAnnuel = self::rangAnnuel($eleve->classe_id, (int) $eleve->id);
            $valeurs = array_filter([$moyenneT1, $moyenneT2, $moyenneGenerale], fn ($v) => $v !== null);
            $moyenneAnnuelle = count($valeurs) > 0 ? round(array_sum($valeurs) / count($valeurs), 2) : null;
            if ($moyenneAnnuelle !== null) {
                $decisionAnnuelle = $moyenneAnnuelle >= 10 ? 'Admis(e) en classe supérieure' : 'Redouble la classe';
            }
        }

        return [
            'lignes' => $lignes,
            'lignesParCategorie' => $lignesParCategorie,
            'conduite' => $conduite,
            'moyenneGenerale' => $moyenneGenerale,
            'rang' => $rang,
            'effectifClasse' => $effectifClasse,
            'moyenneT1' => $moyenneT1,
            'moyenneT2' => $moyenneT2,
            'moyenneAnnuelle' => $moyenneAnnuelle,
            'decisionAnnuelle' => $decisionAnnuelle,
            'rangAnnuel' => $rangAnnuel,
        ];
    }

    // Moyennes annuelles de la classe (moyenne des trimestres notés) : [eleve_id => moyenne]
    public static function moyennesAnnuellesClasse(int $classeId): array
    {
        $parTrimestre = [1 => self::moyennesClasse($classeId, 1), 2 => self::moyennesClasse($classeId, 2), 3 => self::moyennesClasse($classeId, 3)];
        $annuelles = [];
        foreach (Eleve::where('classe_id', $classeId)->pluck('id') as $id) {
            $valeurs = array_filter([$parTrimestre[1][$id] ?? null, $parTrimestre[2][$id] ?? null, $parTrimestre[3][$id] ?? null], fn ($v) => $v !== null);
            $annuelles[(int) $id] = count($valeurs) ? round(array_sum($valeurs) / count($valeurs), 2) : null;
        }

        return $annuelles;
    }

    // Rang de l'élève sur la moyenne annuelle (ex aequo compris)
    public static function rangAnnuel(int $classeId, int $eleveId): ?int
    {
        return self::rangParmi(self::moyennesAnnuellesClasse($classeId), $eleveId);
    }

    // Moyennes générales de tous les élèves d'une classe pour un trimestre : [eleve_id => moyenne]
    public static function moyennesClasse(int $classeId, int $trimestre): array
    {
        $eleveIds = Eleve::where('classe_id', $classeId)->pluck('id');
        $coefConduite = ConfigurationEcole::coefficientConduite();
        $conduites = ConseilClasse::where('annee_scolaire', ConfigurationEcole::anneeActive())
            ->where('trimestre', $trimestre)
            ->whereIn('eleve_id', $eleveIds)
            ->pluck('note_conduite', 'eleve_id');

        return Note::with('matiere')
            ->whereIn('eleve_id', $eleveIds)
            ->where('trimestre', $trimestre)
            ->get()
            ->groupBy('eleve_id')
            ->map(fn (Collection $notes, $eleveId) => self::moyennePonderee($notes, $conduites->get($eleveId), $coefConduite))
            ->all();
    }

    // Moyenne générale pondérée par les coefficients, conduite comprise
    // (null si aucune note de matière complète)
    private static function moyennePonderee(Collection $notes, ?float $noteConduite = null, float $coefConduite = 0): ?float
    {
        $pts = 0;
        $coefs = 0;
        foreach ($notes as $n) {
            if ($n->moyenne_matiere !== null) {
                $pts += $n->moyenne_matiere * $n->matiere->coefficient;
                $coefs += $n->matiere->coefficient;
            }
        }
        if ($coefs > 0 && $noteConduite !== null && $coefConduite > 0) {
            $pts += $noteConduite * $coefConduite;
            $coefs += $coefConduite;
        }
        return $coefs > 0 ? round($pts / $coefs, 2) : null;
    }

    // Rang de l'élève pour la note de conduite, parmi les notes de conduite de la classe
    private static function rangConduite(Collection $conseilsClasse, int $eleveId, ?float $noteEleve): ?int
    {
        if ($noteEleve === null) {
            return null;
        }

        return self::rangParmi($conseilsClasse->filter(fn ($c) => $c->note_conduite !== null)
            ->mapWithKeys(fn ($c) => [(int) $c->eleve_id => $c->note_conduite])->all(), $eleveId);
    }

    // Rang de l'élève dans une matière, parmi les notes de la classe pour cette matière
    private static function rangMatiere(Collection $notesMatiere, int $eleveId, ?float $moyenneEleve): ?int
    {
        if ($moyenneEleve === null) {
            return null;
        }

        return self::rangParmi($notesMatiere->filter(fn (Note $n) => $n->moyenne_matiere !== null)
            ->mapWithKeys(fn (Note $n) => [(int) $n->eleve_id => $n->moyenne_matiere])->all(), $eleveId);
    }

    // Rang = 1 + nombre d'élèves strictement devant : deux moyennes égales ont le même rang (ex aequo)
    public static function rangParmi(array $valeursParEleve, int $eleveId): ?int
    {
        if (! array_key_exists($eleveId, $valeursParEleve) || $valeursParEleve[$eleveId] === null) {
            return null;
        }
        $valeur = round((float) $valeursParEleve[$eleveId], 2);

        return 1 + count(array_filter($valeursParEleve, fn ($v) => $v !== null && round((float) $v, 2) > $valeur));
    }

}