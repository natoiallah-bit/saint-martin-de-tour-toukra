{{--
    Page d'impression des cartes au format CR80 (carte bancaire, 85,6 × 54 mm).
    - Cartes scolaires : paysage, recto puis verso.
    - Cartes scolaires de la maternelle : portrait, une seule face (photo, nom, mention
      Maternelle, parent à appeler en cas d'urgence).
    - Badges du personnel : portrait, une seule face (toutes les informations au recto).
    - Cartes de retrait des enfants : paysage, une seule face (parents, quartier,
      nombre d'enfants, personnes autorisées, emoji à la place de la photo, QR code).
    Sert pour une seule carte ou pour une série (toute une classe, tout le personnel).
    Page autonome, sans le menu de l'application, pour que l'impression ne contienne
    que les cartes. Fond blanc pour économiser l'encre, logo de l'école en filigrane.

    Variables : $titre, $retour (URL), $config, et $eleves OU $personnels OU $cartesRetrait (collections).
--}}
@php
    $logo = \App\Support\Etablissement::banniere();
    $embleme = \App\Support\Etablissement::embleme() ? \App\Support\Etablissement::embleme() : null;
    // Les deux numéros de l'école (Paramètres), à défaut ceux du logo
    $telephonesEcole = collect(preg_split('/\s*[\/,;]\s*/', (string) \App\Support\Etablissement::telephone()))
        ->map(fn ($t) => trim(preg_replace('/^\+?235\s*/', '', $t)))->filter()->take(2);
    $nomEcole = \App\Support\Etablissement::nom();
    $adresse = collect([$config->quartier, $config->ville])->filter()->implode(' · ');
    $annee = $config->annee_scolaire_active;
    $finAnnee = $config->trimestre3_fin;
    $validite = $finAnnee ? 'Valable jusqu\'au ' . $finAnnee->format('d/m/Y') : 'Année scolaire ' . $annee;
    // Maternelle : carte portrait, une seule face (photo, nom, mention, parent à appeler en urgence)
    $estMaternelle = fn ($eleve) => \Illuminate\Support\Str::of((string) $eleve->classe?->cycle?->nom)->ascii()->lower()->contains('mater');
    $cartesMaternelle = collect($eleves ?? [])->isNotEmpty() && collect($eleves)->every($estMaternelle);
    $portrait = (isset($personnels) && count($personnels) > 0) || $cartesMaternelle;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $titre }}</title>
<style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{ font-family:Arial, Helvetica, sans-serif; background:#ece8df; color:#20222b; }

    .barre{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; padding:14px 20px; background:{{ \App\Support\Etablissement::couleurs()['principale'] }}; color:#fff; }
    .barre h1{ font-size:16px; font-weight:700; margin-right:auto; }
    .barre button, .barre a{ background:{{ \App\Support\Etablissement::couleurs()['secondaire'] }}; color:#141c33; border:0; border-radius:6px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer; text-decoration:none; }
    .barre a{ background:transparent; color:#fff; text-decoration:underline; padding:8px 4px; }
    .aide{ padding:8px 20px; font-size:12px; color:#5a5a5a; }

    /* Aperçu à l'écran : cartes agrandies */
    .planche{ display:flex; flex-wrap:wrap; gap:18px 26px; padding:16px 20px 40px; zoom:1.45; }
    .paire{ display:flex; gap:10px; }

    .card{
        width:85.6mm; height:54mm; border-radius:3mm; overflow:hidden; position:relative;
        background:#fff; color:#141c33; border:0.3mm solid #c9cfdd;
        box-shadow:0 2mm 6mm rgba(0,0,0,0.18);
        -webkit-print-color-adjust:exact; print-color-adjust:exact;
    }
    .card.portrait{ width:54mm; height:85.6mm; }

    /* Couleur du cycle (primaire, collège, lycée) : bande verticale à gauche avec le nom
       du cycle, filet sous l'en-tête et titres. Reste discrète pour économiser l'encre. */
    .card{ --cycle:#1b2b5c; }
    .bande{ position:absolute !important; left:0; top:0; bottom:0; width:3mm; display:flex; flex-direction:column; z-index:2 !important; }
    .bande div{
        flex:1; background:var(--cycle); color:#fff; display:flex; align-items:center; justify-content:center;
        writing-mode:vertical-rl; transform:rotate(180deg); font-size:1.9mm; font-weight:700; letter-spacing:0.3mm;
        text-transform:uppercase; white-space:nowrap; overflow:hidden;
    }
    .card.cycle .entete{ border-bottom-color:var(--cycle); margin-left:3mm; }
    .card.cycle .titre{ color:var(--cycle); padding-left:3mm; }

    /* Logo de l'école en filigrane, derrière le contenu */
    .filigrane{
        position:absolute; left:50%; top:55%; transform:translate(-50%, -50%) rotate(-24deg);
        width:112%; object-fit:contain; opacity:0.05; z-index:0; pointer-events:none;
    }
    .card.portrait .filigrane{ width:150%; transform:translate(-50%, -50%) rotate(-58deg); }
    .card > *:not(.filigrane){ position:relative; z-index:1; }

    .entete{ height:11mm; display:flex; align-items:center; justify-content:center; border-bottom:0.6mm solid #1b2b5c; padding:0.8mm 2mm; background:#fff; }
    .entete img{ max-height:100%; max-width:100%; object-fit:contain; }
    .entete .nom-ecole{ color:#141c33; font-weight:700; font-size:3.2mm; text-align:center; line-height:1.1; }
    .titre{ text-align:center; font-size:2.7mm; font-weight:700; letter-spacing:0.25mm; color:#6b4500; text-transform:uppercase; margin:1.3mm 0 1.3mm; }
    .photo{
        border-radius:1.2mm; overflow:hidden; flex-shrink:0; background:#fff; border:0.3mm solid #9aa4bf;
        display:flex; align-items:center; justify-content:center; font-size:2mm; color:#9aa4bf; text-align:center;
    }
    .photo img{ width:100%; height:100%; object-fit:cover; }
    .matricule{ font-family:"Courier New", monospace; font-weight:700; color:#1b2b5c; letter-spacing:0.2mm; }

    /* Carte scolaire — recto */
    .recto .corps{ display:flex; gap:3.2mm; padding:0 3.5mm; }
    .recto .photo{ width:21.5mm; height:26.5mm; }
    .recto .infos{ min-width:0; flex:1; }
    .recto .nom{ font-size:3.9mm; font-weight:700; line-height:1.15; margin-bottom:1.3mm; word-wrap:break-word; color:#1b2b5c; }
    .recto .ligne{ font-size:2.85mm; font-weight:700; color:#1b2b5c; margin-top:1mm; line-height:1.25; }
    .recto .ligne b{ color:#000; font-weight:700; }
    .recto .pied{
        position:absolute; left:3.5mm; right:3.5mm; bottom:1.8mm; display:flex; justify-content:space-between; align-items:flex-end;
        font-size:2.3mm; font-weight:700; color:#000; border-top:0.3mm solid #1b2b5c; padding-top:0.8mm;
    }
    .recto .pied .matricule{ font-size:3.2mm; }

    /* Carte scolaire — verso */
    .verso{ padding:2.6mm 3.5mm; }
    .verso .ecole{ text-align:center; border-bottom:0.35mm solid #141c33; padding-bottom:1.2mm; margin-bottom:1.6mm; }
    .verso .ecole b{ display:block; font-size:2.8mm; color:#141c33; text-transform:uppercase; letter-spacing:0.15mm; }
    .verso .ecole span{ font-size:2.3mm; font-weight:700; color:#000; }
    .verso .contenu{ display:flex; gap:2.5mm; }
    .verso .contacts{ flex:1; min-width:0; }
    .verso .sous-titre{ font-size:2.3mm; text-transform:uppercase; color:#5c3d00; font-weight:700; margin-bottom:0.8mm; }
    .verso .contact{ font-size:2.7mm; font-weight:700; color:#1b2b5c; padding:0.8mm 0; border-bottom:0.2mm dashed #888; line-height:1.25; }
    .verso .contact b{ color:#000; }
    .verso .contact .tel{ display:block; font-weight:700; color:#000; font-size:3.05mm; }
    .verso .qr{ width:21mm; text-align:center; flex-shrink:0; }
    .verso .qr img{ width:20mm; height:20mm; display:block; margin:0 auto; background:#fff; }
    .verso .qr span{ font-size:1.85mm; font-weight:700; color:#000; line-height:1.2; display:block; margin-top:0.4mm; }
    .verso .note{ position:absolute; left:3.5mm; right:3.5mm; bottom:1.8mm; font-size:2.05mm; font-weight:700; color:#000; text-align:center; line-height:1.25; }

    /* Badge du personnel — portrait, une seule face */
    .badge .entete{ height:10mm; }
    .badge .titre{ font-size:2.4mm; margin:1.4mm 0 1.6mm; line-height:1.3; }
    .badge .photo{ width:25mm; height:31mm; margin:0 auto; }
    .badge .nom{ text-align:center; font-size:3.9mm; font-weight:700; color:#1b2b5c; line-height:1.15; margin:1.8mm 2.5mm 0.6mm; word-wrap:break-word; }
    .badge .fonction{ text-align:center; font-size:2.95mm; font-weight:700; color:#000; margin:0 2.5mm; line-height:1.2; }
    .badge .tel{ text-align:center; font-size:2.6mm; font-weight:700; color:#000; margin-top:0.6mm; }
    .badge .bas{
        position:absolute; left:3mm; right:3mm; bottom:5.2mm; display:flex; justify-content:space-between; align-items:flex-end;
        border-top:0.3mm solid #1b2b5c; padding-top:1.2mm;
    }
    .badge .bas .matricule{ font-size:3.1mm; display:block; }
    .badge .bas .validite{ font-size:2.15mm; font-weight:700; color:#000; margin-top:0.6mm; display:block; }
    .badge .bas .qr img{ width:15mm; height:15mm; display:block; background:#fff; }
    .badge .note{ position:absolute; left:2mm; right:2mm; bottom:1.4mm; text-align:center; font-size:1.85mm; font-weight:700; color:#000; line-height:1.2; }

    /* Carte scolaire de la maternelle — portrait, une seule face.
       Mise en colonne : chaque bloc prend sa place, rien ne se superpose même avec un nom long. */
    /* En bas, une marge de 3 mm : à l'impression, le bord de la carte peut être rogné */
    .card.maternelle{ display:flex; flex-direction:column; padding:0 2.2mm 3mm 5mm; }
    .card.cycle.maternelle .titre{ padding-left:0; }
    .maternelle .titre{ font-size:2.2mm; margin:0.9mm 0 0.9mm; flex-shrink:0; }
    .maternelle .entete-mat{
        height:10mm; flex-shrink:0; display:flex; align-items:center; gap:1.6mm; margin:0 -2.2mm 0 -2mm; padding:0.8mm 2mm;
        border-bottom:0.6mm solid var(--cycle);
    }
    .maternelle .entete-mat img{ height:8.2mm; width:8.2mm; border-radius:1mm; object-fit:cover; flex-shrink:0; }
    .maternelle .entete-mat b{ font-size:2.6mm; line-height:1.15; color:{{ \App\Support\Etablissement::couleurs()['principale'] }}; text-transform:uppercase; letter-spacing:0.1mm; }
    .maternelle .photo{ width:26mm; height:31mm; margin:0 auto; flex-shrink:0; }
    .maternelle .nom{
        text-align:center; font-size:3mm; font-weight:800; color:#000; line-height:1.15; margin-top:1mm;
        overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; flex-shrink:0;
    }
    .maternelle .mention{
        align-self:center; margin-top:0.7mm; padding:0.3mm 2.2mm; border-radius:3mm; flex-shrink:0;
        background:var(--cycle); color:#fff; font-size:1.95mm; font-weight:700; letter-spacing:0.35mm; text-transform:uppercase;
    }
    .maternelle .urgence{
        margin-top:auto; border:0.35mm solid var(--cycle); border-radius:1.5mm; padding:0.6mm 1.2mm; text-align:center; flex-shrink:0;
    }
    .maternelle .urgence .sous-titre{ font-size:1.9mm; font-weight:800; color:#000; text-transform:uppercase; letter-spacing:0.15mm; }
    .maternelle .urgence .parent{
        font-size:2.15mm; font-weight:800; color:#000; line-height:1.15; margin-top:0.3mm;
        white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .maternelle .urgence .tel{ display:block; font-size:2.55mm; font-weight:700; color:var(--cycle); letter-spacing:0.15mm; line-height:1.15; }
    .maternelle .urgence .recuperation{ margin-top:0.5mm; padding-top:0.4mm; border-top:0.2mm dashed var(--cycle); color:#000; letter-spacing:0; }
    .maternelle .tel-ecole{
        flex-shrink:0; margin:0.8mm 0 0; padding:0.6mm 1mm; border-radius:1.2mm; background:var(--cycle); color:#fff;
        text-align:center; font-size:2.1mm; font-weight:700; letter-spacing:0.1mm; white-space:nowrap;
    }

    /* Carte de retrait des enfants — paysage, une seule face.
       Textes en gras et foncés pour rester lisibles une fois imprimés. */
    .retrait .filigrane{ opacity:0.05; }
    .retrait .entete{ height:8.6mm; }
    .retrait .titre{ color:#6b4500; font-size:2.75mm; margin:0.9mm 0 0.9mm; }
    .retrait .haut{ display:flex; gap:2.2mm; padding:0 3mm; }
    .retrait .emoji{
        width:15mm; height:18mm; font-size:11mm; line-height:1;
        font-family:"Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif;
    }
    .retrait .famille{ flex:1; min-width:0; }
    .retrait .ligne{ font-size:2.6mm; font-weight:700; color:#000; line-height:1.25; }
    .retrait .etiquette{ color:#1b2b5c; }
    .retrait .tel{ white-space:nowrap; }
    .retrait .cote{ width:14.5mm; flex-shrink:0; }
    .retrait .cote .qr img{ width:13mm; height:13mm; display:block; margin:0 auto; background:#fff; }
    .retrait .cote .nombre{
        margin-top:0.5mm; border:0.4mm solid #0b1640; border-radius:1mm; padding:0.2mm 0.6mm;
        display:flex; align-items:center; gap:0.6mm; height:4.6mm;
    }
    .retrait .cote .nombre b{ font-size:3.6mm; color:#0b1640; line-height:1; }
    .retrait .cote .nombre span{ font-size:1.55mm; font-weight:700; color:#000; text-transform:uppercase; line-height:1.05; }
    .retrait .autorises{ margin:0.6mm 3mm 0; padding-top:0.4mm; border-top:0.3mm solid #1b2b5c; }
    .retrait .sous-titre{ font-size:2.3mm; text-transform:uppercase; color:#5c3d00; font-weight:700; letter-spacing:0.1mm; }
    .retrait .autorise{ font-size:3mm; font-weight:700; color:#000; line-height:1.25; }
    .retrait .autorise .qui{ color:#0b1640; }
    .retrait.seul .autorise{ font-size:3.5mm; }
    .retrait .pied{
        position:absolute; left:3mm; right:3mm; bottom:1.4mm; display:flex; justify-content:space-between; align-items:flex-end; gap:2mm;
        font-size:2.1mm; font-weight:700; color:#000; border-top:0.3mm solid #1b2b5c; padding-top:0.6mm; line-height:1.2;
    }
    .retrait .pied .matricule{ font-size:3.2mm; color:#0b1640; white-space:nowrap; }

    /* Décalage du contenu à droite de la bande de couleur */
    .card.cycle.recto .corps, .card.cycle.retrait .haut{ padding-left:4.6mm; }
    .card.cycle.recto .pied, .card.cycle.retrait .pied, .card.cycle.verso .note{ left:4.6mm; }
    .card.cycle.retrait .autorises{ margin-left:4.6mm; }
    .card.cycle.verso{ padding-left:4.6mm; }
    .card.cycle .recto-nom, .card.cycle.recto .nom{ color:var(--cycle); }
    .card.cycle .pied{ border-top-color:var(--cycle) !important; }
    .card.cycle.verso .ecole{ border-bottom-color:var(--cycle); }

    @media print {
        @page{ size:{{ $portrait ? '54mm 85.6mm' : '85.6mm 54mm' }}; margin:0; }
        body{ background:#fff; }
        .barre, .aide{ display:none !important; }
        .planche{ display:block; padding:0; zoom:1; }
        .paire{ display:block; }
        .card{ border:0; border-radius:0; box-shadow:none; page-break-after:always; break-after:page; }
        .planche > :last-child .card:last-child, .planche > .card:last-child{ page-break-after:auto; break-after:auto; }
    }
</style>
</head>
<body>
    <div class="barre">
        <h1>{{ $titre }}</h1>
        <button type="button" onclick="window.print()">🖨️ Imprimer</button>
        <a href="{{ $retour }}">Retour</a>
        @auth <a href="{{ auth()->user()->urlAccueil() }}">🏠 Accueil</a> @endauth
    </div>
    <div class="aide">
        @if($cartesMaternelle)
            Impression au format carte portrait (54 × 85,6 mm), une seule face par carte.
        @elseif($portrait)
            Impression au format badge portrait (54 × 85,6 mm), une face par badge.
        @elseif(isset($cartesRetrait))
            Impression au format carte (85,6 × 54 mm), une seule face par carte.
        @else
            Impression au format carte (85,6 × 54 mm), recto puis verso pour chaque carte.
        @endif
        Dans la fenêtre d'impression : marges « Aucune », échelle 100 %, et cocher « Graphiques d'arrière-plan ».
    </div>

    <div class="planche">
        @foreach($eleves ?? [] as $eleve)
            @php
                $contacts = collect([
                    ['Père', $eleve->pere_nom, $eleve->pere_telephone],
                    ['Mère', $eleve->mere_nom, $eleve->mere_telephone],
                    ['Tuteur', $eleve->tuteur_nom, $eleve->tuteur_telephone],
                ])->filter(fn ($c) => filled($c[2]));
                $cycleEleve = $eleve->classe?->cycle;
                $styleCycle = '--cycle:' . ($cycleEleve?->couleur_carte ?? \App\Models\Cycle::COULEUR_NEUTRE);
                // Même numéro pour le père et la mère (saisi deux fois) : affiché une seule fois
                $chiffres = fn ($t) => preg_replace('/\D/', '', (string) $t);
                $urgence = $contacts->unique(fn ($c) => $chiffres($c[2]))->take(2);
                // Personne qui vient chercher l'enfant : carte de retrait de la famille, sinon le tuteur
                $carteRetrait = $eleve->relationLoaded('cartesRetrait') ? $eleve->cartesRetrait->first() : null;
                $recuperation = $carteRetrait
                    ? collect([
                        [$carteRetrait->responsable_nom, $carteRetrait->responsable_lien, $carteRetrait->responsable_telephone],
                        [$carteRetrait->second_nom, $carteRetrait->second_lien, $carteRetrait->second_telephone],
                    ])->filter(fn ($p) => filled($p[0]) && filled($p[2]))->first()
                    : (filled($eleve->tuteur_nom) && filled($eleve->tuteur_telephone) ? [$eleve->tuteur_nom, 'tuteur', $eleve->tuteur_telephone] : null);
                // Si c'est le père ou la mère (déjà dans « En cas d'urgence »), rien de plus
                if ($recuperation && (in_array(\Illuminate\Support\Str::of((string) $recuperation[1])->ascii()->lower()->trim()->value(), ['pere', 'mere'], true)
                    || $urgence->contains(fn ($c) => $chiffres($c[2]) === $chiffres($recuperation[2])))) {
                    $recuperation = null;
                }
            @endphp
            @if($estMaternelle($eleve))
                <div class="card portrait maternelle cycle" style="{{ $styleCycle }}">
                    <div class="bande"><div>{{ $cycleEleve?->nom }}</div></div>
                    {{-- Ni filigrane ni grand logo : sur cette petite carte, leurs textes se mêlaient au nom --}}
                    <div class="entete-mat">
                        @if($embleme) <img src="{{ $embleme }}" alt=""> @endif
                        <b>{{ $nomEcole }}</b>
                    </div>
                    <div class="titre">Carte scolaire</div>
                    <div class="photo">
                        @if(\App\Helpers\Fichier::existe($eleve->photo))
                            <img src="{{ \App\Helpers\Fichier::url($eleve->photo) }}" alt="Photo">
                        @else
                            Photo
                        @endif
                    </div>
                    <div class="nom">{{ $eleve->nom }} {{ $eleve->prenom }}</div>
                    <span class="mention">Maternelle</span>
                    <div class="urgence">
                        <div class="sous-titre">En cas d'urgence</div>
                        @forelse($urgence as [$lien, $nom, $tel])
                            <div class="parent">{{ $nom ?: $lien }}@if($nom) ({{ mb_strtolower($lien) }})@endif</div>
                            <span class="tel">{{ $tel }}</span>
                        @empty
                            <div class="parent">—</div>
                        @endforelse
                        @if($recuperation)
                            <div class="sous-titre recuperation">Personne autorisée à retirer l'enfant</div>
                            <div class="parent">{{ $recuperation[0] }}@if($recuperation[1]) ({{ mb_strtolower($recuperation[1]) }})@endif</div>
                            <span class="tel">{{ $recuperation[2] }}</span>
                        @endif
                    </div>
                    @if($telephonesEcole->isNotEmpty())
                        <div class="tel-ecole">Contact école : {{ $telephonesEcole->implode(' · ') }}</div>
                    @endif
                </div>
            @else
            <div class="paire">
                <div class="card recto cycle" style="{{ $styleCycle }}">
                    <div class="bande"><div>{{ $cycleEleve?->nom }}</div></div>
                    @if($logo) <img class="filigrane" src="{{ $logo }}" alt=""> @endif
                    <div class="entete">
                        @if($logo) <img src="{{ $logo }}" alt="{{ $nomEcole }}"> @else <div class="nom-ecole">{{ $nomEcole }}</div> @endif
                    </div>
                    <div class="titre">Carte scolaire {{ $annee }}</div>
                    <div class="corps">
                        <div class="photo">
                            @if(\App\Helpers\Fichier::existe($eleve->photo))
                                <img src="{{ \App\Helpers\Fichier::url($eleve->photo) }}" alt="Photo">
                            @else
                                Photo
                            @endif
                        </div>
                        <div class="infos">
                            <div class="nom">{{ $eleve->nom }} {{ $eleve->prenom }}</div>
                            <div class="ligne">Classe <b>{{ $eleve->classe->nom ?? '—' }}</b>@if($eleve->classe?->cycle) · <b>{{ $eleve->classe->cycle->nom }}</b>@endif</div>
                            <div class="ligne">Né(e) le <b>{{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }}</b>@if($eleve->lieu_naissance) à <b>{{ $eleve->lieu_naissance }}</b>@endif</div>
                            @if($eleve->sexe)
                                <div class="ligne">Sexe <b>{{ $eleve->sexe === 'F' ? 'Féminin' : 'Masculin' }}</b></div>
                            @endif
                            @if($contacts->isNotEmpty())
                                <div class="ligne">Contact <b>{{ $contacts->first()[2] }}</b> ({{ mb_strtolower($contacts->first()[0]) }})</div>
                            @endif
                        </div>
                    </div>
                    <div class="pied">
                        <span>{{ $validite }}</span>
                        <span class="matricule">{{ $eleve->matricule ?? '—' }}</span>
                    </div>
                </div>

                <div class="card verso cycle" style="{{ $styleCycle }}">
                    <div class="bande"><div>{{ $cycleEleve?->nom }}</div></div>
                    @if($logo) <img class="filigrane" src="{{ $logo }}" alt=""> @endif
                    <div class="ecole">
                        <b>{{ $nomEcole }}</b>
                        <span>{{ $adresse }}@if($config->telephone) · Tél. {{ $config->telephone }}@endif</span>
                    </div>
                    <div class="contenu">
                        <div class="contacts">
                            <div class="sous-titre">En cas de besoin, contacter</div>
                            @forelse($contacts as [$lien, $nom, $tel])
                                <div class="contact"><b>{{ $lien }}</b>@if($nom) — {{ $nom }}@endif <span class="tel">{{ $tel }}</span></div>
                            @empty
                                <div class="contact">—</div>
                            @endforelse
                        </div>
                        <div class="qr">
                            <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.carte', $eleve->id)) }}" alt="QR code d'authenticité">
                            <span>Scanner pour vérifier l'authenticité</span>
                        </div>
                    </div>
                    <div class="note">Carte strictement personnelle. En cas de perte, prévenir immédiatement l'administration.</div>
                </div>
            </div>
            @endif
        @endforeach

        @foreach($cartesRetrait ?? [] as $carte)
            @php
                $autorises = collect([
                    [$carte->responsable_nom, $carte->responsable_lien, $carte->responsable_telephone],
                    [$carte->second_nom, $carte->second_lien, $carte->second_telephone],
                ])->filter(fn ($p) => filled($p[0]));
                $nombreEnfants = $carte->eleves->count();
                // Une bande par cycle des enfants (une famille peut avoir des enfants au primaire et au collège)
                $cyclesFamille = $carte->eleves->map(fn ($e) => $e->classe?->cycle)->filter()->unique('id')->sortBy('nom')->values();
                $couleurTitre = $cyclesFamille->count() === 1 ? $cyclesFamille->first()->couleur_carte : \App\Models\Cycle::COULEUR_NEUTRE;
            @endphp
            <div class="card retrait cycle {{ $autorises->count() === 1 ? 'seul' : '' }}" style="--cycle:{{ $couleurTitre }}">
                <div class="bande">
                    @forelse($cyclesFamille as $c)
                        <div style="background:{{ $c->couleur_carte }}">{{ $c->nom }}</div>
                    @empty
                        <div></div>
                    @endforelse
                </div>
                @if($logo) <img class="filigrane" src="{{ $logo }}" alt=""> @endif
                <div class="entete">
                    @if($logo) <img src="{{ $logo }}" alt="{{ $nomEcole }}"> @else <div class="nom-ecole">{{ $nomEcole }}</div> @endif
                </div>
                <div class="titre">Carte de retrait des enfants {{ $annee }}</div>
                <div class="haut">
                    <div class="photo emoji">{{ $carte->emoji }}</div>
                    <div class="famille">
                        @if($carte->pere_nom)
                            <div class="ligne"><span class="etiquette">Père :</span> {{ $carte->pere_nom }}</div>
                        @endif
                        @if($carte->mere_nom)
                            <div class="ligne"><span class="etiquette">Mère :</span> {{ $carte->mere_nom }}</div>
                        @endif
                        @if($carte->telephones_parents)
                            <div class="ligne"><span class="etiquette">Tél. :</span> {!! collect(explode(' · ', $carte->telephones_parents))->map(fn ($t) => '<span class="tel">' . e($t) . '</span>')->implode(' · ') !!}</div>
                        @endif
                        <div class="ligne"><span class="etiquette">Quartier :</span> {{ $carte->quartier ?: '—' }}</div>
                    </div>
                    <div class="cote">
                        <div class="qr">
                            <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.retrait', $carte->id)) }}" alt="QR code d'authenticité">
                        </div>
                        <div class="nombre">
                            <b>{{ $nombreEnfants }}</b>
                            <span>{{ $nombreEnfants > 1 ? 'enfants en charge' : 'enfant en charge' }}</span>
                        </div>
                    </div>
                </div>
                <div class="autorises">
                    <div class="sous-titre">{{ $autorises->count() > 1 ? 'Personnes autorisées' : 'Personne autorisée' }}</div>
                    @foreach($autorises as [$nom, $lien, $tel])
                        <div class="autorise"><span class="qui">{{ $nom }}</span> ({{ $lien }}) · <span class="tel">{{ $tel }}</span></div>
                    @endforeach
                </div>
                <div class="pied">
                    <span>Cette carte est la propriété de l'école. À présenter à chaque sortie · {{ $validite }}</span>
                    <span class="matricule">{{ $carte->numero }}</span>
                </div>
            </div>
        @endforeach

        @foreach($personnels ?? [] as $personnel)
            <div class="card portrait badge">
                @if($logo) <img class="filigrane" src="{{ $logo }}" alt=""> @endif
                <div class="entete">
                    @if($logo) <img src="{{ $logo }}" alt="{{ $nomEcole }}"> @else <div class="nom-ecole">{{ $nomEcole }}</div> @endif
                </div>
                <div class="titre">Badge du personnel<br>{{ $annee }}</div>
                <div class="photo">
                    @if(\App\Helpers\Fichier::existe($personnel->photo))
                        <img src="{{ \App\Helpers\Fichier::url($personnel->photo) }}" alt="Photo">
                    @else
                        Photo
                    @endif
                </div>
                <div class="nom">{{ $personnel->nom }} {{ $personnel->prenom }}</div>
                <div class="fonction">{{ $personnel->fonction ?: $personnel->libelle_type }}</div>
                @if($personnel->telephone)
                    <div class="tel">Tél. {{ $personnel->telephone }}</div>
                @endif
                <div class="bas">
                    <div>
                        <span class="matricule">{{ $personnel->matricule }}</span>
                        <span class="validite">{{ $validite }}</span>
                    </div>
                    <div class="qr">
                        <img src="{{ \App\Helpers\QrAuthenticite::imageUrl(\App\Helpers\QrAuthenticite::lien('verification.badge', $personnel->id)) }}" alt="QR code d'authenticité">
                    </div>
                </div>
                <div class="note">À porter visiblement dans l'établissement</div>
            </div>
        @endforeach
    </div>
</body>
</html>
