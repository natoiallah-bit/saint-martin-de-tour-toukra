@extends('layouts.app')
@section('title', 'Créer un compte')

@section('content')
    <h1>Créer un compte utilisateur</h1>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        <label>Nom complet</label>
        <input type="text" name="name" value="{{ old('name') }}" required>

        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required>

        <label>Mot de passe</label>
        <input type="password" name="password" required>
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Au moins 8 caractères, avec des lettres et des chiffres.</div>

        <label>Rôle</label>
        <select name="role" id="role-select" required>
            <option value="direction">Direction (voit tous les cycles)</option>
            <option value="secretariat">Secrétariat</option>
            <option value="comptable">Comptable</option>
            <option value="enseignant">Enseignant</option>
            <option value="surveillant">Surveillant</option>
            <option value="parent">Parent</option>
            <option value="principal">Principal (pédagogie)</option>
        </select>

        <div id="bloc-roles-supplementaires">
            <label>Rôles supplémentaires <span style="font-weight:normal; color:#5a5a5a;">(facultatif — la personne cumule les droits de tous ses rôles)</span></label>
            <div style="display:flex; flex-wrap:wrap; gap:4px 18px; border:1px solid #e3ddcf; border-radius:6px; padding:10px; background:#fff;">
                @foreach($rolesReglables as $valeur => $libelle)
                    <label class="role-supplementaire" data-role="{{ $valeur }}" style="display:flex; align-items:center; gap:6px; font-weight:normal; margin-top:0;">
                        <input type="checkbox" name="roles_supplementaires[]" value="{{ $valeur }}" style="width:auto;"
                               @checked(in_array($valeur, old('roles_supplementaires', $rolesActuels)))>
                        {{ $libelle }}
                    </label>
                @endforeach
            </div>
            <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">
                Les droits de chaque rôle se règlent dans <a href="{{ route('droits.index') }}">Droits des rôles</a>.
            </div>
        </div>


        <div id="bloc-cycle">
            <label>Cycle assigné <span style="font-weight:normal; color:#5a5a5a;">(obligatoire sauf pour la direction)</span></label>
            <select name="cycle_id">
                <option value="">— Choisir —</option>
                @foreach($cycles as $cycle)
                    <option value="{{ $cycle->id }}">{{ $cycle->nom }}</option>
                @endforeach
            </select>
        </div>

        <div id="bloc-enseignant" style="display:none;">
            <label>Fiche enseignant (matières) <span style="font-weight:normal; color:#5a5a5a;">— pour que ce compte ne voie que ses matières</span></label>
            <select name="enseignant_id">
                <option value="">— Choisir —</option>
                @foreach($enseignants as $enseignant)
                    <option value="{{ $enseignant->id }}">{{ $enseignant->nom }} {{ $enseignant->prenom }}</option>
                @endforeach
            </select>

            <label>Fiche personnel (emploi du temps / paie)</label>
            <select name="personnel_id">
                <option value="">— Choisir —</option>
                @foreach($personnels as $personnel)
                    <option value="{{ $personnel->id }}">{{ $personnel->nom }} {{ $personnel->prenom }}</option>
                @endforeach
            </select>
        </div>

        <div id="bloc-enfants" style="display:none;">
            <label>Enfant(s) rattaché(s) à ce compte parent</label>
            <div style="max-height:220px; overflow-y:auto; border:1px solid #e3ddcf; border-radius:6px; padding:10px; background:#fff;">
                @forelse ($eleves as $eleve)
                    <label style="display:flex; align-items:center; gap:8px; font-weight:normal; margin-top:0; padding:4px 0;">
                        <input type="checkbox" name="enfants[]" value="{{ $eleve->id }}" style="width:auto;">
                        {{ $eleve->nom }} {{ $eleve->prenom }} — {{ $eleve->classe->nom ?? 'Sans classe' }}
                    </label>
                @empty
                    <span style="color:#999;">Aucun élève enregistré pour le moment.</span>
                @endforelse
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Créer le compte</button>
            <a href="{{ route('users.index') }}">Annuler</a>
        </div>
    </form>

    <script>
        function majAffichageRole() {
            const role = document.getElementById('role-select').value;
            const enseignantSupplementaire = document.querySelector('input[name="roles_supplementaires[]"][value="enseignant"]:checked') !== null;
            document.getElementById('bloc-cycle').style.display = (role === 'direction' || role === 'parent') ? 'none' : 'block';
            document.getElementById('bloc-enseignant').style.display = (role === 'enseignant' || (enseignantSupplementaire && role !== 'direction' && role !== 'parent')) ? 'block' : 'none';
            // Pas de rôle supplémentaire pour la Direction (elle a déjà tout) ni pour un parent
            document.getElementById('bloc-roles-supplementaires').style.display = (role === 'direction' || role === 'parent') ? 'none' : 'block';
            document.querySelectorAll('.role-supplementaire').forEach(function (el) {
                el.style.display = (el.dataset.role === role) ? 'none' : 'flex';
            });
            document.getElementById('bloc-enfants').style.display = (role === 'parent') ? 'block' : 'none';
        }
        document.getElementById('role-select').addEventListener('change', majAffichageRole);
        document.querySelectorAll('input[name="roles_supplementaires[]"]').forEach(el => el.addEventListener('change', majAffichageRole));
        majAffichageRole();
    </script>
@endsection
