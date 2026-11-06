@extends('layouts.app')
@section('title', 'Fiche de passage')

@section('content')
<style>
    .fiche-passage{ max-width:900px; margin:0 auto; background:#fff; padding:20px 26px; border-radius:6px; box-shadow:0 4px 24px rgba(0,0,0,0.15); color:#0d2b4e; }
    .fiche-passage .entete{ border-top:2px solid #0d4a8f; border-bottom:2px solid #0d4a8f; padding:4px 0; margin-bottom:12px; }
    .fiche-passage .entete img{ width:100%; height:auto; display:block; }
    .fiche-passage .titre{ text-align:center; background:#eaf2fb; border:1px solid #0d4a8f; padding:6px; margin-bottom:14px; font-weight:bold; color:#0d4a8f; font-size:13px; }
    .fiche-passage table{ width:100%; border-collapse:collapse; font-size:11px; }
    .fiche-passage table, .fiche-passage th, .fiche-passage td{ border:1px solid #0d4a8f; }
    .fiche-passage thead th{ background:#0d4a8f; color:#fff; padding:5px; text-align:center; }
    .fiche-passage tbody td{ padding:5px; text-align:center; }
    .fiche-passage tbody td:nth-child(2){ text-align:left; }
    .fiche-passage .decision-admis{ color:#2e7d4f; font-weight:bold; }
    .fiche-passage .decision-redouble{ color:#a8503c; font-weight:bold; }
    .fiche-passage .no-print{ text-align:center; margin-top:16px; }
    @media print {
        @page{ size:A4 landscape; margin:8mm; }
        * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; color-adjust:exact !important; }
        .fiche-passage{ box-shadow:none; max-width:100%; }
        .no-print{ display:none !important; }
    }
</style>

<div class="fiche-passage">
    <div class="entete">
        @if(\App\Support\Etablissement::banniere())
            <img src="{{ \App\Support\Etablissement::banniere() }}" alt="Logo">
        @else
            <div style="text-align:center; font-weight:bold; font-size:14px; color:#0d4a8f; padding:6px 0;">
                {{ \App\Support\Etablissement::nom() }}
            </div>
        @endif
    </div>

    <div class="titre">
        FICHE DE PASSAGE — {{ $classe->cycle->nom }} {{ $classe->nom }} — ANNÉE SCOLAIRE {{ $anneeScolaire ?? '—' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Rang</th>
                <th>Élève</th>
                <th>Moy. T1</th>
                <th>Moy. T2</th>
                <th>Moy. T3</th>
                <th>Moy. annuelle</th>
                <th>Décision</th>
            </tr>
        </thead>
        <tbody>
            @forelse($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['rang'] ?? '—' }}</td>
                    <td>{{ $ligne['eleve']->nom }} {{ $ligne['eleve']->prenom }}</td>
                    <td>{{ $ligne['moyenneT1'] ?? '—' }}</td>
                    <td>{{ $ligne['moyenneT2'] ?? '—' }}</td>
                    <td>{{ $ligne['moyenneT3'] ?? '—' }}</td>
                    <td>{{ $ligne['moyenneAnnuelle'] ?? '—' }}</td>
                    <td class="{{ $ligne['decision'] === 'Admis(e) en classe supérieure' ? 'decision-admis' : 'decision-redouble' }}">
                        {{ $ligne['decision'] ?? '—' }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun élève dans cette classe.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimer la fiche de passage</button>
    </div>
</div>
@endsection
