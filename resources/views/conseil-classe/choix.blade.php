@extends('layouts.app')
@section('title', 'Conseil de classe')

@section('content')
    <h1>Conseil de classe</h1>

    <p style="max-width:720px; color:#5a5a5a;">
        Saisissez pour chaque élève les heures d'absence, la note de conduite, l'appréciation du conseil,
        le commentaire sur la conduite et, si besoin, la distinction. Ces informations sont imprimées sur le bulletin.
    </p>

    @if($classes->isEmpty())
        <div style="background:#fbe6df; color:#a8503c; padding:14px; border-radius:8px; margin-top:16px;">
            Aucune classe ne vous est accessible. Un enseignant ne voit que les classes dont il est le professeur principal.
        </div>
    @else
        <form action="{{ route('conseil-classe.saisie') }}" method="GET" style="background:#fff; padding:20px; border-radius:10px;">
            <label>Classe</label>
            <select name="classe_id" required>
                <option value="">— Choisir une classe —</option>
                @foreach($classes as $classe)
                    <option value="{{ $classe->id }}">{{ $classe->cycle->nom ?? '' }} — {{ $classe->nom }}</option>
                @endforeach
            </select>

            <label>Trimestre</label>
            <select name="trimestre" required>
                <option value="1">1er trimestre</option>
                <option value="2">2e trimestre</option>
                <option value="3">3e trimestre</option>
            </select>

            <div style="margin-top:20px;">
                <button type="submit" class="btn">Afficher la grille du conseil</button>
            </div>
        </form>
    @endif
@endsection
