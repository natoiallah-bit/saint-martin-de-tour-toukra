<?php

namespace App\Http\Controllers;

use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use Illuminate\Http\Request;

class ParametresController extends Controller
{
    public function index()
    {
        $config = ConfigurationEcole::actuelle();
        $cycles = Cycle::orderBy('nom')->get();

        return view('parametres.index', compact('config', 'cycles'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'nom_ecole' => 'required|string|max:200',
            'nom_court' => 'nullable|string|max:60',
            'couleur_principale' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'couleur_secondaire' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sigle_ecole' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'quartier' => 'nullable|string|max:150',
            'ville' => 'required|string|max:100',
            'inspection' => 'nullable|string|max:150',
            'telephone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'nom_raf' => 'nullable|string|max:150',
            'nom_directeur' => 'nullable|string|max:150',
            'annee_scolaire_active' => 'required|string|max:20',
            'coefficient_conduite' => 'required|numeric|min:0|max:20',
            'trimestre1_debut' => 'nullable|date',
            'trimestre1_fin' => 'nullable|date|after_or_equal:trimestre1_debut',
            'trimestre2_debut' => 'nullable|date',
            'trimestre2_fin' => 'nullable|date|after_or_equal:trimestre2_debut',
            'trimestre3_debut' => 'nullable|date',
            'trimestre3_fin' => 'nullable|date|after_or_equal:trimestre3_debut',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048', // 2 Mo max
            'embleme' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg|max:4096',
            'couleurs' => 'nullable|array',
            'couleurs.*' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        // Couleur des cartes de chaque cycle
        foreach ($request->input('couleurs', []) as $cycleId => $couleur) {
            Cycle::whereKey($cycleId)->update(['couleur' => $couleur ? strtolower($couleur) : null]);
        }
        unset($data['couleurs']);

        $config = ConfigurationEcole::actuelle();

        // Images de l'école dans public/images/ecole (hors du code livré, donc gardées
        // aux mises à jour), toujours en PNG ou JPG
        foreach (['logo' => 'banniere.png', 'embleme' => 'embleme.png', 'photo' => 'photo.jpg'] as $champ => $fichier) {
            if ($request->hasFile($champ)) {
                $request->file($champ)->move(public_path('images/ecole'), $fichier);
                if ($champ === 'logo') {
                    $data['logo_path'] = 'images/ecole/' . $fichier;
                }
            }
            unset($data[$champ]);
        }

        if (empty($data['sigle_ecole'])) {
            unset($data['sigle_ecole']);
        } else {
            $data['sigle_ecole'] = strtoupper($data['sigle_ecole']);
        }
        $config->update($data);

        return redirect()->route('parametres.index')->with('success', 'Paramètres mis à jour.');
    }
}
