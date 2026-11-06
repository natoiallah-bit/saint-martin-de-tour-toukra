@extends('layouts.app')
@section('title', 'Modifier une matière')

@section('content')
    <h1>Modifier {{ $matiere->nom }}</h1>
    <form action="{{ route('matieres-gestion.update', $matiere->id) }}" method="POST">
        @csrf @method('PUT')
        <label>Cycle</label>
        <select name="cycle_id" required>
            @foreach($cycles as $cycle)
                <option value="{{ $cycle->id }}" @selected($matiere->cycle_id==$cycle->id)>{{ $cycle->nom }}</option>
            @endforeach
        </select>

        <label>Nom de la matière</label>
        <input type="text" name="nom" value="{{ $matiere->nom }}" required>

        <label>Coefficient</label>
        @if(\App\Models\Matiere::peutFixerCoefficient(auth()->user(), $matiere->cycle_id))
            <input type="number" name="coefficient" min="1" max="10" value="{{ $matiere->coefficient }}" required>
        @else
            <input type="number" value="{{ $matiere->coefficient }}" disabled>
            <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Le coefficient est fixé par la Direction ou le Principal du cycle.</div>
        @endif

        <label>Catégorie (optionnel)</label>
        <input type="text" name="categorie" value="{{ $matiere->categorie }}">

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Mettre à jour</button>
            <a href="{{ route('matieres-gestion.index') }}">Annuler</a>
        </div>
    </form>
@endsection
