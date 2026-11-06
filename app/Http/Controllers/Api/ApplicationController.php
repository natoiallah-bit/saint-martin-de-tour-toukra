<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\PresenceController;
use App\Models\Appareil;
use App\Models\Paiement;
use App\Models\User;
use App\Support\DonneesHorsLigne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Mode hors ligne de l'application : l'appareil télécharge les classes et les élèves,
 * fait l'appel, saisit des notes ou encaisse sans réseau, puis envoie ses saisies.
 *
 * Chaque saisie envoyée passe par le même contrôleur que la page web (mêmes droits,
 * mêmes vérifications). Elle porte un identifiant (uuid) créé sur l'appareil : si la
 * connexion coupe pendant l'envoi et que l'appareil renvoie, rien n'est enregistré deux fois.
 */
class ApplicationController extends Controller
{
    // Saisies possibles hors ligne : contrôleur et méthode de la page web, droit nécessaire
    private const TYPES = [
        'appel' => [PresenceController::class, 'enregistrer', 'presences'],
        'notes' => [NoteController::class, 'enregistrer', 'notes'],
        'paiement' => [PaiementController::class, 'store', 'scolarite'],
    ];

    public function connexion(Request $request)
    {
        $donnees = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'appareil_uuid' => 'required|uuid',
            'appareil_nom' => 'required|string|max:100',
        ]);

        $user = User::where('email', $donnees['email'])->first();
        if (! $user || ! Hash::check($donnees['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou mot de passe incorrect.']);
        }
        if (! collect(self::TYPES)->contains(fn ($t) => $this->peutSaisir($user, $t[2]))) {
            throw ValidationException::withMessages(['email' => "Ce compte n'a pas accès à l'appel, aux notes ni aux encaissements."]);
        }

        $jeton = Appareil::connecter($donnees['appareil_uuid'], $donnees['appareil_nom'], $user);

        return response()->json([
            'jeton' => $jeton,
            'utilisateur' => ['nom' => $user->name, 'email' => $user->email, 'role' => $user->role],
        ]);
    }

    public function deconnexion(Request $request)
    {
        $request->attributes->get('appareil')->update(['jeton_hash' => null]);

        return response()->json(['ok' => true]);
    }

    // Tout ce qu'il faut pour travailler sans réseau, limité à ce que le compte a le droit de voir
    public function donnees(Request $request)
    {
        $user = $request->user();
        $request->attributes->get('appareil')->update(['derniere_synchro' => now()]);

        return response()->json((new DonneesHorsLigne($user))->tout() + [
            'utilisateur' => ['nom' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'droits' => collect(self::TYPES)->map(fn ($t) => $this->peutSaisir($user, $t[2])),
            'heure_serveur' => now()->toIso8601String(),
        ]);
    }

    public function envoyer(Request $request)
    {
        $request->validate([
            'elements' => 'required|array|max:200',
            'elements.*.uuid' => 'required|uuid',
            'elements.*.type' => 'required|in:' . implode(',', array_keys(self::TYPES)),
            'elements.*.donnees' => 'required|array',
        ]);

        $user = $request->user();
        $appareil = $request->attributes->get('appareil');
        $resultats = [];

        foreach ($request->input('elements') as $element) {
            $resultats[] = ['uuid' => $element['uuid']] + $this->traiter($element, $user, $appareil);
        }
        $appareil->update(['derniere_synchro' => now()]);

        return response()->json(['resultats' => $resultats]);
    }

    private function traiter(array $element, User $user, Appareil $appareil): array
    {
        $deja = DB::table('envois_appareils')->where('uuid', $element['uuid'])->first();
        if ($deja) {
            return ['statut' => 'accepte', 'reference' => $deja->reference];
        }

        [$controleur, $methode, $module] = self::TYPES[$element['type']];
        if (! $this->peutSaisir($user, $module)) {
            return ['statut' => 'refuse', 'raison' => "Votre compte n'a plus le droit de faire cette saisie."];
        }

        try {
            $reference = DB::transaction(function () use ($element, $controleur, $methode, $user, $appareil) {
                $dernierPaiement = (int) Paiement::max('id');
                $this->appelerPage($controleur, $methode, $this->donneesPage($element), $user);

                // Pour un encaissement : le vrai numéro de reçu remplace le numéro provisoire
                $reference = $element['type'] === 'paiement'
                    ? Paiement::where('id', '>', $dernierPaiement)->where('saisi_par', $user->id)->value('numero_recu')
                    : null;

                DB::table('envois_appareils')->insert([
                    'uuid' => $element['uuid'], 'appareil_id' => $appareil->id, 'user_id' => $user->id,
                    'type' => $element['type'], 'reference' => $reference,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

                return $reference;
            });

            return ['statut' => 'accepte', 'reference' => $reference];
        } catch (ValidationException $e) {
            return ['statut' => 'refuse', 'raison' => collect($e->errors())->flatten()->implode(' ')];
        } catch (HttpExceptionInterface $e) {
            return ['statut' => 'refuse', 'raison' => $e->getMessage() ?: 'Saisie refusée par le serveur.'];
        } catch (Throwable $e) {
            report($e);

            return ['statut' => 'refuse', 'raison' => 'Erreur du serveur : réessayez plus tard ou faites la saisie sur le PC.'];
        }
    }

    // Seuls les champs de la page web sont transmis
    private function donneesPage(array $element): array
    {
        $champs = [
            'appel' => ['classe_id', 'date', 'cours', 'statuts', 'remarques'],
            'notes' => ['classe_id', 'matiere_id', 'trimestre', 'notes'],
            'paiement' => ['eleve_id', 'types', 'montant', 'mode_paiement', 'date_paiement', 'description'],
        ][$element['type']];

        $donnees = array_intersect_key($element['donnees'], array_flip($champs));
        if ($element['type'] === 'paiement') {
            $donnees['description'] = trim(mb_substr((string) ($donnees['description'] ?? ''), 0, 230) . ' (saisi hors ligne)');
        }

        return $donnees;
    }

    // Même traitement que le formulaire de la page web, avec le compte de l'appareil
    private function appelerPage(string $controleur, string $methode, array $donnees, User $user): void
    {
        $sousRequete = Request::create('/api/envoyer', 'POST', $donnees);
        $sousRequete->setUserResolver(fn () => $user);
        $sousRequete->setLaravelSession(app('session.store'));

        app($controleur)->{$methode}($sousRequete);
    }

    // Même règle que le middleware droit: pour un enregistrement (niveau Modifier)
    private function peutSaisir(User $user, string $module): bool
    {
        return ! in_array($user->role, ['parent', 'eleve'], true) && $user->peut($module, 'modifier');
    }
}
