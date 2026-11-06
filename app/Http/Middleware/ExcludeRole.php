<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ExcludeRole
{
    /**
     * Bloque l'accès à la route pour les rôles listés en paramètre,
     * sans toucher au comportement des autres rôles.
     *
     * Contrairement au middleware "role" (liste d'autorisation, qui
     * n'accepte QUE les rôles listés), celui-ci est une liste
     * d'EXCLUSION : tout le monde passe, sauf les rôles indiqués.
     * Utile pour fermer une route à un rôle précis (ex : principal)
     * sans devoir deviner/énumérer tous les autres rôles qui y ont
     * déjà accès aujourd'hui.
     *
     * Exemple d'utilisation dans routes/web.php :
     *   Route::get('/paiements', [...])->middleware('excluderole:principal');
     */
    public function handle(Request $request, Closure $next, ...$rolesInterdits)
    {
        $role = auth()->user()?->role;

        if ($role !== null && in_array($role, $rolesInterdits, true)) {
            abort(403, "Cette section n'est pas accessible à votre rôle.");
        }

        return $next($request);
    }
}
