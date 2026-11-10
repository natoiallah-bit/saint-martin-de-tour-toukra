<?php

namespace App\Http\Controllers;

use App\Support\Sauvegarde;

class SauvegardeController extends Controller
{
    public function index()
    {
        return view('sauvegarde.index');
    }

    public function telecharger()
    {
        set_time_limit(600);

        try {
            $zip = Sauvegarde::zip();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('erreur', 'Sauvegarde impossible : ' . $e->getMessage());
        }

        $nom = Sauvegarde::nomDossier() . '_' . now()->format('Y-m-d_H\hi') . '.zip';

        return response()->download($zip, $nom)->deleteFileAfterSend(true);
    }
}
