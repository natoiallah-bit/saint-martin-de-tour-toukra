<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;

use App\Helpers\PorteeCycle;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\ClasseScolaire;
use Illuminate\Http\Request;

class EleveController extends Controller
{
    // Matricule affiché (à titre indicatif) sur le formulaire de création
    protected function genererMatricule(): string
    {
        return Eleve::prefixeMatricule() . str_pad(Eleve::max('id') + 1, 4, '0', STR_PAD_LEFT);
    }

    // Matricule définitif, calculé à partir de l'id réel attribué par la base.
    // Si ce numéro est déjà pris (ex. matricule venu d'un import Excel), on ajoute un suffixe.
    protected function attribuerMatricule(Eleve $eleve): void
    {
        $eleve->attribuerMatricule();
    }

    protected function rules($eleveId = null): array
    {
        return [
            'classe_id' => ['required', PorteeCycle::regleClasse()],
            // Généré automatiquement à la création ; modifiable ensuite seulement par le formulaire d'édition
            'matricule' => $eleveId
                ? 'required|string|max:50|unique:eleves,matricule,' . $eleveId
                : 'prohibited',
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:150',
            'sexe' => 'nullable|in:M,F',
            'quartier' => 'nullable|string|max:150',
            'date_entree' => 'nullable|date|before_or_equal:today',
            'ancienne_ecole' => 'nullable|string|max:200',
            'aptitude_physique' => 'nullable|in:apte,inapte_partiel,inapte_total',
            'aptitude_remarque' => 'nullable|string|max:500',
            'aptitude_jusqu_au' => 'nullable|date',
            'tuteur_nom' => 'nullable|string|max:150',
            'pere_nom' => 'nullable|string|max:150',
            'pere_telephone' => 'nullable|string|max:30',
            'pere_email' => 'nullable|email|max:150',
            'mere_nom' => 'nullable|string|max:150',
            'mere_telephone' => 'nullable|string|max:30',
            'tuteur_telephone' => 'nullable|string|max:30',
            'exonere' => 'nullable|boolean',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ];
    }

    // Gère l'upload de la photo et retourne le chemin à enregistrer, ou null
    protected function traiterPhoto(Request $request): ?string
    {
        if (!$request->hasFile('photo')) {
            return null;
        }

        return Fichier::enregistrer($request->file('photo'), 'photos_eleves', 'eleve_');
    }

    public function index(Request $request)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $classeId = $request->integer('classe_id') ?: null;
        $recherche = trim((string) $request->get('q'));

        // Classe par classe dans l'ordre de l'école (maternelle → lycée, CP1 → CM2, 6e → 3e…),
        // puis élèves par ordre alphabétique (nom, puis prénom)
        $eleves = Eleve::with('classe.cycle')
            ->join('classes', 'eleves.classe_id', '=', 'classes.id')
            ->when($cycleId, fn ($q) => $q->where('classes.cycle_id', $cycleId))
            ->when($classeId, fn ($q) => $q->where('eleves.classe_id', $classeId))
            ->when($recherche !== '', function ($q) use ($recherche) {
                $q->where(function ($q) use ($recherche) {
                    $q->where('eleves.nom', 'like', "%{$recherche}%")
                        ->orWhere('eleves.prenom', 'like', "%{$recherche}%")
                        ->orWhere('eleves.matricule', 'like', "%{$recherche}%");
                });
            })
            ->orderBy('classes.ordre')
            ->orderBy('classes.nom')
            ->orderBy('eleves.nom')
            ->orderBy('eleves.prenom')
            ->select('eleves.*')
            ->paginate(20)
            ->withQueryString();

        // Classes du filtre et de l'impression des cartes de toute une classe
        $classesCartes = ClasseScolaire::when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->ordonnees()->get();

        return view('eleves.index', compact('eleves', 'classesCartes', 'classeId', 'recherche'));
    }

    // Liste imprimable complète (sans pagination), regroupée par classe,
    // avec les élèves triés par ordre alphabétique dans chaque classe.
    public function listeImprimable()
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $classes = ClasseScolaire::with(['cycle', 'eleves' => function ($q) {
                $q->orderBy('nom')->orderBy('prenom');
            }])
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();

        return view('eleves.liste-imprimable', compact('classes'));
    }

    public function create()
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();

        $matriculePropose = $this->genererMatricule();

        return view('eleves.create', compact('classes', 'matriculePropose'));
    }

    public function store(Request $request)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        $data = $request->validate($this->rules());
        $data['exonere'] = $request->has('exonere');

        $cheminPhoto = $this->traiterPhoto($request);
        if ($cheminPhoto) {
            $data['photo'] = $cheminPhoto;
        } else {
            unset($data['photo']);
        }

        $eleve = Eleve::create($data);
        $this->attribuerMatricule($eleve);

        return redirect()->route('eleves.index')->with('success', 'Élève ajouté avec succès (matricule ' . $eleve->matricule . ').');
    }

    public function edit(Eleve $eleve)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        PorteeCycle::verifierEleve($eleve);

        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        $classes = ClasseScolaire::with('cycle')
            ->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))
            ->ordonnees()
            ->get();

        return view('eleves.edit', compact('eleve', 'classes'));
    }

    public function update(Request $request, Eleve $eleve)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        PorteeCycle::verifierEleve($eleve);

        $data = $request->validate($this->rules($eleve->id));
        $data['exonere'] = $request->has('exonere');

        $cheminPhoto = $this->traiterPhoto($request);
        if ($cheminPhoto) {
            // Supprime l'ancienne photo si elle existe
            Fichier::supprimer($eleve->photo);
            $data['photo'] = $cheminPhoto;
        } else {
            unset($data['photo']);
        }

        $eleve->update($data);

        return redirect()->route('recherche.eleve', $eleve)->with('success', 'Élève mis à jour.');
    }

    public function destroy(Eleve $eleve)
    {
        if (auth()->user()->role === 'parent') {
            abort(403);
        }

        PorteeCycle::verifierEleve($eleve);

        // Ses paiements disparaîtraient avec lui, sans trace : on les supprime d'abord (avec motif) s'il le faut
        if (\App\Models\Paiement::where('eleve_id', $eleve->id)->exists()) {
            return back()->with('erreur', "Impossible de supprimer {$eleve->nom} {$eleve->prenom} : des paiements de scolarité sont enregistrés à son nom. "
                . "S'il s'agit d'une erreur, supprimez d'abord ces paiements (avec un motif) dans Scolarité.");
        }

        $eleve->delete();
        return redirect()->route('eleves.index')->with('success', 'Élève supprimé.');
    }

    public function carte(Eleve $eleve)
    {
        if (auth()->user()->role === 'parent' && ! auth()->user()->estParentDe($eleve->id)) {
            abort(403, "Cette carte ne concerne pas l'un de vos enfants.");
        }
        if (auth()->user()->role !== 'parent') {
            PorteeCycle::verifierEleve($eleve);
        }

        $eleve->load('classe.cycle', 'cartesRetrait');

        return view('cartes.page', [
            'titre' => "Carte scolaire — {$eleve->nom} {$eleve->prenom}",
            'retour' => auth()->user()->role === 'parent' ? route('parent.dashboard') : route('eleves.index'),
            'config' => ConfigurationEcole::actuelle(),
            'eleves' => collect([$eleve]),
        ]);
    }

    // Cartes scolaires de toute une classe, à imprimer en une fois
    public function cartes(Request $request)
    {
        $request->validate(['classe_id' => 'required|integer']);

        $classe = ClasseScolaire::with('cycle')->findOrFail($request->classe_id);
        PorteeCycle::verifierClasse($classe);

        $eleves = $classe->eleves()->with('classe.cycle', 'cartesRetrait')->orderBy('nom')->orderBy('prenom')->get();

        return view('cartes.page', [
            'titre' => "Cartes scolaires — {$classe->nom} ({$eleves->count()} élève" . ($eleves->count() > 1 ? 's' : '') . ')',
            'retour' => route('eleves.index'),
            'config' => ConfigurationEcole::actuelle(),
            'eleves' => $eleves,
        ]);
    }
}
