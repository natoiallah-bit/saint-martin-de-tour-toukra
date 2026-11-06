@extends('layouts.app')
@section('title', 'Ajouter un matériel')

@section('content')
    <h1>Ajouter un matériel</h1>

    <form action="{{ route('materiels.store') }}" method="POST">
        @csrf
        <div style="display:flex; flex-wrap:wrap; gap:0 24px;">
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Nom du matériel</label>
                <input type="text" name="nom" placeholder="Ex : Table-bancs" required>
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
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Quantité</label>
                <input type="number" name="quantite" min="0" value="1" required>
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>État</label>
                <select name="etat" required>
                    <option value="bon">Bon état</option>
                    <option value="moyen">État moyen</option>
                    <option value="mauvais">Mauvais état</option>
                    <option value="hors_service">Hors service</option>
                </select>
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Valeur unitaire estimée (FCFA)</label>
                <input type="number" name="valeur_estimee" min="0" step="500" value="0">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Localisation</label>
                <input type="text" name="localisation" placeholder="Ex : Salle CM2, Bureau direction">
            </div>

            <div style="flex:1 1 45%; min-width:280px;">
                <label>Date d'acquisition</label>
                <input type="date" name="date_acquisition">
            </div>
            <div style="flex:1 1 45%; min-width:280px;">
                <label>Remarque</label>
                <input type="text" name="remarque" placeholder="(optionnel)">
            </div>
        </div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('materiels.index') }}">Annuler</a>
        </div>
    </form>
@endsection
