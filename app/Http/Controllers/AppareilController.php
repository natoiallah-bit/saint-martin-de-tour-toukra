<?php

namespace App\Http\Controllers;

use App\Models\Appareil;
use Illuminate\Support\Facades\DB;

// Téléphones et ordinateurs connectés au mode hors ligne de l'application
class AppareilController extends Controller
{
    public function index()
    {
        $appareils = Appareil::with('user')->orderByDesc('derniere_synchro')->get();
        $envois = DB::table('envois_appareils')->selectRaw('appareil_id, COUNT(*) as total')
            ->groupBy('appareil_id')->pluck('total', 'appareil_id');

        return view('appareils.index', compact('appareils', 'envois'));
    }

    // Téléphone perdu, ou personne partie : l'appareil devra se reconnecter avec un mot de passe
    public function deconnecter(Appareil $appareil)
    {
        $appareil->update(['jeton_hash' => null]);

        return back()->with('success', "L'appareil « {$appareil->nom} » est déconnecté.");
    }
}
