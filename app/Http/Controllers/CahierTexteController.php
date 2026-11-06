<?php

namespace App\Http\Controllers;

use App\Helpers\CoursEnLigne;
use App\Helpers\Rapport;
use App\Models\CahierTexte;
use App\Models\ClasseScolaire;
use App\Models\EmploiTemps;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

// Cahier de texte : l'enseignant note ce qu'il a fait à chaque séance et le travail donné ;
// le Principal (ou le directeur du primaire) suit l'avancement et vise les séances.
class CahierTexteController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $classes = CoursEnLigne::classesGerees($user, 'cahier_texte');
        $classeId = $request->integer('classe_id') ?: null;
        // Seulement une classe que le compte a le droit de suivre
        if ($classeId && ! $classes->contains('id', $classeId)) {
            $classeId = null;
        }
        $matiereId = $request->integer('matiere_id') ?: null;

        // Période : celle choisie, sinon le trimestre en cours, sinon les 30 derniers jours
        $trimestre = Rapport::periode(Rapport::periodeEnCours());
        $du = $request->filled('du') ? Carbon::parse($request->du) : ($trimestre['debut'] ?? now()->subDays(30));
        $au = $request->filled('au') ? Carbon::parse($request->au) : ($trimestre['fin'] && $trimestre['fin']->lt(now()) ? $trimestre['fin'] : now());

        $affectations = CoursEnLigne::affectations($user, 'cahier_texte');
        $requete = CahierTexte::with(['classe', 'matiere', 'auteur', 'viseur'])
            ->whereIn('classe_id', $classes->pluck('id'))
            ->when($classeId, fn ($q) => $q->where('classe_id', $classeId))
            ->when($matiereId, fn ($q) => $q->where('matiere_id', $matiereId))
            ->whereDate('date', '>=', $du->toDateString())
            ->whereDate('date', '<=', $au->toDateString());

        // Un enseignant ne voit que ses propres matières
        if ($affectations !== null) {
            $requete->where(function ($q) use ($affectations) {
                foreach ($affectations as [$classe, $matiere]) {
                    $q->orWhere(fn ($qq) => $qq->where('classe_id', $classe)->where('matiere_id', $matiere));
                }
                $q->orWhereRaw('1 = 0');
            });
        }

        $seances = $requete->orderByDesc('date')->orderByDesc('id')->paginate(30)->withQueryString();
        $suivi = $affectations === null ? $this->suivi($classes, $classeId, $du, $au) : collect();

        return view('cahier-texte.index', [
            'seances' => $seances,
            'classes' => $classes,
            'matieres' => $classeId && ($c = $classes->firstWhere('id', $classeId)) ? CoursEnLigne::matieresGerees($user, $c, 'cahier_texte') : collect(),
            'classeId' => $classeId,
            'matiereId' => $matiereId,
            'du' => $du,
            'au' => $au,
            'suivi' => $suivi,
            'peutViser' => $this->peutViser(),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        $classes = CoursEnLigne::classesGerees($user, 'cahier_texte');
        $seance = new CahierTexte([
            'classe_id' => $request->integer('classe_id') ?: $classes->first()?->id,
            'matiere_id' => $request->integer('matiere_id') ?: null,
            'emploi_temps_id' => $request->integer('emploi_temps_id') ?: null,
            'date' => $request->input('date', now()->toDateString()),
        ]);

        return view('cahier-texte.form', ['seance' => $seance] + $this->listes($seance));
    }

    public function store(Request $request)
    {
        $donnees = $this->valider($request);
        $seance = CahierTexte::create($donnees + ['auteur_id' => auth()->id()]);

        return redirect()->route('cahier-texte.index', ['classe_id' => $seance->classe_id])
            ->with('success', 'Séance du ' . $seance->date->format('d/m/Y') . ' inscrite au cahier de texte.');
    }

    public function edit(CahierTexte $seance)
    {
        $this->autoriser($seance->classe_id, $seance->matiere_id);

        return view('cahier-texte.form', ['seance' => $seance] + $this->listes($seance));
    }

    public function update(Request $request, CahierTexte $seance)
    {
        $this->autoriser($seance->classe_id, $seance->matiere_id);
        $seance->update($this->valider($request));

        return redirect()->route('cahier-texte.index', ['classe_id' => $seance->classe_id])->with('success', 'Séance modifiée.');
    }

    public function destroy(CahierTexte $seance)
    {
        $this->autoriser($seance->classe_id, $seance->matiere_id);
        $seance->delete();

        return back()->with('success', 'Séance supprimée du cahier de texte.');
    }

    // Visa du Principal : observation et date de contrôle
    public function viser(Request $request, CahierTexte $seance)
    {
        abort_unless($this->peutViser(), 403, 'Seuls le Principal et la Direction visent le cahier de texte.');
        $this->autoriser($seance->classe_id, $seance->matiere_id);
        $request->validate(['visa' => 'nullable|string|max:2000']);
        $seance->update(['visa' => $request->visa ?: 'Vu', 'vise_par' => auth()->id(), 'vise_le' => now()]);

        return back()->with('success', 'Séance visée.');
    }

    // Séances prévues à l'emploi du temps et séances remplies, par classe et matière
    protected function suivi($classes, ?int $classeId, Carbon $du, Carbon $au)
    {
        $fin = $au->copy()->min(now());
        $joursParNom = collect(CarbonPeriod::create($du->copy()->startOfDay(), $fin->copy()->startOfDay()))
            ->countBy(fn ($jour) => EmploiTemps::jourDe($jour));

        $creneaux = EmploiTemps::with(['matiere', 'enseignant', 'classe'])
            ->whereIn('classe_id', $classeId ? [$classeId] : $classes->pluck('id'))
            ->get()
            ->groupBy(fn ($c) => $c->classe_id . '-' . $c->matiere_id);

        $remplies = CahierTexte::whereIn('classe_id', $classeId ? [$classeId] : $classes->pluck('id'))
            ->whereDate('date', '>=', $du->toDateString())->whereDate('date', '<=', $fin->toDateString())
            ->get()->groupBy(fn ($s) => $s->classe_id . '-' . $s->matiere_id);

        return $creneaux->map(function ($liste, $cle) use ($joursParNom, $remplies) {
            $premier = $liste->first();
            $prevues = $liste->sum(fn ($c) => $joursParNom->get($c->jour, 0));
            $faites = $remplies->get($cle, collect());

            return [
                'classe' => $premier->classe,
                'matiere' => $premier->matiere,
                'enseignant' => $liste->pluck('enseignant')->filter()->unique('id')->map(fn ($p) => trim($p->prenom . ' ' . $p->nom))->implode(', '),
                'prevues' => $prevues,
                'remplies' => $faites->count(),
                'derniere' => $faites->max('date'),
                'taux' => $prevues > 0 ? min(100, round($faites->count() * 100 / $prevues)) : null,
            ];
        })->sortBy(fn ($l) => [$l['classe']->ordre ?? 9999, $l['classe']->nom ?? '', $l['matiere']->nom ?? ''])->values();
    }

    protected function valider(Request $request): array
    {
        $donnees = $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'matiere_id' => 'required|integer|exists:matieres,id',
            'emploi_temps_id' => 'nullable|integer|exists:emplois_temps,id',
            'date' => 'required|date|before_or_equal:today',
            'titre' => 'required|string|max:200',
            'contenu' => 'nullable|string|max:10000',
            'travail_a_faire' => 'nullable|string|max:5000',
        ], ['date.before_or_equal' => 'On ne remplit le cahier de texte que pour une séance déjà faite.']);
        $this->autoriser((int) $donnees['classe_id'], (int) $donnees['matiere_id']);
        // Le créneau choisi doit appartenir à cette classe
        if (! empty($donnees['emploi_temps_id'])
            && ! \App\Models\EmploiTemps::whereKey($donnees['emploi_temps_id'])->where('classe_id', $donnees['classe_id'])->exists()) {
            $donnees['emploi_temps_id'] = null;
        }

        return $donnees;
    }

    protected function listes(CahierTexte $seance): array
    {
        $user = auth()->user();
        $classes = CoursEnLigne::classesGerees($user, 'cahier_texte');
        $classe = $classes->firstWhere('id', $seance->classe_id) ?? $classes->first();

        return [
            'classes' => $classes,
            'matieres' => $classe ? CoursEnLigne::matieresGerees($user, $classe, 'cahier_texte') : collect(),
            'creneaux' => $classe ? EmploiTemps::with('matiere')->where('classe_id', $classe->id)->orderBy('heure_debut')->get() : collect(),
        ];
    }

    protected function autoriser(int $classeId, int $matiereId): void
    {
        abort_unless(CoursEnLigne::peutGerer(auth()->user(), $classeId, $matiereId, 'cahier_texte'), 403, "Vous n'enseignez pas cette matière dans cette classe.");
    }

    protected function peutViser(): bool
    {
        $user = auth()->user();

        return $user->peut('cahier_texte', 'modifier') && ! $user->estLimiteAuxClassesEnseignant('cahier_texte', 'modifier');
    }
}
