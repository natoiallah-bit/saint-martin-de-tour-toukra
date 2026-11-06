@extends('layouts.app')
@section('title', 'Modifier un élève')

@section('content')
    <h1>Modifier {{ $eleve->prenom }} {{ $eleve->nom }}</h1>

    <form action="{{ route('eleves.update', $eleve) }}" method="POST" enctype="multipart/form-data">
        @csrf @method('PUT')

        <h3 style="margin-top:24px; color:#c9973b;">Identité</h3>
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Matricule <span style="font-weight:normal; color:#5a5a5a;">(non modifiable)</span></label>
                <input type="text" name="matricule" value="{{ $eleve->matricule }}" readonly style="background:#f1ece0; color:#5a5a5a;">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Classe</label>
                <select name="classe_id" required>
                    @foreach($classes as $classe)
                        <option value="{{ $classe->id }}" @selected(old('classe_id', $eleve->classe_id) == $classe->id)>
                            {{ $classe->cycle->nom }} — {{ $classe->nom }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom</label>
                <input type="text" name="nom" value="{{ old('nom', $eleve->nom) }}" required>
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Prénom(s)</label>
                <input type="text" name="prenom" value="{{ old('prenom', $eleve->prenom) }}" required>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Date de naissance</label>
                <input type="date" name="date_naissance" value="{{ old('date_naissance', $eleve->date_naissance?->format('Y-m-d')) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Lieu de naissance</label>
                <input type="text" name="lieu_naissance" value="{{ old('lieu_naissance', $eleve->lieu_naissance) }}">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Sexe</label>
                <select name="sexe">
                    <option value="">—</option>
                    <option value="M" @selected(old('sexe', $eleve->sexe) === 'M')>Masculin</option>
                    <option value="F" @selected(old('sexe', $eleve->sexe) === 'F')>Féminin</option>
                </select>
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Quartier</label>
                <input type="text" name="quartier" value="{{ old('quartier', $eleve->quartier) }}">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Photo de l'élève <span style="font-weight:normal; color:#5a5a5a;">(laisser vide pour garder l'actuelle)</span></label>
                <input type="file" name="photo" accept=".jpg,.jpeg,.png">
                @if($eleve->photo)
                    <div style="margin-top:6px; font-size:12px; color:#4c7a63;">Photo actuelle enregistrée ✓</div>
                @endif
            </div>
        </div>

        <h3 style="margin-top:24px; color:#c9973b;">Scolarité et santé</h3>
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 30%; min-width:220px;">
                <label>Date d'entrée dans l'établissement</label>
                <input type="date" name="date_entree" value="{{ old('date_entree', $eleve->date_entree?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}">
            </div>
            <div style="flex:1 1 60%; min-width:280px;">
                <label>Ancienne école</label>
                <input type="text" name="ancienne_ecole" value="{{ old('ancienne_ecole', $eleve->ancienne_ecole) }}" placeholder="Ex : École primaire de Chagoua">
            </div>
            <div style="flex:1 1 30%; min-width:220px;">
                <label>Aptitude physique (EPS)</label>
                <select name="aptitude_physique">
                    @foreach(\App\Models\Eleve::APTITUDES as $code => $libelle)
                        <option value="{{ $code }}" @selected(old('aptitude_physique', $eleve->aptitude_physique ?? 'apte') === $code)>{{ $libelle }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1 1 30%; min-width:220px;">
                <label>Inaptitude jusqu'au (facultatif)</label>
                <input type="date" name="aptitude_jusqu_au" value="{{ old('aptitude_jusqu_au', $eleve->aptitude_jusqu_au?->format('Y-m-d')) }}">
            </div>
            <div style="flex:1 1 30%; min-width:220px;">
                <label>Remarque médicale</label>
                <input type="text" name="aptitude_remarque" value="{{ old('aptitude_remarque', $eleve->aptitude_remarque) }}" placeholder="Ex : asthme, dispense de course">
            </div>
        </div>

        <h3 style="margin-top:24px; color:#c9973b;">Famille / Tuteur</h3>
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom du tuteur</label>
                <input type="text" name="tuteur_nom" value="{{ old('tuteur_nom', $eleve->tuteur_nom) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Téléphone du tuteur</label>
                <input type="text" name="tuteur_telephone" value="{{ old('tuteur_telephone', $eleve->tuteur_telephone) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom du père</label>
                <input type="text" name="pere_nom" value="{{ old('pere_nom', $eleve->pere_nom) }}">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Téléphone du père</label>
                <input type="text" name="pere_telephone" value="{{ old('pere_telephone', $eleve->pere_telephone) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Email du père</label>
                <input type="email" name="pere_email" value="{{ old('pere_email', $eleve->pere_email) }}">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom de la mère</label>
                <input type="text" name="mere_nom" value="{{ old('mere_nom', $eleve->mere_nom) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Téléphone de la mère</label>
                <input type="text" name="mere_telephone" value="{{ old('mere_telephone', $eleve->mere_telephone) }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label style="display:flex; align-items:center; gap:8px; margin-top:30px;">
                    <input type="checkbox" name="exonere" value="1" style="width:auto; margin-top:0;" @checked($eleve->exonere)> Élève exonéré
                </label>
            </div>
        </div>

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
            <button type="submit" class="btn">Mettre à jour</button>
            <a href="{{ route('eleves.index') }}">Annuler</a>
        </div>
    </form>
@endsection
