@extends('layouts.app')
@section('title', 'Modifier un matériel')

@section('content')
    <h1>Modifier {{ $materiel->nom }}</h1>

    <form action="{{ route('materiels.update', $materiel) }}" method="POST">
        @csrf @method('PUT')
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom du matériel</label>
                <input type="text" name="nom" value="{{ $materiel->nom }}" required>
            </div>
            @if($cycles->isNotEmpty())
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Cycle</label>
                <select name="cycle_id">
                    <option value="">Commun (toute l'école)</option>
                    @foreach($cycles as $c) <option value="{{ $c->id }}" @selected(old('cycle_id', $materiel->cycle_id ?? null) == $c->id)>{{ $c->nom }}</option> @endforeach
                </select>
            </div>
            @endif
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Catégorie</label>
                <select name="categorie" required>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" @selected($materiel->categorie==$cat)>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Quantité</label>
                <input type="number" name="quantite" min="0" value="{{ $materiel->quantite }}" required>
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>État</label>
                <select name="etat" required>
                    <option value="bon" @selected($materiel->etat=='bon')>Bon état</option>
                    <option value="moyen" @selected($materiel->etat=='moyen')>État moyen</option>
                    <option value="mauvais" @selected($materiel->etat=='mauvais')>Mauvais état</option>
                    <option value="hors_service" @selected($materiel->etat=='hors_service')>Hors service</option>
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Valeur unitaire estimée (FCFA)</label>
                <input type="number" name="valeur_estimee" min="0" step="500" value="{{ $materiel->valeur_estimee }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Localisation</label>
                <input type="text" name="localisation" value="{{ $materiel->localisation }}">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Date d'acquisition</label>
                <input type="date" name="date_acquisition" value="{{ $materiel->date_acquisition?->format('Y-m-d') }}">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Remarque</label>
                <input type="text" name="remarque" value="{{ $materiel->remarque }}">
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Mettre à jour</button>
            <a href="{{ route('materiels.index') }}">Annuler</a>
        </div>
    </form>
@endsection
