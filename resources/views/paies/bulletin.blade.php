@extends('layouts.app')
@section('title', 'Bulletin de paie')

@section('content')
<style>
    .paie-sheet, .paie-sheet * { font-family: Verdana, Geneva, sans-serif; }

    .paie-sheet{
        max-width:620px; margin:0 auto; background:#fff;
        padding:16px 22px; border-radius:6px; box-shadow:0 4px 24px rgba(0,0,0,0.15);
        color:#0d2b4e;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; color-adjust:exact;
    }

    .paie-sheet .entete{
        border-top:2px solid #0d4a8f; border-bottom:2px solid #0d4a8f;
        padding:4px 0; margin-bottom:12px;
    }
    .paie-sheet .entete img{ width:100%; height:auto; display:block; }
    .paie-sheet .entete .nom-ecole{ text-align:center; font-weight:bold; font-size:14px; color:#0d4a8f; padding:6px 0; }
    .paie-sheet .entete .adresse{ font-size:9px; font-weight:normal; color:#5a6b80; }

    .paie-sheet .titre-bulletin{
        text-align:center; background:#eaf2fb; border:1px solid #0d4a8f;
        padding:6px; margin-bottom:12px; font-weight:bold; color:#0d4a8f; font-size:13px;
    }

    .paie-sheet .infos-grid{
        display:grid; grid-template-columns:1fr 1fr; gap:4px 20px; margin-bottom:14px; font-size:12px;
    }

    .paie-sheet table.recap{ width:100%; border-collapse:collapse; font-size:12px; margin-bottom:14px; }
    .paie-sheet table.recap td{ padding:7px 8px; border-bottom:1px solid #d9e6f5; }
    .paie-sheet table.recap td:last-child{ text-align:right; font-family:monospace; font-weight:bold; }
    .paie-sheet table.recap .negatif{ color:#a8503c; }

    .paie-sheet .net-a-payer{
        text-align:center; background:#0d4a8f; color:#fff; padding:12px; border-radius:6px;
        font-size:19px; font-weight:bold; margin-bottom:16px;
    }

    .paie-sheet .pied{
        display:flex; justify-content:space-between; align-items:flex-end; margin-top:16px; font-size:11px; gap:10px;
    }
    .paie-sheet .pied .qr-bloc{ margin-top:8px; text-align:center; }
    .paie-sheet .pied .qr-bloc img{ width:52px; height:52px; }
    .paie-sheet .pied .qr-bloc span{ font-size:7px; color:#5a6b80; display:block; margin-top:1px; }
    .paie-sheet .pied .signature{ text-align:center; width:150px; flex-shrink:0; }
    .paie-sheet .pied .signature .ligne{
        margin-top:24px; border-top:1px solid #0d2b4e; padding-top:3px; font-weight:bold;
    }

    .paie-sheet .no-print{ text-align:center; margin-top:14px; }

    @media print {
        @page{ size:A4; margin:8mm; }
        * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; color-adjust:exact !important; }
        .paie-sheet{ box-shadow:none; max-width:100%; padding:0; }
        .no-print{ display:none !important; }
    }
</style>

<div class="paie-sheet">

    <div class="entete">
        @if(\App\Support\Etablissement::banniere())
            <img src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ \App\Support\Etablissement::nom() }}">
        @else
            <div class="nom-ecole">
                {{ \App\Support\Etablissement::nom() }}<br>
                <span class="adresse">{{ \App\Support\Etablissement::adresse() }}</span>
            </div>
        @endif
    </div>

    <div class="titre-bulletin">
        BULLETIN DE PAIE — {{ $paie->mois_libelle }}
    </div>

    <div class="infos-grid">
        <div><b>Nom :</b> {{ $paie->personnel->nom }} {{ $paie->personnel->prenom }}</div>
        <div><b>Matricule :</b> {{ $paie->personnel->matricule }}</div>
        <div><b>Fonction :</b> {{ $paie->personnel->fonction ?? $paie->personnel->libelle_type }}</div>
        <div><b>Type :</b> {{ $paie->personnel->libelle_type }}</div>
    </div>

    <table class="recap">
        @if ($paie->nombre_heures)
            <tr>
                <td>Salaire ({{ rtrim(rtrim(number_format($paie->nombre_heures, 2, ',', ' '), '0'), ',') }} h × {{ number_format($paie->taux_horaire, 0, ',', ' ') }} FCFA/h)</td>
                <td>{{ number_format($paie->salaire_base, 0, ',', ' ') }} FCFA</td>
            </tr>
        @else
            <tr><td>Salaire de base</td><td>{{ number_format($paie->salaire_base, 0, ',', ' ') }} FCFA</td></tr>
        @endif
        <tr><td>Primes</td><td>+ {{ number_format($paie->primes, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Acompte déjà versé</td><td class="negatif">− {{ number_format($paie->acompte, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Retenues</td><td class="negatif">− {{ number_format($paie->retenues, 0, ',', ' ') }} FCFA</td></tr>
    </table>

    <div class="net-a-payer">
        NET À PAYER : {{ number_format($paie->net_a_payer, 0, ',', ' ') }} FCFA
    </div>

    <div class="pied">
        <div>
            Fait à N'Djamena, le {{ now()->format('d/m/Y') }}
            @if (class_exists(\App\Helpers\QrAuthenticite::class) && \Illuminate\Support\Facades\Route::has('verification.paie'))
                <div class="qr-bloc">
                    <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.paie', $paie->id), 80) }}" alt="QR authenticité">
                    <span>Scanner pour vérifier l'authenticité</span>
                </div>
            @endif
        </div>
        <div class="signature">
            <div class="ligne">Le/la bénéficiaire</div>
        </div>
        <div class="signature">
            <div class="ligne">LA RAF</div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimer ce bulletin</button>
    </div>
</div>
@endsection
