@extends('layouts.app')
@section('title', 'Ajouter un membre du personnel')

@section('content')
    <h1>Ajouter un membre du personnel</h1>

    <form action="{{ route('personnels.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Matricule <span style="font-weight:normal; color:#5a5a5a;">(généré automatiquement)</span></label>
                <input type="text" value="{{ $matriculePropose }}" disabled style="background:#f1ece0; color:#5a5a5a;">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Type</label>
                <select name="type" required>
                    <option value="enseignant">Enseignant</option>
                    <option value="administratif">Administratif</option>
                    <option value="securite">Sécurité</option>
                    <option value="entretien">Entretien</option>
                    <option value="autre">Autre</option>
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom</label>
                <input type="text" name="nom" value="{{ old('nom') }}" required>
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Prénom</label>
                <input type="text" name="prenom" value="{{ old('prenom') }}" required>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Fonction</label>
                <input type="text" name="fonction" placeholder="Ex : Professeur de Mathématiques" value="{{ old('fonction') }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;"></div>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:0 20px;">
            <div style="flex:1 1 30%; min-width:200px;">
                <label>Statut</label>
                <select name="statut">
                    <option value="">—</option>
                    @foreach(\App\Models\Personnel::STATUTS as $code => $libelle)
                        <option value="{{ $code }}" @selected(old('statut', null) === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1 1 30%; min-width:200px;">
                <label>Date d'embauche</label>
                <input type="date" name="date_embauche" value="{{ old('date_embauche', null) }}">
            </div>
            <div style="flex:1 1 30%; min-width:200px;">
                <label>Cycle d'affectation</label>
                <select name="cycle_id">
                    <option value="">Commun (toute l'école)</option>
                    @foreach(\App\Models\Cycle::orderBy('nom')->get() as $c)
                        <option value="{{ $c->id }}" @selected((int) old('cycle_id', null) === $c->id)>{{ $c->nom }}</option>
                    @endforeach
                </select>
                <div style="font-size:11px; color:#5a5a5a;">Sa paie est gérée par le comptable de ce cycle ; « Commun » : par la Direction.</div>
            </div>
        </div>

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="paiement_horaire" id="paiement-horaire" value="1" style="width:auto;" {{ old('paiement_horaire') ? 'checked' : '' }}>
            Payé(e) à l'heure (ex : enseignants vacataires du collège)
        </label>

        <div style="display:flex; flex-wrap:wrap; gap:0 24px; margin-top:12px;">
            <div id="bloc-salaire-fixe" style="flex:1 1 45%; min-width:280px;">
                <label>Salaire de base (FCFA)</label>
                <input type="number" name="salaire_base" min="0" step="500" value="{{ old('salaire_base') }}">
            </div>
            <div id="bloc-taux-horaire" style="flex:1 1 45%; min-width:280px; display:none;">
                <label>Taux horaire (FCFA / heure)</label>
                <input type="number" name="taux_horaire" min="0" step="50" value="{{ old('taux_horaire') }}">
                <p style="font-size:11.5px; color:#5a5a5a; margin-top:4px;">
                    Le salaire de ce mois sera calculé automatiquement (nombre d'heures × taux horaire) lors de la génération du bulletin de paie.
                </p>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="{{ old('telephone') }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email') }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Photo <span style="font-weight:normal; color:#5a5a5a;">(pour le badge)</span></label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png">
            </div>
        </div>

        <label style="display:flex; align-items:center; gap:8px; margin-top:16px;">
            <input type="checkbox" name="actif" value="1" checked style="width:auto;"> Personnel actif
        </label>

        @if ($errors->any())
            <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-top:16px;">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('personnels.index') }}">Annuler</a>
        </div>
    </form>

    <script>
        function majAffichagePaiement() {
            const horaire = document.getElementById('paiement-horaire').checked;
            document.getElementById('bloc-salaire-fixe').style.display = horaire ? 'none' : 'block';
            document.getElementById('bloc-taux-horaire').style.display = horaire ? 'block' : 'none';
        }
        document.getElementById('paiement-horaire').addEventListener('change', majAffichagePaiement);
        majAffichagePaiement();
    </script>
@endsection
