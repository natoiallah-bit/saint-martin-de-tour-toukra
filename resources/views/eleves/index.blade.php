@extends('layouts.app')
@section('title', 'Élèves')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Élèves</h1>
        <div>
            <a href="{{ route('eleves.liste-imprimable') }}" class="btn" style="background:#5a5a5a; color:#fff;">🖨️ Liste imprimable</a>
            @if($classesCartes->isNotEmpty())
                <form action="{{ route('eleves.cartes') }}" method="GET" style="display:inline-flex; gap:6px; align-items:center; margin:0 6px;">
                    <select name="classe_id" required style="width:auto; margin:0; padding:6px 8px;" aria-label="Classe pour les cartes">
                        <option value="">— Classe —</option>
                        @foreach($classesCartes as $c)
                            <option value="{{ $c->id }}" @selected($classeId === $c->id)>{{ $c->nom }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn" style="background:#4c7a63; color:#fff;">🪪 Cartes de la classe</button>
                </form>
            @endif
            @droit('eleves', 'modifier')
            <a href="{{ route('eleves.create') }}" class="btn">+ Ajouter un élève</a>
            @enddroit
        </div>
    </div>

    {{-- Filtre par classe et recherche par nom, prénom ou matricule --}}
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin:0 0 14px;">
        <select name="classe_id" onchange="this.form.submit()" style="width:auto; min-width:200px; margin:0;" aria-label="Filtrer par classe">
            <option value="">Toutes les classes</option>
            @foreach($classesCartes as $c)
                <option value="{{ $c->id }}" @selected($classeId === $c->id)>{{ $c->nom }}</option>
            @endforeach
        </select>
        <input type="search" name="q" value="{{ $recherche }}" placeholder="Rechercher un nom, un prénom ou un matricule…" style="max-width:340px; margin:0;">
        <button type="submit" class="btn">Rechercher</button>
        @if($classeId || $recherche !== '')
            <a href="{{ route('eleves.index') }}">Effacer</a>
        @endif
        <span style="color:#666; font-size:13px; margin-left:auto;">{{ $eleves->total() }} élève(s)</span>
    </form>

    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Classe</th>
                <th>Quartier</th>
                <th>Tuteur</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($eleves as $eleve)
                <tr>
                    <td>{{ $eleve->matricule }}</td>
                    <td><a href="{{ route('recherche.eleve', $eleve) }}" style="color:inherit; font-weight:600;">{{ $eleve->nom }}</a></td>
                    <td>{{ $eleve->prenom }}</td>
                    <td>{{ $eleve->classe->nom ?? '—' }}</td>
                    <td>{{ $eleve->quartier ?? '—' }}</td>
                    <td>{{ $eleve->tuteur_nom }}</td>
                    <td class="actions">
                        <a href="{{ route('recherche.eleve', $eleve) }}" class="btn" style="background:#141c33; color:#fff;">📋 Fiche</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun élève enregistré pour l'instant.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $eleves->links() }}</div>
@endsection
