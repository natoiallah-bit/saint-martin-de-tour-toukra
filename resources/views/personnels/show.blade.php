@extends('layouts.app')
@section('title', $personnel->nom . ' ' . $personnel->prenom)

@section('content')
@php $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA'; @endphp
<style>
    .fiche{ display:grid; grid-template-columns:200px 1fr; gap:22px; background:#fff; border:1px solid #e3ddcf; border-radius:12px; padding:20px; }
    @media (max-width:800px){ .fiche{ grid-template-columns:1fr; } }
    .fiche .photo{ width:180px; height:220px; border-radius:10px; overflow:hidden; background:#f1efe9; display:flex; align-items:center; justify-content:center; color:#999; }
    .fiche .photo img{ width:100%; height:100%; object-fit:cover; }
    .rubriques{ display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:16px; margin-top:18px; }
    .rubrique{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:14px 16px; }
    .rubrique h3{ margin-bottom:8px; }
    .ligne{ display:flex; justify-content:space-between; gap:10px; padding:5px 0; border-bottom:1px solid #f1ede4; font-size:14px; }
    .ligne span:first-child{ color:#6b6b6b; }
    .ligne span:last-child{ font-weight:600; text-align:right; }
    .actions-fiche{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; background:#fff; border:1px solid #e3ddcf; border-radius:12px; padding:12px 14px; margin-bottom:16px; }
    .form-certificat{ flex-basis:100%; display:flex; flex-wrap:wrap; gap:8px; align-items:center; border-top:1px solid #f1ede4; padding-top:10px; }
    .form-certificat input{ flex:1; min-width:220px; margin:0; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <h1>{{ $personnel->nom }} {{ $personnel->prenom }}</h1>
    <div>
        @droit('personnel') <a href="{{ route('personnels.index') }}">← Retour à la liste</a> @else <a href="{{ url()->previous() }}">← Retour</a> @enddroit
    </div>
</div>

{{-- Documents et actions sur le membre du personnel, selon les droits de chacun --}}
@if(auth()->user()->peut('personnel') || auth()->user()->peut('paie', 'modifier') || auth()->user()->peut('attestations_travail', 'modifier'))
<div class="actions-fiche">
    @droit('personnel') <a href="{{ route('personnels.badge', $personnel) }}" class="btn" style="background:#4c7a63; color:#fff;">🪪 Badge</a> @enddroit
    @droit('personnel') <a href="{{ route('personnels.documents.index', $personnel) }}" class="btn" style="background:#243365; color:#fff;">📁 Documents</a> @enddroit
    @droit('personnel', 'modifier') <a href="{{ route('personnels.edit', $personnel) }}" class="btn">✏️ Modifier</a> @enddroit
    @if($personnel->actif)
        @droit('paie', 'modifier')
            <a href="{{ route('paies.create', ['personnel_id' => $personnel->id]) }}" class="btn" style="background:#6b4a8a; color:#fff;">💵 Établir une paie</a>
        @enddroit
    @endif
    @droit('personnel', 'supprimer')
        <form action="{{ route('personnels.destroy', $personnel) }}" method="POST" style="display:inline; margin-left:auto;" onsubmit="return confirm('Supprimer définitivement ce membre du personnel ?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
        </form>
    @enddroit
    @droit('attestations_travail', 'modifier')
        <form action="{{ route('attestations.store', $personnel) }}" method="POST" class="form-certificat">
            @csrf
            <span style="font-weight:700;">📄 Certificat de travail</span>
            <button type="submit" class="btn" style="background:#141c33; color:#fff;">Délivrer le certificat</button>
        </form>
    @enddroit
</div>
@endif

<div class="fiche">
    <div class="photo">
        @if(\App\Helpers\Fichier::existe($personnel->photo))
            <img src="{{ \App\Helpers\Fichier::url($personnel->photo) }}" alt="Photo">
        @else
            Pas de photo
        @endif
    </div>
    <div>
        <div style="font-size:20px; font-weight:700; color:#141c33;">{{ $personnel->fonction ?: $personnel->libelle_type }}</div>
        <div style="margin:6px 0 12px; color:#5a5a5a;">
            {{ $personnel->matricule }} · {{ $personnel->libelle_type }} ·
            <b style="color:{{ $personnel->actif ? '#3f6b55' : '#a8503c' }};">{{ $personnel->actif ? 'Actif' : 'Inactif' }}</b>
        </div>
        <div class="ligne"><span>Statut</span><span>{{ $personnel->libelle_statut }}</span></div>
        <div class="ligne"><span>Date d'embauche</span><span>{{ $personnel->date_embauche?->format('d/m/Y') ?? '—' }}</span></div>
        <div class="ligne"><span>Ancienneté</span><span>{{ $personnel->anciennete ?? '—' }}</span></div>
        <div class="ligne"><span>Cycle d'affectation</span><span>{{ $personnel->cycle->nom ?? 'Commun (toute l\'école)' }}</span></div>
        <div class="ligne"><span>Téléphone</span><span>{{ $personnel->telephone ?: '—' }}</span></div>
        <div class="ligne"><span>Email</span><span>{{ $personnel->email ?: '—' }}</span></div>
    </div>
</div>

<div class="rubriques">
    @if($voirPaie)
        <div class="rubrique">
            <h3>Rémunération</h3>
            @if($personnel->paiement_horaire)
                <div class="ligne"><span>Mode</span><span>À l'heure</span></div>
                <div class="ligne"><span>Taux horaire</span><span>{{ $fcfa($personnel->taux_horaire ?? 0) }}</span></div>
            @else
                <div class="ligne"><span>Salaire de base</span><span>{{ $fcfa($personnel->salaire_base ?? 0) }}</span></div>
            @endif
            <div style="margin-top:8px; font-weight:700;">Dernières paies</div>
            @forelse($personnel->paies as $paie)
                <div class="ligne"><span><a href="{{ route('paies.bulletin', $paie) }}">{{ $paie->mois_libelle }}</a></span><span>{{ $fcfa($paie->net_a_payer) }}</span></div>
            @empty
                <div style="color:#888; font-size:13px;">Aucune paie enregistrée.</div>
            @endforelse
        </div>
    @endif

    <div class="rubrique">
        <h3>Présence ce mois-ci</h3>
        @php $p = $pointagesMois->countBy('statut'); @endphp
        @forelse($p as $statut => $nombre)
            <div class="ligne"><span>{{ ucfirst($statut) }}</span><span>{{ $nombre }} jour(s)</span></div>
        @empty
            <div style="color:#888; font-size:13px;">Aucun pointage ce mois-ci.</div>
        @endforelse
    </div>

    @if($creneaux->isNotEmpty())
        <div class="rubrique">
            <h3>Emploi du temps ({{ $creneaux->count() }} cours / semaine)</h3>
            @foreach($creneaux->groupBy('jour') as $jour => $liste)
                <div class="ligne"><span>{{ ucfirst($jour) }}</span>
                    <span>{{ $liste->map(fn ($c) => substr($c->heure_debut, 0, 5) . ' ' . ($c->classe->nom ?? '') . ' · ' . ($c->matiere->nom ?? ''))->implode(' | ') }}</span></div>
            @endforeach
        </div>
    @endif

    @isset($attestations)
        <div class="rubrique">
            <h3>📄 Certificats de travail</h3>
            @forelse($attestations as $a)
                <div class="ligne">
                    <span><a href="{{ route('attestations.imprimer', $a) }}">{{ $a->numero }}</a> · {{ $a->date_delivrance->format('d/m/Y') }}</span>
                    <span>
                        @droit('attestations_travail', 'supprimer')
                            <form action="{{ route('attestations.destroy', $a) }}" method="POST" style="display:inline;" onsubmit="return confirm('Annuler ce certificat ?');">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none; border:0; color:#a8503c; cursor:pointer; font-weight:700;" title="Annuler">✕</button>
                            </form>
                        @enddroit
                    </span>
                </div>
            @empty
                <div style="color:#888; font-size:13px;">Aucun certificat délivré.</div>
            @endforelse
        </div>
    @endisset

    @droit('personnel')
    <div class="rubrique">
        <h3>Accès et documents</h3>
        <div class="ligne"><span>Compte de connexion</span>
            <span>{{ $comptes->isEmpty() ? 'Aucun' : $comptes->map(fn ($u) => $u->email . ' (' . $u->libelle_roles . ')')->implode(', ') }}</span></div>
        <div class="ligne"><span>Documents (contrat, diplômes…)</span>
            <span><a href="{{ route('personnels.documents.index', $personnel) }}">{{ $documents }} document(s)</a></span></div>
        <div class="ligne"><span>Badge</span><span><a href="{{ route('personnels.badge', $personnel) }}">Imprimer le badge</a></span></div>
    </div>
    @enddroit
</div>
@endsection
