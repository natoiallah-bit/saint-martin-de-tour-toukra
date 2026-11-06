@extends('layouts.app')
@section('title', $dossier->exists ? 'Modifier la comparution' : 'Traduire un élève au conseil de discipline')

@section('content')
    @include('cours._style')
    <h1>{{ $dossier->exists ? 'Modifier la comparution' : 'Traduire un élève au conseil de discipline' }}</h1>
    @include('cours._erreurs')

    <form action="{{ $dossier->exists ? route('discipline.update', $dossier) : route('discipline.store') }}" method="POST" class="formulaire">
        @csrf
        @if($dossier->exists) @method('PUT') @endif

        <label>Élève</label>
        <select name="eleve_id" required>
            <option value="">— Choisir —</option>
            @foreach($eleves as $e)
                <option value="{{ $e->id }}" @selected((int) old('eleve_id', $dossier->eleve_id) === $e->id)>{{ $e->classe->nom ?? '' }} — {{ $e->nom }} {{ $e->prenom }}</option>
            @endforeach
        </select>

        <label>Motif (visible par le parent)</label>
        <input type="text" name="motif" value="{{ old('motif', $dossier->motif) }}" required maxlength="200" placeholder="Ex : Bagarre dans la cour, absences répétées non justifiées…">

        <label>Faits reprochés (détails)</label>
        <textarea name="faits" rows="5" placeholder="Ce qui s'est passé, témoins…">{{ old('faits', $dossier->faits) }}</textarea>

        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0 16px;">
            <div><label>Date des faits</label><input type="date" name="date_faits" value="{{ old('date_faits', $dossier->date_faits?->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}"></div>
            <div><label>Date et heure du conseil</label><input type="datetime-local" name="date_conseil" value="{{ old('date_conseil', $dossier->date_conseil?->format('Y-m-d\TH:i')) }}"></div>
            <div><label>Lieu</label><input type="text" name="lieu" value="{{ old('lieu', $dossier->lieu) }}" placeholder="Ex : Bureau du Directeur"></div>
        </div>
        <div class="aide">Le parent reçoit une alerte dans son espace avec le motif, la date et le lieu du conseil, et doit confirmer en avoir pris connaissance.</div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">{{ $dossier->exists ? 'Enregistrer' : 'Enregistrer et alerter le parent' }}</button>
            <a href="{{ $dossier->exists ? route('discipline.show', $dossier) : route('discipline.index') }}">Annuler</a>
        </div>
    </form>
@endsection
