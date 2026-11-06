@extends('layouts.app')
@section('title', $seance->exists ? 'Modifier la séance' : 'Remplir le cahier de texte')

@section('content')
    @include('cours._style')
    <h1>{{ $seance->exists ? 'Modifier la séance' : 'Remplir le cahier de texte' }}</h1>
    @include('cours._erreurs')

    <form action="{{ $seance->exists ? route('cahier-texte.update', $seance) : route('cahier-texte.store') }}" method="POST" class="formulaire">
        @csrf
        @if($seance->exists) @method('PUT') @endif

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 16px;">
            <div>
                <label>Classe</label>
                <select name="classe_id" required onchange="window.location='{{ route('cahier-texte.create') }}?classe_id=' + this.value" @disabled($seance->exists)>
                    @foreach($classes as $c) <option value="{{ $c->id }}" @selected((int) old('classe_id', $seance->classe_id) === $c->id)>{{ $c->nom }}</option> @endforeach
                </select>
                @if($seance->exists) <input type="hidden" name="classe_id" value="{{ $seance->classe_id }}"> @endif
            </div>
            <div>
                <label>Matière</label>
                <select name="matiere_id" required>
                    @foreach($matieres as $m) <option value="{{ $m->id }}" @selected((int) old('matiere_id', $seance->matiere_id) === $m->id)>{{ $m->nom }}</option> @endforeach
                </select>
            </div>
            <div>
                <label>Date de la séance</label>
                <input type="date" name="date" value="{{ old('date', $seance->date instanceof \Carbon\Carbon ? $seance->date->format('Y-m-d') : $seance->date) }}" max="{{ now()->format('Y-m-d') }}" required>
            </div>
        </div>

        @if($creneaux->isNotEmpty())
            <label>Créneau de l'emploi du temps (facultatif)</label>
            <select name="emploi_temps_id">
                <option value="">—</option>
                @foreach($creneaux as $c)
                    <option value="{{ $c->id }}" @selected((int) old('emploi_temps_id', $seance->emploi_temps_id) === $c->id)>{{ ucfirst($c->jour) }} {{ substr($c->heure_debut, 0, 5) }}–{{ substr($c->heure_fin, 0, 5) }} · {{ $c->matiere->nom ?? '' }}</option>
                @endforeach
            </select>
        @endif

        <label>Titre de la leçon / chapitre</label>
        <input type="text" name="titre" value="{{ old('titre', $seance->titre) }}" required maxlength="200" placeholder="Ex : Chapitre 3 — Les fractions (suite)">

        <label>Contenu de la séance</label>
        <textarea name="contenu" rows="6" placeholder="Ce qui a été vu : notions, exercices faits en classe…">{{ old('contenu', $seance->contenu) }}</textarea>

        <label>Travail à faire pour la prochaine fois</label>
        <textarea name="travail_a_faire" rows="3" placeholder="Ex : Exercices 4 et 5 page 32 pour jeudi">{{ old('travail_a_faire', $seance->travail_a_faire) }}</textarea>
        <div class="aide">Le travail à faire apparaît aussi dans l'espace des élèves et de leurs parents.</div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('cahier-texte.index') }}">Annuler</a>
        </div>
    </form>
@endsection
