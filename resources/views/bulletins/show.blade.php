@extends('layouts.app')
@section('title', 'Bulletin')

@section('content')
@php
    // Nombre à la française sans zéros inutiles : 15,5 / 1 / 12,75
    $nombre = fn ($v) => $v === null ? null : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');

    // Rappel des trimestres précédents : une colonne par trimestre déjà passé
    $rappels = $trimestre > 1 ? range(1, $trimestre - 1) : [];
    $nbColonnes = 8 + count($rappels);

    // Maternelle : toutes les matières dans une seule liste, sans catégories ni bilans
    $groupesAffiches = ($estMaternelle ?? false)
        ? (collect($lignes)->isEmpty() ? collect() : collect(['' => ['lignes' => $lignes]]))
        : $lignesParCategorie;

    // Informations du conseil de classe encore manquantes (rien ne doit être écrit à la main)
    $manquants = array_keys(array_filter([
        'note de conduite' => $conseil?->note_conduite === null,
        'appréciation générale du conseil' => blank($conseil?->appreciation),
    ]));
@endphp
<style>
    .bulletin-sheet, .bulletin-sheet * { font-family: Verdana, Geneva, sans-serif; }

    /* Taille du texte : Verdana 10 (collège, lycée), Verdana 9 au primaire */
    .bulletin-sheet{ --t:10px; font-size:var(--t); }
    .bulletin-sheet.primaire{ --t:9px; }
    /* Les cellules suivent cette taille (et non celle des tableaux du reste de l'application) */
    .bulletin-sheet td, .bulletin-sheet th{ font-size:inherit; }

    .bulletin-sheet{
        max-width:720px; margin:0 auto; background:#fff;
        padding:18px 24px; border-radius:6px; box-shadow:0 4px 24px rgba(0,0,0,0.15);
        color:#0d2b4e;
        -webkit-print-color-adjust:exact; print-color-adjust:exact; color-adjust:exact;
    }

    .bulletin-sheet .republique{
        text-align:center; font-size:calc(var(--t) - 2px); font-weight:bold; margin-bottom:4px; line-height:1.3; color:#0d2b4e;
    }
    .bulletin-sheet .republique .devise{ font-style:italic; font-weight:500; }

    .bulletin-sheet .entete{
        border-top:2px solid #0d4a8f; border-bottom:2px solid #0d4a8f;
        padding:2px 0; margin-bottom:6px;
    }
    /* En-tête réduit : le logo ne dépasse pas 62 px de haut */
    .bulletin-sheet .entete img{ width:100%; max-height:62px; object-fit:contain; display:block; }
    .bulletin-sheet .entete .nom-ecole{ text-align:center; font-weight:bold; font-size:14px; color:#0d4a8f; padding:6px 0; }
    .bulletin-sheet .entete .adresse{ font-size:9px; font-weight:normal; color:#5a6b80; }

    .bulletin-sheet .titre-bulletin{
        text-align:center; background:#eaf2fb; border:1px solid #0d4a8f;
        padding:3px; margin-bottom:8px; font-weight:bold; color:#0d4a8f; font-size:calc(var(--t) + 1.5px);
    }

    .bulletin-sheet .bloc-eleve{ display:flex; gap:14px; margin-bottom:10px; }
    .bulletin-sheet .photo-eleve{
        width:56px; height:68px; border-radius:5px; overflow:hidden; border:1px solid #cfe0f2;
        flex-shrink:0; background:#eaf2fb; display:flex; align-items:center; justify-content:center;
        font-size:7.5px; color:#7a92ab; text-align:center;
    }
    .bulletin-sheet .photo-eleve img{ width:100%; height:100%; object-fit:cover; }
    .bulletin-sheet .infos-eleve{
        display:grid; grid-template-columns:1fr 1fr; gap:2px 16px; font-size:var(--t); flex:1;
    }

    .bulletin-sheet table.notes{ width:100%; border-collapse:collapse; font-size:var(--t); margin-bottom:8px; }
    .bulletin-sheet table.notes, .bulletin-sheet table.notes th, .bulletin-sheet table.notes td{
        border:1px solid #0d4a8f;
    }
    .bulletin-sheet table.notes thead th{
        background:#0d4a8f; color:#fff; padding:3px 3px; text-align:center; font-size:calc(var(--t) - 1px);
    }
    .bulletin-sheet table.notes thead th:first-child{ text-align:left; }
    .bulletin-sheet table.notes tbody td{ padding:2px 3px; text-align:center; border:1px solid #9fb9d6; }
    /* Colonnes de nombres étroites ; les rappels des trimestres précédents en gris */
    .bulletin-sheet table.notes .col-n{ width:1%; white-space:nowrap; }
    .bulletin-sheet table.notes .col-rappel{ color:#5a6b80; background:#f7f9fc; }
    .bulletin-sheet table.notes thead th.col-rappel{ background:#3d6ea8; color:#fff; }
    .bulletin-sheet table.notes tbody td:first-child{ text-align:left; }
    .bulletin-sheet table.notes .ligne-categorie td{
        background:#eaf2fb; font-weight:bold; color:#0d4a8f; text-transform:uppercase; font-size:calc(var(--t) - 1.5px); letter-spacing:0.3px;
    }
    .bulletin-sheet table.notes .ligne-bilan td{ background:#0d4a8f; color:#fff; font-weight:bold; }

    /* Moyennes des trimestres, moyenne et rang annuels, sur une même ligne */
    .bulletin-sheet table.synthese{ width:100%; border-collapse:collapse; margin:8px 0; table-layout:fixed; }
    .bulletin-sheet table.synthese th, .bulletin-sheet table.synthese td{ border:1px solid #0d4a8f; text-align:center; padding:3px 4px; }
    .bulletin-sheet table.synthese th{ background:#eaf2fb; color:#0d4a8f; font-size:calc(var(--t) - 1.5px); text-transform:uppercase; }
    .bulletin-sheet table.synthese td{ font-size:calc(var(--t) + 1.5px); font-weight:bold; color:#0d4a8f; }
    .bulletin-sheet table.synthese td.actuel{ background:#f5f8fc; }
    .bulletin-sheet table.synthese td.decision-admis{ color:#2e7d4f; background:#eaf6ef; font-size:var(--t); }
    .bulletin-sheet table.synthese td.decision-redouble{ color:#a8503c; background:#fbe6df; font-size:var(--t); }
    .bulletin-sheet .date-bulletin{ text-align:center; margin-top:10px; }

    .bulletin-sheet .enseignant-matiere{ display:block; font-size:calc(var(--t) - 2.5px); color:#5a6b80; font-weight:normal; }
    .bulletin-sheet td.appreciation-matiere{ font-size:calc(var(--t) - 1px); white-space:nowrap; }

    .bulletin-sheet table.conseil{ width:100%; border-collapse:collapse; font-size:var(--t); margin:8px 0 4px; }
    .bulletin-sheet table.conseil th, .bulletin-sheet table.conseil td{ border:1px solid #0d4a8f; padding:5px 7px; vertical-align:top; }
    .bulletin-sheet table.conseil th{
        background:#eaf2fb; color:#0d4a8f; font-size:calc(var(--t) - 1.5px); text-transform:uppercase; text-align:left; width:22%;
    }
    .bulletin-sheet table.conseil td.valeur{ font-weight:bold; font-size:calc(var(--t) + 1px); }
    .bulletin-sheet table.conseil td.texte{ min-height:28px; height:30px; }
    .bulletin-sheet table.conseil .distinction{ text-align:center; font-weight:bold; font-size:calc(var(--t) + 1px); color:#0d4a8f; }
    .bulletin-sheet table.conseil .distinction-titre{ text-align:center; width:20%; }

    .bulletin-sheet table.notes .ligne-conduite td{ background:#f5f8fc; }
    .bulletin-sheet .alerte-conseil{
        background:#fbe6df; color:#8a3b2a; border:1px solid #e3b5a8; border-radius:6px;
        padding:8px 12px; font-size:12px; margin-bottom:10px;
    }

    .bulletin-sheet .pied{
        display:flex; justify-content:space-between; align-items:flex-end; margin-top:8px; font-size:var(--t); gap:10px;
    }
    .bulletin-sheet .pied .qr-bloc{ margin-top:8px; text-align:center; }
    .bulletin-sheet .pied .qr-bloc img{ width:52px; height:52px; }
    .bulletin-sheet .pied .qr-bloc span{ font-size:7px; color:#5a6b80; display:block; margin-top:1px; }
    .bulletin-sheet .pied .signature{ text-align:center; flex-shrink:0; width:160px; }
    .bulletin-sheet .pied .signature .ligne{
        margin-top:20px; border-top:1px solid #0d2b4e; padding-top:3px; font-weight:bold;
    }

    .bulletin-sheet .no-print{ text-align:center; margin-top:14px; }

    @media print {
        @page{ size:A4; margin:8mm; }
        * { -webkit-print-color-adjust:exact !important; print-color-adjust:exact !important; color-adjust:exact !important; }
        .bulletin-sheet{ box-shadow:none; max-width:100%; padding:0; }
        .no-print{ display:none !important; }
    }
</style>

<div class="bulletin-sheet {{ ($estPrimaire ?? false) ? 'primaire' : '' }}">

    @if(count($manquants))
        <div class="alerte-conseil no-print">
            Avant d'imprimer, complétez dans « Conseil de classe » : {{ implode(', ', $manquants) }}.
            @if(Route::has('conseil-classe.saisie') && auth()->user()?->peut('conseil_classe', 'modifier'))
                <a href="{{ route('conseil-classe.saisie', ['classe_id' => $eleve->classe_id, 'trimestre' => $trimestre]) }}">Ouvrir le conseil de classe</a>
            @endif
        </div>
    @endif

    @if($assiduite === null)
        <div class="alerte-conseil no-print">
            Les absences et retards ne peuvent pas être comptés : réglez les dates du {{ $trimestre }}{{ $trimestre == 1 ? 'er' : 'e' }} trimestre dans Paramètres.
        </div>
    @elseif($assiduite['heures'] === null && $assiduite['jours'] > 0)
        <div class="alerte-conseil no-print">
            La classe n'a pas d'emploi du temps : les absences sont comptées en jours et non en heures.
        </div>
    @endif

    <div class="republique">
        RÉPUBLIQUE DU TCHAD<br>
        <span class="devise">Unité – Travail – Progrès</span><br>
        MINISTÈRE DE L'ÉDUCATION NATIONALE ET DE LA PROMOTION CIVIQUE
    </div>

    <div class="entete">
        @if(\App\Support\Etablissement::banniere())
            <img src="{{ \App\Support\Etablissement::banniere() }}" alt="{{ \App\Support\Etablissement::nom() }}">
        @else
            <div class="nom-ecole">
                {{ \App\Support\Etablissement::nom() }}<br>
                <span class="adresse">{{ \App\Support\Etablissement::adresse() }}</span>
            </div>
        @endif
    </div>

    <div class="titre-bulletin">
        BULLETIN DE NOTES — {{ $trimestre }}{{ $trimestre == 1 ? 'er' : 'e' }} TRIMESTRE
        @if($trimestre == 3) (BILAN ANNUEL) @endif
        <br><span style="font-size:var(--t); font-weight:normal;">Année scolaire {{ $anneeScolaire ?? '—' }}</span>
    </div>

    <div class="bloc-eleve">
        <div class="photo-eleve">
            @if(\App\Helpers\Fichier::existe($eleve->photo))
                <img src="{{ \App\Helpers\Fichier::url($eleve->photo) }}" alt="Photo">
            @else
                Photo<br>élève
            @endif
        </div>
        <div class="infos-eleve">
            <div><b>Élève :</b> {{ $eleve->nom }} {{ $eleve->prenom }}</div>
            <div><b>Classe :</b> {{ $eleve->classe->nom }}</div>
            <div><b>Matricule :</b> {{ $eleve->matricule ?? '—' }}</div>
            <div><b>Né(e) le :</b> {{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }} @if($eleve->lieu_naissance) à {{ $eleve->lieu_naissance }} @endif</div>
            <div style="grid-column:1 / -1;"><b>Professeur principal :</b> {{ $profPrincipal ?? '—' }}</div>
        </div>
    </div>

    <table class="notes">
        <thead>
            <tr>
                <th>Matière</th>
                <th class="col-n">Coef</th>
                <th class="col-n">Dev.</th>
                <th class="col-n">Compo</th>
                @foreach($rappels as $t)
                    <th class="col-n col-rappel">T{{ $t }}</th>
                @endforeach
                <th class="col-n">Moy. T{{ $trimestre }}</th>
                <th class="col-n">Points</th>
                <th class="col-n">Rang</th>
                <th>Appréciation</th>
            </tr>
        </thead>
        <tbody>
            {{-- Maternelle : pas de catégories ni de bilans, toutes les matières dans une seule liste --}}
            @forelse($groupesAffiches as $categorie => $groupe)
                @if(! ($estMaternelle ?? false))
                <tr class="ligne-categorie">
                    <td colspan="{{ $nbColonnes }}">{{ $categorie }}</td>
                </tr>
                @endif
                @foreach($groupe['lignes'] as $ligne)
                    <tr>
                        <td>
                            {{ $ligne['matiere'] }}
                            @if($ligne['enseignant'] ?? null)
                                <span class="enseignant-matiere">{{ $ligne['enseignant'] }}</span>
                            @endif
                        </td>
                        <td>{{ $ligne['coefficient'] }}</td>
                        <td>{{ $nombre($ligne['moyenne_classe']) ?? '—' }}</td>
                        <td>{{ $nombre($ligne['moyenne_composition']) ?? '—' }}</td>
                        @foreach($rappels as $t)
                            <td class="col-rappel">{{ $nombre($ligne['rappel'][$t] ?? null) ?? '—' }}</td>
                        @endforeach
                        <td><b>{{ $nombre($ligne['moyenne_matiere']) ?? '—' }}</b></td>
                        <td>{{ $nombre($ligne['moyenne_coeff']) ?? '—' }}</td>
                        <td>{{ $ligne['rang_matiere'] ?? '—' }}</td>
                        <td class="appreciation-matiere">{{ $ligne['appreciation'] ?? '—' }}</td>
                    </tr>
                @endforeach
                @if(! ($estMaternelle ?? false))
                <tr class="ligne-bilan">
                    <td>Bilan {{ strtolower($categorie) }}</td>
                    <td>{{ $groupe['total_coefficients'] }}</td>
                    <td colspan="{{ 3 + count($rappels) }}"></td>
                    <td colspan="2">{{ $groupe['bilan'] ?? '—' }} / 20</td>
                    <td>{{ \App\Helpers\Appreciation::pour($groupe['bilan']) ?? '' }}</td>
                </tr>
                @endif
            @empty
                <tr><td colspan="{{ $nbColonnes }}">Aucune note saisie pour ce trimestre.</td></tr>
            @endforelse

            {{-- Conduite : note du conseil, comptée dans la moyenne avec le coefficient fixé par l'administration --}}
            @if(! ($estMaternelle ?? false))
            <tr class="ligne-categorie">
                <td colspan="{{ $nbColonnes }}">Conduite</td>
            </tr>
            @endif
            <tr class="ligne-conduite">
                <td>Conduite</td>
                <td>{{ $nombre($conduite['coefficient']) }}</td>
                <td>—</td>
                <td>—</td>
                @foreach($rappels as $t)
                    <td class="col-rappel">{{ $nombre($conduite['rappel'][$t] ?? null) ?? '—' }}</td>
                @endforeach
                <td><b>{{ $nombre($conduite['note']) ?? '—' }}</b></td>
                <td>{{ $nombre($conduite['moyenne_coeff']) ?? '—' }}</td>
                <td>{{ $conduite['rang'] ?? '—' }}</td>
                <td class="appreciation-matiere" style="white-space:normal;">{{ $conduite['appreciation'] ?: '—' }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Moyennes des trimestres sur une même ligne, puis moyenne et rang annuels au 3e trimestre --}}
    <table class="synthese">
        <tr>
            <th>Moyenne T1</th>
            @if($trimestre >= 2) <th>Moyenne T2</th> @endif
            @if($trimestre >= 3) <th>Moyenne T3</th> @endif
            <th>Rang T{{ $trimestre }}</th>
            @if($trimestre >= 3)
                <th>Moyenne annuelle</th>
                <th>Rang annuel</th>
                <th>Décision</th>
            @endif
        </tr>
        <tr>
            <td class="{{ $trimestre == 1 ? 'actuel' : '' }}">{{ ($trimestre == 1 ? $moyenneGenerale : $moyenneT1) ?? '—' }}</td>
            @if($trimestre >= 2) <td class="{{ $trimestre == 2 ? 'actuel' : '' }}">{{ ($trimestre == 2 ? $moyenneGenerale : $moyenneT2) ?? '—' }}</td> @endif
            @if($trimestre >= 3) <td class="actuel">{{ $moyenneGenerale ?? '—' }}</td> @endif
            <td>{{ $rang ?? '—' }} / {{ $effectifClasse ?? '—' }}</td>
            @if($trimestre >= 3)
                <td>{{ $moyenneAnnuelle ?? '—' }}</td>
                <td>{{ $rangAnnuel ?? '—' }} / {{ $effectifClasse ?? '—' }}</td>
                <td class="{{ $decisionAnnuelle === 'Admis(e) en classe supérieure' ? 'decision-admis' : ($decisionAnnuelle ? 'decision-redouble' : '') }}">{{ $decisionAnnuelle ?? '—' }}</td>
            @endif
        </tr>
    </table>

    {{-- Décisions du conseil de classe (saisies dans "Conseil de classe") --}}
    <table class="conseil">
        <tr>
            <th>Absences</th>
            <td class="valeur">
                @if($assiduite === null)
                    —
                @elseif($assiduite['heures'] !== null)
                    {{ $nombre($assiduite['heures']) }} h
                @else
                    {{ $assiduite['jours'] }} jour{{ $assiduite['jours'] > 1 ? 's' : '' }}
                @endif
            </td>
            <th>Retards</th>
            <td class="valeur">{{ $assiduite === null ? '—' : $assiduite['retards'] }}</td>
        </tr>
        <tr>
            <th>Appréciation générale du conseil de classe</th>
            <td colspan="2" class="texte">{{ $conseil?->appreciation ?: '—' }}</td>
            <td class="distinction">
                <div style="font-size:calc(var(--t) - 1.5px); text-transform:uppercase; color:#5a6b80; font-weight:normal;">Distinction</div>
                {{ $distinction ?? '—' }}
            </td>
        </tr>
    </table>

    <div class="date-bulletin">Fait à N'Djamena, le {{ now()->format('d/m/Y') }}</div>

    <div class="pied">
        <div>
            @if (class_exists(\App\Helpers\QrAuthenticite::class) && \Illuminate\Support\Facades\Route::has('verification.bulletin'))
                <div class="qr-bloc">
                    @php
                        $urlVerif = \App\Helpers\QrAuthenticite::lien('verification.bulletin', ['eleve' => $eleve->id, 'trimestre' => $trimestre]);
                    @endphp
                    <img src="{{ \App\Helpers\QrAuthenticite::imageUrl($urlVerif, 80) }}" alt="QR authenticité">
                    <span>Scanner pour vérifier l'authenticité</span>
                </div>
            @endif
        </div>
        <div class="signature">
            <div class="ligne">{{ ($estPrimaire ?? ! $estCollege) ? 'Le Directeur du Primaire' : 'Le Principal' }}</div>
        </div>
    </div>

    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Imprimer ce bulletin</button>
    </div>
</div>
@endsection
