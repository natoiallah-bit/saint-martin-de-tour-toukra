@extends('layouts.app')
@section('title', 'Mon espace parent')

@section('content')
<h1>Mon espace — {{ auth()->user()->name }}</h1>

{{-- Alertes du conseil de discipline, à confirmer par le parent --}}
@foreach ($alertesDiscipline as $d)
    <div style="background:#fbe6df; border:2px solid #a8503c; border-radius:10px; padding:14px 18px; margin-bottom:16px;">
        <div style="font-size:16px; font-weight:700; color:#8b2e1a;">
            ⚠️ {{ $d->estDecide() && $d->convocation_lue_le ? 'Décision du conseil de discipline' : 'Convocation au conseil de discipline' }} — {{ $d->eleve->prenom }} {{ $d->eleve->nom }}
        </div>
        <div style="margin-top:6px;"><b>Motif :</b> {{ $d->motif }}</div>
        @if ($d->date_conseil)
            <div><b>Conseil :</b> le {{ $d->date_conseil->format('d/m/Y à H:i') }}{{ $d->lieu ? ' — ' . $d->lieu : '' }}. Votre présence est demandée.</div>
        @endif
        @if ($d->estDecide())
            <div style="margin-top:4px;"><b>Décision :</b> {{ $d->libelle_decision }} (le {{ $d->date_decision?->format('d/m/Y') }}){{ $d->decision_details ? ' — ' . $d->decision_details : '' }}</div>
        @endif
        <form action="{{ route('discipline.lu', $d) }}" method="POST" style="margin-top:10px;">
            @csrf
            <button type="submit" class="btn" style="background:#8b2e1a; color:#fff;">J'ai pris connaissance de ce message</button>
        </form>
    </div>
@endforeach

@forelse ($cartes as $carte)
    @php $eleve = $carte['eleve']; @endphp
    <div style="background:#fff; border-radius:10px; padding:18px 22px; margin-bottom:18px; box-shadow:0 2px 10px rgba(0,0,0,0.06);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:12px;">
            <div>
                <div style="font-size:17px; font-weight:bold; color:#141c33;">{{ $eleve->nom }} {{ $eleve->prenom }}</div>
                <div style="font-size:12.5px; color:#5b5d6b;">Classe : {{ $eleve->classe->nom ?? '—' }}</div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                @if ($aCarteScolaire)
                    <a href="{{ route('eleves.carte', $eleve) }}" class="btn">Voir la carte scolaire</a>
                @endif
            </div>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:10px; margin-bottom:14px;">
            <div style="background:#eaf2fb; border-radius:8px; padding:10px; text-align:center;">
                <div style="font-size:9.5px; text-transform:uppercase; color:#5b5d6b;">Frais annuel</div>
                <div style="font-weight:bold;">{{ number_format($carte['montant_annuel'], 0, ',', ' ') }} FCFA</div>
            </div>
            <div style="background:#eaf2fb; border-radius:8px; padding:10px; text-align:center;">
                <div style="font-size:9.5px; text-transform:uppercase; color:#5b5d6b;">Déjà versé</div>
                <div style="font-weight:bold;">{{ number_format($carte['verse'], 0, ',', ' ') }} FCFA</div>
            </div>
            @if ($eleve->exonere)
                <div style="background:#4c7a63; color:#fff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:9.5px; text-transform:uppercase;">Restant à payer</div>
                    <div style="font-weight:bold;">Exonéré(e)</div>
                </div>
            @else
                <div style="background:#a8503c; color:#fff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:9.5px; text-transform:uppercase;">Restant à payer</div>
                    <div style="font-weight:bold;">{{ number_format($carte['restant'], 0, ',', ' ') }} FCFA</div>
                </div>
            @endif
        </div>

        @if ($certificats->has($eleve->id))
            <div style="background:#eef1f8; border:1px solid #c9d3ea; border-radius:8px; padding:10px 12px; margin-bottom:14px;">
                <div style="font-weight:bold; color:#141c33; margin-bottom:6px;">📄 Certificats d'inscription</div>
                @foreach ($certificats[$eleve->id] as $certificat)
                    <div style="display:flex; justify-content:space-between; gap:10px; padding:4px 0; font-size:13.5px;">
                        <span>N° {{ $certificat->numero }} · délivré le {{ $certificat->date_delivrance->format('d/m/Y') }}</span>
                        <a href="{{ route('certificats.imprimer', $certificat) }}" style="font-weight:bold;">Voir / imprimer</a>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($dossiersDiscipline->has($eleve->id))
            <div style="margin-bottom:14px;">
                <b style="font-size:12.5px; color:#5b5d6b;">Conseil de discipline :</b>
                @foreach ($dossiersDiscipline->get($eleve->id) as $d)
                    <div style="font-size:13px; margin-top:4px;">
                        {{ $d->date_conseil?->format('d/m/Y') ?? $d->created_at->format('d/m/Y') }} — {{ $d->motif }} :
                        <b>{{ $d->libelle_decision ?? 'décision en attente' }}</b>
                    </div>
                @endforeach
            </div>
        @endif

        @if ($aBulletins)
            <div style="margin-bottom:14px;">
                <b style="font-size:12.5px; color:#5b5d6b;">Bulletins :</b>
                @foreach ([1, 2, 3] as $trimestre)
                    <a href="{{ route('bulletins.show', ['eleve' => $eleve->id, 'trimestre' => $trimestre]) }}" style="margin-left:8px;">
                        {{ $trimestre }}{{ $trimestre == 1 ? 'er' : 'e' }} trimestre
                    </a>
                @endforeach
            </div>
        @endif

        <div>
            <b style="font-size:12.5px; color:#5b5d6b;">Historique des paiements :</b>
            @if ($carte['historique']->isEmpty())
                <div style="font-size:12.5px; color:#999; margin-top:6px;">Aucun paiement enregistré pour le moment.</div>
            @else
                <table style="width:100%; border-collapse:collapse; margin-top:8px; font-size:12px;">
                    <thead>
                        <tr style="background:#eaf2fb; text-align:left;">
                            <th style="padding:6px 8px;">Date</th>
                            <th style="padding:6px 8px;">Objet</th>
                            <th style="padding:6px 8px;">Mode</th>
                            <th style="padding:6px 8px; text-align:right;">Montant</th>
                            <th style="padding:6px 8px;">N° reçu</th>
                            @if ($aRecu)
                                <th style="padding:6px 8px;"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($carte['historique'] as $paiement)
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:6px 8px;">{{ \Illuminate\Support\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</td>
                                <td style="padding:6px 8px;">{{ $paiement->libelle_types }}</td>
                                <td style="padding:6px 8px;">{{ $paiement->libelle_mode_paiement }}</td>
                                <td style="padding:6px 8px; text-align:right;">{{ number_format($paiement->montant, 0, ',', ' ') }} FCFA</td>
                                <td style="padding:6px 8px;">{{ $paiement->numero_recu }}</td>
                                @if ($aRecu)
                                    <td style="padding:6px 8px;">
                                        <a href="{{ route('paiements.recu', $paiement) }}" target="_blank">Voir le reçu</a>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
@empty
    <div style="background:#fff; border-radius:10px; padding:20px; text-align:center; color:#999;">
        Aucun enfant n'est encore rattaché à ce compte. Contacte le secrétariat du {{ \App\Support\Etablissement::nom() }}.
    </div>
@endforelse
@endsection
