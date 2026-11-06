@extends('layouts.app')
@section('title', 'Emploi du temps')

@section('content')
    <h1>Emploi du temps — choisir une classe</h1>

    <div style="display:flex; flex-wrap:wrap; gap:12px;">
        @foreach($classes as $classe)
            <a href="{{ route('emplois-temps.index', $classe) }}" class="btn" style="text-align:center;">
                {{ $classe->cycle->nom }} — {{ $classe->nom }}
            </a>
        @endforeach
    </div>

    @if($classes->isEmpty())
        <p>Aucune classe enregistrée.</p>
    @endif
@endsection
