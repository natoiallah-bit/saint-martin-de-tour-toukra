<?php

namespace App\Http\Controllers;

use App\Helpers\Assiduite;
use App\Helpers\BulletinCalcul;
use App\Helpers\PorteeCycle;
use App\Models\CarteRetrait;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\EmploiTemps;
use App\Models\FraisScolarite;
use App\Models\Paiement;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Http\Request;

// Recherche d'un élève ou d'un membre du personnel (barre du haut de chaque page).
// Chaque compte ne voit que ce que ses droits lui permettent, dans son cycle.
class RechercheController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless(self::peutRechercherEleves($user) || self::peutRechercherPersonnel($user), 403);
        $q = trim((string) $request->get('q'));
        $cycleId = PorteeCycle::pourUtilisateurConnecte();

        $eleves = collect();
        $personnels = collect();
        if (mb_strlen($q) >= 2) {
            if (self::peutRechercherEleves($user)) {
                // Chaque mot doit se retrouver dans le nom, le prénom ou le matricule (« Moussa Ali »)
                $eleves = Eleve::with('classe.cycle')
                    ->when($cycleId, fn ($r) => $r->whereHas('classe', fn ($c) => $c->where('cycle_id', $cycleId)));
                foreach (preg_split('/\s+/', $q) as $mot) {
                    $eleves->where(fn ($r) => $r->where('nom', 'like', "%{$mot}%")->orWhere('prenom', 'like', "%{$mot}%")->orWhere('matricule', 'like', "%{$mot}%"));
                }
                $eleves = $eleves->orderBy('nom')->orderBy('prenom')->take(30)->get();
            }
            if (self::peutRechercherPersonnel($user)) {
                $personnels = self::personnelVisible($user)
                    ->where(fn ($r) => $r->where('nom', 'like', "%{$q}%")->orWhere('prenom', 'like', "%{$q}%")
                        ->orWhere('matricule', 'like', "%{$q}%")->orWhere('telephone', 'like', "%{$q}%")
                        ->orWhere('fonction', 'like', "%{$q}%"))
                    ->orderBy('nom')->orderBy('prenom')->take(30)->get();
            }
        }

        return view('recherche.index', compact('q', 'eleves', 'personnels'));
    }

    // Fiche complète d'un élève, rubrique par rubrique selon les droits du compte
    public function eleve(Eleve $eleve)
    {
        $user = auth()->user();
        abort_unless(self::peutRechercherEleves($user), 403);
        PorteeCycle::verifierEleve($eleve);
        $eleve->load('classe.cycle');
        $annee = ConfigurationEcole::anneeActive();

        $donnees = ['eleve' => $eleve, 'annee' => $annee];

        if ($user->peut('bulletins') && $eleve->classe_id) {
            $donnees['moyennes'] = collect([1, 2, 3])->mapWithKeys(fn ($t) => [$t => BulletinCalcul::moyennesClasse($eleve->classe_id, $t)[$eleve->id] ?? null]);
        }
        if ($user->peut('presences') && $eleve->classe_id) {
            $donnees['assiduite'] = collect([1, 2, 3])->mapWithKeys(fn ($t) => [$t => Assiduite::pourEleve($eleve->id, $eleve->classe_id, $t)]);
        }
        if ($user->peut('certificats')) {
            $donnees['certificats'] = \App\Models\CertificatScolarite::where('eleve_id', $eleve->id)->orderByDesc('date_delivrance')->orderByDesc('id')->get();
        }
        if ($user->peut('scolarite')) {
            $frais = $eleve->classe ? FraisScolarite::pourCycle($eleve->classe->cycle_id, $annee) : null;
            $verse = Paiement::totalVerse($eleve->id, $annee);
            $donnees['finances'] = [
                'annuel' => $frais ? (float) $frais->montant_annuel : 0,
                'exigible' => $frais ? $frais->montantExigible() : 0,
                'verse' => $verse,
                'derniers' => Paiement::where('eleve_id', $eleve->id)->where('annee_scolaire', $annee)->orderByDesc('date_paiement')->take(5)->get(),
            ];
        }
        if ($user->peut('discipline')) {
            $donnees['discipline'] = \App\Models\DossierDiscipline::where('eleve_id', $eleve->id)->orderByDesc('created_at')->get();
        }
        if ($user->peut('cartes_retrait')) {
            $donnees['cartesRetrait'] = CarteRetrait::whereHas('eleves', fn ($q) => $q->where('eleves.id', $eleve->id))->get();
        }

        return view('recherche.eleve', $donnees);
    }

    public static function peutRechercherEleves(User $user): bool
    {
        return ! in_array($user->role, ['parent', 'eleve'], true)
            && collect(['eleves', 'scolarite', 'bulletins', 'presences'])->contains(fn ($m) => $user->peut($m));
    }

    // Personnel : ceux qui gèrent le personnel, et le Principal (enseignants de son cycle)
    public static function peutRechercherPersonnel(User $user): bool
    {
        return $user->peut('personnel') || $user->aRole('principal');
    }

    // Membres du personnel que le compte peut voir
    public static function personnelVisible(User $user)
    {
        $requete = Personnel::with('cycle');
        if ($user->peut('personnel')) {
            return $requete;
        }

        // Principal d'un cycle : personnel affecté au cycle, ou qui enseigne dans ses classes
        $cycleId = PorteeCycle::pourUtilisateurConnecte();
        if (! $cycleId) {
            return $requete;
        }
        $enseignants = EmploiTemps::whereIn('classe_id', ClasseScolaire::where('cycle_id', $cycleId)->pluck('id'))->pluck('enseignant_id');

        return $requete->where(fn ($q) => $q->where('cycle_id', $cycleId)->orWhereIn('id', $enseignants));
    }
}
