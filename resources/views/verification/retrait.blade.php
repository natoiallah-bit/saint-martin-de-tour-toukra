<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Vérification d'authenticité — Carte de retrait</title>
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
    <div class="ok">✓ Carte authentique</div>
    <h1>{{ \App\Support\Etablissement::nom() }}</h1>
    <div class="sub">Carte de retrait des enfants n° {{ $carte->numero }}</div>

    <table>
        <tr><td>Parents</td><td>{{ $carte->parents ?: '—' }}@if($carte->telephones_parents)<br>{{ $carte->telephones_parents }}@endif</td></tr>
        <tr><td>Quartier</td><td>{{ $carte->quartier ?: '—' }}</td></tr>
        <tr><td>Personne autorisée</td><td>{{ $carte->responsable_nom }} ({{ $carte->responsable_lien }})<br>{{ $carte->responsable_telephone }}</td></tr>
        @if($carte->second_nom)
            <tr><td>Personne autorisée</td><td>{{ $carte->second_nom }} ({{ $carte->second_lien }})<br>{{ $carte->second_telephone }}</td></tr>
        @endif
        @foreach($carte->eleves as $eleve)
            <tr><td>Enfant</td><td>{{ $eleve->nom }} {{ $eleve->prenom }} — {{ $eleve->classe->nom ?? '—' }}</td></tr>
        @endforeach
    </table>

    <div class="note">
        Seules les personnes ci-dessus peuvent récupérer ces enfants. Vérifier la pièce
        d'identité en cas de doute. Vérification effectuée le {{ now()->format('d/m/Y à H:i') }}.
    </div>
</div>
</body>
</html>
