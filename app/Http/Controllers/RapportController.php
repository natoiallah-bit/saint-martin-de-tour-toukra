<?php

namespace App\Http\Controllers;

use App\Helpers\PorteeCycle;
use App\Helpers\Rapport;
use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use App\Models\Personnel;
use Illuminate\Http\Request;

// Rapport trimestriel ou annuel.
// - Direction : rapport général (pédagogie + finances), toute l'école ou un cycle.
// - Volet pédagogique (Principal, directeur du primaire…) et volet financier (Comptable) :
//   selon les droits, limités au cycle du compte s'il en a un.
class RapportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $voirPedagogie = $user->peut('rapport_pedagogique');
        $voirFinances = $user->peut('rapport_financier');
        abort_unless($voirPedagogie || $voirFinances, 403, "Vous n'avez accès à aucun volet des rapports.");

        $request->validate([
            'periode' => 'nullable|in:' . implode(',', array_keys(Rapport::PERIODES)),
            'cycle_id' => 'nullable|integer|exists:cycles,id',
        ]);

        // Un compte rattaché à un cycle ne voit que ce cycle ; les autres peuvent choisir
        $cycleImpose = PorteeCycle::pourUtilisateurConnecte();
        $cycleId = $cycleImpose ?: ($request->integer('cycle_id') ?: null);

        $periode = Rapport::periode($request->input('periode', Rapport::periodeEnCours()));
        $classes = Rapport::classes($cycleId);

        return view('rapports.index', [
            'config' => ConfigurationEcole::actuelle(),
            'periode' => $periode,
            'cycles' => Cycle::orderBy('nom')->get(),
            'cycle' => $cycleId ? Cycle::find($cycleId) : null,
            'cycleImpose' => (bool) $cycleImpose,
            'general' => $voirPedagogie && $voirFinances,
            'pedagogie' => $voirPedagogie ? Rapport::pedagogique($classes, $periode) : null,
            'finances' => $voirFinances ? Rapport::financier($classes, $periode, $cycleId === null, $cycleId) : null,
            'personnel' => [
                'enseignants' => Personnel::where('type', 'enseignant')->where('actif', true)->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->count(),
                'total' => Personnel::where('actif', true)->when($cycleId, fn ($q) => $q->where('cycle_id', $cycleId))->count(),
            ],
        ]);
    }
}
