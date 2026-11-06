<?php

namespace App\Http\Controllers;

use App\Models\ClasseScolaire;
use App\Models\EmploiTemps;
use App\Models\Presence;
use Illuminate\Http\Request;
use App\Helpers\PorteeCycle;

// Cahier d'appel : l'appel se fait à chaque cours (créneau de l'emploi du temps).
// La Direction, le Secrétariat ou le Surveillant peuvent aussi noter une absence
// pour toute la journée.
class PresenceController extends Controller
{
    // Étape 1 : choisir la classe (parmi celles où l'enseignant a cours) et la date
    public function choix(Request $request)
    {
        $user = auth()->user();
        $classesQuery = ClasseScolaire::with('cycle');

        if ($this->estLimiteASesCours()) {
            $classeIds = EmploiTemps::where('enseignant_id', $user->personnel_id)
                ->pluck('classe_id')->unique();
            $classesQuery->whereIn('id', $classeIds);
        } else {
            $cycleId = PorteeCycle::pourUtilisateurConnecte();
            $classesQuery->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId));
        }

        $classes = $classesQuery->ordonnees()->get();
        $date = $request->get('date', now()->format('Y-m-d'));

        return view('presences.choix', compact('classes', 'date'));
    }

    // Étape 2 : choisir le cours du jour, puis grille d'appel pour ce cours
    public function saisie(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'date' => 'required|date',
            'cours' => 'nullable',
        ]);

        $classe = ClasseScolaire::with(['eleves' => fn($q) => $q->orderBy('nom')->orderBy('prenom')])->findOrFail($request->classe_id);
        $this->autoriserClasse($classe);
        PorteeCycle::verifierClasse($classe);

        $date = $request->date;
        $coursDuJour = $this->coursDuJour($classe, $date);
        $toutLaJournee = $request->cours === 'journee';

        // Pas encore de cours choisi : liste des cours du jour (avec l'état de l'appel)
        if (! $request->filled('cours') && ($coursDuJour->isNotEmpty() || $this->estLimiteASesCours())) {
            $appelsFaits = Presence::where('classe_id', $classe->id)->whereDate('date', $date)
                ->whereNotNull('emploi_temps_id')->distinct()->pluck('emploi_temps_id')->all();

            return view('presences.cours', [
                'classe' => $classe,
                'date' => $date,
                'coursDuJour' => $coursDuJour,
                'appelsFaits' => $appelsFaits,
                'peutAppelJournee' => ! $this->estLimiteASesCours(),
            ]);
        }

        $cours = null;
        if (! $toutLaJournee && $request->filled('cours')) {
            $cours = $coursDuJour->firstWhere('id', (int) $request->cours);
            abort_unless($cours, 404, "Ce cours n'est pas prévu pour cette classe ce jour-là.");
        }
        abort_if(! $cours && $this->estLimiteASesCours(), 403, "Vous ne pouvez faire l'appel que pour vos propres cours.");

        $presencesExistantes = Presence::where('classe_id', $classe->id)
            ->whereDate('date', $date)
            ->where('emploi_temps_id', $cours?->id)
            ->get()
            ->keyBy('eleve_id');

        return view('presences.saisie', [
            'classe' => $classe,
            'date' => $date,
            'cours' => $cours,
            'presencesExistantes' => $presencesExistantes,
        ]);
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'classe_id' => 'required|exists:classes,id',
            'date' => 'required|date',
            'cours' => 'nullable|integer',
            'statuts' => 'array',
            'statuts.*' => 'in:present,absent,retard',
        ]);

        $classe = ClasseScolaire::findOrFail($request->classe_id);
        $this->autoriserClasse($classe);
        PorteeCycle::verifierClasse($classe);

        $cours = null;
        if ($request->filled('cours')) {
            $cours = $this->coursDuJour($classe, $request->date)->firstWhere('id', (int) $request->cours);
            abort_unless($cours, 404, "Ce cours n'est pas prévu pour cette classe ce jour-là.");
        }
        abort_if(! $cours && $this->estLimiteASesCours(), 403, "Vous ne pouvez faire l'appel que pour vos propres cours.");

        // Seuls les élèves de cette classe peuvent recevoir une présence
        $elevesDeLaClasse = $classe->eleves()->pluck('id')->all();

        foreach ($request->statuts ?? [] as $eleveId => $statut) {
            if (! in_array((int) $eleveId, $elevesDeLaClasse)) {
                continue;
            }

            // whereDate : comparaison sur le jour seul, quelle que soit la base de données
            $presence = Presence::where('eleve_id', $eleveId)
                ->whereDate('date', $request->date)
                ->where('emploi_temps_id', $cours?->id)
                ->first()
                ?? new Presence(['eleve_id' => $eleveId, 'date' => $request->date, 'emploi_temps_id' => $cours?->id]);

            $presence->fill([
                'classe_id' => $request->classe_id,
                'duree_minutes' => $cours?->dureeMinutes(),
                'statut' => $statut,
                'remarque' => $request->remarques[$eleveId] ?? null,
                'saisie_par' => auth()->id(),
            ])->save();
        }

        $libelle = $cours
            ? $cours->matiere->nom . ' (' . substr($cours->heure_debut, 0, 5) . ')'
            : 'toute la journée';

        return redirect()->route('presences.saisie', ['classe_id' => $classe->id, 'date' => $request->date])
            ->with('success', "Appel enregistré : {$libelle}, le " . \Carbon\Carbon::parse($request->date)->format('d/m/Y') . '.');
    }

    // Cours de la classe prévus ce jour-là ; un enseignant ne voit que les siens
    private function coursDuJour(ClasseScolaire $classe, string $date)
    {
        $jour = EmploiTemps::jourDe($date);

        return EmploiTemps::with(['matiere', 'enseignant'])
            ->where('classe_id', $classe->id)
            ->where('jour', $jour)
            ->when($this->estLimiteASesCours(), fn ($q) => $q->where('enseignant_id', auth()->user()->personnel_id ?? 0))
            ->orderBy('heure_debut')
            ->get();
    }

    // Un enseignant ne fait l'appel que pour ses cours, sauf si un autre de ses rôles
    // lui donne déjà le droit de saisir les présences de toute la classe
    private function estLimiteASesCours(): bool
    {
        return auth()->user()->estLimiteAuxClassesEnseignant('presences', 'modifier');
    }

    // Un enseignant ne peut faire l'appel que pour une classe où il a effectivement cours
    private function autoriserClasse(ClasseScolaire $classe): void
    {
        $user = auth()->user();
        if ($this->estLimiteASesCours()) {
            $aCours = EmploiTemps::where('enseignant_id', $user->personnel_id)
                ->where('classe_id', $classe->id)
                ->exists();
            if (! $aCours) {
                abort(403, "Vous n'avez pas cours dans cette classe.");
            }
        }
    }
}
