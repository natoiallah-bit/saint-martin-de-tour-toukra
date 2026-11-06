@extends('layouts.app')
@section('title', 'Ajouter une matière')

@section('content')
    <h1>Ajouter une matière</h1>
    <form action="{{ route('matieres-gestion.store') }}" method="POST">
        @csrf
        <label>Cycle</label>
        <select name="cycle_id" required>
            @foreach($cycles as $cycle)
                <option value="{{ $cycle->id }}">{{ $cycle->nom }}</option>
            @endforeach
        </select>

        <label>Nom de la matière</label>
        <input type="text" name="nom" placeholder="Ex : Philosophie" required>

        <label>Coefficient</label>
        <input type="number" name="coefficient" min="1" max="10" value="1">
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Fixé par la Direction ou le Principal du cycle ; pour les autres comptes, la matière est créée avec le coefficient 1.</div>

        <label>Catégorie (optionnel)</label>
        <input type="text" name="categorie" placeholder="Ex : Scientifique, Littéraire, Accessoire">

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('matieres-gestion.index') }}">Annuler</a>
        </div>
    </form>
@endsection
