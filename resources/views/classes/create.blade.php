@extends('layouts.app')
@section('title', 'Ajouter une classe')

@section('content')
    <h1>Ajouter une classe</h1>
    <form action="{{ route('classes.store') }}" method="POST">
        @csrf
        <label>Cycle</label>
        <select name="cycle_id" id="cycle-select" required>
            <option value="">— Choisir —</option>
            @foreach($cycles as $cycle)
                <option value="{{ $cycle->id }}">{{ $cycle->nom }}</option>
            @endforeach
        </select>

        <label>Nom de la classe</label>
        <input type="text" name="nom" placeholder="Ex : CP1, CM2, 6ème A..." required>

        <label>Professeur principal <span style="font-weight:normal; color:#5a5a5a;">(optionnel — il pourra consulter les bulletins et préparer le conseil de cette classe)</span></label>
        <select name="prof_principal_personnel_id">
            <option value="">— Aucun —</option>
            @foreach($enseignants as $enseignant)
                <option value="{{ $enseignant->id }}" @selected(old('prof_principal_personnel_id') == $enseignant->id)>{{ $enseignant->nom }} {{ $enseignant->prenom }}</option>
            @endforeach
        </select>
        <p style="font-size:13px; color:#5a5a5a; margin-top:4px;">La liste reprend le Personnel de type Enseignant. Pour qu'il accède aux bulletins, son compte utilisateur doit être lié à sa fiche Personnel (Comptes utilisateurs).</p>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('classes.index') }}">Annuler</a>
        </div>
    </form>

@endsection
