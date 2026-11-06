<?php

namespace App\Http\Controllers;

use App\Models\Personnel;
use App\Models\Pointage;
use Illuminate\Http\Request;

class PointageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date = \Illuminate\Support\Carbon::parse($request->get('date', now()->format('Y-m-d')))->format('Y-m-d');
        $personnels = Personnel::where('actif', true)->orderBy('nom')->get();
        $pointagesExistants = Pointage::whereDate('date', $date)->get()->keyBy('personnel_id');

        return view('personnels.pointage', compact('personnels', 'date', 'pointagesExistants'));
    }

    public function enregistrer(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'statuts' => 'array',
            'statuts.*' => 'in:present,absent,retard,conge',
        ]);

        $date = \Illuminate\Support\Carbon::parse($request->date)->format('Y-m-d');
        $existants = Personnel::whereIn('id', array_keys($request->statuts ?? []))->pluck('id')->all();
        foreach ($request->statuts ?? [] as $personnelId => $statut) {
            if (! in_array((int) $personnelId, $existants, true)) {
                continue;
            }
            $pointage = Pointage::where('personnel_id', $personnelId)->whereDate('date', $date)->first()
                ?? new Pointage(['personnel_id' => $personnelId, 'date' => $date]);
            $pointage->fill(['statut' => $statut, 'remarque' => $request->remarques[$personnelId] ?? null])->save();
        }

        return redirect()->route('pointage.index', ['date' => $date])->with('success', 'Pointage enregistré.');
    }
}
