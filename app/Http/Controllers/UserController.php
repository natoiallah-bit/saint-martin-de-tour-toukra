<?php

namespace App\Http\Controllers;

use App\Helpers\Droits;
use App\Models\Cycle;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Personnel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // Au moins 8 caractères, avec des lettres et des chiffres
    protected function reglesMotDePasse(): Password
    {
        return Password::min(8)->letters()->numbers();
    }

    protected function rolesValides(): string
    {
        return 'direction,secretariat,comptable,enseignant,surveillant,parent,principal';
    }

    // Règles communes aux rôles supplémentaires (rôles réglables uniquement, sans le rôle principal)
    protected function reglesRolesSupplementaires(): array
    {
        return [
            'roles_supplementaires' => 'nullable|array',
            'roles_supplementaires.*' => ['string', Rule::in(array_keys(Droits::ROLES))],
        ];
    }

    // Rôles supplémentaires retenus : aucun pour la Direction (elle a déjà tout) ni pour un parent
    // Un comptable travaille toujours pour un cycle : sans cycle il verrait tout, y compris le commun de la Direction
    protected function exigerCyclePourComptable(array $data, array $rolesSupplementaires): void
    {
        if (($data['role'] === 'comptable' || in_array('comptable', $rolesSupplementaires, true)) && empty($data['cycle_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cycle_id' => 'Choisissez le cycle du comptable : chaque comptable ne gère que son cycle (le commun est géré par la Direction).',
            ]);
        }
    }

    protected function rolesSupplementairesRetenus(array $data): array
    {
        if (in_array($data['role'], ['direction', 'parent'], true)) {
            return [];
        }

        return array_values(array_unique(array_diff($data['roles_supplementaires'] ?? [], [$data['role']])));
    }

    protected function enregistrerRolesSupplementaires(User $user, array $roles): void
    {
        $user->rolesSupplementaires()->delete();
        foreach ($roles as $role) {
            $user->rolesSupplementaires()->create(['role' => $role]);
        }
    }

    public function index()
    {
        // Les comptes élèves se gèrent dans « Comptes élèves » (identifiant = matricule)
        $users = User::with(['cycle', 'enfants', 'rolesSupplementaires'])->where('role', '!=', 'eleve')->orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $cycles = Cycle::all();
        $eleves = Eleve::orderBy('nom')->orderBy('prenom')->get();
        $enseignants = Enseignant::orderBy('nom')->get();
        $personnels = Personnel::where('type', 'enseignant')->orderBy('nom')->get();
        $rolesReglables = Droits::ROLES;
        $rolesActuels = [];
        return view('users.create', compact('cycles', 'eleves', 'enseignants', 'personnels', 'rolesReglables', 'rolesActuels'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', $this->reglesMotDePasse()],
            'role' => 'required|in:' . $this->rolesValides(),
            'cycle_id' => 'nullable|exists:cycles,id',
            'enseignant_id' => 'nullable|exists:enseignants,id',
            'personnel_id' => 'nullable|exists:personnels,id',
            'enfants' => 'nullable|array',
            'enfants.*' => 'exists:eleves,id',
        ] + $this->reglesRolesSupplementaires());

        $enfants = $data['enfants'] ?? [];
        $rolesSupplementaires = $this->rolesSupplementairesRetenus($data);
        $this->exigerCyclePourComptable($data, $rolesSupplementaires);
        unset($data['enfants'], $data['roles_supplementaires']);

        // Fiches enseignant / personnel : utiles si le compte a le rôle Enseignant (principal ou supplémentaire)
        if ($data['role'] !== 'enseignant' && ! in_array('enseignant', $rolesSupplementaires, true)) {
            $data['enseignant_id'] = null;
            $data['personnel_id'] = null;
        }

        $data['password'] = Hash::make($data['password']);

        $user = User::create($data);
        $this->enregistrerRolesSupplementaires($user, $rolesSupplementaires);

        if ($user->role === 'parent') {
            $user->enfants()->sync($enfants);
        }

        return redirect()->route('users.index')->with('success', 'Compte créé.');
    }

    public function edit(User $user)
    {
        abort_if($user->role === 'eleve', 404, 'Les comptes élèves se gèrent dans « Comptes élèves ».');
        $cycles = Cycle::all();
        $eleves = Eleve::orderBy('nom')->orderBy('prenom')->get();
        $enseignants = Enseignant::orderBy('nom')->get();
        $personnels = Personnel::where('type', 'enseignant')->orderBy('nom')->get();
        $enfantsActuels = $user->enfants()->pluck('eleves.id')->toArray();

        $rolesReglables = Droits::ROLES;
        $rolesActuels = $user->rolesSupplementaires()->pluck('role')->all();

        return view('users.edit', compact('user', 'cycles', 'eleves', 'enseignants', 'personnels', 'enfantsActuels', 'rolesReglables', 'rolesActuels'));
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->role === 'eleve', 404, 'Les comptes élèves se gèrent dans « Comptes élèves ».');
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:' . $this->rolesValides(),
            'cycle_id' => 'nullable|exists:cycles,id',
            'enseignant_id' => 'nullable|exists:enseignants,id',
            'personnel_id' => 'nullable|exists:personnels,id',
            'password' => ['nullable', 'string', $this->reglesMotDePasse()],
            'enfants' => 'nullable|array',
            'enfants.*' => 'exists:eleves,id',
        ] + $this->reglesRolesSupplementaires());

        $enfants = $data['enfants'] ?? [];
        $rolesSupplementaires = $this->rolesSupplementairesRetenus($data);
        $this->exigerCyclePourComptable($data, $rolesSupplementaires);
        unset($data['enfants'], $data['roles_supplementaires']);

        // Fiches enseignant / personnel : utiles si le compte a le rôle Enseignant (principal ou supplémentaire)
        if ($data['role'] !== 'enseignant' && ! in_array('enseignant', $rolesSupplementaires, true)) {
            $data['enseignant_id'] = null;
            $data['personnel_id'] = null;
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $this->enregistrerRolesSupplementaires($user, $rolesSupplementaires);

        if ($user->role === 'parent') {
            $user->enfants()->sync($enfants);
        } else {
            $user->enfants()->sync([]);
        }

        return redirect()->route('users.index')->with('success', 'Compte mis à jour.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['general' => 'Tu ne peux pas supprimer ton propre compte.']);
        }
        $user->delete();
        return redirect()->route('users.index')->with('success', 'Compte supprimé.');
    }
}
