<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', \App\Support\Etablissement::nomCourt()) — Gestion Scolaire</title>
    <style>
        :root{
            --indigo-950:#141c33; --indigo-800:#243365; --gold-500:#c9973b;
            --sand-50:#f7f4ee; --line:#e3ddcf; --ink-900:#20222b; --sage-600:#4c7a63;
        }
        *{box-sizing:border-box;}
        body{ font-family: Arial, sans-serif; background:var(--sand-50); color:var(--ink-900); margin:0; }
        nav{ background:var(--indigo-950); color:#fff; padding:16px 30px; display:flex; justify-content:space-between; align-items:center;}
        nav a{ color:#fff; text-decoration:none; font-weight:bold; }
        nav a:hover{ color:var(--gold-500); }
        main{ max-width:1000px; margin:30px auto; padding:0 20px; }
        h1{ color:var(--indigo-950); }
        table{ width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; }
        th{ background:var(--indigo-950); color:#fff; padding:10px; text-align:left; font-size:13px; }
        td{ padding:10px; border-bottom:1px solid var(--line); font-size:14px; }
        .btn{ background:var(--gold-500); color:var(--indigo-950); padding:8px 16px; border:none; border-radius:6px; text-decoration:none; font-weight:bold; cursor:pointer; display:inline-block; }
        .btn-danger{ background:#a8503c; color:#fff; }
        .alert{ background:var(--sage-600); color:#fff; padding:12px 16px; border-radius:6px; margin-bottom:16px; }
        form label{ display:block; margin-top:12px; font-weight:bold; font-size:13px; }
        form input, form select{ width:100%; padding:8px; border:1px solid var(--line); border-radius:6px; margin-top:4px; }
        .actions a, .actions button{ margin-right:6px; font-size:12px; padding:5px 10px; }
        @media print { nav, .btn { display:none; } main { max-width:100%; margin:0; } }
    </style>
</head>
<body>
    <nav>
        <div>🏫 <strong>{{ \App\Support\Etablissement::nomCourt() }}</strong> — Gestion Scolaire</div>
        <div>
            <a href="{{ route('eleves.index') }}">Élèves</a>
            <a href="{{ route('notes.choix') }}" style="margin-left:20px;">Notes</a>
            <a href="{{ route('bulletins.choix') }}" style="margin-left:20px;">Bulletins</a>
            <a href="{{ route('paiements.index') }}" style="margin-left:20px;">Scolarité</a>
        </div>
    </nav>
    <main>
        @if(session('success'))
            <div class="alert">{{ session('success') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
