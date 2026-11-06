<?php

namespace App\Http\Controllers;

use App\Helpers\Fichier;

class FichierController extends Controller
{
    // Module(s) dont il faut au moins le droit "Lecture" pour ouvrir les fichiers de chaque dossier
    protected array $modulesParDossier = [
        // Photos : listes, cartes scolaires et bulletins (les parents ne voient que celles de leurs enfants)
        'photos_eleves' => ['eleves', 'bulletins'],
        'documents_eleves' => ['documents_eleves'],
        'documents_personnel' => ['personnel'],
        'photos_personnel' => ['personnel'],
        'pieces_justificatives' => ['scolarite', 'comptabilite'],
    ];

    public function afficher(string $chemin)
    {
        $dossier = explode('/', $chemin)[1] ?? '';
        abort_unless(array_key_exists($dossier, $this->modulesParDossier), 404);

        $user = auth()->user();

        if ($user->role === 'parent') {
            abort_unless($dossier === 'photos_eleves' && $user->enfants()->where('photo', $chemin)->exists(), 403);
        } else {
            $autorise = collect($this->modulesParDossier[$dossier])->contains(fn ($module) => $user->peut($module));
            abort_unless($autorise, 403);
        }

        $complet = Fichier::chemin($chemin);
        abort_unless($complet, 404);

        return response()->file($complet);
    }
}
