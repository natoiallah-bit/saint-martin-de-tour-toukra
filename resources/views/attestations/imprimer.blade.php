{{-- Certificat de travail, A4, signée par le Directeur, vérifiable par QR code --}}
@php
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $dateLongue = fn ($d) => $d->day . ($d->day === 1 ? 'er' : '') . ' ' . $mois[$d->month] . ' ' . $d->year;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Certificat de travail — {{ $personnel->nom }} {{ $personnel->prenom }}</title>
<style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{ font-family:Georgia, 'Times New Roman', serif; background:#ece8df; color:#000; }
    .barre{ display:flex; gap:12px; align-items:center; padding:14px 20px; background:#141c33; color:#fff; font-family:Arial, sans-serif; }
    .barre h1{ font-size:16px; margin-right:auto; }
    .barre button{ background:#c9973b; border:0; border-radius:6px; padding:8px 14px; font-weight:700; cursor:pointer; }
    .barre a{ color:#fff; font-weight:700; font-size:13px; }
    .succes{ max-width:210mm; margin:12px auto 0; background:#e2ede6; color:#2f5f45; padding:10px 14px; border-radius:6px; font-family:Arial, sans-serif; font-weight:700; }
    .feuille{ width:210mm; min-height:297mm; margin:16px auto; background:#fff; padding:14mm 18mm; position:relative; }
    .cadre{ border:3px double #141c33; min-height:265mm; padding:10mm 12mm; display:flex; flex-direction:column; }
    .entete{ display:flex; justify-content:space-between; font-family:Arial, sans-serif; font-size:11px; font-weight:bold; line-height:1.5; }
    .entete .droite{ text-align:center; }
    .logo{ width:100%; max-height:30mm; object-fit:contain; display:block; margin:6mm 0 4mm; }
    .nom-ecole{ text-align:center; font-weight:bold; font-size:20px; margin:6mm 0 4mm; }
    .titre{ text-align:center; margin:8mm 0 2mm; font-size:26px; font-weight:bold; letter-spacing:3px; color:#141c33; text-decoration:underline; }
    .numero{ text-align:center; font-family:monospace; font-size:13px; margin-bottom:10mm; }
    .corps{ font-size:16px; line-height:2.1; text-align:justify; }
    .corps b{ font-size:17px; }
    .pied{ margin-top:auto; display:flex; justify-content:space-between; align-items:flex-end; padding-top:10mm; }
    .qr{ text-align:center; font-family:Arial, sans-serif; font-size:9px; color:#444; }
    .qr img{ width:28mm; height:28mm; display:block; }
    .signature{ text-align:center; font-size:15px; line-height:1.6; min-width:70mm; }
    .signature .espace{ height:24mm; }
    @media print{ body{ background:#fff; } .barre, .succes{ display:none; } .feuille{ margin:0; } @page{ size:A4; margin:0; } }
</style>
</head>
<body>
    <div class="barre">
        <h1>Certificat de travail N° {{ $attestation->numero }}</h1>
        <button onclick="window.print()">🖨️ Imprimer</button>
        <a href="{{ route('personnels.show', $personnel) }}">Retour</a>
        <a href="{{ auth()->user()->urlAccueil() }}">🏠 Accueil</a>
    </div>
    @if(session('success')) <div class="succes">✓ {{ session('success') }}</div> @endif

    <div class="feuille"><div class="cadre">
        <div class="entete">
            <div>MINISTÈRE DE L'ÉDUCATION NATIONALE<br>ET DE LA PROMOTION CIVIQUE @if($config->inspection)<br>{{ $config->inspection }} @endif</div>
            <div class="droite">RÉPUBLIQUE DU TCHAD<br><i style="font-weight:normal;">Unité – Travail – Progrès</i></div>
        </div>

        @if(\App\Support\Etablissement::banniere())
            <img class="logo" src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ $config->nom_ecole }}">
        @else
            <div class="nom-ecole">{{ $config->nom_ecole }}</div>
        @endif

        <div class="titre">CERTIFICAT DE TRAVAIL</div>
        <div class="numero">N° {{ $attestation->numero }}</div>

        <div class="corps">
            <p>
                Je soussigné(e), <b>{{ $config->nom_directeur ?: '……………………………………' }}</b>, Directeur du {{ $config->nom_ecole }},
                certifie que <b>{{ $personnel->nom }} {{ $personnel->prenom }}</b>, matricule <b>{{ $personnel->matricule }}</b>,
                @if($attestation->en_poste)
                    est employé(e) dans notre établissement @if($attestation->date_embauche) depuis le <b>{{ $dateLongue($attestation->date_embauche) }}</b>@endif
                    en qualité de <b>{{ $attestation->fonction }}</b>@if($attestation->libelle_statut) ({{ mb_strtolower($attestation->libelle_statut) }})@endif, et y exerce à ce jour.
                @else
                    a été employé(e) dans notre établissement @if($attestation->date_embauche) à compter du <b>{{ $dateLongue($attestation->date_embauche) }}</b>@endif
                    en qualité de <b>{{ $attestation->fonction }}</b>@if($attestation->libelle_statut) ({{ mb_strtolower($attestation->libelle_statut) }})@endif.
                @endif
            </p>
            <p>En foi de quoi, le présent certificat lui est délivré pour servir et valoir ce que de droit.</p>
        </div>

        <div class="pied">
            <div class="qr">
                <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.attestation', $attestation->numero)) }}" alt="QR authenticité">
                Scannez pour vérifier
            </div>
            <div class="signature">
                Fait à {{ $config->ville ?: "N'Djamena" }}, le {{ $dateLongue($attestation->date_delivrance) }}
                <div style="font-weight:bold; margin-top:3mm;">Le Directeur</div>
                <div class="espace"></div>
                <b>{{ $config->nom_directeur }}</b>
            </div>
        </div>
    </div></div>
</body>
</html>
