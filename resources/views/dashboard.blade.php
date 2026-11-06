@extends('layouts.app')
@section('title', 'Tableau de bord')

@section('content')
<style>
  .stat-grid{ display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:16px; margin-bottom:24px; }
  .stat-card{ background:#fff; border-radius:12px; padding:18px; border:1px solid #e3ddcf; }
  .stat-card .lbl{ font-size:10.5px; text-transform:uppercase; letter-spacing:0.4px; color:#5a5a5a; font-weight:700; }
  .stat-card .val{ font-family:'Fraunces', serif; font-size:26px; font-weight:600; color:#141c33; margin-top:6px; }
  .c1{ border-left:4px solid #243365; } .c2{ border-left:4px solid #4c7a63; }
  .c3{ border-left:4px solid #c9973b; } .c4{ border-left:4px solid #a8503c; }

  .banniere{
    width:100%; height:200px; border-radius:14px; margin-bottom:24px;
    background:linear-gradient(155deg, #141c33 0%, #334581 60%, #0d1830 100%);
    display:flex; align-items:center; justify-content:center;
    border:2px dashed #c9973b; overflow:hidden;
  }
  .banniere img{ width:100%; height:100%; object-fit:cover; display:block; }
  .banniere .placeholder{ color:#c9973b; font-weight:700; font-size:13px; text-align:center; padding:20px; }

  .financier{ display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px; }
  @media (max-width:1000px){ .financier{ grid-template-columns:1fr; } }
  .fin-card{ border-radius:10px; padding:16px; }
  .fin-card .lbl{ font-size:10.5px; text-transform:uppercase; letter-spacing:0.4px; font-weight:700; }
  .fin-card .val{ font-family:'Fraunces', serif; font-size:19px; font-weight:600; margin-top:4px; }
</style>

<h1>Tableau de bord</h1>

@if(auth()->user()->peut('rapport_pedagogique') || auth()->user()->peut('rapport_financier'))
    <a href="{{ route('rapports.index') }}" class="btn" style="display:inline-flex; align-items:center; gap:8px; padding:12px 20px; font-size:15px; margin:-6px 0 22px;">
        📊 Rapport trimestriel / annuel
    </a>
@endif

<div class="banniere">
    @if(\App\Support\Etablissement::photo())
        <img src="{{ \App\Support\Etablissement::photo() }}" alt="Photo du complexe">
    @else
        <div class="placeholder">📷 Emplacement photo du complexe<br><span style="font-weight:400; font-size:11px;">(ajoutez la photo de l'école dans Paramètres)</span></div>
    @endif
</div>

<div class="stat-grid">
    <div class="stat-card c1">
        <div class="lbl">Élèves</div>
        <div class="val">{{ $totalEleves }}</div>
    </div>
    <div class="stat-card c2">
        <div class="lbl">Classes</div>
        <div class="val">{{ $totalClasses }}</div>
    </div>
    <div class="stat-card c3">
        <div class="lbl">Enseignants</div>
        <div class="val">{{ $totalEnseignants }}</div>
    </div>
    @droit('scolarite')
    <div class="stat-card c4">
        <div class="lbl">Taux de recouvrement</div>
        <div class="val">{{ $tauxRecouvrement }}%</div>
    </div>
    @enddroit
</div>

@if(auth()->user()->peut('scolarite') && auth()->user()->peut('comptabilite'))
<div class="financier">
    <div class="fin-card" style="background:#e2ede6;">
        <div class="lbl" style="color:#4c7a63;">Scolarité encaissée</div>
        <div class="val">{{ number_format($totalScolarite, 0, ',', ' ') }} FCFA</div>
    </div>
    <div class="fin-card" style="background:#fbe6df;">
        <div class="lbl" style="color:#a8503c;">Dépenses totales</div>
        <div class="val">{{ number_format($totalDepenses, 0, ',', ' ') }} FCFA</div>
    </div>
    <div class="fin-card" style="background:#141c33; color:#fff;">
        <div class="lbl" style="color:#c9973b;">Solde</div>
        <div class="val" style="color:#fff;">{{ number_format($solde, 0, ',', ' ') }} FCFA</div>
    </div>
</div>
@endif

<h3 style="margin-bottom:10px;">Effectifs par classe</h3>
<table>
    <thead><tr><th>Cycle</th><th>Classe</th><th>Effectif</th></tr></thead>
    <tbody>
        @forelse($effectifsParClasse as $classe)
            <tr>
                <td>{{ $classe->cycle->nom ?? '—' }}</td>
                <td>{{ $classe->nom }}</td>
                <td>{{ $classe->eleves_count }}</td>
            </tr>
        @empty
            <tr><td colspan="3">Aucune classe enregistrée.</td></tr>
        @endforelse
    </tbody>
</table>
@endsection
