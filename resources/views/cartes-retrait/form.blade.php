@extends('layouts.app')
@section('title', $carte->exists ? 'Modifier la carte de retrait' : 'Nouvelle carte de retrait')

@section('content')
    @php
        $liens = \App\Models\CarteRetrait::LIENS;
        $coches = collect(old('eleves', $carte->exists ? $carte->eleves->pluck('id')->all() : []))->map(fn ($id) => (int) $id)->all();
    @endphp

    <h1>{{ $carte->exists ? 'Modifier la carte ' . $carte->numero : 'Nouvelle carte de retrait' }}</h1>

    @if($errors->any())
        <div style="background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px;">
            @foreach($errors->all() as $e) <div>{{ $e }}</div> @endforeach
        </div>
    @endif

    <form action="{{ $carte->exists ? route('cartes-retrait.update', $carte) : route('cartes-retrait.store') }}" method="POST">
        @csrf
        @if($carte->exists) @method('PUT') @endif

        <h3 style="margin-top:10px;">1. Enfants en charge</h3>
        <p style="color:#666; font-size:13px;">Cochez les enfants de la famille : leur nombre figure sur la carte. Les informations des parents sont reprises de la fiche du premier enfant coché (modifiables ci-dessous).</p>
        <input type="text" id="filtre-eleves" placeholder="Filtrer par nom ou classe…" style="max-width:320px;">
        <div id="liste-eleves" style="max-height:280px; overflow:auto; border:1px solid #ddd; border-radius:6px; padding:8px 12px; margin-top:8px;">
            @forelse($eleves as $eleve)
                <label class="ligne-eleve" style="display:flex; align-items:center; gap:8px; font-weight:normal; margin:4px 0;">
                    <input type="checkbox" name="eleves[]" value="{{ $eleve->id }}" style="width:auto;" @checked(in_array($eleve->id, $coches, true))
                           data-famille="{{ json_encode($eleve->only(['pere_nom', 'pere_telephone', 'mere_nom', 'mere_telephone', 'quartier'])) }}">
                    {{ $eleve->nom }} {{ $eleve->prenom }} <small style="color:#777;">— {{ $eleve->classe->nom ?? 'sans classe' }}</small>
                </label>
            @empty
                <p>Aucun élève inscrit.</p>
            @endforelse
        </div>
        <p style="margin-top:6px;">Enfants cochés : <b id="nombre-enfants">{{ count($coches) }}</b></p>

        <h3 style="margin-top:22px;">2. Parents</h3>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0 16px;">
            <div>
                <label>Nom du père</label>
                <input type="text" name="pere_nom" value="{{ old('pere_nom', $carte->pere_nom) }}" maxlength="150">
            </div>
            <div>
                <label>Téléphone du père</label>
                <input type="text" name="pere_telephone" value="{{ old('pere_telephone', $carte->pere_telephone) }}" maxlength="30">
            </div>
            <div>
                <label>Nom de la mère</label>
                <input type="text" name="mere_nom" value="{{ old('mere_nom', $carte->mere_nom) }}" maxlength="150">
            </div>
            <div>
                <label>Téléphone de la mère</label>
                <input type="text" name="mere_telephone" value="{{ old('mere_telephone', $carte->mere_telephone) }}" maxlength="30">
            </div>
        </div>
        <label>Quartier</label>
        <input type="text" name="quartier" value="{{ old('quartier', $carte->quartier) }}" maxlength="100">

        <h3 style="margin-top:22px;">3. Personne autorisée à récupérer les enfants</h3>
        <label>Nom et prénom</label>
        <input type="text" name="responsable_nom" value="{{ old('responsable_nom', $carte->responsable_nom) }}" required maxlength="150">

        <label>Lien avec la famille</label>
        <select name="responsable_lien" required>
            @foreach($liens as $lien)
                <option value="{{ $lien }}" @selected(old('responsable_lien', $carte->responsable_lien) === $lien)>{{ $lien }}</option>
            @endforeach
        </select>

        <label>Image sur la carte</label>
        <div style="display:flex; gap:20px; margin:6px 0 4px;">
            <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
                <input type="radio" name="responsable_sexe" value="F" style="width:auto;" @checked(old('responsable_sexe', $carte->responsable_sexe) === 'F')>
                <span style="font-size:26px;">👩‍👧‍👦</span> Femme
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-weight:normal;">
                <input type="radio" name="responsable_sexe" value="M" style="width:auto;" @checked(old('responsable_sexe', $carte->responsable_sexe) === 'M')>
                <span style="font-size:26px;">👨‍👧‍👦</span> Homme
            </label>
        </div>

        <label>Téléphone</label>
        <input type="text" name="responsable_telephone" value="{{ old('responsable_telephone', $carte->responsable_telephone) }}" required maxlength="30">

        <label>N° de pièce d'identité (facultatif, non imprimé)</label>
        <input type="text" name="responsable_piece" value="{{ old('responsable_piece', $carte->responsable_piece) }}" maxlength="60">

        <h3 style="margin-top:22px;">4. Deuxième personne autorisée (facultatif)</h3>
        <label>Nom et prénom</label>
        <input type="text" name="second_nom" value="{{ old('second_nom', $carte->second_nom) }}" maxlength="150">

        <label>Lien avec la famille</label>
        <select name="second_lien">
            <option value="">— Choisir —</option>
            @foreach($liens as $lien)
                <option value="{{ $lien }}" @selected(old('second_lien', $carte->second_lien) === $lien)>{{ $lien }}</option>
            @endforeach
        </select>

        <label>Téléphone</label>
        <input type="text" name="second_telephone" value="{{ old('second_telephone', $carte->second_telephone) }}" maxlength="30">

        <label>N° de pièce d'identité (facultatif, non imprimé)</label>
        <input type="text" name="second_piece" value="{{ old('second_piece', $carte->second_piece) }}" maxlength="60">

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('cartes-retrait.index') }}">Annuler</a>
        </div>
    </form>

    <script>
        document.getElementById('filtre-eleves').addEventListener('input', function () {
            const texte = this.value.toLowerCase();
            document.querySelectorAll('#liste-eleves .ligne-eleve').forEach(function (ligne) {
                ligne.style.display = ligne.textContent.toLowerCase().includes(texte) ? 'flex' : 'none';
            });
        });

        // Nombre d'enfants cochés, et reprise des parents depuis la fiche de l'enfant (champs vides seulement)
        document.querySelectorAll('#liste-eleves input[type=checkbox]').forEach(function (caseEleve) {
            caseEleve.addEventListener('change', function () {
                document.getElementById('nombre-enfants').textContent = document.querySelectorAll('#liste-eleves input:checked').length;
                if (! this.checked) return;
                const famille = JSON.parse(this.dataset.famille);
                Object.keys(famille).forEach(function (champ) {
                    const saisie = document.querySelector('[name=' + champ + ']');
                    if (saisie && ! saisie.value && famille[champ]) saisie.value = famille[champ];
                });
            });
        });
    </script>
@endsection
