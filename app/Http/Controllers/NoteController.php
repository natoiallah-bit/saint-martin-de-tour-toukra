<?php

namespace App\Http\Controllers;

use App\Models\Eleve;

use App\Models\ClasseScolaire;
use App\Models\Matiere;
use App\Models\Evaluation;
use App\Models\Note;
use App\Models\NoteEvaluation;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Helpers\CoursEnLigne;
use App\Helpers\PorteeCycle;

class NoteController extends Controller
{
    // Étape 1 : choisir classe + matière + trimestre
    public function choix()
    {
        $user = auth()->user();
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        // Un enseignant ne voit et ne peut saisir que ses classes et ses matières
        // (emploi du temps ou matières qui lui sont attribuées)
        $paires = $user->estLimiteAuxClassesEnseignant('notes', 'modifier') ? CoursEnLigne::pairesEnseignant($user) : null;

        $classes = ClasseScolaire::with('cycle')
            ->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))
            ->when($paires !== null, fn ($q) => $q->whereIn('id', $paires->pluck(0)))
            ->ordonnees()
            ->get();

        $matieresAutorisees = Matiere::with('cycle')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->when($paires !== null, fn ($q) => $q->whereIn('id', $paires->pluck(1)))
            ->get();

        return view('notes.choix', compact('classes', 'matieresAutorisees'));
    }

    // Étape 2 : devoirs du trimestre (avec leurs pourcentages) et grille de saisie de la classe
    public function saisie(Request $request)
    {
        [$classe, $matiere, $trimestre] = $this->contexte($request);

        $evaluations = Evaluation::pour($classe->id, $matiere->id, $trimestre);
        $notesDevoirs = NoteEvaluation::whereIn('evaluation_id', $evaluations->pluck('id'))->get()
            ->groupBy('eleve_id')->map(fn ($n) => $n->pluck('note', 'evaluation_id')->all());
        $notesExistantes = Note::where('matiere_id', $matiere->id)->where('trimestre', $trimestre)
            ->whereIn('eleve_id', $classe->eleves->pluck('id'))->get()->keyBy('eleve_id');

        return view('notes.saisie', compact('classe', 'matiere', 'trimestre', 'evaluations', 'notesDevoirs', 'notesExistantes'));
    }

    // Ajouter, modifier ou supprimer un devoir (libellé, date, pourcentage)
    public function evaluationStore(Request $request)
    {
        [$classe, $matiere, $trimestre] = $this->contexte($request);
        $donnees = $this->validerEvaluation($request);
        Evaluation::create($donnees + ['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'trimestre' => $trimestre]);

        return redirect()->route('notes.saisie', ['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'trimestre' => $trimestre])
            ->with('success', 'Devoir ajouté : ' . $donnees['libelle'] . ' (' . rtrim(rtrim(number_format($donnees['poids'], 2, ',', ''), '0'), ',') . ' %).');
    }

    public function evaluationUpdate(Request $request, Evaluation $evaluation)
    {
        $this->autoriserEvaluation($evaluation);
        $evaluation->update($this->validerEvaluation($request));
        $this->recalculer($evaluation->classe_id, $evaluation->matiere_id, $evaluation->trimestre);

        return back()->with('success', 'Devoir modifié.');
    }

    public function evaluationDestroy(Evaluation $evaluation)
    {
        $this->autoriserEvaluation($evaluation);
        $evaluation->delete();
        $this->recalculer($evaluation->classe_id, $evaluation->matiere_id, $evaluation->trimestre);

        return back()->with('success', 'Devoir supprimé.');
    }

    // Enregistrement des notes de la classe : une note par devoir + la composition
    public function enregistrer(Request $request)
    {
        $request->validate([
            'notes' => 'array',
            'notes.*.devoirs' => 'nullable|array',
            'notes.*.devoirs.*' => 'nullable|numeric|min:0|max:20',
            'notes.*.moyenne_composition' => 'nullable|numeric|min:0|max:20',
        ]);
        [$classe, $matiere, $trimestre] = $this->contexte($request);

        $evaluations = Evaluation::pour($classe->id, $matiere->id, $trimestre)->keyBy('id');
        $elevesDeLaClasse = $classe->eleves->pluck('id')->all();

        DB::transaction(function () use ($request, $evaluations, $elevesDeLaClasse, $matiere, $trimestre, $classe) {
            foreach ($request->notes ?? [] as $eleveId => $valeurs) {
                // Seuls les élèves de cette classe reçoivent une note
                if (! in_array((int) $eleveId, $elevesDeLaClasse)) {
                    continue;
                }
                foreach ($valeurs['devoirs'] ?? [] as $evaluationId => $note) {
                    if (! $evaluations->has((int) $evaluationId)) {
                        continue;
                    }
                    NoteEvaluation::updateOrCreate(
                        ['evaluation_id' => $evaluationId, 'eleve_id' => $eleveId],
                        ['note' => $note === '' ? null : $note]
                    );
                }
                $this->enregistrerMoyenne((int) $eleveId, $classe->id, $matiere->id, $trimestre, $valeurs['moyenne_composition'] ?? null, true);
            }
        });

        return redirect()->route('notes.saisie', ['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'trimestre' => $trimestre])
            ->with('success', 'Notes enregistrées avec succès.');
    }

    // Classe, matière et trimestre de la demande, avec les contrôles d'accès
    private function contexte(Request $request): array
    {
        $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'matiere_id' => 'required|exists:matieres,id',
            'trimestre' => 'required|in:1,2,3',
        ]);
        $matiere = Matiere::findOrFail($request->matiere_id);
        $classe = ClasseScolaire::with(['cycle', 'eleves' => fn ($q) => $q->orderBy('nom')->orderBy('prenom')])->findOrFail($request->classe_id);
        PorteeCycle::verifierClasse($classe);
        $this->autoriserMatiere($classe, $matiere);
        abort_unless((int) $matiere->cycle_id === (int) $classe->cycle_id, 422, "Cette matière n'est pas enseignée dans le cycle de cette classe.");

        return [$classe, $matiere, (int) $request->trimestre];
    }

    private function validerEvaluation(Request $request): array
    {
        return $request->validate([
            'libelle' => 'required|string|max:100',
            'poids' => 'required|numeric|min:1|max:100',
            'date' => 'nullable|date',
        ], ['poids.required' => 'Indiquez le pourcentage du devoir.']);
    }

    private function autoriserEvaluation(Evaluation $evaluation): void
    {
        PorteeCycle::verifierClasse($evaluation->classe);
        $this->autoriserMatiere($evaluation->classe, Matiere::findOrFail($evaluation->matiere_id));
    }

    // Moyenne des devoirs (pondérée) et composition, enregistrées sur la note du trimestre
    private function enregistrerMoyenne(int $eleveId, int $classeId, int $matiereId, int $trimestre, $composition = null, bool $avecComposition = false): void
    {
        $evaluations = Evaluation::pour($classeId, $matiereId, $trimestre);
        $notes = NoteEvaluation::where('eleve_id', $eleveId)->whereIn('evaluation_id', $evaluations->pluck('id'))->pluck('note', 'evaluation_id')->all();
        $moyenneDevoirs = Evaluation::moyennePonderee($evaluations, $notes);

        $note = Note::firstOrNew(['eleve_id' => $eleveId, 'matiere_id' => $matiereId, 'trimestre' => $trimestre]);
        if ($avecComposition) {
            $note->moyenne_composition = $composition === '' ? null : $composition;
        }
        $note->moyenne_classe = $moyenneDevoirs;

        // Rien du tout pour cet élève : pas de ligne vide
        if (! $note->exists && $note->moyenne_classe === null && $note->moyenne_composition === null) {
            return;
        }
        $note->save();
    }

    // Après un changement de pourcentage ou la suppression d'un devoir
    private function recalculer(int $classeId, int $matiereId, int $trimestre): void
    {
        foreach (Eleve::where('classe_id', $classeId)->pluck('id') as $eleveId) {
            $this->enregistrerMoyenne($eleveId, $classeId, $matiereId, $trimestre);
        }
    }

    // Un enseignant ne peut saisir que les notes de ses propres matières, dans ses classes
    private function autoriserMatiere(ClasseScolaire $classe, Matiere $matiere): void
    {
        $user = auth()->user();
        if ($user->estLimiteAuxClassesEnseignant('notes', 'modifier')
            && ! CoursEnLigne::pairesEnseignant($user)->contains(fn ($p) => $p[0] === (int) $classe->id && $p[1] === (int) $matiere->id)) {
            abort(403, "Vous ne pouvez saisir des notes que pour vos propres matières et classes.");
        }
    }
}
