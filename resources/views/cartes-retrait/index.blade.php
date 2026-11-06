@extends('layouts.app')
@section('title', 'Cartes de retrait')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>Cartes de retrait des enfants</h1>
        <div style="display:flex; gap:8px;">
            @if($cartes->total() > 0)
                <a href="{{ route('cartes-retrait.imprimer-tout') }}" class="btn" target="_blank">🖨️ Imprimer toutes les cartes</a>
            @endif
            @droit('cartes_retrait', 'modifier')
            <a href="{{ route('cartes-retrait.create') }}" class="btn">+ Nouvelle carte</a>
            @enddroit
        </div>
    </div>
    <p style="color:#666; margin-top:4px;">Une carte par famille : 1 ou 2 personnes autorisées à venir chercher les enfants.</p>

    <form method="GET" style="margin:12px 0 16px;">
        <input type="text" name="q" value="{{ $recherche }}" placeholder="Rechercher : numéro, personne autorisée, enfant…" style="max-width:360px;">
    </form>

    <table>
        <thead><tr><th>N°</th><th>Parents</th><th>Personnes autorisées</th><th>Enfants en charge</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($cartes as $carte)
                <tr>
                    <td><b>{{ $carte->numero }}</b></td>
                    <td>{{ $carte->parents ?: '—' }}@if($carte->quartier)<br><small style="color:#777;">{{ $carte->quartier }}</small>@endif</td>
                    <td>
                        <span style="font-size:20px;">{{ $carte->emoji }}</span>
                        <b>{{ $carte->responsable_nom }}</b> ({{ $carte->responsable_lien }}) — {{ $carte->responsable_telephone }}
                        @if($carte->second_nom)
                            <br><span style="margin-left:28px;">{{ $carte->second_nom }} ({{ $carte->second_lien }}) — {{ $carte->second_telephone }}</span>
                        @endif
                    </td>
                    <td>
                        <b>{{ $carte->eleves->count() }}</b> :
                        @foreach($carte->eleves as $eleve)
                            {{ $eleve->nom }} {{ $eleve->prenom }} <small style="color:#777;">({{ $eleve->classe->nom ?? '—' }})</small>@if(! $loop->last)<br>@endif
                        @endforeach
                    </td>
                    <td class="actions">
                        <a href="{{ route('cartes-retrait.imprimer', $carte) }}" class="btn" target="_blank">Imprimer</a>
                        @droit('cartes_retrait', 'modifier')
                        <a href="{{ route('cartes-retrait.edit', $carte) }}" class="btn">Modifier</a>
                        @enddroit
                        @droit('cartes_retrait', 'supprimer')
                        <form action="{{ route('cartes-retrait.destroy', $carte) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette carte de retrait ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Aucune carte de retrait.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px;">{{ $cartes->links() }}</div>
@endsection
