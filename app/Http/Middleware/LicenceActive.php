<?php

namespace App\Http\Middleware;

use App\Support\Licence;
use Closure;
use Illuminate\Http\Request;

// Licence terminée au-delà de la tolérance : l'application reste consultable (GET) mais ne
// permet plus de modifier les données. Connexion, déconnexion et page Licence restent ouvertes.
class LicenceActive
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }
        if ($request->is('login', 'logout', 'deconnexion-urgence', 'licence', 'licence/*', 'api/connexion', 'api/deconnexion')) {
            return $next($request);
        }

        $etat = Licence::etat();
        if ($etat['statut'] !== 'bloquee') {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => $etat['message'], 'licence' => 'lecture_seule'], 402);
        }

        return back()->with('erreur', $etat['message']);
    }
}
