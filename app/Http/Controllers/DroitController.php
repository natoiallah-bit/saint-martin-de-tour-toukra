<?php

namespace App\Http\Controllers;

use App\Helpers\Droits;
use App\Models\DroitRole;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Page "Droits des rôles" : la Direction règle, pour chaque rôle et chaque module,
// le niveau de droit (Aucun, Lecture, Modifier, Supprimer).
class DroitController extends Controller
{
    public function index()
    {
        $droits = DroitRole::all()
            ->groupBy('role')
            ->map(fn ($lignes) => $lignes->pluck('niveau', 'module'));

        return view('droits.index', [
            'droits' => $droits,
            'roles' => Droits::ROLES,
            'modules' => Droits::MODULES,
            'niveaux' => Droits::NIVEAUX,
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'droits' => 'required|array',
            'droits.*' => 'array',
            'droits.*.*' => ['required', 'integer', Rule::in(array_keys(Droits::NIVEAUX))],
        ]);

        foreach (Droits::ROLES as $role => $libelle) {
            foreach (Droits::MODULES as $module => $nomModule) {
                $niveau = $request->input("droits.$role.$module");
                if ($niveau === null) {
                    continue;
                }
                DroitRole::updateOrCreate(['role' => $role, 'module' => $module], ['niveau' => (int) $niveau]);
            }
        }

        return redirect()->route('droits.index')->with('success', 'Droits des rôles enregistrés.');
    }

    // Remet tous les droits comme à l'installation
    public function retablir()
    {
        foreach (Droits::ROLES as $role => $libelle) {
            foreach (Droits::MODULES as $module => $nomModule) {
                DroitRole::updateOrCreate(
                    ['role' => $role, 'module' => $module],
                    ['niveau' => Droits::DEFAUTS[$role][$module] ?? Droits::AUCUN]
                );
            }
        }

        return redirect()->route('droits.index')->with('success', 'Droits par défaut rétablis.');
    }
}
