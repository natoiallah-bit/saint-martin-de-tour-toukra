{{-- Lettre de convocation au conseil de discipline, A4, à remettre au parent --}}
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Convocation — {{ $dossier->eleve->nom }}</title>
<style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{ font-family:Arial, Helvetica, sans-serif; background:#ece8df; color:#000; }
    .barre{ display:flex; gap:12px; align-items:center; padding:14px 20px; background:#141c33; color:#fff; }
    .barre h1{ font-size:16px; margin-right:auto; }
    .barre button{ background:#c9973b; border:0; border-radius:6px; padding:8px 14px; font-weight:700; cursor:pointer; }
    .barre a{ color:#fff; font-weight:700; font-size:13px; }
    .lettre{ width:210mm; min-height:297mm; margin:16px auto; background:#fff; padding:16mm 18mm; font-size:14px; line-height:1.7; }
    .entete{ text-align:center; font-size:11px; font-weight:bold; line-height:1.5; margin-bottom:4mm; }
    .logo{ width:100%; max-height:32mm; object-fit:contain; display:block; margin-bottom:6mm; }
    .objet{ font-weight:bold; margin:8mm 0 6mm; font-size:15px; }
    .encadre{ border:2px solid #000; padding:8px 12px; margin:5mm 0; }
    .signature{ text-align:right; margin-top:14mm; }
    @media print{ body{ background:#fff; } .barre{ display:none; } .lettre{ margin:0; } @page{ size:A4; margin:0; } }
</style>
</head>
<body>
    <div class="barre">
        <h1>Convocation au conseil de discipline</h1>
        <button onclick="window.print()">🖨️ Imprimer</button>
        <a href="{{ route('discipline.show', $dossier) }}">Retour</a>
    </div>
    <div class="lettre">
        <div class="entete">RÉPUBLIQUE DU TCHAD<br><i style="font-weight:normal;">Unité – Travail – Progrès</i></div>
        @if(\App\Support\Etablissement::banniere()) <img class="logo" src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ $config->nom_ecole }}">
        @else <div style="text-align:center; font-weight:bold; font-size:18px; margin-bottom:6mm;">{{ $config->nom_ecole }}</div> @endif

        <p style="text-align:right;">{{ $config->ville ?: "N'Djamena" }}, le {{ now()->format('d/m/Y') }}</p>
        <p style="margin-top:6mm;">Aux parents de l'élève <b>{{ $dossier->eleve->nom }} {{ $dossier->eleve->prenom }}</b>, classe de <b>{{ $dossier->eleve->classe->nom ?? '—' }}</b></p>

        <p class="objet"><u>Objet</u> : Convocation au conseil de discipline</p>

        <p>Madame, Monsieur,</p>
        <p>Nous avons le regret de vous informer que votre enfant est traduit devant le conseil de discipline de l'établissement pour le motif suivant :</p>
        <div class="encadre"><b>{{ $dossier->motif }}</b>@if($dossier->date_faits) (faits du {{ $dossier->date_faits->format('d/m/Y') }})@endif</div>
        <p>
            Vous êtes prié(e) d'accompagner votre enfant à la séance du conseil
            @if($dossier->date_conseil) le <b>{{ $dossier->date_conseil->format('d/m/Y') }} à {{ $dossier->date_conseil->format('H:i') }}</b> @else à une date qui vous sera communiquée @endif
            @if($dossier->lieu) ({{ $dossier->lieu }}) @endif.
        </p>
        <p>Votre présence est indispensable. Veuillez agréer, Madame, Monsieur, l'expression de nos salutations distinguées.</p>

        <div class="signature">La Direction<br><br><br><b>Le Directeur</b></div>

        <div style="margin-top:16mm; border-top:1px dashed #666; padding-top:4mm; font-size:12px;">
            <b>Coupon à retourner signé</b> — Je soussigné(e) ………………………………… parent de {{ $dossier->eleve->prenom }} {{ $dossier->eleve->nom }},
            reconnais avoir reçu la convocation au conseil de discipline. Date : ……/……/………… Signature :
        </div>
    </div>
</body>
</html>
