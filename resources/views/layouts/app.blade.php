<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', \App\Support\Etablissement::nomCourt()) — Gestion Scolaire</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @php $teinte = \App\Support\Etablissement::couleurs(); @endphp
    <style>
        :root{
            /* Couleurs du logo de l'école (Paramètres) et leurs nuances */
            --indigo-950:{{ $teinte['principale'] }}; --indigo-900:{{ $teinte['principale_900'] }}; --indigo-800:{{ $teinte['principale_800'] }}; --bleu-clair:{{ $teinte['principale_clair'] }};
            --gold-500:{{ $teinte['secondaire'] }}; --gold-400:{{ $teinte['secondaire_400'] }};
            --sand-50:#f7f4ee; --line:#e3ddcf; --ink-900:#20222b; --sage-600:#4c7a63; --clay-600:#a8503c;
        }
        *{box-sizing:border-box; margin:0; padding:0;}
        body{ font-family:'Inter', Arial, sans-serif; background:#fff; color:var(--ink-900); }

        .topbar-marquee{
            background:linear-gradient(90deg, var(--indigo-950), var(--indigo-800)); color:#fff;
            font-family:'Fraunces', serif; font-weight:700; font-size:18px;
            padding:8px 0; letter-spacing:0.4px;
        }
        .topbar-marquee marquee{ padding:0 20px; }

        .app{ display:flex; min-height:calc(100vh - 42px); }

        .sidebar{
            width:230px; flex-shrink:0; background:var(--indigo-950); color:#fff;
            padding:20px 14px; align-self:flex-start; position:sticky; top:0;
            max-height:100vh; overflow-y:auto;
        }
        .logo-zone{
            display:flex; flex-direction:column; align-items:center; text-align:center;
            padding:10px 8px 18px; border-bottom:1px solid var(--indigo-800); margin-bottom:14px;
        }
        .logo-slot{
            width:72px; height:72px; border-radius:12px; margin-bottom:8px;
            border:2px solid rgba(255,255,255,.85); display:flex; align-items:center; justify-content:center;
            font-size:10px; color:#fff; font-weight:bold; overflow:hidden; background:var(--indigo-950);
        }
        .logo-slot img{ width:100%; height:100%; object-fit:cover; }
        .logo-zone .nom{ font-family:'Fraunces', serif; font-weight:600; font-size:14px; line-height:1.3; }
        .logo-zone .sous{ font-size:9.5px; text-transform:uppercase; letter-spacing:0.5px; color:{{ $teinte['principale_texte'] }}; margin-top:3px; }

        .menu-label{ font-size:9.5px; text-transform:uppercase; letter-spacing:0.6px; color:#b9b5e0; margin:14px 8px 6px; }
        .menu-item{
            display:block; color:#e6e4f6; text-decoration:none; font-size:13px; font-weight:600;
            padding:8px 10px; border-radius:7px; margin-bottom:2px;
        }
        .menu-item:hover{ background:var(--indigo-800); color:#fff; }

        /* Barre du haut : utilisateur connecté et déconnexion, visible sur toutes les pages */
        .barre-compte{
            display:flex; align-items:center; justify-content:flex-end; gap:14px; flex-wrap:wrap;
            background:#fff; border-bottom:1px solid #dcd9ef; padding:10px 34px;
            position:sticky; top:0; z-index:20;
        }
        .barre-compte .avatar{
            width:36px; height:36px; border-radius:50%; background:var(--indigo-950); color:#fff;
            display:flex; align-items:center; justify-content:center; font-weight:700; font-size:14px; flex-shrink:0;
        }
        .barre-compte .qui{ line-height:1.25; text-align:right; }
        .barre-compte .qui b{ display:block; font-size:14.5px; color:var(--indigo-950); }
        .barre-compte .qui span{ font-size:12px; color:#5a5f70; }
        .barre-compte .cycle-ok{ color:#8a6a2a; font-weight:600; }
        .barre-compte .cycle-manquant{ color:var(--clay-600); font-weight:600; }
        .btn-deconnexion{
            background:var(--clay-600); color:#fff; border:none; padding:9px 16px;
            border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; text-decoration:none; display:inline-block;
        }
        .btn-deconnexion:hover{ background:#8e4130; }
        .btn-accueil{
            margin-right:auto; background:var(--indigo-950); color:#fff; padding:9px 16px; border-radius:6px;
            font-size:13px; font-weight:700; text-decoration:none; display:inline-flex; align-items:center; gap:6px;
        }
        .btn-accueil:hover{ background:var(--indigo-800); }
        .barre-compte:has(.recherche-haut) .btn-accueil{ margin-right:0; }
        .recherche-haut{ margin:0 auto 0 0; flex:1; max-width:440px; }
        .recherche-haut input{ width:100%; margin:0; padding:9px 12px; border:1px solid #d6d3ec; border-radius:20px; background:var(--bleu-clair); font-size:13.5px; }
        .recherche-haut input:focus{ outline:2px solid var(--indigo-800); background:#fff; }
        .zone-page{ flex:1; min-width:0; display:flex; flex-direction:column; }

        main{ flex:1; min-width:0; padding:30px 34px 60px; }
        h1{ font-family:'Fraunces', serif; font-weight:600; font-size:28px; color:var(--indigo-950); margin-bottom:20px; }
        h3{ font-family:'Fraunces', serif; font-weight:600; color:var(--indigo-800); font-size:16.5px; }
        table{ width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #e4e2f1; }
        th{ background:var(--indigo-950); color:#fff; padding:10px; text-align:left; font-size:12.5px; }
        td{ padding:10px; border-bottom:1px solid #e4e2f1; font-size:13.5px; }
        tbody tr:nth-child(even) td{ background:#f7f6fc; }
        .btn:hover{ background:var(--indigo-800); }
        .btn.btn-danger:hover{ background:#8e4130; }
        .btn{ background:var(--indigo-950); color:#fff; padding:8px 16px; border:none; border-radius:6px; text-decoration:none; font-weight:700; cursor:pointer; display:inline-block; font-size:13px; }
        .btn-danger{ background:var(--clay-600); color:#fff; }
        .alert{ background:var(--sage-600); color:#fff; padding:12px 16px; border-radius:6px; margin-bottom:16px; }
        form label{ display:block; margin-top:12px; font-weight:600; font-size:12.5px; }
        form input, form select{ width:100%; padding:8px; border:1px solid #d6d3ec; border-radius:6px; margin-top:4px; font-family:'Inter', sans-serif; }
        .actions a, .actions button{ margin-right:6px; font-size:12px; padding:5px 10px; }

        @media print { .sidebar, .topbar-marquee, .barre-compte { display:none; } main { max-width:100%; padding:0; } }
        .menu-bascule{ display:none; }
        /* Téléphone et tablette : le menu passe en haut, replié derrière « Menu » ; les tableaux défilent */
        @media (max-width:900px){
            .topbar-marquee{ font-size:14px; padding:5px 0; }
            .app{ flex-direction:column; }
            .sidebar{ width:100%; position:static; max-height:none; padding:10px 14px; display:flex; flex-wrap:wrap; align-items:center; gap:8px; }
            .logo-zone{ flex-direction:row; padding:0; margin:0 auto 0 0; border:0; gap:8px; }
            .logo-slot{ width:40px; height:40px; margin:0; }
            .logo-zone .sous{ display:none; }
            .menu-bascule{ display:inline-block; background:var(--indigo-900); color:#fff; border:1px solid var(--indigo-800); border-radius:8px; padding:8px 14px; font-weight:700; font-size:14px; cursor:pointer; }
            .sidebar .menu{ display:none; width:100%; }
            body.menu-ouvert .sidebar .menu{ display:block; }
            main{ padding:16px 14px 40px; }
            main h1{ font-size:22px; }
            main table{ display:block; overflow-x:auto; }
            .barre-compte{ position:static; padding:8px 14px; gap:8px; }
            .barre-compte .qui span{ display:none; }
            .barre-compte .recherche-haut{ order:5; flex-basis:100%; }
        }
    </style>
</head>
<body>
    @php
        $utilisateur = auth()->user();
        // Alertes non lues du conseil de discipline (cloche du parent)
        $nbAlertes = $utilisateur?->role === 'parent'
            ? \App\Models\DossierDiscipline::whereIn('eleve_id', $utilisateur->enfants()->pluck('eleves.id'))->get()->filter(fn ($d) => $d->estNonLuParParent())->count()
            : 0;
        $role = $utilisateur?->role;
        // Menu : un lien n'apparaît que si le compte a au moins le droit "Lecture"
        // sur le module (droits réglés dans "Droits des rôles", cumulés si plusieurs
        // rôles) et si sa route existe réellement.
        $sections = [
            'Pédagogie' => [
                ['eleves.index', 'Élèves', 'eleves'],
                ['import.eleves.formulaire', 'Import Excel', 'import_eleves', 'modifier'],
                ['classes.index', 'Classes', 'classes'],
                ['matieres-gestion.index', 'Matières', 'classes'],
                ['notes.choix', 'Notes', 'notes'],
                ['bulletins.choix', 'Bulletins', 'bulletins'],
                ['conseil-classe.choix', 'Conseil de classe', 'conseil_classe'],
                ['presences.choix', 'Présences', 'presences'],
                ['emplois-temps.choix', 'Emploi du temps', 'emplois_temps'],
                ['fiches-passage.choix', 'Fiches de passage', 'fiches_passage'],
                ['cahier-texte.index', 'Cahier de texte', 'cahier_texte'],
            ],
            'Pilotage' => [
                ['rapports.index', 'Rapports trimestriels', 'rapport_pedagogique|rapport_financier'],
            ],
            'Administration' => [
                ['paiements.index', 'Scolarité', 'scolarite'],
                ['verification.document', 'Vérifier un document', 'verification_documents'],
                ['frais.index', 'Frais', 'frais'],
                ['convocations.retards.index', 'Relances impayés', 'convocations'],
                ['convocations.reunion.form', 'Convocation réunion', 'convocations'],
                ['personnels.index', 'Personnel', 'personnel'],
            ],
            'Vie scolaire' => [
                ['pointage.index', 'Pointage', 'pointage'],
                ['discipline.index', 'Conseil de discipline', 'discipline'],
                ['cartes-retrait.index', 'Cartes de retrait', 'cartes_retrait'],
            ],
            'Finances & Biens' => [
                ['operations.index', 'Comptabilité', 'comptabilite'],
                ['tresorerie.index', 'Caisse et banque', 'comptabilite'],
                ['materiels.index', 'Logistique', 'logistique'],
                ['paies.index', 'Paie du personnel', 'paie'],
                ...($role === 'direction' ? [['journal-financier.index', 'Journal des corrections', 'comptabilite']] : []),
            ],
        ];
        $liensVisibles = function (array $liens) use ($utilisateur) {
            return array_filter($liens, fn ($l) => $utilisateur
                && \Illuminate\Support\Facades\Route::has($l[0])
                // Plusieurs modules séparés par « | » : il suffit d'avoir accès à l'un d'eux
                && collect(explode('|', $l[2]))->contains(fn ($module) => $utilisateur->peut($module, $l[3] ?? 'lecture')));
        };
        // Affiche un lien seulement si sa route existe réellement (évite toute page blanche
        // ou erreur "Route not defined" si une route n'a pas encore été ajoutée).
        $lien = function (string $routeName, string $label) {
            if (! \Illuminate\Support\Facades\Route::has($routeName)) {
                return '';
            }
            return '<a href="' . route($routeName) . '" class="menu-item">' . e($label) . '</a>';
        };
    @endphp

    <div class="topbar-marquee">
        <marquee behavior="scroll" direction="left" scrollamount="5">
            🏫 Application de Gestion Scolaire — {{ \App\Support\Etablissement::nom() }}
        </marquee>
    </div>

    <div class="app">
        <aside class="sidebar">
            <a href="{{ Route::has('dashboard') ? route('dashboard') : url('/') }}" class="logo-zone" style="text-decoration:none; color:#fff;">
                <div class="logo-slot">
                    @if(\App\Support\Etablissement::embleme())
                        <img src="{{ \App\Support\Etablissement::embleme() }}" alt="Logo">
                    @elseif(\App\Support\Etablissement::banniere())
                        <img src="{{ \App\Support\Etablissement::banniere() }}" alt="Logo">
                    @else
                        {{ \App\Support\Etablissement::initiales() }}
                    @endif
                </div>
                <div class="nom">{{ \App\Support\Etablissement::nomCourt() }}</div>
                <div class="sous">Gestion Scolaire</div>
            </a>
            <button type="button" class="menu-bascule" onclick="document.body.classList.toggle('menu-ouvert')">☰ Menu</button>

            <nav class="menu">

            @if($utilisateur?->aRole('enseignant'))
                <div class="menu-label">Mon espace</div>
                {!! $lien('emplois-temps.mes-cours', 'Mon emploi du temps') !!}
            @endif

            @foreach($sections as $titre => $liens)
                @php($visibles = $liensVisibles($liens))
                @if(count($visibles))
                    <div class="menu-label">{{ $titre }}</div>
                    @foreach($visibles as $l)
                        {!! $lien($l[0], $l[1]) !!}
                    @endforeach
                @endif
            @endforeach

            @if($role === 'direction')
                <div class="menu-label">Système</div>
                {!! $lien('parametres.index', 'Paramètres') !!}
                {!! $lien('licence.index', 'Licence') !!}
                {!! $lien('users.index', 'Comptes utilisateurs') !!}
                {!! $lien('droits.index', 'Droits des rôles') !!}
                {!! $lien('appareils.index', 'Appareils connectés') !!}
            @endif

            @if($role === 'parent')
                <div class="menu-label">Mon espace</div>
                {!! $lien('parent.dashboard', 'Mes enfants') !!}
            @endif

            </nav>
        </aside>

        <div class="zone-page">
            @auth
                <div class="barre-compte">
                    <a href="{{ $utilisateur->urlAccueil() }}" class="btn-accueil">🏠 Accueil</a>
                    @if(\App\Http\Controllers\RechercheController::peutRechercherEleves($utilisateur) || \App\Http\Controllers\RechercheController::peutRechercherPersonnel($utilisateur))
                        <form action="{{ route('recherche') }}" method="GET" class="recherche-haut">
                            <input type="search" name="q" value="{{ request()->routeIs('recherche') ? request('q') : '' }}" placeholder="🔎 Rechercher un élève ou un membre du personnel…" minlength="2" required>
                        </form>
                    @endif
                    @if($utilisateur->role === 'parent')
                        @if($nbAlertes)
                            <a href="{{ route('parent.dashboard') }}" style="background:#a8503c; color:#fff; padding:7px 12px; border-radius:20px; font-weight:700; font-size:13px; text-decoration:none;">🔔 {{ $nbAlertes }} alerte{{ $nbAlertes > 1 ? 's' : '' }}</a>
                        @endif
                    @endif
                    <div class="qui">
                        <b>{{ $utilisateur->name }}</b>
                        <span>
                            {{ $utilisateur->libelle_roles }}
                            @if($utilisateur->cycle)
                                · <span class="cycle-ok">Cycle : {{ $utilisateur->cycle->nom }}</span>
                            @elseif(! in_array($utilisateur->role, ['direction', 'parent', 'eleve']))
                                · <span class="cycle-manquant">⚠️ Aucun cycle assigné</span>
                            @endif
                        </span>
                    </div>
                    <div class="avatar" title="{{ $utilisateur->name }}">{{ mb_strtoupper(mb_substr($utilisateur->name, 0, 1)) }}</div>
                    @if(Route::has('logout'))
                        <form action="{{ route('logout') }}" method="POST" style="margin:0;"
                              onsubmit="return confirm('Voulez-vous vraiment vous déconnecter ?');">
                            @csrf
                            <button type="submit" class="btn-deconnexion">⏻ Déconnexion</button>
                        </form>
                    @else
                        <a href="{{ url('/deconnexion-urgence') }}" class="btn-deconnexion">⏻ Déconnexion</a>
                    @endif
                </div>
            @endauth

            <main>
                @auth
                    <?php $licence = \App\Support\Licence::etat(); ?>
                    @if($licence['message'])
                        <div class="alert" style="background:{{ $licence['statut'] === 'bientot' ? '#fff3d6' : '#fbe6df' }}; color:{{ $licence['statut'] === 'bientot' ? '#6b4e00' : '#7a2e1c' }};">
                            {{ $licence['message'] }}
                            @if(auth()->user()->role === 'direction')
                                <a href="{{ route('licence.index') }}" style="color:inherit; font-weight:700;">Licence</a>
                            @else
                                Prévenez la Direction.
                            @endif
                        </div>
                    @endif
                @endauth
                @if(session('success'))
                    <div class="alert">{{ session('success') }}</div>
                @endif
                @if(session('erreur'))
                    <div class="alert" style="background:#fbe6df; color:#7a2e1c; border-color:#e8b4a6;">{{ session('erreur') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
<script>
    // Suppression ou correction d'une donnée financière : le motif est demandé et gardé au journal
    function demanderMotif(formulaire, question) {
        const motif = window.prompt(question + "\n\nIndiquez le motif (obligatoire) :");
        if (!motif || !motif.trim()) { return false; }
        formulaire.querySelector('input[name=motif]').value = motif.trim();
        return true;
    }
</script>
</body>
</html>
