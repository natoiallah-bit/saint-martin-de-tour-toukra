@extends('layouts.app')
@section('title', 'Appel')

@section('content')
    <h1>Appel — {{ $classe->cycle->nom }} {{ $classe->nom }} — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h1>
    <p style="font-size:16px; font-weight:bold; color:#243365;">
        @if($cours)
            {{ $cours->matiere->nom ?? '' }} — {{ substr($cours->heure_debut, 0, 5) }} à {{ substr($cours->heure_fin, 0, 5) }}
            @if($cours->enseignant) ({{ trim($cours->enseignant->prenom . ' ' . $cours->enseignant->nom) }}) @endif
        @else
            Toute la journée
        @endif
    </p>

    {{-- Raccourci : noter la séance au cahier de texte après l'appel --}}
    @if($cours && auth()->user()->peut('cahier_texte', 'modifier') && \Carbon\Carbon::parse($date)->lte(now())
        && \App\Helpers\CoursEnLigne::peutGerer(auth()->user(), $classe->id, $cours->matiere_id, 'cahier_texte'))
        <p><a href="{{ route('cahier-texte.create', ['classe_id' => $classe->id, 'matiere_id' => $cours->matiere_id, 'emploi_temps_id' => $cours->id, 'date' => \Carbon\Carbon::parse($date)->toDateString()]) }}" class="btn" style="background:#243365; color:#fff;">📒 Remplir le cahier de texte de ce cours</a></p>
    @endif

    <form action="{{ route('presences.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="classe_id" value="{{ $classe->id }}">
        <input type="hidden" name="date" value="{{ $date }}">
        @if($cours)
            <input type="hidden" name="cours" value="{{ $cours->id }}">
        @endif

        <table>
            <thead>
                <tr><th>Élève</th><th>Présent</th><th>Absent</th><th>Retard</th><th>Remarque</th></tr>
            </thead>
            <tbody>
                @foreach($classe->eleves as $eleve)
                    @php $existante = $presencesExistantes->get($eleve->id); @endphp
                    <tr>
                        <td>{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                        <td style="text-align:center;"><input type="radio" name="statuts[{{ $eleve->id }}]" value="present" {{ ($existante->statut ?? 'present') == 'present' ? 'checked' : '' }}></td>
                        <td style="text-align:center;"><input type="radio" name="statuts[{{ $eleve->id }}]" value="absent" {{ ($existante->statut ?? '') == 'absent' ? 'checked' : '' }}></td>
                        <td style="text-align:center;"><input type="radio" name="statuts[{{ $eleve->id }}]" value="retard" {{ ($existante->statut ?? '') == 'retard' ? 'checked' : '' }}></td>
                        <td><input type="text" name="remarques[{{ $eleve->id }}]" value="{{ $existante->remarque ?? '' }}" placeholder="Optionnel"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer l'appel</button>
            <a href="{{ route('presences.saisie', ['classe_id' => $classe->id, 'date' => $date]) }}">Retour aux cours du jour</a>
        </div>
    </form>
@endsection
