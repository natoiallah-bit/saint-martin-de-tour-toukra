@extends('layouts.app')
@section('title', 'Fiche de passage')

@section('content')
    <h1>Fiche de passage de fin d'année</h1>

    @if($classes->isEmpty())
        <div style="background:#eaf2fb; color:#0d4a8f; padding:14px; border-radius:8px;">
            Aucune classe disponible.
        </div>
    @else
        <div style="background:#fff; padding:20px; border-radius:10px;">
            <ul style="list-style:none; padding:0; margin:0;">
                @foreach($classes as $classe)
                    <li style="padding:8px 0; border-bottom:1px solid #eee;">
                        <a href="{{ route('fiches-passage.afficher', $classe) }}">{{ $classe->cycle->nom }} — {{ $classe->nom }}</a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
