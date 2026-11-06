<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;
use App\Models\Personnel;
use Illuminate\Http\Request;

class PersonnelController extends Controller
{
    // Génère le prochain matricule disponible, du type PERS-0001
    protected function genererMatricule(): string
    {
        $dernier = Personnel::max('id') + 1;
        return 'PERS-' . str_pad($dernier, 4, '0', STR_PAD_LEFT);
    }

    protected function rules(Request $request, $id = null, bool $pourCreation = false): array
    {
        $paiementHoraire = $request->boolean('paiement_horaire');

        $regles = [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'type' => 'required|in:enseignant,administratif,securite,entretien,autre',
            'fonction' => 'nullable|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'salaire_base' => $paiementHoraire ? 'nullable|numeric|min:0' : 'required|numeric|min:0',
            'paiement_horaire' => 'nullable|boolean',
            'taux_horaire' => $paiementHoraire ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'actif' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'cycle_id' => 'nullable|integer|exists:cycles,id',
            'date_embauche' => 'nullable|date|before_or_equal:today',
            'statut' => 'nullable|in:' . implode(',', array_keys(Personnel::STATUTS)),
        ];

        if (!$pourCreation) {
            $regles['matricule'] = 'required|string|max:50|unique:personnels,matricule' . ($id ? ",$id" : '');
        }

        return $regles;
    }

    // Liste avec recherche (nom, prénom, matricule, téléphone, fonction) et filtres
    public function index(Request $request)
    {
        $recherche = trim((string) $request->get('q'));
        $query = Personnel::with('cycle')->orderBy('nom')->orderBy('prenom')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('cycle'), fn ($q) => $request->cycle === 'commun' ? $q->whereNull('cycle_id') : $q->where('cycle_id', $request->cycle))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    foreach (['nom', 'prenom', 'matricule', 'telephone', 'fonction'] as $champ) {
                        $q->orWhere($champ, 'like', "%{$recherche}%");
                    }
                });
            });
        $personnels = $query->paginate(20)->withQueryString();

        return view('personnels.index', [
            'personnels' => $personnels,
            'recherche' => $recherche,
            'cycles' => \App\Models\Cycle::orderBy('nom')->get(),
        ]);
    }

    // Fiche complète d'un membre du personnel ; la rémunération n'apparaît
    // qu'aux comptes qui ont accès à la paie
    public function show(Personnel $personnel)
    {
        // Gestion du personnel, ou Principal pour le personnel de son cycle
        abort_unless(RechercheController::personnelVisible(auth()->user())->whereKey($personnel->id)->exists()
            && RechercheController::peutRechercherPersonnel(auth()->user()), 403);
        $personnel->load(['cycle', 'paies' => fn ($q) => $q->orderByDesc('mois')->take(6)]);
        $voirPaie = auth()->user()->peut('paie');

        return view('personnels.show', [
            'personnel' => $personnel,
            'voirPaie' => $voirPaie,
            'comptes' => \App\Models\User::where('personnel_id', $personnel->id)->get(),
            'creneaux' => \App\Models\EmploiTemps::with(['classe', 'matiere'])->where('enseignant_id', $personnel->id)->orderBy('jour')->orderBy('heure_debut')->get(),
            'documents' => \App\Models\DocumentPersonnel::where('personnel_id', $personnel->id)->count(),
            'attestations' => auth()->user()->peut('attestations_travail')
                ? \App\Models\AttestationTravail::where('personnel_id', $personnel->id)->orderByDesc('date_delivrance')->orderByDesc('id')->get()
                : null,
            'pointagesMois' => $personnel->pointages()->whereYear('date', now()->year)->whereMonth('date', now()->month)->get(),
        ]);
    }

    public function create()
    {
        $matriculePropose = $this->genererMatricule();
        return view('personnels.create', compact('matriculePropose'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request, null, true));
        $data['actif'] = $request->has('actif');
        $data['paiement_horaire'] = $request->has('paiement_horaire');
        $data['salaire_base'] = $data['paiement_horaire'] ? 0 : $data['salaire_base'];
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            $data['photo'] = Fichier::enregistrer($request->file('photo'), 'photos_personnel', 'pers_');
        }

        $personnel = Personnel::create($data);

        $personnel->matricule = 'PERS-' . str_pad($personnel->id, 4, '0', STR_PAD_LEFT);
        $personnel->save();

        return redirect()->route('personnels.index')->with('success', 'Membre du personnel ajouté (matricule ' . $personnel->matricule . ').');
    }

    public function edit(Personnel $personnel)
    {
        return view('personnels.edit', compact('personnel'));
    }

    public function update(Request $request, Personnel $personnel)
    {
        $data = $request->validate($this->rules($request, $personnel->id));
        $data['actif'] = $request->has('actif');
        $data['paiement_horaire'] = $request->has('paiement_horaire');
        $data['salaire_base'] = $data['paiement_horaire'] ? 0 : ($data['salaire_base'] ?? 0);
        unset($data['photo']);
        if ($request->hasFile('photo')) {
            Fichier::supprimer($personnel->photo);
            $data['photo'] = Fichier::enregistrer($request->file('photo'), 'photos_personnel', 'pers_');
        }
        $personnel->update($data);

        return redirect()->route('personnels.show', $personnel)->with('success', 'Fiche mise à jour.');
    }

    public function destroy(Personnel $personnel)
    {
        // Ses bulletins de paie et ses salaires disparaîtraient de la comptabilité : on le rend inactif à la place
        if ($personnel->paies()->exists()) {
            return back()->with('erreur', "Impossible de supprimer {$personnel->nom} {$personnel->prenom} : des bulletins de paie sont enregistrés à son nom. "
                . "Modifiez sa fiche et décochez « Actif » s'il a quitté l'établissement.");
        }
        $personnel->delete();
        return redirect()->route('personnels.index')->with('success', 'Membre du personnel supprimé.');
    }

    public function badge(Personnel $personnel)
    {
        return view('cartes.page', [
            'titre' => "Badge — {$personnel->nom} {$personnel->prenom}",
            'retour' => route('personnels.index'),
            'config' => \App\Models\ConfigurationEcole::actuelle(),
            'personnels' => collect([$personnel]),
        ]);
    }

    // Badges de tout le personnel actif, à imprimer en une fois
    public function badges()
    {
        $personnels = Personnel::where('actif', true)->orderBy('nom')->orderBy('prenom')->get();

        return view('cartes.page', [
            'titre' => "Badges du personnel ({$personnels->count()})",
            'retour' => route('personnels.index'),
            'config' => \App\Models\ConfigurationEcole::actuelle(),
            'personnels' => $personnels,
        ]);
    }
}
