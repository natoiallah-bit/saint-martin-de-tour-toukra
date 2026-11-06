{{--
    Lettres de relance des impayés, une par famille, format A4.
    Page autonome (sans le menu) pour n'imprimer que les lettres. Fond blanc.
    Niveau 1 : rappel, niveau 2 : deuxième relance, niveau 3 : convocation.
--}}
@php
    $nomEcole = \App\Support\Etablissement::nom();
    $logo = \App\Support\Etablissement::banniere();
    $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';
    $ville = $config->ville ?: "N'Djamena";
    $objets = [
        1 => 'Rappel — Scolarité ' . $config->annee_scolaire_active,
        2 => 'Deuxième relance — Scolarité ' . $config->annee_scolaire_active,
        3 => 'Convocation — Régularisation de la scolarité',
    ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lettres de relance ({{ $lettres->count() }})</title>
<style>
    *{ box-sizing:border-box; margin:0; padding:0; }
    body{ font-family:Arial, Helvetica, sans-serif; background:#ece8df; color:#20222b; }
    .barre{ display:flex; gap:12px; align-items:center; flex-wrap:wrap; padding:14px 20px; background:#141c33; color:#fff; }
    .barre h1{ font-size:16px; margin-right:auto; }
    .barre a{ color:#fff; font-weight:700; text-decoration:underline; font-size:13px; }
    .barre button{ background:#c9973b; color:#141c33; border:0; border-radius:6px; padding:8px 14px; font-weight:700; font-size:13px; cursor:pointer; }
    .info{ padding:10px 20px; font-size:13px; }
    .info.ok{ background:#e2ede6; color:#3f6b55; }
    .info.apercu{ background:#fff4dc; color:#7a5a12; }

    .lettre{ width:210mm; min-height:297mm; margin:16px auto; background:#fff; padding:14mm 18mm; box-shadow:0 2mm 8mm rgba(0,0,0,.15); position:relative; }
    .entete{ text-align:center; font-size:11px; font-weight:bold; line-height:1.5; margin-bottom:4mm; }
    .entete i{ font-weight:normal; }
    .logo{ width:100%; max-height:32mm; object-fit:contain; display:block; margin-bottom:4mm; }
    .nom-ecole{ text-align:center; font-size:18px; font-weight:bold; color:#141c33; border-top:2px solid #141c33; border-bottom:2px solid #141c33; padding:6px 0; margin-bottom:6mm; }
    .lieu-date{ text-align:right; font-size:13px; margin:2mm 0 6mm; }
    .destinataire{ margin-left:auto; width:85mm; font-size:13.5px; line-height:1.5; margin-bottom:8mm; }
    .objet{ font-size:14px; font-weight:bold; margin-bottom:6mm; }
    .objet span{ text-decoration:underline; }
    .niveau-3 .objet{ color:#8b2e1a; }
    .corps{ font-size:13.5px; line-height:1.7; text-align:justify; }
    .corps p{ margin-bottom:3.5mm; }
    table{ width:100%; border-collapse:collapse; font-size:13px; margin:2mm 0 5mm; }
    th, td{ border:1px solid #9aa4bf; padding:5px 8px; text-align:left; }
    th{ background:#f1f3f8; font-size:12px; }
    td.m, th.m{ text-align:right; white-space:nowrap; }
    tr.total td{ font-weight:bold; background:#f7f3ea; }
    .montant{ border:2px solid #141c33; text-align:center; padding:8px; font-size:16px; font-weight:bold; margin:4mm 0 5mm; }
    .signature{ display:flex; justify-content:flex-end; margin-top:12mm; font-size:13px; }
    .signature div{ text-align:center; width:70mm; }
    .signature b{ display:block; margin-top:22mm; border-top:1px solid #20222b; padding-top:4px; }
    .coupon{ position:absolute; left:18mm; right:18mm; bottom:12mm; border-top:1px dashed #666; padding-top:3mm; font-size:11.5px; line-height:1.7; }
    .coupon b{ font-size:12px; }

    @media print{
        @page{ size:A4; margin:0; }
        body{ background:#fff; }
        .barre, .info{ display:none; }
        .lettre{ margin:0; box-shadow:none; page-break-after:always; }
        .lettre:last-child{ page-break-after:auto; }
    }
</style>
</head>
<body>
    <div class="barre">
        <h1>Lettres de relance ({{ $lettres->count() }})</h1>
        <button type="button" onclick="window.print()">🖨️ Imprimer</button>
        <a href="{{ route('convocations.retards.index') }}">Retour aux impayés</a>
        @auth <a href="{{ auth()->user()->urlAccueil() }}">🏠 Accueil</a> @endauth
    </div>
    @if($enregistre)
        <div class="info ok">Ces lettres sont enregistrées dans l'historique des relances.</div>
    @else
        <div class="info apercu">Aperçu : ces lettres ne sont pas enregistrées dans l'historique.</div>
    @endif

    @foreach($lettres as $famille)
        @php
            $niveau = $famille['niveau_suivant'];
            $plusieurs = $famille['enfants']->count() > 1;
            $enfants = $plusieurs ? 'vos enfants' : 'votre enfant';
            $echue = $famille['enfants']->map(fn ($s) => $s['frais']?->derniereEcheanceEchue())->filter()->sortBy(fn ($e) => $e[3])->last();
            $precedentes = $famille['relances']->pluck('date_relance')->map->format('d/m/Y')->unique()->values();
            $echeance = $echue ? 'la ' . mb_strtolower($echue[1]) . ' (date limite : ' . $echue[3]->format('d/m/Y') . ')' : null;
        @endphp
        <div class="lettre niveau-{{ $niveau }}">
            <div class="entete">RÉPUBLIQUE DU TCHAD<br><i>Unité – Travail – Progrès</i></div>
            @if($logo)
                <img class="logo" src="{{ $logo }}" alt="{{ $nomEcole }}">
            @else
                <div class="nom-ecole">{{ $nomEcole }}</div>
            @endif

            <div class="lieu-date">{{ $ville }}, le {{ now()->format('d/m/Y') }}</div>

            <div class="destinataire">
                À l'attention de<br>
                <b>{{ $famille['destinataire'] }}</b><br>
                @if($famille['telephone']) Tél. {{ $famille['telephone'] }} @endif
            </div>

            <div class="objet"><span>Objet</span> : {{ $objets[$niveau] }}</div>

            <div class="corps">
                <p>Madame, Monsieur,</p>

                @if($niveau === 1)
                    <p>
                        Sauf erreur de notre part, nous constatons qu'à la date du {{ now()->format('d/m/Y') }},
                        la scolarité de {{ $enfants }} n'est pas entièrement réglée.
                        @if($echeance) L'échéance concernée est <b>{{ $echeance }}</b>. @endif
                    </p>
                @elseif($niveau === 2)
                    <p>
                        Malgré notre rappel du {{ $precedentes->last() ?? '—' }}, la scolarité de {{ $enfants }}
                        n'est toujours pas réglée à ce jour.
                        @if($echeance) L'échéance concernée est <b>{{ $echeance }}</b>. @endif
                    </p>
                @else
                    <p>
                        Malgré nos relances {{ $precedentes->isNotEmpty() ? 'des ' . $precedentes->implode(', ') : '' }},
                        la scolarité de {{ $enfants }} n'est toujours pas réglée.
                    </p>
                @endif

                <table>
                    <thead><tr><th>Élève</th><th>Classe</th><th class="m">Dû à ce jour</th><th class="m">Déjà versé</th><th class="m">Reste à payer</th></tr></thead>
                    <tbody>
                        @foreach($famille['enfants'] as $s)
                            <tr>
                                <td>{{ $s['eleve']->nom }} {{ $s['eleve']->prenom }}</td>
                                <td>{{ $s['eleve']->classe->nom ?? '—' }}</td>
                                <td class="m">{{ $fcfa($s['exigible']) }}</td>
                                <td class="m">{{ $fcfa($s['verse']) }}</td>
                                <td class="m"><b>{{ $fcfa($s['retard']) }}</b></td>
                            </tr>
                        @endforeach
                        @if($plusieurs)
                            <tr class="total"><td colspan="4">Total</td><td class="m">{{ $fcfa($famille['retard']) }}</td></tr>
                        @endif
                    </tbody>
                </table>

                @if($niveau === 3)
                    <p>
                        En conséquence, vous êtes prié(e) de <b>vous présenter à l'administration de l'école
                        au plus tard le {{ $dateLimite->format('d/m/Y') }}</b>, muni(e) de cette lettre, afin de
                        régulariser la somme de <b>{{ $fcfa($famille['retard']) }}</b> ou de convenir avec nous
                        d'un arrangement.
                    </p>
                    <p>Sans nouvelles de votre part à cette date, la direction se réserve le droit de prendre les mesures prévues par le règlement intérieur.</p>
                @else
                    <div class="montant">Montant à régler avant le {{ $dateLimite->format('d/m/Y') }} : {{ $fcfa($famille['retard']) }}</div>
                    <p>
                        @if($niveau === 1)
                            Nous vous prions de bien vouloir régulariser cette somme auprès de la caisse de l'école.
                            Si le paiement a déjà été effectué, merci de ne pas tenir compte de ce rappel et de présenter votre reçu.
                        @else
                            Nous vous demandons de régulariser cette situation sans tarder. À défaut, vous serez convoqué(e) à l'administration.
                        @endif
                    </p>
                @endif

                <p>Veuillez agréer, Madame, Monsieur, l'expression de nos salutations distinguées.</p>
            </div>

            <div class="signature">
                <div>La Direction<b>Le Directeur</b></div>
            </div>

            <div class="coupon">
                <b>Coupon à retourner à l'école</b> — {{ $objets[$niveau] }}<br>
                Je soussigné(e) ……………………………………………… parent de {{ $famille['enfants']->map(fn ($s) => $s['eleve']->prenom . ' ' . $s['eleve']->nom)->implode(', ') }},
                atteste avoir reçu cette lettre le ……/……/………… &nbsp;&nbsp; Signature :
            </div>
        </div>
    @endforeach
</body>
</html>
