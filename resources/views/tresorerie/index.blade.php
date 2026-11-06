@extends('layouts.app')
@section('title', 'Caisse et banque')

@section('content')
@php
    $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';
    $comptes = \App\Helpers\Tresorerie::COMPTES;
    $couleurs = ['caisse' => '#4c7a63', 'banque' => '#243365', 'mobile' => '#c9973b'];
    $icones = ['caisse' => '💵', 'banque' => '🏦', 'mobile' => '📱'];
    $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $parametresPortee = $cycleImpose ? [] : array_filter(['cycle' => $portee]);
    $nomPortee = $cycleImpose ? $cycleImpose->nom : ($portee === 'commun' ? 'Commun (Direction)' : ($portee ? optional($cycles->firstWhere('id', $portee))->nom : "Toute l'école"));
@endphp
<style>
    .cartes-tresor{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:14px; margin-bottom:20px; }
    .carte-tresor{ border-radius:12px; padding:16px; color:#fff; }
    .carte-tresor .lib{ font-size:12px; text-transform:uppercase; font-weight:700; opacity:.9; }
    .carte-tresor .val{ font-size:24px; font-weight:800; margin-top:4px; }
    .carte-tresor .sous{ font-size:12px; opacity:.85; margin-top:4px; }
    .blocs{ display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:16px; margin-top:20px; }
    .bloc{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:14px 16px; }
    .n{ text-align:right; white-space:nowrap; }
    @media print{ .sidebar, .barre-compte, .topbar-marquee, .pas-imprimer{ display:none !important; } }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <h1>Caisse et banque <span style="font-size:15px; color:#5a5a5a;">— {{ $annee }} · {{ $nomPortee }}</span></h1>
    <div class="pas-imprimer" style="display:flex; gap:8px;">
        <a href="{{ route('operations.index', $parametresPortee) }}" class="btn" style="background:#243365; color:#fff;">📒 Comptabilité</a>
        <button type="button" class="btn" onclick="window.print()">🖨️ Imprimer le journal</button>
    </div>
</div>

<form method="GET" class="pas-imprimer" style="display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;">
    @if($cycleImpose)
        <div style="padding-bottom:8px;">Cycle : <b>{{ $cycleImpose->nom }}</b></div>
    @else
        <div><label>Cycle</label>
            <select name="cycle" onchange="this.form.submit()" style="max-width:220px;">
                <option value="">Toute l'école</option>
                @foreach($cycles as $c) <option value="{{ $c->id }}" @selected((string) $portee === (string) $c->id)>{{ $c->nom }}</option> @endforeach
                <option value="commun" @selected($portee === 'commun')>Commun (Direction)</option>
            </select></div>
    @endif
    <div><label>Journal du</label>
        <input type="date" name="jour" value="{{ $jour->format('Y-m-d') }}" onchange="this.form.submit()" style="max-width:200px;"></div>
</form>

<h3>Soldes à ce jour</h3>
<div class="cartes-tresor">
    @foreach($comptes as $cle => $libelle)
        <div class="carte-tresor" style="background:{{ $couleurs[$cle] }};">
            <div class="lib">{{ $icones[$cle] }} {{ $libelle }}</div>
            <div class="val">{{ $fcfa($aujourdhui['comptes'][$cle]['fin']) }}</div>
            <div class="sous">Solde de départ {{ $annee }} : {{ $fcfa($ouverture[$cle]) }}</div>
        </div>
    @endforeach
    <div class="carte-tresor" style="background:#141c33;">
        <div class="lib">Total disponible</div>
        <div class="val">{{ $fcfa(collect($aujourdhui['comptes'])->sum('fin')) }}</div>
        <div class="sous">Caisse + banque + Mobile Money</div>
    </div>
</div>

<h3>Journal du {{ $jours[$jour->dayOfWeek] }} {{ $jour->format('d/m/Y') }}</h3>
<table style="margin-bottom:10px;">
    <thead><tr><th>Compte</th><th class="n">Solde en début de journée</th><th class="n">Entrées</th><th class="n">Sorties</th><th class="n">Solde en fin de journée</th></tr></thead>
    <tbody>
        @foreach($comptes as $cle => $libelle)
            @php $c = $situation['comptes'][$cle]; @endphp
            <tr>
                <td><b>{{ $icones[$cle] }} {{ $libelle }}</b></td>
                <td class="n">{{ $fcfa($c['debut']) }}</td>
                <td class="n" style="color:#3f6b55;">+ {{ $fcfa($c['entrees']) }}</td>
                <td class="n" style="color:#a8503c;">− {{ $fcfa($c['sorties']) }}</td>
                <td class="n"><b>{{ $fcfa($c['fin']) }}</b></td>
            </tr>
        @endforeach
    </tbody>
</table>
<table>
    <thead><tr><th>Opération</th><th>Compte</th><th class="n">Entrée</th><th class="n">Sortie</th></tr></thead>
    <tbody>
        @forelse($situation['jour'] as $l)
            <tr>
                <td>{{ $l['libelle'] }}</td>
                <td>{{ $comptes[$l['compte']] }}</td>
                <td class="n" style="color:#3f6b55;">{{ $l['entree'] ? $fcfa($l['entree']) : '' }}</td>
                <td class="n" style="color:#a8503c;">{{ $l['sortie'] ? $fcfa($l['sortie']) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="4">Aucun mouvement ce jour-là.</td></tr>
        @endforelse
    </tbody>
</table>
<p style="font-size:12px; color:#5a5a5a; margin-top:6px;">Les encaissements de scolarité, les recettes et dépenses de la comptabilité et les salaires sont rangés automatiquement : espèces → caisse, chèque ou virement → banque, Mobile Money → Mobile Money.</p>

<div class="blocs pas-imprimer">
    @droit('comptabilite', 'modifier')
        <div class="bloc">
            <h3>🔁 Versement entre comptes</h3>
            @if($saisiePossible)
                <form method="POST" action="{{ route('tresorerie.versements.store', $parametresPortee) }}">
                    @csrf
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                        <div><label>De</label>
                            <select name="compte_source">@foreach($comptes as $cle => $libelle) <option value="{{ $cle }}" @selected(old('compte_source', 'caisse') === $cle)>{{ $libelle }}</option> @endforeach</select></div>
                        <div><label>Vers</label>
                            <select name="compte_destination">@foreach($comptes as $cle => $libelle) <option value="{{ $cle }}" @selected(old('compte_destination', 'banque') === $cle)>{{ $libelle }}</option> @endforeach</select></div>
                        <div><label>Montant (FCFA)</label><input type="number" name="montant" min="1" value="{{ old('montant') }}" required></div>
                        <div><label>Date</label><input type="date" name="date_mouvement" value="{{ old('date_mouvement', now()->format('Y-m-d')) }}" required></div>
                    </div>
                    <label>Description (optionnel)</label>
                    <input type="text" name="description" maxlength="255" value="{{ old('description') }}" placeholder="Ex : dépôt des espèces à la banque, bordereau n° 152">
                    <button type="submit" class="btn" style="margin-top:10px;">Enregistrer le versement</button>
                </form>
            @else
                <p style="color:#5a5a5a;">Choisissez un cycle ou le commun pour enregistrer un versement.</p>
            @endif
        </div>

        <div class="bloc">
            <h3>📌 Soldes de départ {{ $annee }}</h3>
            @if($saisiePossible)
                <p style="font-size:13px; color:#5a5a5a;">Argent présent dans chaque compte au début de l'année scolaire (à saisir une seule fois, à la rentrée).</p>
                <form method="POST" action="{{ route('tresorerie.soldes', $parametresPortee) }}">
                    @csrf
                    @foreach($comptes as $cle => $libelle)
                        <label>{{ $libelle }}</label>
                        <input type="number" name="soldes[{{ $cle }}]" min="0" value="{{ (float) $ouverture[$cle] }}">
                    @endforeach
                    <button type="submit" class="btn" style="margin-top:10px;">Enregistrer les soldes de départ</button>
                </form>
            @else
                <p style="color:#5a5a5a;">Choisissez un cycle ou le commun pour saisir ses soldes de départ.</p>
            @endif
        </div>
    @enddroit

    <div class="bloc">
        <h3>Derniers versements</h3>
        @forelse($versements as $v)
            <div style="display:flex; justify-content:space-between; gap:10px; padding:5px 0; border-bottom:1px solid #f1ede4; font-size:14px;">
                <span>{{ $v->date_mouvement->format('d/m/Y') }} · {{ $v->libelle }}{{ $v->description ? ' — ' . $v->description : '' }}@if(! $portee) <span style="color:#888;"> ({{ $v->cycle->nom ?? 'Commun' }})</span>@endif</span>
                <span style="white-space:nowrap;"><b>{{ $fcfa($v->montant) }}</b>
                    @droit('comptabilite', 'supprimer')
                        <form action="{{ route('tresorerie.versements.destroy', $v) }}" method="POST" style="display:inline;" onsubmit="return demanderMotif(this, 'Supprimer ce versement ?');">
                            @csrf @method('DELETE')
                            <input type="hidden" name="motif">
                            <button type="submit" style="background:none; border:0; color:#a8503c; cursor:pointer; font-weight:700;" title="Supprimer">✕</button>
                        </form>
                    @enddroit
                </span>
            </div>
        @empty
            <div style="color:#888; font-size:13px;">Aucun versement cette année.</div>
        @endforelse
    </div>
</div>
@endsection
