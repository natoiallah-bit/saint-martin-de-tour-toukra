<?php

namespace App\Http\Controllers;

use App\Helpers\BulletinCalcul;
use App\Models\CarteRetrait;
use App\Models\Eleve;
use App\Models\Paie;
use App\Models\Paiement;
use App\Models\Personnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

class VerificationController extends Controller
{
    public function bulletin(Eleve $eleve, int $trimestre)
    {
        $resultat = BulletinCalcul::calculer($eleve, $trimestre);

        return view('verification.bulletin', [
            'eleve' => $eleve,
            'trimestre' => $trimestre,
            'moyenneGenerale' => $resultat['moyenneGenerale'],
            'rang' => $resultat['rang'],
            'effectifClasse' => $resultat['effectifClasse'],
        ]);
    }

    public function recu(string $numero)
    {
        $paiement = Paiement::with('eleve.classe')->where('numero_recu', $numero)->first();

        return view('verification.recu', [
            'numero' => $numero,
            'paiement' => $paiement,
        ]);
    }

    public function carte(Eleve $eleve)
    {
        return view('verification.carte', [
            'eleve' => $eleve,
        ]);
    }

    public function badge(Personnel $personnel)
    {
        return view('verification.badge', ['personnel' => $personnel]);
    }

    public function certificat(string $numero)
    {
        return view('verification.certificat', [
            'numero' => $numero,
            'certificat' => \App\Models\CertificatScolarite::with('eleve')->where('numero', $numero)->first(),
        ]);
    }

    public function attestation(string $numero)
    {
        return view('verification.attestation', [
            'numero' => $numero,
            'attestation' => \App\Models\AttestationTravail::with('personnel')->where('numero', $numero)->first(),
        ]);
    }

    public function retrait(CarteRetrait $carte)
    {
        return view('verification.retrait', ['carte' => $carte->load('eleves.classe')]);
    }

    public function paie(int $id)
    {
        $paie = Paie::with('personnel')->find($id);

        return view('paies.verification.paie', [
            'paie' => $paie,
        ]);
    }

    // Page "Vérifier un document" (secrétariat, caisse, direction)
    public function formulaire()
    {
        return view('verification.formulaire');
    }

    // Vérifie le contenu d'un QR code scanné ou collé. On ne garde que le chemin
    // signé (/verification/...) : l'adresse du serveur inscrite dans le QR code
    // (localhost, ancienne adresse IP, autre réseau) n'a donc aucune importance.
    public function verifier(Request $request)
    {
        $request->validate(['code' => 'required|string|max:2000']);

        $code = trim($request->input('code'));
        $position = strpos($code, '/verification/');
        $erreur = fn (string $message) => back()->withInput()->withErrors(['code' => $message]);

        if ($position === false) {
            return $erreur("Ce QR code ne vient pas d'un document de l'école.");
        }

        $chemin = substr($code, $position);
        $requete = Request::create($chemin);

        try {
            $route = Route::getRoutes()->match($requete);
        } catch (\Throwable) {
            return $erreur("Ce QR code ne correspond à aucun document de l'école.");
        }

        $documents = ['verification.bulletin', 'verification.recu', 'verification.carte', 'verification.badge', 'verification.paie', 'verification.retrait', 'verification.certificat', 'verification.attestation'];
        if (! in_array($route->getName(), $documents, true) || ! URL::hasValidSignature($requete, false)) {
            return $erreur("Document NON AUTHENTIQUE : la signature du QR code n'est pas valide.");
        }

        return redirect(url($chemin));
    }
}
