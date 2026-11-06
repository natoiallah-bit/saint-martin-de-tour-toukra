<?php

namespace App\Http\Middleware;

use App\Helpers\Droits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierDroit
{
    /**
     * Utilisation dans les routes : ->middleware('droit:scolarite')
     *
     * Le niveau demandé dépend de l'action : lire une page (Lecture), enregistrer
     * ou ouvrir un formulaire (Modifier), supprimer (Supprimer). On peut l'imposer :
     * 'droit:eleves,lecture'. Ajouter ',parent' autorise aussi les parents (le
     * contrôleur vérifie alors qu'il s'agit bien de leur enfant).
     */
    public function handle(Request $request, Closure $next, string $module, string ...$options): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'parent' && in_array('parent', $options, true)) {
            return $next($request);
        }

        $niveauImpose = collect($options)->first(fn ($o) => $o !== 'parent');
        $niveauRequis = $niveauImpose
            ? Droits::niveau($niveauImpose)
            : Droits::niveauRequis($request->method(), $request->route()?->getName());

        if (! $user || $user->niveau($module) < $niveauRequis) {
            abort(403, 'Vous n\'avez pas le droit « ' . Droits::NIVEAUX[$niveauRequis] . ' » sur : ' . Droits::MODULES[$module] . '.');
        }

        return $next($request);
    }
}
