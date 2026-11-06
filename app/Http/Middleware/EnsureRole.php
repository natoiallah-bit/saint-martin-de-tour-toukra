<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    // Utilisation dans les routes : ->middleware('role:direction,secretariat')
    public function handle(Request $request, Closure $next, ...$rolesAutorises): Response
    {
        $user = $request->user();

        // Un compte peut avoir plusieurs rôles : il suffit que l'un d'eux soit autorisé
        if (!$user || !$user->aRole(...$rolesAutorises)) {
            abort(403, 'Accès réservé à certains profils (' . implode(', ', $rolesAutorises) . ').');
        }

        return $next($request);
    }
}
