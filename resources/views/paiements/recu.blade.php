<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Reçu {{ $paiement->numero_recu }}</title>
<style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{ font-family: Verdana, Geneva, sans-serif; background:#f0f0f0; padding:16px; color:#0d2b4e; }

    .page{
        width:720px;
        margin:0 auto;
        background:#fff;
        border:1px solid #0d4a8f;
        border-radius:8px;
        padding:14px 18px;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; color-adjust:exact;
    }

    .recu{
        border:1.5px solid #0d4a8f;
        border-radius:7px;
        padding:10px 14px;
        position:relative;
        background:#fff;
    }
    .recu + .recu{ margin-top:10px; }
    .recu::before{
        content:"";
        position:absolute; top:0; left:0; right:0; height:4px;
        background:#0d4a8f;
        border-radius:5px 5px 0 0;
    }
    .banniere{ margin-bottom:6px; }
    .banniere img{ width:100%; height:auto; display:block; border-radius:5px; }
    .banniere .school{ font-weight:bold; font-size:14px; color:#0d4a8f; text-align:center; }
    .banniere .addr{ font-size:9px; color:#5a6b80; text-align:center; }

    .head{ display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #0d4a8f; padding-bottom:6px; margin-bottom:8px; }
    .head .titre{ text-align:right; }
    .head .titre .t{ font-size:13px; font-weight:bold; color:#0d4a8f; }
    .head .titre .n{ font-size:10px; font-family:monospace; color:#5a6b80; }
    .copie-tag{
        display:inline-block; background:#eaf2fb; color:#0d4a8f; border:1px solid #0d4a8f;
        font-size:8px; font-weight:bold; padding:2px 10px; border-radius:100px;
    }
    .info-grid{ display:grid; grid-template-columns:1fr 1fr 1fr; gap:3px 14px; margin-bottom:8px; font-size:10.5px; }
    .info-grid b{ color:#5a6b80; font-size:8px; text-transform:uppercase; display:block; }
    .montant-box{
        background:#0d4a8f; color:#fff; border-radius:6px; padding:7px 12px;
        display:flex; justify-content:space-between; align-items:center; margin-bottom:7px;
    }
    .montant-box .lbl{ font-size:9px; text-transform:uppercase; color:#eaf2fb; }
    .montant-box .val{ font-size:17px; font-weight:bold; font-family:monospace; }
    .lettres{
        font-style:italic; font-size:10px; border:1px dashed #0d4a8f; border-radius:5px;
        padding:5px 10px; margin-bottom:8px; background:#eaf2fb;
        color:#0d2b4e;
    }
    table.recap{ width:100%; border-collapse:collapse; font-size:10px; margin-bottom:8px; }
    table.recap td{ padding:3px 6px; border-bottom:1px solid #cfe0f2; }
    table.recap td:first-child{ color:#5a6b80; }
    table.recap td:last-child{ text-align:right; font-weight:bold; font-family:monospace; color:#0d2b4e; }
    .note{ font-size:9.5px; color:#5a6b80; margin-bottom:6px; }
    .foot{ display:flex; justify-content:space-between; align-items:flex-end; margin-top:8px; padding-top:6px; border-top:1px solid #cfe0f2; font-size:9px; color:#5a6b80; }
    .foot div{ text-align:center; width:110px; }
    .foot .line{ border-top:1px solid #0d2b4e; margin-top:16px; padding-top:2px; color:#0d2b4e; font-weight:600; }
    .foot .qr-zone{ text-align:center; width:auto; }
    .foot .qr-zone img{ width:42px; height:42px; }
    .foot .qr-zone span{ font-size:7px; display:block; margin-top:1px; }

    .print-btn{ text-align:center; margin-bottom:14px; }
    .print-btn button{ background:#0d4a8f; color:#fff; border:none; padding:9px 22px; border-radius:6px; cursor:pointer; font-size:13px; }

    @media print {
        @page { size:A4; margin:8mm; }
        * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; color-adjust:exact !important; }
        body{ background:#fff; padding:0; }
        .print-btn{ display:none; }
        .page{ width:100%; border:none; padding:0; }
        .recu{ page-break-inside:avoid; }
    }
</style>
</head>
<body>

<div class="print-btn">
    <button onclick="window.print()">{{ count($exemplaires) > 1 ? 'Imprimer le reçu (2 exemplaires sur une page)' : 'Imprimer mon reçu' }}</button>
    @auth
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : auth()->user()->urlAccueil() }}" style="margin-left:12px;">Retour</a>
        <a href="{{ auth()->user()->urlAccueil() }}" style="margin-left:12px;">🏠 Accueil</a>
    @endauth
</div>

<div class="page">
@foreach ($exemplaires as $copie)
<div class="recu">
    <div class="banniere">
        @if(\App\Support\Etablissement::banniere())
            <img src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ \App\Support\Etablissement::nom() }}">
        @else
            <div class="school">{{ \App\Support\Etablissement::nom() }}</div>
            <div class="addr">{{ \App\Support\Etablissement::adresse() }}</div>
        @endif
    </div>

    <div class="head">
        <div class="copie-tag">Exemplaire {{ $copie }}</div>
        <div class="titre">
            <div class="t">Reçu de paiement</div>
            <div class="n">N° {{ $paiement->numero_recu }}</div>
        </div>
    </div>

    <div class="info-grid">
        <div><b>Élève</b>{{ $paiement->eleve->nom }} {{ $paiement->eleve->prenom }}</div>
        <div><b>Classe</b>{{ $paiement->eleve->classe->nom ?? '—' }}</div>
        <div><b>Année scolaire</b>{{ $paiement->annee_scolaire }}</div>
        <div><b>Date de paiement</b>{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</div>
        <div><b>Mode de paiement</b>{{ $paiement->libelle_mode_paiement }}</div>
        <div><b>Type(s)</b>{{ $paiement->libelle_types }}</div>
    </div>

    <div class="montant-box">
        <span class="lbl">Montant reçu</span>
        <span class="val">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</span>
    </div>

    <div class="lettres">
        Arrêté le présent reçu à la somme de : <strong>{{ $montantEnLettres }}</strong>.
    </div>

    <table class="recap">
        <tr><td>Frais annuel (cycle)</td><td>{{ number_format($montantAnnuel, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Total versé à ce jour</td><td>{{ number_format($verseTotal, 0, ',', ' ') }} FCFA</td></tr>
        <tr><td>Restant à payer</td><td>{{ $paiement->eleve->exonere ? 'Exonéré(e)' : number_format($restant, 0, ',', ' ') . ' FCFA' }}</td></tr>
    </table>

    @if ($corrigeLe)
        <p class="note">Reçu corrigé le {{ \Carbon\Carbon::parse($corrigeLe)->format('d/m/Y') }} (montants ci-dessus à jour).</p>
    @endif
    @if ($paiement->description)
        <p class="note">Note : {{ $paiement->description }}</p>
    @endif

    <div class="foot">
        @if (class_exists(\App\Helpers\QrAuthenticite::class) && \Illuminate\Support\Facades\Route::has('verification.recu'))
            <div class="qr-zone">
                <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.recu', $paiement->numero_recu), 70) }}" alt="QR authenticité">
                <span>Vérifier l'authenticité</span>
            </div>
        @else
            <div></div>
        @endif
        <div style="width:auto; min-width:150px;">Le / La Comptable<div class="line">{{ $nomComptable ?: 'Signature' }}</div><span style="font-size:8px;">Signature &amp; Cachet</span></div>
    </div>
</div>
@endforeach
</div>

</body>
</html>
