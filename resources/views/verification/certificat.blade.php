<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification d'authenticité — Certificat d'inscription</title>
<style>
    *{box-sizing:border-box; margin:0; padding:0;}
    body{ font-family:Arial, sans-serif; background:#e9e4d8; padding:30px 16px; color:#20222b; }
    .sheet{ max-width:480px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 6px 30px rgba(20,28,51,0.18); padding:28px 26px; text-align:center; }
    .logo{ width:56px; height:56px; border-radius:50%; margin:0 auto 12px; object-fit:cover; }
    .ok{ display:inline-flex; align-items:center; gap:6px; background:#e2ede6; color:#4c7a63; font-weight:bold; font-size:13px; padding:6px 14px; border-radius:100px; margin-bottom:16px; }
    .ko{ display:inline-flex; align-items:center; gap:6px; background:#fbe6df; color:#a8503c; font-weight:bold; font-size:13px; padding:6px 14px; border-radius:100px; margin-bottom:16px; }
    h1{ font-size:17px; color:#141c33; margin-bottom:4px; }
    .sub{ font-size:12px; color:#5b5d6b; margin-bottom:20px; }
    table{ width:100%; font-size:13px; }
    td{ padding:7px 4px; border-bottom:1px solid #e3ddcf; text-align:left; }
    td:first-child{ color:#5b5d6b; }
    td:last-child{ text-align:right; font-weight:bold; }
    .note{ font-size:10.5px; color:#999; margin-top:18px; }
</style>
</head>
<body>
<div class="sheet">
    @if(\App\Support\Etablissement::banniere())
        <img src="{{ \App\Support\Etablissement::banniere() }}" alt="Logo" class="logo">
    @endif

    @if($certificat)
        <div class="ok">✓ Document authentique</div>
        <h1>{{ \App\Support\Etablissement::nom() }}</h1>
        <div class="sub">Certificat d'inscription N° {{ $numero }}</div>

        <table>
            <tr><td>Élève</td><td>{{ $certificat->eleve->nom ?? '—' }} {{ $certificat->eleve->prenom ?? '' }}</td></tr>
            <tr><td>Classe</td><td>{{ $certificat->classe_nom ?? '—' }}</td></tr>
            <tr><td>Année scolaire</td><td>{{ $certificat->annee_scolaire }}</td></tr>
            <tr><td>Délivré le</td><td>{{ $certificat->date_delivrance->format('d/m/Y') }}</td></tr>
        </table>

        <div class="note">
            Cette page confirme que ce certificat a bien été délivré par le {{ \App\Support\Etablissement::nom() }}.
            Vérification effectuée le {{ now()->format('d/m/Y à H:i') }}.
        </div>
    @else
        <div class="ko">✕ Certificat introuvable ou annulé</div>
        <h1>{{ \App\Support\Etablissement::nom() }}</h1>
        <div class="sub">Aucun certificat valide ne correspond au numéro {{ $numero }}.</div>
        <div class="note">Ce document n'a pas pu être vérifié. Contactez l'administration de l'établissement.</div>
    @endif
</div>
</body>
</html>
