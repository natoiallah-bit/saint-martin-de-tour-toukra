@extends('layouts.app')
@section('title', 'Cahier de texte')

@section('content')
    @include('cours._style')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>Cahier de texte</h1>
        @droit('cahier_texte', 'modifier')
            <a href="{{ route('cahier-texte.create', array_filter(['classe_id' => $classeId, 'matiere_id' => $matiereId])) }}" class="btn">+ Remplir une séance</a>
        @enddroit
    </div>

    <form method="GET" class="bloc" style="display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end;">
        <div><label style="margin-top:0;">Classe</label>
            <select name="classe_id" onchange="this.form.submit()" style="min-width:180px;">
                <option value="">Toutes</option>
                @foreach($classes as $c) <option value="{{ $c->id }}" @selected($classeId === $c->id)>{{ $c->nom }}</option> @endforeach
            </select></div>
        @if($matieres->isNotEmpty())
            <div><label style="margin-top:0;">Matière</label>
                <select name="matiere_id" onchange="this.form.submit()" style="min-width:160px;">
                    <option value="">Toutes</option>
                    @foreach($matieres as $m) <option value="{{ $m->id }}" @selected($matiereId === $m->id)>{{ $m->nom }}</option> @endforeach
                </select></div>
        @endif
        <div><label style="margin-top:0;">Du</label><input type="date" name="du" value="{{ $du->format('Y-m-d') }}"></div>
        <div><label style="margin-top:0;">Au</label><input type="date" name="au" value="{{ $au->format('Y-m-d') }}"></div>
        <button type="submit" class="btn">Afficher</button>
    </form>

    {{-- Suivi de l'avancement (Principal, Direction) --}}
    @if($suivi->isNotEmpty())
        <div class="bloc">
            <h3>Suivi des séances du {{ $du->format('d/m/Y') }} au {{ $au->min(now())->format('d/m/Y') }}</h3>
            <p class="aide" style="margin-bottom:8px;">Séances prévues d'après l'emploi du temps, comparées aux séances inscrites au cahier de texte.</p>
            <table>
                <thead><tr><th>Classe</th><th>Matière</th><th>Enseignant</th><th>Prévues</th><th>Remplies</th><th>Suivi</th><th>Dernière séance</th></tr></thead>
                <tbody>
                    @foreach($suivi as $l)
                        <tr>
                            <td>{{ $l['classe']->nom }}</td>
                            <td>{{ $l['matiere']->nom ?? '—' }}</td>
                            <td>{{ $l['enseignant'] ?: '—' }}</td>
                            <td>{{ $l['prevues'] }}</td>
                            <td>{{ $l['remplies'] }}</td>
                            <td>
                                @if($l['taux'] === null) —
                                @else <span class="pastille {{ $l['taux'] >= 80 ? 'vert' : ($l['taux'] >= 50 ? 'or' : 'rouge') }}">{{ $l['taux'] }} %</span>
                                @endif
                            </td>
                            <td>{{ $l['derniere'] ? \Carbon\Carbon::parse($l['derniere'])->format('d/m/Y') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Séances --}}
    <div class="bloc">
        <h3>Séances ({{ $seances->total() }})</h3>
        @forelse($seances as $s)
            <div class="element" style="align-items:flex-start;">
                <div class="infos" style="flex:1; min-width:260px;">
                    <b>{{ $s->date->format('d/m/Y') }} — {{ $s->classe->nom }} — {{ $s->matiere->nom }}</b>
                    <div style="margin-top:4px; font-weight:700; color:#243365;">{{ $s->titre }}</div>
                    @if($s->contenu) <div style="white-space:pre-line; margin-top:4px;">{{ $s->contenu }}</div> @endif
                    @if($s->travail_a_faire) <div style="margin-top:4px;"><b>Travail à faire :</b> {{ $s->travail_a_faire }}</div> @endif
                    <small>Par {{ $s->auteur->name ?? '—' }}</small>
                    @if($s->vise_le)
                        <div style="margin-top:6px; background:#e2ede6; padding:6px 10px; border-radius:6px; font-size:13px;">
                            ✔ Visé par {{ $s->viseur->name ?? '—' }} le {{ $s->vise_le->format('d/m/Y') }} : {{ $s->visa }}
                        </div>
                    @endif
                </div>
                <div class="actions" style="display:flex; flex-direction:column; gap:6px; align-items:flex-end;">
                    @droit('cahier_texte', 'modifier') <a href="{{ route('cahier-texte.edit', $s) }}" class="btn">Modifier</a> @enddroit
                    @if($peutViser)
                        <form action="{{ route('cahier-texte.viser', $s) }}" method="POST" style="display:flex; gap:6px;">
                            @csrf
                            <input type="text" name="visa" placeholder="Observation (facultatif)" value="{{ $s->visa }}" style="width:200px; margin:0;">
                            <button class="btn" style="background:#243365; color:#fff;">{{ $s->vise_le ? 'Revisier' : 'Viser' }}</button>
                        </form>
                    @endif
                    @droit('cahier_texte', 'supprimer')
                        <form action="{{ route('cahier-texte.destroy', $s) }}" method="POST" onsubmit="return confirm('Supprimer cette séance ?');">@csrf @method('DELETE')<button class="btn btn-danger">Supprimer</button></form>
                    @enddroit
                </div>
            </div>
        @empty
            <div class="vide">Aucune séance inscrite sur cette période.</div>
        @endforelse
        <div style="margin-top:12px;">{{ $seances->links() }}</div>
    </div>
@endsection
