<?php

namespace App\Http\Middleware;

use App\Models\Appareil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Routes /api de l'application : le téléphone s'identifie avec le jeton reçu à la connexion
class AuthentifierAppareil
{
    public function handle(Request $request, Closure $next): Response
    {
        $appareil = Appareil::parJeton($request->bearerToken());

        if (! $appareil || ! $appareil->user) {
            return response()->json(['message' => 'Session expirée : reconnectez-vous.'], 401);
        }

        Auth::setUser($appareil->user);
        $request->setUserResolver(fn () => $appareil->user);
        $request->attributes->set('appareil', $appareil);

        return $next($request);
    }
}
