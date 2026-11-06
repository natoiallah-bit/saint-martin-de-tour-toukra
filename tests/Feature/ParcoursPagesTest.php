<?php

namespace Tests\Feature;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Parcours général : ouvre toutes les pages (GET) de l'application avec chaque
 * rôle et vérifie qu'aucune ne provoque d'erreur serveur (500).
 */
class ParcoursPagesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    private array $crees = [];

    // Crée un enregistrement en remplissant les colonnes obligatoires avec des valeurs plausibles
    private function creer(string $classe, array $valeurs = []): Model
    {
        /** @var Model $modele */
        $modele = new $classe();
        $table = $modele->getTable();
        foreach (Schema::getColumns($table) as $col) {
            $nom = $col['name'];
            if (array_key_exists($nom, $valeurs) || $col['auto_increment'] || in_array($nom, ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            if ($col['nullable'] && ! str_ends_with($nom, '_id')) {
                continue;
            }
            if ($col['default'] !== null && ! str_ends_with($nom, '_id')) {
                continue;
            }
            $type = strtolower($col['type_name']);
            if (str_ends_with($nom, '_id')) {
                $cible = Str::studly(Str::beforeLast($nom, '_id'));
                $valeurs[$nom] = ($this->crees[$cible] ?? null)?->getKey();
                if ($valeurs[$nom] === null && ! $col['nullable']) {
                    $valeurs[$nom] = 1;
                }
                continue;
            }
            $valeurs[$nom] = match (true) {
                str_contains($type, 'date') || str_contains($type, 'time') => now()->toDateString(),
                str_contains($type, 'int') || str_contains($type, 'dec') || str_contains($type, 'num') || str_contains($type, 'float') || str_contains($type, 'double') => 1,
                str_contains($type, 'bool') || str_contains($type, 'tinyint') => 1,
                str_contains($type, 'json') => '[]',
                default => Str::limit($nom . '-' . Str::random(4), 18, ''),
            };
        }

        $modele->forceFill($valeurs)->save();

        return $modele;
    }

    public function test_aucune_page_ne_provoque_d_erreur(): void
    {
        $cycle = $this->cycle('Primaire');
        $eleve = $this->eleve($cycle, 'PARCOURS');
        $eleve->update(['sexe' => 'F', 'date_naissance' => '2015-01-01']);
        $this->crees = ['Cycle' => $cycle, 'Classe' => $eleve->classe, 'Eleve' => $eleve, 'ClasseScolaire' => $eleve->classe];

        $personnel = Models\Personnel::create(['matricule' => 'PERS-0001', 'nom' => 'PROF', 'prenom' => 'Test', 'type' => 'enseignant', 'salaire_base' => 100000, 'actif' => true, 'cycle_id' => $cycle->id, 'statut' => 'permanent', 'date_embauche' => '2020-09-01']);
        $this->crees['Personnel'] = $personnel;
        $this->crees['Enseignant'] = Models\Enseignant::create(['nom' => 'PROF', 'prenom' => 'Test']);
        $matiere = Models\Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Calcul', 'coefficient' => 2, 'categorie' => 'Scientifique', 'enseignant_id' => $personnel->id]);
        $this->crees['Matiere'] = $matiere;
        Models\EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'matiere_id' => $matiere->id, 'enseignant_id' => $personnel->id]);
        Models\FraisScolarite::create(['cycle_id' => $cycle->id, 'annee_scolaire' => Models\ConfigurationEcole::anneeActive(), 'montant_annuel' => 100000]);

        $direction = $this->utilisateur('direction');
        $paiement = $this->paiement($eleve);
        $paiement->update(['annee_scolaire' => Models\ConfigurationEcole::anneeActive(), 'saisi_par' => $direction->id]);
        $paie = Models\Paie::create(['personnel_id' => $personnel->id, 'mois' => now()->format('Y-m'), 'salaire_base' => 100000, 'primes' => 0, 'acompte' => 0, 'retenues' => 0, 'date_paiement' => now()->toDateString()]);
        $paie->synchroniserDepense();
        $this->crees += ['Paiement' => $paiement, 'Paie' => $paie, 'User' => $direction];

        $objets = [
            'operation' => Models\OperationFinanciere::create(['type' => 'depense', 'categorie' => 'Carburant', 'montant' => 5000, 'mode_paiement' => 'Espèces', 'date_operation' => now()->toDateString(), 'cycle_id' => $cycle->id]),
            'materiel' => Models\Materiel::create(['nom' => 'Bancs', 'categorie' => 'Mobilier', 'quantite' => 3, 'etat' => 'bon', 'valeur_estimee' => 1000, 'cycle_id' => $cycle->id]),
            'carte' => Models\CarteRetrait::create(['numero' => 'RET-0001', 'pere_nom' => 'Moussa', 'responsable_nom' => 'Moussa', 'responsable_lien' => 'Père', 'responsable_sexe' => 'M', 'responsable_telephone' => '1']),
            'dossier' => Models\DossierDiscipline::create(['eleve_id' => $eleve->id, 'motif' => 'Retards', 'date_conseil' => now()->addDay()]),
        ];
        $objets['carte']->eleves()->attach($eleve->id);
        foreach (['CahierTexte', 'Evaluation', 'CertificatScolarite', 'AttestationTravail'] as $nom) {
            $this->crees[$nom] = $this->creer('App\\Models\\' . $nom, match ($nom) {
                'CahierTexte', 'Evaluation' => ['classe_id' => $eleve->classe_id, 'matiere_id' => $matiere->id] + (in_array($nom, ['Evaluation'], true) ? ['trimestre' => 1] : []),
                'CertificatScolarite' => ['eleve_id' => $eleve->id, 'numero' => 'CERT-2026-0001'],
                'AttestationTravail' => ['personnel_id' => $personnel->id, 'numero' => 'AT-2026-0001'],
                default => [],
            });
        }

        $parametres = [
            'eleve' => $eleve->id, 'classe' => $eleve->classe_id, 'class' => $eleve->classe_id, 'matiere' => $matiere->id, 'matieres_gestion' => $matiere->id,
            'personnel' => $personnel->id, 'paiement' => $paiement->id, 'paie' => $paie->id, 'id' => $paie->id, 'trimestre' => 1,
            'user' => $direction->id, 'certificat' => $this->crees['CertificatScolarite']->id, 'attestation' => $this->crees['AttestationTravail']->id,
            'numero' => 'X',
        ] + collect($objets)->map->getKey()->all();

        $principal = $this->utilisateur('principal', $cycle);
        $enseignant = $this->utilisateur('enseignant');
        $enseignant->update(['personnel_id' => $personnel->id, 'enseignant_id' => $this->crees['Enseignant']->id]);
        $comptes = [
            'direction' => $direction,
            'secretariat' => $this->utilisateur('secretariat'),
            'comptable' => $this->utilisateur('comptable', $cycle),
            'enseignant' => $enseignant,
            'surveillant' => $this->utilisateur('surveillant'),
            'principal' => $principal,
            'parent' => $this->parentDe($eleve),
        ];

        $erreurs = [];
        $ouvertes = 0;
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || ! in_array('auth', $route->gatherMiddleware(), true)
                || preg_match('#^(storage|fichiers|pieces-jointes|logout|_)#', $uri) || str_contains($uri, '{chemin}') || str_contains($uri, '{piece}')) {
                continue;
            }
            preg_match_all('/\{(\w+)\??\}/', $uri, $m);
            $valeurs = [];
            foreach ($m[1] as $p) {
                // « seance » : séance du cahier de texte ou séance de classe virtuelle selon la page
                $valeurs[$p] = $p === 'seance'
                    ? (str_starts_with($uri, 'cahier') ? $this->crees['CahierTexte']->id : $this->crees['SeanceEnLigne']->id)
                    : ($parametres[$p] ?? null);
            }
            if (in_array(null, $valeurs, true)) {
                $erreurs[] = "paramètre inconnu : $uri";
                continue;
            }
            $url = '/' . preg_replace_callback('/\{(\w+)\??\}/', fn ($x) => $valeurs[$x[1]], $uri);

            foreach ($comptes as $role => $compte) {
                $reponse = $this->actingAs($compte)->get($url);
                $ouvertes++;
                if ($reponse->getStatusCode() >= 500) {
                    $message = $reponse->exception ? class_basename($reponse->exception) . ' : ' . Str::limit($reponse->exception->getMessage(), 220) : '';
                    $erreurs[] = "[$role] GET $url → {$reponse->getStatusCode()} $message";
                }
            }
        }

        // Pages publiques de vérification (liens signés des QR codes)
        foreach ([['verification.recu', $paiement->numero_recu], ['verification.carte', $eleve->id], ['verification.badge', $personnel->id], ['verification.paie', $paie->id],
                  ['verification.retrait', $objets['carte']->id], ['verification.certificat', 'CERT-2026-0001'], ['verification.attestation', 'AT-2026-0001'], ['verification.bulletin', [$eleve->id, 1]]] as [$nom, $p]) {
            auth()->logout();
            $reponse = $this->get(URL::signedRoute($nom, $p, null, false));
            if ($reponse->getStatusCode() >= 500) {
                $erreurs[] = "[public] $nom → {$reponse->getStatusCode()} " . ($reponse->exception ? Str::limit($reponse->exception->getMessage(), 220) : '');
            }
        }

        $this->assertGreaterThan(500, $ouvertes);
        $this->assertSame([], $erreurs, implode("\n", $erreurs));
    }
}
