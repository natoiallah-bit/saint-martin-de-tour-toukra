@extends('layouts.app')
@section('title', "Cahier d'appel")

@section('content')
    <h1>Cahier d'appel — faire l'appel</h1>

    <form action="{{ route('presences.saisie') }}" method="GET" style="background:#fff; padding:20px; border-radius:10px;">
        <label>Classe</label>
        <select name="classe_id" required>
            <option value="">— Choisir une classe —</option>
            @foreach($classes as $classe)
                <option value="{{ $classe->id }}">{{ $classe->cycle->nom }} — {{ $classe->nom }}</option>
            @endforeach
        </select>

        <label>Date</label>
        <input type="date" name="date" value="{{ $date }}" required>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Faire l'appel</button>
        </div>
    </form>

    @if($classes->isEmpty())
        <div style="background:#fbe6df; color:#a8503c; padding:14px; border-radius:8px; margin-top:16px;">
            Aucune classe ne vous est assignée dans l'emploi du temps pour le moment.
        </div>
    @endif
@endsection
