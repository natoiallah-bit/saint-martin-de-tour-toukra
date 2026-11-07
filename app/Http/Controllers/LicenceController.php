<?php

namespace App\Http\Controllers;

use App\Support\Etablissement;
use App\Support\Licence;
use Illuminate\Http\Request;

class LicenceController extends Controller
{
    public function index()
    {
        return view('licence.index', ['etat' => Licence::etat(), 'config' => Etablissement::config()]);
    }

    public function activer(Request $request)
    {
        $donnees = $request->validate(['cle' => 'required|string|max:2000']);

        try {
            $licence = Licence::lire($donnees['cle']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cle' => $e->getMessage()])->withInput();
        }

        Etablissement::config()->forceFill(['licence_cle' => preg_replace('/\s+/', '', $donnees['cle'])])->save();

        return redirect()->route('licence.index')
            ->with('success', "Licence enregistrée : valable jusqu'au " . $licence['fin']->format('d/m/Y') . '.');
    }
}
