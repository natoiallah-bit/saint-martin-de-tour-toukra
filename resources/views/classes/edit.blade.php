@extends('layouts.app')
@section('title', 'Modifier une classe')

@section('content')
    <h1>Modifier {{ $classe->nom }}</h1>
    <form action="{{ route('classes.update', $classe->id) }}" method="POST">
        @csrf @method('PUT')
        <label>Cycle</label>
        <select name="cycle_id" id="cycle-select" required>
            @foreach($cycles as $cycle)
                <option value="{{ $cycle->id }}" @selected($classe->cycle_id==$cycle->id)>{{ $cycle->nom }}</option>
            @endforeach
        </select>

        <label>Nom de la classe</label>
        <input type="text" name="nom" value="{{ $classe->nom }}" required>

        <label>Professeur principal <span style="font-weight:normal; color:#5a5a5a;">(optionnel — il pourra consulter les bulletins et préparer le conseil de cette classe)</span></label>
        @php
            $actuel = old('prof_principal_personnel_id', $classe->prof_principal_personnel_id);
            [$deLaClasse, $autres] = $enseignants->partition(fn ($e) => in_array($e->id, $enseignantsDeLaClasse));
        @endphp
        <select name="prof_principal_personnel_id">
            <option value="">— Aucun —</option>
            @if($deLaClasse->isNotEmpty())
                <optgroup label="Ont cours dans cette classe">
                    @foreach($deLaClasse as $enseignant)
                        <option value="{{ $enseignant->id }}" @selected($actuel == $enseignant->id)>{{ $enseignant->nom }} {{ $enseignant->prenom }}</option>
                    @endforeach
                </optgroup>
            @endif
            <optgroup label="{{ $deLaClasse->isNotEmpty() ? 'Autres enseignants' : 'Enseignants' }}">
                @foreach($autres as $enseignant)
                    <option value="{{ $enseignant->id }}" @selected($actuel == $enseignant->id)>{{ $enseignant->nom }} {{ $enseignant->prenom }}</option>
                @endforeach
            </optgroup>
        </select>
        @if(! $classe->prof_principal_personnel_id && $classe->profPrincipal)
            <p style="font-size:13px; color:#8a6a2a; margin-top:4px;">Ancien choix : {{ $classe->profPrincipal->prenom }} {{ $classe->profPrincipal->nom }}. Choisissez-le dans la liste puis enregistrez.</p>
        @endif
        <p style="font-size:13px; color:#5a5a5a; margin-top:4px;">La liste reprend le Personnel de type Enseignant. Pour qu'il accède aux bulletins, son compte utilisateur doit être lié à sa fiche Personnel (Comptes utilisateurs).</p>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Mettre à jour</button>
            <a href="{{ route('classes.index') }}">Annuler</a>
        </div>
    </form>

@endsection
