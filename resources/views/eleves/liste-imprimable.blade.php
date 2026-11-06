@extends('layouts.app')
@section('title', 'Liste des élèves — Impression')

@section('content')
<style>
    .liste-eleves{ max-width:950px; margin:0 auto; background:#fff; padding:20px 26px; border-radius:6px; box-shadow:0 4px 24px rgba(0,0,0,0.15); color:#0d2b4e; }
    .liste-eleves .entete{ border-top:2px solid #0d4a8f; border-bottom:2px solid #0d4a8f; padding:4px 0; margin-bottom:12px; }
    .liste-eleves .entete img{ width:100%; height:auto; display:block; }
    .liste-eleves .titre{ text-align:center; background:#eaf2fb; border:1px solid #0d4a8f; padding:6px; margin-bottom:18px; font-weight:bold; color:#0d4a8f; font-size:14px; }
    .liste-eleves .classe-bloc{ margin-bottom:22px; page-break-inside:avoid; }
    .liste-eleves .classe-titre{ background:#0d4a8f; color:#fff; padding:5px 10px; font-weight:bold; font-size:13px; }
    .liste-eleves table{ width:100%; border-collapse:collapse; font-size:12px; }
    .liste-eleves table, .liste-eleves th, .liste-eleves td{ border:1px solid #0d4a8f; }
    .liste-eleves thead th{ background:#f1f6fc; padding:5px; text-align:left; }
    .liste-eleves tbody td{ padding:4px 6px; }
    .liste-eleves tbody td:first-child{ text-align:center; width:40px; }
    .liste-eleves .effectif{ font-weight:normal; font-size:11px; }
    .liste-eleves .no-print{ text-align:center; margin:16px 0; }
    @media print {
        @page{ size:A4; margin:10mm; }
        * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; color-adjust:exact !important; }
        .liste-eleves{ box-shadow:none; max-width:100%; }
        .no-print{ display:none !important; }
        .liste-eleves .classe-bloc{ page-break-after:always; break-after:page; }
        .liste-eleves .classe-bloc:last-of-type{ page-break-after:auto; break-after:auto; }
    }
</style>

<div class="liste-eleves">
    <div class="titre">
        LISTE DES ÉLÈVES PAR CLASSE
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimer la liste</button>
        <a href="{{ route('eleves.index') }}" class="btn">← Retour</a>
    </div>

    @forelse($classes as $classe)
        <div class="classe-bloc">
            {{-- Le logo est répété au début de chaque classe pour qu'il
                 apparaisse sur chaque page imprimée (une classe = une page,
                 voir le saut de page dans le CSS ci-dessus). --}}
            <div class="entete">
                @if(\App\Support\Etablissement::banniere())
                    <img src="{{ \App\Support\Etablissement::banniere() }}" alt="Logo">
                @else
                    <div style="text-align:center; font-weight:bold; font-size:14px; color:#0d4a8f; padding:6px 0;">
                        {{ \App\Support\Etablissement::nom() }}
                    </div>
                @endif
            </div>
            <div class="classe-titre">
                {{ $classe->cycle->nom ?? '' }} — {{ $classe->nom }}
                <span class="effectif">({{ $classe->eleves->count() }} élève{{ $classe->eleves->count() > 1 ? 's' : '' }})</span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Matricule</th>
                        <th>Nom</th>
                        <th>Prénom</th>
                        <th>Sexe</th>
                        <th>Date de naissance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classe->eleves as $i => $eleve)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $eleve->matricule }}</td>
                            <td>{{ $eleve->nom }}</td>
                            <td>{{ $eleve->prenom }}</td>
                            <td>{{ $eleve->sexe ?? '—' }}</td>
                            <td>{{ $eleve->date_naissance ? \Carbon\Carbon::parse($eleve->date_naissance)->format('d/m/Y') : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6">Aucun élève dans cette classe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align:center;">Aucune classe trouvée.</p>
    @endforelse

    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimer la liste</button>
    </div>
</div>
@endsection
