<?php

namespace App\Http\Controllers;

use App\Models\ClasseScolaire;
use App\Models\Eleve;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportEleveController extends Controller
{
    /**
     * Champs de la table "eleves" proposés dans le mapping, avec quelques
     * variantes d'intitulés de colonnes couramment utilisées dans les
     * fichiers Excel, pour pré-sélectionner automatiquement la bonne colonne.
     */
    protected function champsDisponibles(): array
    {
        return [
            'nom' => ['label' => 'Nom *', 'variantes' => ['nom', 'nom de famille', 'nom eleve', 'name']],
            'prenom' => ['label' => 'Prénom *', 'variantes' => ['prenom', 'prénom', 'prenoms', 'prénoms']],
            'classe' => ['label' => 'Classe *', 'variantes' => ['classe', 'classe actuelle', 'niveau']],
            'sexe' => ['label' => 'Sexe', 'variantes' => ['sexe', 'genre']],
            'date_naissance' => ['label' => 'Date de naissance', 'variantes' => ['date de naissance', 'ne le', 'née le', 'date naissance', 'naissance']],
            'lieu_naissance' => ['label' => 'Lieu de naissance', 'variantes' => ['lieu de naissance', 'lieu naissance']],
            'quartier' => ['label' => 'Quartier', 'variantes' => ['quartier', 'adresse']],
            'matricule' => ['label' => 'Matricule', 'variantes' => ['matricule']],
            'tuteur_nom' => ['label' => 'Nom du tuteur', 'variantes' => ['tuteur', 'nom du tuteur', 'nom tuteur']],
            'pere_nom' => ['label' => 'Nom du père', 'variantes' => ['pere', 'père', 'nom du pere', 'nom du père']],
            'pere_telephone' => ['label' => 'Téléphone du père', 'variantes' => ['telephone pere', 'téléphone père', 'tel pere', 'tél père', 'contact pere']],
            'pere_email' => ['label' => 'Email du père', 'variantes' => ['email pere', 'email père', 'mail pere']],
            'mere_nom' => ['label' => 'Nom de la mère', 'variantes' => ['mere', 'mère', 'nom de la mere', 'nom de la mère']],
            'mere_telephone' => ['label' => 'Téléphone de la mère', 'variantes' => ['telephone mere', 'téléphone mère', 'tel mere', 'tél mère', 'contact mere']],
            'tuteur_telephone' => ['label' => 'Téléphone du tuteur', 'variantes' => ['telephone tuteur', 'téléphone tuteur', 'tel tuteur', 'tél tuteur', 'contact tuteur']],
        ];
    }

    protected function normaliser(string $texte): string
    {
        $texte = Str::of($texte)->lower()->trim();
        $texte = strtr($texte, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c',
        ]);
        return trim(preg_replace('/\s+/', ' ', $texte));
    }

    public function formulaireUpload()
    {
        return view('import.eleves-upload');
    }

    public function televerser(Request $request)
    {
        $request->validate([
            'fichier' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $fichier = $request->file('fichier');
        $nomStocke = 'import_' . time() . '_' . uniqid() . '.' . $fichier->getClientOriginalExtension();
        $fichier->move(storage_path('app/private/imports_temp'), $nomStocke);

        $chemin = storage_path('app/private/imports_temp/' . $nomStocke);

        try {
            $spreadsheet = IOFactory::load($chemin);
            $feuille = $spreadsheet->getActiveSheet();
            $lignes = $feuille->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            @unlink($chemin);
            return back()->withErrors(['fichier' => "Impossible de lire ce fichier : " . $e->getMessage()]);
        }

        if (count($lignes) < 2) {
            @unlink($chemin);
            return back()->withErrors(['fichier' => "Le fichier semble vide ou ne contient qu'une ligne d'en-tête."]);
        }

        $entetes = array_map(fn ($v) => trim((string) $v), $lignes[0]);
        $apercu = array_slice($lignes, 1, 5);

        // Pré-sélection automatique des colonnes selon l'intitulé détecté
        $champs = $this->champsDisponibles();
        $suggestions = [];
        foreach ($champs as $cle => $info) {
            $suggestions[$cle] = null;
            foreach ($entetes as $index => $entete) {
                if (in_array($this->normaliser($entete), array_map(fn ($v) => $this->normaliser($v), $info['variantes']), true)) {
                    $suggestions[$cle] = $index;
                    break;
                }
            }
        }

        return view('import.eleves-mapping', [
            'nomStocke' => $nomStocke,
            'entetes' => $entetes,
            'apercu' => $apercu,
            'champs' => $champs,
            'suggestions' => $suggestions,
            'totalLignes' => count($lignes) - 1,
        ]);
    }

    public function importer(Request $request)
    {
        $request->validate([
            'nom_stocke' => ['required', 'string'],
            'mapping' => ['required', 'array'],
        ]);

        $chemin = storage_path('app/private/imports_temp/' . basename($request->nom_stocke));

        if (! file_exists($chemin)) {
            return back()->withErrors(['fichier' => "Le fichier temporaire a expiré, merci de reténéverser le fichier."]);
        }

        $spreadsheet = IOFactory::load($chemin);
        // Valeurs brutes : une date Excel arrive en nombre de jours, convertie sans ambiguïté jour/mois
        $lignes = $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        $donnees = array_slice($lignes, 1); // on saute l'en-tête

        $mapping = $request->input('mapping'); // ['nom' => 2, 'prenom' => 3, ...] (index colonne ou "" si ignoré)

        // Seulement les classes du cycle de l'utilisateur s'il est limité à un cycle
        $cycleId = \App\Helpers\PorteeCycle::pourUtilisateurConnecte();
        $classesCache = ClasseScolaire::when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->get()
            ->keyBy(fn ($c) => $this->normaliser($c->nom));

        $crees = 0;
        $doublons = 0;
        $erreurs = [];

        foreach ($donnees as $numLigne => $ligne) {
            $valeur = function (string $champ) use ($ligne, $mapping) {
                $index = $mapping[$champ] ?? '';
                if ($index === '' || $index === null || ! isset($ligne[$index])) {
                    return null;
                }
                $v = trim((string) $ligne[$index]);
                // Les colonnes de la base acceptent 100 caractères au plus
                return $v === '' ? null : mb_substr($v, 0, 100);
            };

            $nom = $valeur('nom');
            $prenom = $valeur('prenom');
            $classeTexte = $valeur('classe');

            $numeroAffiche = $numLigne + 2; // +2 : index 0-based + ligne d'en-tête

            if (! $nom || ! $prenom) {
                $erreurs[] = "Ligne {$numeroAffiche} : nom ou prénom manquant, ligne ignorée.";
                continue;
            }

            if (! $classeTexte) {
                $erreurs[] = "Ligne {$numeroAffiche} ({$nom} {$prenom}) : classe non renseignée, ligne ignorée.";
                continue;
            }

            $classe = $classesCache->get($this->normaliser($classeTexte));
            if (! $classe) {
                $erreurs[] = "Ligne {$numeroAffiche} ({$nom} {$prenom}) : classe \"{$classeTexte}\" introuvable" . ($cycleId ? ' dans votre cycle' : " dans l'application") . ', ligne ignorée.';
                continue;
            }

            // Anti-doublon : même nom + prénom + classe déjà existants
            $existeDeja = Eleve::where('nom', $nom)
                ->where('prenom', $prenom)
                ->where('classe_id', $classe->id)
                ->exists();

            if ($existeDeja) {
                $doublons++;
                continue;
            }

            $dateNaissance = null;
            $dateTexte = $valeur('date_naissance');
            if ($dateTexte) {
                $dateNaissance = $this->lireDate($dateTexte);
                if (! $dateNaissance) {
                    $erreurs[] = "Ligne {$numeroAffiche} ({$nom} {$prenom}) : date de naissance « {$dateTexte} » non reconnue (format attendu jj/mm/aaaa), laissée vide.";
                }
            }

            $sexe = $valeur('sexe');
            if ($sexe) {
                $sexeNorm = $this->normaliser($sexe);
                $sexe = str_starts_with($sexeNorm, 'f') ? 'F' : (str_starts_with($sexeNorm, 'm') ? 'M' : $sexe);
            }

            $matricule = $valeur('matricule');
            if ($matricule && Eleve::where('matricule', $matricule)->exists()) {
                $erreurs[] = "Ligne {$numeroAffiche} ({$nom} {$prenom}) : le matricule {$matricule} existe déjà, un nouveau matricule est attribué.";
                $matricule = null;
            }

            try {
                $eleve = Eleve::create([
                'classe_id' => $classe->id,
                'nom' => $nom,
                'prenom' => $prenom,
                'date_naissance' => $dateNaissance,
                'lieu_naissance' => $valeur('lieu_naissance'),
                'sexe' => $sexe,
                'quartier' => $valeur('quartier'),
                'matricule' => $matricule,
                'tuteur_nom' => $valeur('tuteur_nom'),
                'pere_nom' => $valeur('pere_nom'),
                'pere_telephone' => $valeur('pere_telephone'),
                'pere_email' => $valeur('pere_email'),
                'mere_nom' => $valeur('mere_nom'),
                'mere_telephone' => $valeur('mere_telephone'),
                'tuteur_telephone' => $valeur('tuteur_telephone'),
                ]);
            } catch (\Throwable $e) {
                // Une ligne invalide n'arrête pas tout l'import
                $erreurs[] = "Ligne {$numeroAffiche} ({$nom} {$prenom}) : enregistrement impossible, ligne ignorée.";
                report($e);
                continue;
            }
            if (! $eleve->matricule) {
                $eleve->attribuerMatricule();
            }

            $crees++;
        }

        @unlink($chemin);

        return view('import.eleves-resultat', [
            'crees' => $crees,
            'doublons' => $doublons,
            'erreurs' => $erreurs,
            'total' => count($donnees),
        ]);
    }

    // Date de naissance écrite à la française (jj/mm/aaaa), ou date Excel (nombre de jours)
    private function lireDate(string $texte): ?string
    {
        $texte = trim($texte);
        if (is_numeric($texte) && (float) $texte > 1000) {
            try {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $texte)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'Y-m-d', 'd/m/y'] as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $texte);
            if ($date && $date->format($format) === $texte) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
