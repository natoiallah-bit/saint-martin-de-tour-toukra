@extends('layouts.app')
@section('title', 'Appel')

@section('content')
    <h1>Appel — {{ $classe->cycle->nom ?? '' }} {{ $classe->nom }} — {{ ucfirst(\App\Models\EmploiTemps::jourDe($date) ?? 'dimanche') }} {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h1>

    <p style="color:#5a5a5a;">Choisissez le cours pour lequel vous faites l'appel. <a href="{{ route('presences.choix', ['date' => $date]) }}">Changer de classe ou de date</a></p>

    @if($coursDuJour->isEmpty())
        <div style="background:#fbe6df; color:#a8503c; padding:14px; border-radius:8px; margin-top:16px;">
            Aucun cours prévu ce jour-là pour cette classe dans l'emploi du temps{{ $peutAppelJournee ? '' : ' (pour vous)' }}.
        </div>
    @else
        <table>
            <thead><tr><th>Horaire</th><th>Matière</th><th>Enseignant</th><th>Appel</th><th></th></tr></thead>
            <tbody>
                @foreach($coursDuJour as $c)
                    <tr>
                        <td>{{ substr($c->heure_debut, 0, 5) }} – {{ substr($c->heure_fin, 0, 5) }}</td>
                        <td>{{ $c->matiere->nom ?? '—' }}</td>
                        <td>{{ $c->enseignant ? trim($c->enseignant->prenom . ' ' . $c->enseignant->nom) : '—' }}</td>
                        <td>
                            @if(in_array($c->id, $appelsFaits))
                                <span style="color:#4c7a63; font-weight:bold;">✓ Fait</span>
                            @else
                                <span style="color:#a8503c;">À faire</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('presences.saisie', ['classe_id' => $classe->id, 'date' => $date, 'cours' => $c->id]) }}" class="btn">
                                {{ in_array($c->id, $appelsFaits) ? 'Modifier l\'appel' : 'Faire l\'appel' }}
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if($peutAppelJournee)
        <p style="margin-top:20px;">
            <a href="{{ route('presences.saisie', ['classe_id' => $classe->id, 'date' => $date, 'cours' => 'journee']) }}">
                Noter une absence pour toute la journée
            </a>
            <span style="color:#5a5a5a;">— l'élève manque alors toutes les heures de cours du jour.</span>
        </p>
    @endif
@endsection
