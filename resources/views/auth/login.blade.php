<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — Gestion Scolaire</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root{
            @php $teinte = \App\Support\Etablissement::couleurs(); @endphp
            --indigo-950:{{ $teinte['principale'] }}; --indigo-800:{{ $teinte['principale_800'] }}; --gold-500:{{ $teinte['secondaire'] }}; --gold-400:{{ $teinte['secondaire_400'] }};
            --sand-50:#f7f4ee; --line:#e3ddcf; --ink-900:#20222b; --clay-600:#a8503c;
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{
            font-family:'Inter', Arial, sans-serif; background:var(--indigo-950);
            min-height:100vh; display:flex; align-items:center; justify-content:center;
            background-image: repeating-linear-gradient(115deg, rgba(255,255,255,0.05) 0 2px, transparent 2px 26px);
        }
        .carte{
            background:#fff; border-radius:14px; padding:36px 38px; width:100%; max-width:380px;
            box-shadow:0 20px 60px rgba(0,0,0,0.35);
        }
        .logo-slot{
            width:84px; height:84px; border-radius:12px; margin:0 auto 14px; overflow:hidden;
            display:flex; align-items:center; justify-content:center;
            font-size:10px; color:#fff; font-weight:bold; background:var(--indigo-950);
        }
        .logo-slot img{ width:100%; height:100%; object-fit:cover; }
        h1{ font-family:'Fraunces', serif; font-weight:600; font-size:20px; color:var(--indigo-950); text-align:center; }
        .sous{ text-align:center; font-size:12px; color:#5a5a5a; margin-top:4px; margin-bottom:24px; }
        label{ display:block; font-weight:600; font-size:12.5px; margin-top:14px; color:var(--indigo-950); }
        input[type=email], input[type=password]{
            width:100%; padding:10px; border:1px solid var(--line); border-radius:7px; margin-top:5px; font-family:'Inter';
        }
        .btn{
            width:100%; background:var(--indigo-950); color:#fff; border:none; padding:11px;
            border-radius:7px; font-weight:700; margin-top:20px; cursor:pointer; font-size:14px;
        }
        .info{ background:#e2ede6; color:#3f6b55; padding:10px; border-radius:6px; font-size:12.5px; margin-top:14px; font-weight:600; }
        .erreur{ background:#fbe6df; color:var(--clay-600); padding:10px; border-radius:6px; font-size:12.5px; margin-top:14px; }
        .remember{ display:flex; align-items:center; gap:8px; margin-top:14px; font-size:12.5px; }
        .remember input{ width:auto; }
    </style>
</head>
<body>
    <div class="carte">
        <div class="logo-slot">
            @if(\App\Support\Etablissement::embleme())
                <img src="{{ \App\Support\Etablissement::embleme() }}" alt="Logo">
            @elseif(\App\Support\Etablissement::banniere())
                <img src="{{ \App\Support\Etablissement::banniere() }}" alt="Logo">
            @else
                {{ \App\Support\Etablissement::initiales() }}
            @endif
        </div>
        <h1>{{ \App\Support\Etablissement::nomCourt() }}</h1>
        <div class="sous">Connexion — Application de gestion scolaire</div>

        @if (session('success'))
            <div class="info">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="erreur">{{ $errors->first() }}</div>
        @endif

        <form action="{{ route('login.submit') }}" method="POST">
            @csrf
            <label>Email (ou matricule pour les élèves)</label>
            <input type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label>Mot de passe</label>
            <input type="password" name="password" required>

            <label class="remember">
                <input type="checkbox" name="remember"> Se souvenir de moi
            </label>

            <button type="submit" class="btn">Se connecter</button>
        </form>
    </div>
</body>
</html>
