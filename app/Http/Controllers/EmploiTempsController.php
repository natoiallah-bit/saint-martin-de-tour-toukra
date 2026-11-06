<?php

namespace App\Http\Controllers;

use App\Models\ClasseScolaire;
use App\Models\EmploiTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use Illuminate\Http\Request;
use App\Helpers\PorteeCycle;

class EmploiTempsController extends Controller
{
    // Choix de la classe à afficher/éditer
    public function choix()
    {
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle')
            ->when($cycleId, fn($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();
        return view('emplois-temps.choix', compact('classes'));
    }

    // Grille hebdomadaire d'une classe
    public function index(ClasseScolaire $classe)
    {
        PorteeCycle::verifierClasse($classe);
        $creneaux = EmploiTemps::with(['matiere', 'enseignant'])
            ->where('classe_id', $classe->id)
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('jour');

        return view('emplois-temps.index', [
            'classe' => $classe,
            'creneaux' => $creneaux,
            'jours' => EmploiTemps::jours(),
        ]);
    }

    public function create(ClasseScolaire $classe)
    {
        $this->autoriserModification($classe);
        $matieres = Matiere::where('cycle_id', $classe->cycle_id)->orderBy('nom')->get();
        $enseignants = Personnel::where('type', 'enseignant')->where('actif', true)->orderBy('nom')->get();

        return view('emplois-temps.create', [
            'classe' => $classe,
            'matieres' => $matieres,
            'enseignants' => $enseignants,
            'jours' => EmploiTemps::jours(),
        ]);
    }

    public function suggestionEnseignant(ClasseScolaire $classe, Matiere $matiere)
    {
        PorteeCycle::verifierClasse($classe);
        $dernier = EmploiTemps::where('classe_id', $classe->id)
            ->where('matiere_id', $matiere->id)
            ->whereNotNull('enseignant_id')
            ->latest()
            ->first();

        return response()->json(['enseignant_id' => $dernier?->enseignant_id]);
    }

    public function store(Request $request, ClasseScolaire $classe)
    {
        $this->autoriserModification($classe);
        $data = $request->validate([
            'jour' => 'required|in:lundi,mardi,mercredi,jeudi,vendredi,samedi',
            'heure_debut' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'heure_fin' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/', 'after:heure_debut'],
            // La matière doit être enseignée dans le cycle de la classe
            'matiere_id' => ['required', \Illuminate\Validation\Rule::exists('matieres', 'id')->where('cycle_id', $classe->cycle_id)],
            'enseignant_id' => 'nullable|exists:personnels,id',
        ], ['matiere_id.exists' => "Cette matière n'est pas enseignée dans le cycle de cette classe."]);
        $data['classe_id'] = $classe->id;

        // Deux créneaux se chevauchent si l'un commence avant la fin de l'autre (heures comparées en HH:MM)
        $debut = substr($data['heure_debut'], 0, 5);
        $fin = substr($data['heure_fin'], 0, 5);
        $chevauche = fn ($c) => substr($c->heure_debut, 0, 5) < $fin && substr($c->heure_fin, 0, 5) > $debut;
        $duJour = EmploiTemps::where('jour', $data['jour'])->get();

        if ($duJour->where('classe_id', $classe->id)->contains($chevauche)) {
            return back()->withErrors(['heure_debut' => 'Un cours est déjà programmé sur ce créneau pour cette classe.'])->withInput();
        }
        if (! empty($data['enseignant_id']) && $duJour->where('enseignant_id', $data['enseignant_id'])->contains($chevauche)) {
            return back()->withErrors(['enseignant_id' => 'Cet enseignant a déjà un cours à cette heure-là dans une autre classe.'])->withInput();
        }

        EmploiTemps::create($data);

        return redirect()->route('emplois-temps.index', $classe)->with('success', 'Créneau ajouté à l\'emploi du temps.');
    }

    public function destroy(ClasseScolaire $classe, EmploiTemps $emploiTemp)
    {
        $this->autoriserModification($classe);
        abort_unless((int) $emploiTemp->classe_id === (int) $classe->id, 404);
        $emploiTemp->delete();
        return redirect()->route('emplois-temps.index', $classe)->with('success', 'Créneau supprimé.');
    }

    // Emploi du temps personnel de l'enseignant connecté, toutes classes confondues
    public function mesCours()
    {
        $user = auth()->user();
        if (! $user->personnel_id) {
            abort(403, "Votre compte n'est pas relié à une fiche personnel. Contactez la direction.");
        }

        $creneaux = EmploiTemps::with(['matiere', 'classe.cycle'])
            ->where('enseignant_id', $user->personnel_id)
            ->orderBy('heure_debut')
            ->get()
            ->groupBy('jour');

        return view('emplois-temps.mes-cours', [
            'creneaux' => $creneaux,
            'jours' => EmploiTemps::jours(),
        ]);
    }

    // Classe du cycle de l'utilisateur ; un enseignant consulte les emplois du temps sans les modifier
    // (sinon il pourrait s'ajouter un cours et obtenir l'appel, les notes et le cahier de texte de la classe)
    private function autoriserModification(ClasseScolaire $classe): void
    {
        PorteeCycle::verifierClasse($classe);
        abort_if(auth()->user()->estLimiteAuxClassesEnseignant('emplois_temps', 'modifier'), 403,
            "Les emplois du temps sont préparés par la Direction ou le Principal.");
    }
}
