@extends('layouts.app')
@section('title', 'Conseil de discipline')

@section('content')
    @include('cours._style')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>Conseil de discipline</h1>
        @droit('discipline', 'modifier') <a href="{{ route('discipline.create') }}" class="btn">+ Traduire un élève au conseil</a> @enddroit
    </div>

    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px;">
        <select name="statut" onchange="this.form.submit()" style="max-width:220px; margin:0;">
            <option value="">Tous les dossiers</option>
            <option value="attente" @selected(request('statut') === 'attente')>En attente de décision</option>
            <option value="decide" @selected(request('statut') === 'decide')>Décidés</option>
        </select>
        <select name="classe_id" onchange="this.form.submit()" style="max-width:220px; margin:0;">
            <option value="">Toutes les classes</option>
            @foreach($classes as $c) <option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nom }}</option> @endforeach
        </select>
    </form>

    <table>
        <thead><tr><th>Élève</th><th>Classe</th><th>Motif</th><th>Conseil</th><th>Décision</th><th>Parent informé</th><th></th></tr></thead>
        <tbody>
            @forelse($dossiers as $d)
                <tr>
                    <td><b>{{ $d->eleve->nom }} {{ $d->eleve->prenom }}</b></td>
                    <td>{{ $d->eleve->classe->nom ?? '—' }}</td>
                    <td>{{ $d->motif }}</td>
                    <td>{{ $d->date_conseil?->format('d/m/Y H:i') ?? '—' }}</td>
                    <td>@if($d->estDecide()) <span class="pastille">{{ $d->libelle_decision }}</span> @else <span class="pastille or">En attente</span> @endif</td>
                    <td>@if($d->estNonLuParParent()) <span class="pastille rouge">Pas encore lu</span> @else <span class="pastille vert">✔ Lu</span> @endif</td>
                    <td><a href="{{ route('discipline.show', $d) }}" class="btn" style="background:#243365; color:#fff;">Ouvrir</a></td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun dossier.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:14px;">{{ $dossiers->links() }}</div>
@endsection
