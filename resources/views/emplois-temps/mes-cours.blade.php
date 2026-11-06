@extends('layouts.app')
@section('title', 'Mon emploi du temps')

@section('content')
    <h1>Mon emploi du temps</h1>

    @foreach($jours as $jour)
        @if($creneaux->has($jour) && $creneaux[$jour]->isNotEmpty())
            <h3 style="text-transform:capitalize; margin-top:20px;">{{ $jour }}</h3>
            <table>
                <thead><tr><th>Heure</th><th>Classe</th><th>Matière</th></tr></thead>
                <tbody>
                    @foreach($creneaux[$jour]->sortBy('heure_debut') as $creneau)
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($creneau->heure_debut)->format('H:i') }} – {{ \Carbon\Carbon::parse($creneau->heure_fin)->format('H:i') }}</td>
                            <td>{{ $creneau->classe->cycle->nom }} — {{ $creneau->classe->nom }}</td>
                            <td>{{ $creneau->matiere->nom }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    @if($creneaux->isEmpty())
        <div style="background:#eaf2fb; color:#0d4a8f; padding:14px; border-radius:8px;">
            Aucun créneau ne vous est actuellement assigné dans l'emploi du temps.
        </div>
    @endif
@endsection
