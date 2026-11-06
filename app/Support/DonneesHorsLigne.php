<?php

namespace App\Support;

use App\Helpers\CoursEnLigne;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\EmploiTemps;
use App\Models\Evaluation;
use App\Models\FraisScolarite;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\NoteEvaluation;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Données téléchargées par l'application pour travailler sans réseau.
 * Mêmes limites que les pages web : cycle du compte, et pour un enseignant
 * seulement ses cours (appel) et ses matières (notes).
 */
class DonneesHorsLigne
{
    private ?int $cycleId;

    public function __construct(private User $user)
    {
        $this->cycleId = $user->role === 'direction' ? null : $user->cycle_id;
    }

    public function tout(): array
    {
        $annee = ConfigurationEcole::anneeActive();
        $appel = $this->peut('presences') ? $this->appel() : null;
        $notes = $this->peut('notes') ? $this->notes() : null;
        $encaissement = $this->peut('scolarite') ? $this->encaissement($annee) : null;

        $classeIds = collect([$appel['classe_ids'] ?? [], $notes['classe_ids'] ?? [], $encaissement['classe_ids'] ?? []])->flatten()->unique();
        $classes = ClasseScolaire::with('cycle:id,nom')->whereIn('id', $classeIds)->ordonnees()->get();
        $eleves = Eleve::whereIn('classe_id', $classeIds)->orderBy('nom')->orderBy('prenom')
            ->get(['id', 'classe_id', 'matricule', 'nom', 'prenom', 'exonere']);

        return [
            'annee_scolaire' => $annee,
            'classes' => $classes->map(fn ($c) => ['id' => $c->id, 'nom' => $c->nom, 'cycle_id' => $c->cycle_id, 'cycle' => $c->cycle?->nom])->values(),
            'eleves' => $eleves->map(fn ($e) => [
                'id' => $e->id, 'classe_id' => $e->classe_id, 'matricule' => $e->matricule,
                'nom' => $e->nom, 'prenom' => $e->prenom, 'exonere' => (bool) $e->exonere,
            ])->values(),
            'appel' => $appel,
            'notes' => $notes,
            'encaissement' => $encaissement,
        ];
    }

    private function peut(string $module): bool
    {
        return ! in_array($this->user->role, ['parent', 'eleve'], true) && $this->user->peut($module, 'modifier');
    }

    // Comme PresenceController : un enseignant limité ne voit que ses cours, sans appel à la journée
    private function appel(): array
    {
        $limite = $this->user->estLimiteAuxClassesEnseignant('presences', 'modifier');

        $classeIds = $limite
            ? EmploiTemps::where('enseignant_id', $this->user->personnel_id ?? 0)->pluck('classe_id')->unique()
            : $this->classesDuCycle();

        $creneaux = EmploiTemps::with('matiere:id,nom')->whereIn('classe_id', $classeIds)
            ->when($limite, fn ($q) => $q->where('enseignant_id', $this->user->personnel_id ?? 0))
            ->orderBy('heure_debut')->get();

        return [
            'classe_ids' => $classeIds->values()->all(),
            'appel_journee' => ! $limite,
            'creneaux' => $creneaux->map(fn ($c) => [
                'id' => $c->id, 'classe_id' => $c->classe_id, 'jour' => $c->jour,
                'heure_debut' => substr((string) $c->heure_debut, 0, 5), 'heure_fin' => substr((string) $c->heure_fin, 0, 5),
                'matiere' => $c->matiere?->nom,
            ])->values(),
        ];
    }

    // Comme NoteController : paires (classe, matière) autorisées, devoirs et notes déjà saisies de l'année
    private function notes(): array
    {
        $paires = $this->user->estLimiteAuxClassesEnseignant('notes', 'modifier')
            ? CoursEnLigne::pairesEnseignant($this->user)
            : null;

        $classes = ClasseScolaire::when($this->cycleId, fn ($q) => $q->where('cycle_id', $this->cycleId))
            ->when($paires !== null, fn ($q) => $q->whereIn('id', $paires->pluck(0)))
            ->get(['id', 'cycle_id']);
        $matieres = Matiere::when($this->cycleId, fn ($q) => $q->where('cycle_id', $this->cycleId))
            ->when($paires !== null, fn ($q) => $q->whereIn('id', $paires->pluck(1)))
            ->orderBy('nom')->get(['id', 'cycle_id', 'nom']);

        // Une matière ne se saisit que dans les classes de son cycle
        $autorisees = collect();
        foreach ($classes as $classe) {
            foreach ($matieres->where('cycle_id', $classe->cycle_id) as $matiere) {
                if ($paires === null || $paires->contains(fn ($p) => $p[0] === (int) $classe->id && $p[1] === (int) $matiere->id)) {
                    $autorisees->push(['classe_id' => $classe->id, 'matiere_id' => $matiere->id]);
                }
            }
        }

        $classeIds = $autorisees->pluck('classe_id')->unique()->values();
        $matiereIds = $autorisees->pluck('matiere_id')->unique()->values();
        $evaluations = Evaluation::whereIn('classe_id', $classeIds)->whereIn('matiere_id', $matiereIds)
            ->orderBy('date')->orderBy('id')->get(['id', 'classe_id', 'matiere_id', 'trimestre', 'libelle', 'poids']);
        $eleveIds = Eleve::whereIn('classe_id', $classeIds)->pluck('id');

        return [
            'classe_ids' => $classeIds->all(),
            'matieres' => $matieres->whereIn('id', $matiereIds)->map(fn ($m) => ['id' => $m->id, 'nom' => $m->nom])->values(),
            'paires' => $autorisees->values(),
            'evaluations' => $evaluations->map(fn ($e) => [
                'id' => $e->id, 'classe_id' => $e->classe_id, 'matiere_id' => $e->matiere_id,
                'trimestre' => (int) $e->trimestre, 'libelle' => $e->libelle, 'poids' => (float) $e->poids,
            ])->values(),
            'notes_devoirs' => NoteEvaluation::whereIn('evaluation_id', $evaluations->pluck('id'))->whereNotNull('note')
                ->get(['evaluation_id', 'eleve_id', 'note'])
                ->map(fn ($n) => ['evaluation_id' => $n->evaluation_id, 'eleve_id' => $n->eleve_id, 'note' => (float) $n->note])->values(),
            'compositions' => Note::whereIn('eleve_id', $eleveIds)->whereIn('matiere_id', $matiereIds)->whereNotNull('moyenne_composition')
                ->get(['eleve_id', 'matiere_id', 'trimestre', 'moyenne_composition'])
                ->map(fn ($n) => ['eleve_id' => $n->eleve_id, 'matiere_id' => $n->matiere_id, 'trimestre' => (int) $n->trimestre, 'note' => (float) $n->moyenne_composition])->values(),
        ];
    }

    // Comme PaiementController : élèves du cycle du compte, frais de l'année et montants déjà versés
    private function encaissement(string $annee): array
    {
        $classeIds = $this->classesDuCycle();
        $cycleIds = ClasseScolaire::whereIn('id', $classeIds)->pluck('cycle_id')->unique();

        $frais = $cycleIds->mapWithKeys(function ($cycleId) use ($annee) {
            $f = FraisScolarite::pourCycle($cycleId, $annee);

            return [$cycleId => $f ? ['montant_annuel' => (float) $f->montant_annuel, 'exigible' => (float) $f->montantExigible()] : null];
        })->filter();

        $verses = Paiement::where('annee_scolaire', $annee)
            ->whereIn('eleve_id', Eleve::whereIn('classe_id', $classeIds)->select('id'))
            ->groupBy('eleve_id')->selectRaw('eleve_id, SUM(montant) as total')->pluck('total', 'eleve_id')
            ->map(fn ($t) => (float) $t);

        return [
            'classe_ids' => $classeIds->values()->all(),
            'frais' => $frais,
            'verses' => $verses,
            'types' => Paiement::libellesDisponibles(),
            'modes' => collect(['especes', 'mobile_money', 'cheque', 'virement'])->mapWithKeys(fn ($m) => [$m => Paiement::libelleModePaiement($m)]),
        ];
    }

    private function classesDuCycle(): Collection
    {
        return ClasseScolaire::when($this->cycleId, fn ($q) => $q->where('cycle_id', $this->cycleId))->pluck('id');
    }
}
