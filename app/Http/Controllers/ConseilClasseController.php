<?php

namespace App\Http\Controllers;

use App\Helpers\Appreciation;
use App\Helpers\Assiduite;
use App\Helpers\BulletinCalcul;
use App\Helpers\PorteeCycle;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\ConseilClasse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Saisie des décisions du conseil de classe (absences, conduite, appréciation,
// distinction), reprises sur les bulletins de chaque élève.
class ConseilClasseController extends Controller
{
    // Classes accessibles : celles du cycle de l'utilisateur ; pour un enseignant,
    // seulement celles dont il est le professeur principal
    protected function classesAccessibles()
    {
        $user = auth()->user();
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        return ClasseScolaire::with('cycle')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->when($user->estLimiteAuxClassesEnseignant('conseil_classe', 'modifier'),
                fn ($q) => $q->duProfesseurPrincipal($user))
            ->ordonnees();
    }

    public function choix()
    {
        $classes = $this->classesAccessibles()->get();

        return view('conseil-classe.choix', compact('classes'));
    }

    public function saisie(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|integer',
            'trimestre' => 'required|in:1,2,3',
        ]);

        $classe = $this->classesAccessibles()
            ->with(['eleves' => fn ($q) => $q->orderBy('nom')->orderBy('prenom')])
            ->findOrFail($request->classe_id);
        $trimestre = (int) $request->trimestre;

        $conseils = ConseilClasse::where('trimestre', $trimestre)
            ->where('annee_scolaire', ConfigurationEcole::anneeActive())
            ->whereIn('eleve_id', $classe->eleves->pluck('id'))
            ->get()
            ->keyBy('eleve_id');

        $moyennes = BulletinCalcul::moyennesClasse($classe->id, $trimestre);

        return view('conseil-classe.saisie', [
            'classe' => $classe,
            'trimestre' => $trimestre,
            'conseils' => $conseils,
            'moyennes' => $moyennes,
            // Absences et retards calculés à partir de l'appel (null : dates du trimestre non réglées)
            'assiduite' => Assiduite::pourClasse($classe->id, $trimestre),
            'avecEmploiDuTemps' => \App\Models\EmploiTemps::where('classe_id', $classe->id)->exists(),
            'distinctions' => Appreciation::DISTINCTIONS,
        ]);
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|integer',
            'trimestre' => 'required|in:1,2,3',
            'conseils' => 'array',
            'conseils.*.note_conduite' => 'nullable|numeric|min:0|max:20',
            'conseils.*.appreciation' => 'nullable|string|max:500',
            'conseils.*.commentaire_conduite' => 'nullable|string|max:500',
            'conseils.*.distinction' => ['nullable', Rule::in(Appreciation::DISTINCTIONS)],
        ]);

        $classe = $this->classesAccessibles()->findOrFail($request->classe_id);
        $elevesDeLaClasse = $classe->eleves()->pluck('id')->all();
        $annee = ConfigurationEcole::anneeActive();

        foreach ($request->input('conseils', []) as $eleveId => $valeurs) {
            if (! in_array((int) $eleveId, $elevesDeLaClasse)) {
                continue;
            }

            ConseilClasse::updateOrCreate(
                ['eleve_id' => $eleveId, 'trimestre' => $request->trimestre, 'annee_scolaire' => $annee],
                [
                    'note_conduite' => $valeurs['note_conduite'] ?? null,
                    'appreciation' => $valeurs['appreciation'] ?? null,
                    'commentaire_conduite' => $valeurs['commentaire_conduite'] ?? null,
                    'distinction' => $valeurs['distinction'] ?? null,
                ]
            );
        }

        return redirect()
            ->route('conseil-classe.saisie', ['classe_id' => $classe->id, 'trimestre' => $request->trimestre])
            ->with('success', 'Décisions du conseil de classe enregistrées.');
    }
}
