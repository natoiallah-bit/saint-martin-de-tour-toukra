@extends('layouts.app')
@section('title', 'Rapport ' . mb_strtolower($periode['libelle']))

@section('content')
@php
    $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';
    $note = fn ($m) => $m === null ? '—' : number_format($m, 2, ',', ' ');
    $pct = fn ($a, $b) => $b > 0 ? round($a * 100 / $b) . ' %' : '—';
    $titre = $general ? 'Rapport général' : ($pedagogie ? 'Rapport pédagogique' : 'Rapport financier');
    $logo = \App\Support\Etablissement::banniere();
@endphp
<style>
    .rapport h2{ font-family:'Fraunces', serif; font-size:20px; color:#141c33; margin:28px 0 10px; padding-bottom:6px; border-bottom:2px solid #c9973b; }
    .rapport h3{ margin:18px 0 8px; }
    .rapport table{ margin-bottom:8px; }
    .rapport td.n, .rapport th.n{ text-align:right; white-space:nowrap; }
    .rapport tr.total td{ font-weight:700; background:#f7f3ea; }
    .synthese{ display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin:14px 0 6px; }
    .synthese div{ background:#fff; border:1px solid #e3ddcf; border-left:4px solid #243365; border-radius:8px; padding:12px 14px; }
    .synthese div.fin{ border-left-color:#4c7a63; }
    .synthese b{ display:block; font-family:'Fraunces', serif; font-size:21px; color:#141c33; margin-top:4px; }
    .synthese span{ font-size:11px; text-transform:uppercase; letter-spacing:0.3px; color:#5a5a5a; font-weight:700; }
    .filtres{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#fff; padding:12px 14px; border-radius:8px; border:1px solid #e3ddcf; }
    .filtres select{ width:auto; margin:0; }
    .avertissement{ background:#fff4dc; color:#7a5a12; padding:10px 14px; border-radius:6px; margin-top:12px; font-size:13px; }
    .entete-impression{ display:none; }
    .deux-colonnes{ display:grid; grid-template-columns:1fr 1fr; gap:18px; }
    @media (max-width:1000px){ .deux-colonnes{ grid-template-columns:1fr; } }
    .signature{ display:none; }
    @media print{
        .filtres, .no-print{ display:none !important; }
        .entete-impression{ display:block; text-align:center; margin-bottom:10px; }
        .entete-impression img{ max-height:26mm; max-width:100%; }
        .rapport h2{ break-after:avoid; }
        .rapport table{ break-inside:auto; font-size:11px; }
        .rapport tr{ break-inside:avoid; }
        th{ background:#e8eaf0 !important; color:#000 !important; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
        .signature{ display:flex; justify-content:flex-end; margin-top:24px; }
        @page{ size:A4; margin:12mm; }
        body, main{ background:#fff !important; }
    }
</style>

<div class="rapport">
    <div class="entete-impression">
        @if($logo) <img src="{{ $logo }}" alt="{{ $config->nom_ecole }}"> @else <b>{{ $config->nom_ecole }}</b> @endif
    </div>

    <h1>{{ $titre }} — {{ $periode['libelle'] }} {{ $config->annee_scolaire_active }}</h1>
    <p style="margin-top:-12px; color:#5a5a5a;">
        {{ $cycle ? 'Cycle : ' . $cycle->nom : 'Toute l\'école' }}
        @if($periode['debut'] && $periode['fin'])
            · du {{ $periode['debut']->format('d/m/Y') }} au {{ $periode['fin']->format('d/m/Y') }}
        @endif
        · édité le {{ now()->format('d/m/Y') }}
    </p>

    <form method="GET" class="filtres">
        <label style="margin:0;">Période</label>
        <select name="periode" onchange="this.form.submit()">
            @foreach(\App\Helpers\Rapport::PERIODES as $code => $libelle)
                <option value="{{ $code }}" @selected($periode['code'] === $code)>{{ $code === 'annee' ? 'Rapport annuel' : $libelle }}</option>
            @endforeach
        </select>
        @unless($cycleImpose)
            <label style="margin:0;">Cycle</label>
            <select name="cycle_id" onchange="this.form.submit()">
                <option value="">Toute l'école</option>
                @foreach($cycles as $c)
                    <option value="{{ $c->id }}" @selected($cycle?->id === $c->id)>{{ $c->nom }}</option>
                @endforeach
            </select>
        @endunless
        <button type="button" class="btn" onclick="window.print()" style="margin-left:auto;">🖨️ Imprimer le rapport</button>
    </form>

    @if(! $periode['debut'] || ! $periode['fin'])
        <div class="avertissement">
            Les dates des trimestres ne sont pas réglées dans Paramètres : l'assiduité n'est pas calculée
            et les encaissements portent sur toute l'année.
        </div>
    @endif

    {{-- Synthèse --}}
    <div class="synthese">
        @if($pedagogie)
            <div><span>Élèves</span><b>{{ $pedagogie['total']['effectif'] }}</b><small>{{ $pedagogie['total']['garcons'] }} G · {{ $pedagogie['total']['filles'] }} F</small></div>
            <div><span>Moyenne générale</span><b>{{ $note($pedagogie['total']['moyenne']) }}</b></div>
            <div><span>Taux de réussite</span><b>{{ $pct($pedagogie['total']['reussite'], $pedagogie['total']['classes']) }}</b><small>{{ $pedagogie['total']['reussite'] }} / {{ $pedagogie['total']['classes'] }} élèves ≥ 10</small></div>
            <div><span>Heures d'absence</span><b>{{ $pedagogie['total']['heures'] ?? '—' }}</b><small>{{ $pedagogie['total']['retards'] }} retard(s)</small></div>
        @endif
        @if($finances)
            <div class="fin"><span>Encaissé sur la période</span><b>{{ $fcfa($finances['total']['encaisse']) }}</b></div>
            <div class="fin"><span>Taux de recouvrement</span><b>{{ $finances['total']['taux'] !== null ? $finances['total']['taux'] . ' %' : '—' }}</b></div>
            <div class="fin"><span>Reste à recouvrer</span><b>{{ $fcfa($finances['total']['reste']) }}</b><small>{{ $finances['total']['en_retard'] }} élève(s) en retard</small></div>
        @endif
        @if($general)
            <div><span>Personnel actif</span><b>{{ $personnel['total'] }}</b><small>dont {{ $personnel['enseignants'] }} enseignant(s)</small></div>
        @endif
    </div>

    {{-- ====================== Volet pédagogique ====================== --}}
    @if($pedagogie)
        <h2>Volet pédagogique</h2>

        <h3>Effectifs et résultats par classe</h3>
        <table>
            <thead><tr>
                <th>Classe</th><th class="n">Effectif</th><th class="n">G</th><th class="n">F</th><th class="n">Classés</th>
                <th class="n">Moyenne</th><th class="n">Plus forte</th><th class="n">Plus faible</th><th class="n">≥ 10</th><th class="n">Réussite</th>
            </tr></thead>
            <tbody>
                @foreach($pedagogie['lignes'] as $l)
                    <tr>
                        <td>{{ $l['classe']->nom }}</td>
                        <td class="n">{{ $l['effectif'] }}</td><td class="n">{{ $l['garcons'] }}</td><td class="n">{{ $l['filles'] }}</td>
                        <td class="n">{{ $l['classes'] }}</td>
                        <td class="n"><b>{{ $note($l['moyenne']) }}</b></td><td class="n">{{ $note($l['max']) }}</td><td class="n">{{ $note($l['min']) }}</td>
                        <td class="n">{{ $l['reussite'] }}</td><td class="n">{{ $pct($l['reussite'], $l['classes']) }}</td>
                    </tr>
                @endforeach
                @php $t = $pedagogie['total']; @endphp
                <tr class="total">
                    <td>Total</td><td class="n">{{ $t['effectif'] }}</td><td class="n">{{ $t['garcons'] }}</td><td class="n">{{ $t['filles'] }}</td>
                    <td class="n">{{ $t['classes'] }}</td><td class="n">{{ $note($t['moyenne']) }}</td><td class="n">{{ $note($t['max']) }}</td><td class="n">{{ $note($t['min']) }}</td>
                    <td class="n">{{ $t['reussite'] }}</td><td class="n">{{ $pct($t['reussite'], $t['classes']) }}</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size:12px; color:#666;">
            Moyennes générales conduite comprise. @if($periode['annuel']) Moyenne annuelle = moyenne des trimestres. @endif
            « Classés » : élèves qui ont une moyenne sur la période.
        </p>

        <h3>Assiduité (d'après l'appel)</h3>
        <table>
            <thead><tr><th>Classe</th><th class="n">Heures d'absence</th><th class="n">Jours d'absence</th><th class="n">Retards</th><th class="n">Heures par élève</th></tr></thead>
            <tbody>
                @foreach($pedagogie['lignes'] as $l)
                    <tr>
                        <td>{{ $l['classe']->nom }}</td>
                        <td class="n">{{ $l['heures'] ?? '—' }}</td><td class="n">{{ $l['jours'] }}</td><td class="n">{{ $l['retards'] }}</td>
                        <td class="n">{{ $l['heures'] !== null && $l['effectif'] ? number_format($l['heures'] / $l['effectif'], 1, ',', ' ') : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="total"><td>Total</td><td class="n">{{ $t['heures'] ?? '—' }}</td><td class="n">{{ $t['jours'] }}</td><td class="n">{{ $t['retards'] }}</td>
                    <td class="n">{{ $t['heures'] !== null && $t['effectif'] ? number_format($t['heures'] / $t['effectif'], 1, ',', ' ') : '—' }}</td></tr>
            </tbody>
        </table>

        <div class="deux-colonnes">
            <div>
                <h3>Moyennes par matière</h3>
                <table>
                    <thead><tr><th>Matière</th><th class="n">Moyenne</th><th class="n">Réussite</th></tr></thead>
                    <tbody>
                        @forelse($pedagogie['matieres'] as $m)
                            <tr><td>{{ $m['matiere'] }}</td><td class="n">{{ $note($m['moyenne']) }}</td><td class="n">{{ $m['reussite'] !== null ? $m['reussite'] . ' %' : '—' }}</td></tr>
                        @empty
                            <tr><td colspan="3">Aucune note sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div>
                <h3>Tableau d'honneur</h3>
                <table>
                    <thead><tr><th>#</th><th>Élève</th><th>Classe</th><th class="n">Moyenne</th></tr></thead>
                    <tbody>
                        @forelse($pedagogie['honneur'] as $i => $h)
                            <tr>
                                <td>{{ $i + 1 }}</td><td>{{ $h['eleve']->nom }} {{ $h['eleve']->prenom }}</td><td>{{ $h['classe']->nom }}</td>
                                <td class="n"><b>{{ $note($h['moyenne']) }}</b><br><small>{{ \App\Helpers\Appreciation::distinction($h['moyenne']) }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="4">Aucun élève avec 12 de moyenne ou plus.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ====================== Volet financier ====================== --}}
    @if($finances)
        <h2>Volet financier</h2>

        <h3>Scolarité par classe</h3>
        <table>
            <thead><tr>
                <th>Classe</th><th class="n">Payants</th><th class="n">Exonérés</th>
                <th class="n">{{ $periode['annuel'] ? 'Attendu (année)' : 'Dû à la fin du trimestre' }}</th>
                <th class="n">Encaissé sur la période</th><th class="n">Versé au total</th><th class="n">Reste dû</th>
                <th class="n">En retard</th><th class="n">Recouvrement</th>
            </tr></thead>
            <tbody>
                @foreach($finances['lignes'] as $l)
                    <tr>
                        <td>{{ $l['classe']->nom }}</td><td class="n">{{ $l['payants'] }}</td><td class="n">{{ $l['exoneres'] }}</td>
                        <td class="n">{{ $fcfa($l['attendu']) }}</td><td class="n">{{ $fcfa($l['encaisse']) }}</td><td class="n">{{ $fcfa($l['cumul']) }}</td>
                        <td class="n" style="color:#a8503c;">{{ $fcfa($l['reste']) }}</td><td class="n">{{ $l['en_retard'] }}</td>
                        <td class="n">{{ $l['taux'] !== null ? $l['taux'] . ' %' : '—' }}</td>
                    </tr>
                @endforeach
                @php $f = $finances['total']; @endphp
                <tr class="total">
                    <td>Total</td><td class="n">{{ $f['payants'] }}</td><td class="n">{{ $f['exoneres'] }}</td>
                    <td class="n">{{ $fcfa($f['attendu']) }}</td><td class="n">{{ $fcfa($f['encaisse']) }}</td><td class="n">{{ $fcfa($f['cumul']) }}</td>
                    <td class="n">{{ $fcfa($f['reste']) }}</td><td class="n">{{ $f['en_retard'] }}</td><td class="n">{{ $f['taux'] !== null ? $f['taux'] . ' %' : '—' }}</td>
                </tr>
            </tbody>
        </table>
        <p style="font-size:12px; color:#666;">
            {{ $periode['annuel'] ? 'Attendu : frais annuels des élèves non exonérés.' : 'Dû : ce que chaque élève devait avoir payé à la fin du trimestre selon l\'échéancier (page Frais).' }}
            « Versé au total » : paiements de l'année jusqu'à la fin de la période.
        </p>

        <div class="deux-colonnes">
            <div>
                <h3>Encaissements par mode de paiement</h3>
                <table>
                    <thead><tr><th>Mode</th><th class="n">Paiements</th><th class="n">Montant</th></tr></thead>
                    <tbody>
                        @forelse($finances['par_mode'] as $m)
                            <tr><td>{{ $m['mode'] }}</td><td class="n">{{ $m['nombre'] }}</td><td class="n">{{ $fcfa($m['montant']) }}</td></tr>
                        @empty
                            <tr><td colspan="3">Aucun paiement sur la période.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($finances['ecole'])
                @php $e = $finances['ecole']; @endphp
                <div>
                    <h3>{{ $cycle ? 'Recettes et dépenses du cycle' : 'Recettes et dépenses de l\'établissement' }}</h3>
                    <table>
                        <tbody>
                            <tr><td>Scolarité encaissée</td><td class="n">{{ $fcfa($f['encaisse']) }}</td></tr>
                            @foreach($e['recettes'] as $r)
                                <tr><td>+ {{ $r['categorie'] }}</td><td class="n">{{ $fcfa($r['montant']) }}</td></tr>
                            @endforeach
                            @foreach($e['depenses'] as $d)
                                <tr><td>− {{ $d['categorie'] }}</td><td class="n" style="color:#a8503c;">{{ $fcfa($d['montant']) }}</td></tr>
                            @endforeach
                            <tr class="total"><td>Solde de la période</td><td class="n">{{ $fcfa($e['solde']) }}</td></tr>
                            <tr><td>dont salaires versés (bulletins de paie)</td><td class="n">{{ $fcfa($e['salaires']) }}</td></tr>
                        </tbody>
                    </table>
                    <p style="font-size:12px; color:#666;">Recettes et dépenses de la Comptabilité. Les salaires versés y sont ajoutés automatiquement depuis la Paie du personnel : le solde en tient compte.</p>
                </div>
            @endif
        </div>
    @endif

    <div class="signature">
        <div style="text-align:center; width:70mm;">
            Fait à {{ $config->ville ?: "N'Djamena" }}, le {{ now()->format('d/m/Y') }}
            <div style="margin-top:22mm; border-top:1px solid #20222b; padding-top:4px; font-weight:bold;">{{ $general ? 'Le Directeur' : ($pedagogie ? 'Le Responsable pédagogique' : 'Le Comptable') }}</div>
        </div>
    </div>
</div>
@endsection
