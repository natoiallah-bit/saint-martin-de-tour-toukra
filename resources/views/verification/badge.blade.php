<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification d'authenticité — Badge du personnel</title>
<style>
    *{box-sizing:border-box; margin:0; padding:0;}
    body{ font-family:Arial, sans-serif; background:#e9e4d8; padding:30px 16px; color:#20222b; }
    .sheet{ max-width:480px; margin:0 auto; background:#fff; border-radius:10px; box-shadow:0 6px 30px rgba(20,28,51,0.18); padding:28px 26px; text-align:center; }
    .logo{ width:56px; height:56px; border-radius:50%; margin:0 auto 12px; object-fit:cover; }
    .ok{ display:inline-flex; align-items:center; gap:6px; background:#e2ede6; color:#4c7a63; font-weight:bold; font-size:13px; padding:6px 14px; border-radius:100px; margin-bottom:16px; }
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
    @if($personnel->actif)
        <div class="ok">✓ Badge authentique</div>
    @else
        <div class="ok" style="background:#fbe6df; color:#a8503c;">⚠ Badge authentique, mais personne plus en activité</div>
    @endif
    <h1>{{ \App\Support\Etablissement::nom() }}</h1>
    <div class="sub">Badge du personnel</div>

    <table>
        <tr><td>Nom</td><td>{{ $personnel->nom }} {{ $personnel->prenom }}</td></tr>
        <tr><td>Fonction</td><td>{{ $personnel->fonction ?: $personnel->libelle_type }}</td></tr>
        <tr><td>Matricule</td><td>{{ $personnel->matricule }}</td></tr>
        <tr><td>Statut</td><td>{{ $personnel->actif ? 'En activité' : 'Ne fait plus partie du personnel' }}</td></tr>
    </table>

    <div class="note">
        Cette page confirme que ce badge a été délivré par l'établissement.
        Vérification effectuée le {{ now()->format('d/m/Y à H:i') }}.
    </div>
</div>
</body>
</html>
