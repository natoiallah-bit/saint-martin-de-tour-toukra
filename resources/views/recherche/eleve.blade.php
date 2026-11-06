@extends('layouts.app')
@section('title', $eleve->nom . ' ' . $eleve->prenom)

@section('content')
@php
    $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA';
    $note = fn ($m) => $m === null ? '—' : number_format($m, 2, ',', ' ');
    $user = auth()->user();
@endphp
<style>
    .fiche{ display:grid; grid-template-columns:170px 1fr; gap:22px; background:#fff; border:1px solid #e3ddcf; border-radius:12px; padding:20px; }
    @media (max-width:800px){ .fiche{ grid-template-columns:1fr; } }
    .fiche .photo{ width:150px; height:185px; border-radius:10px; overflow:hidden; background:#f1efe9; display:flex; align-items:center; justify-content:center; color:#999; }
    .fiche .photo img{ width:100%; height:100%; object-fit:cover; }
    .rubriques{ display:grid; grid-template-columns:repeat(auto-fit, minmax(270px, 1fr)); gap:16px; margin-top:18px; }
    .rubrique{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:14px 16px; }
    .rubrique h3{ margin-bottom:8px; }
    .ligne{ display:flex; justify-content:space-between; gap:10px; padding:5px 0; border-bottom:1px solid #f1ede4; font-size:14px; }
    .ligne span:first-child{ color:#6b6b6b; }
    .ligne span:last-child{ font-weight:600; text-align:right; }
    .actions-fiche{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; background:#fff; border:1px solid #e3ddcf; border-radius:12px; padding:12px 14px; margin-bottom:16px; }
    .form-certificat{ flex-basis:100%; display:flex; flex-wrap:wrap; gap:8px; align-items:center; border-top:1px solid #f1ede4; padding-top:10px; }
    .form-certificat input{ flex:1; min-width:220px; margin:0; }
    .alerte-sante{ background:#fbe6df; color:#a8503c; padding:8px 12px; border-radius:6px; font-weight:700; margin-top:8px; }
</style>

<div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
    <h1>{{ $eleve->nom }} {{ $eleve->prenom }}</h1>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ $user->peut('eleves') ? route('eleves.index') : url()->previous() }}">← Retour à la liste</a>
    </div>
</div>

{{-- Documents et actions sur l'élève, selon les droits de chacun --}}
<div class="actions-fiche">
    @droit('eleves') <a href="{{ route('eleves.carte', $eleve) }}" class="btn" style="background:#4c7a63; color:#fff;">🪪 Carte scolaire</a> @enddroit
    @droit('documents_eleves') <a href="{{ route('eleves.documents.index', $eleve) }}" class="btn" style="background:#243365; color:#fff;">📁 Documents</a> @enddroit
    @droit('eleves', 'modifier') <a href="{{ route('eleves.edit', $eleve) }}" class="btn">✏️ Modifier</a> @enddroit
    @droit('discipline', 'modifier') <a href="{{ route('discipline.create', ['eleve_id' => $eleve->id]) }}" class="btn" style="background:#6b4a8a; color:#fff;">⚖️ Conseil de discipline</a> @enddroit
    @droit('eleves', 'supprimer')
        <form action="{{ route('eleves.destroy', $eleve) }}" method="POST" style="display:inline; margin-left:auto;" onsubmit="return confirm('Supprimer définitivement cet élève ?');">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger">🗑️ Supprimer</button>
        </form>
    @enddroit
    @droit('certificats', 'modifier')
        @if($eleve->classe_id)
            <form action="{{ route('certificats.store', $eleve) }}" method="POST" class="form-certificat">
                @csrf
                <span style="font-weight:700;">📄 Certificat d'inscription</span>
                <button type="submit" class="btn" style="background:#141c33; color:#fff;">Délivrer et envoyer au parent</button>
            </form>
        @endif
    @enddroit
</div>

<div class="fiche">
    <div class="photo">
        @if(\App\Helpers\Fichier::existe($eleve->photo))
            <img src="{{ \App\Helpers\Fichier::url($eleve->photo) }}" alt="Photo">
        @else
            Pas de photo
        @endif
    </div>
    <div>
        <div style="font-size:18px; font-weight:700; color:#141c33;">{{ $eleve->classe->nom ?? 'Sans classe' }} @if($eleve->classe?->cycle) · {{ $eleve->classe->cycle->nom }} @endif</div>
        <div style="margin:4px 0 10px; color:#5a5a5a;">Matricule {{ $eleve->matricule ?? '—' }}{{ $eleve->exonere ? ' · Exonéré' : '' }}</div>
        <div class="ligne"><span>Sexe</span><span>{{ $eleve->sexe === 'F' ? 'Féminin' : ($eleve->sexe === 'M' ? 'Masculin' : '—') }}</span></div>
        <div class="ligne"><span>Né(e) le</span><span>{{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }}{{ $eleve->lieu_naissance ? ' à ' . $eleve->lieu_naissance : '' }}</span></div>
        <div class="ligne"><span>Date d'entrée dans l'établissement</span><span>{{ $eleve->date_entree?->format('d/m/Y') ?? '—' }}</span></div>
        <div class="ligne"><span>Ancienne école</span><span>{{ $eleve->ancienne_ecole ?: '—' }}</span></div>
        <div class="ligne"><span>Quartier</span><span>{{ $eleve->quartier ?: '—' }}</span></div>
    </div>
</div>

<div class="rubriques">
    <div class="rubrique">
        <h3>👨‍👩‍👧 Famille</h3>
        <div class="ligne"><span>Père</span><span>{{ $eleve->pere_nom ?: '—' }} {{ $eleve->pere_telephone ? '· ' . $eleve->pere_telephone : '' }}</span></div>
        <div class="ligne"><span>Mère</span><span>{{ $eleve->mere_nom ?: '—' }} {{ $eleve->mere_telephone ? '· ' . $eleve->mere_telephone : '' }}</span></div>
        <div class="ligne"><span>Tuteur</span><span>{{ $eleve->tuteur_nom ?: '—' }} {{ $eleve->tuteur_telephone ? '· ' . $eleve->tuteur_telephone : '' }}</span></div>
        @isset($cartesRetrait)
            <div class="ligne"><span>Carte de retrait</span><span>{{ $cartesRetrait->isEmpty() ? 'Aucune' : $cartesRetrait->pluck('numero')->implode(', ') }}</span></div>
        @endisset
    </div>

    <div class="rubrique">
        <h3>🩺 Santé et aptitude physique</h3>
        <div class="ligne"><span>Aptitude (EPS)</span><span>{{ $eleve->libelle_aptitude }}</span></div>
        @if($eleve->aptitude_physique !== 'apte')
            <div class="alerte-sante">
                {{ $eleve->libelle_aptitude }}@if($eleve->aptitude_jusqu_au) jusqu'au {{ $eleve->aptitude_jusqu_au->format('d/m/Y') }}@endif
                @if($eleve->aptitude_remarque) — {{ $eleve->aptitude_remarque }} @endif
            </div>
        @endif
    </div>

    @isset($moyennes)
        <div class="rubrique">
            <h3>📊 Résultats {{ $annee }}</h3>
            @foreach($moyennes as $t => $m)
                <div class="ligne"><span>{{ $t }}{{ $t == 1 ? 'er' : 'e' }} trimestre</span>
                    <span>{{ $note($m) }} @if($m !== null) <a href="{{ route('bulletins.show', [$eleve, $t]) }}" style="font-weight:400;">bulletin</a> @endif</span></div>
            @endforeach
            @php $valeurs = $moyennes->filter(fn ($m) => $m !== null); @endphp
            @if($valeurs->count()) <div class="ligne"><span>Moyenne de l'année (à ce jour)</span><span>{{ $note(round($valeurs->avg(), 2)) }}</span></div> @endif
        </div>
    @endisset

    @isset($assiduite)
        <div class="rubrique">
            <h3>🕒 Assiduité</h3>
            @foreach($assiduite as $t => $a)
                <div class="ligne"><span>{{ $t }}{{ $t == 1 ? 'er' : 'e' }} trimestre</span>
                    <span>@if($a === null) — @else {{ $a['heures'] !== null ? $a['heures'] . ' h' : $a['jours'] . ' j' }} d'absence · {{ $a['retards'] }} retard(s) @endif</span></div>
            @endforeach
        </div>
    @endisset

    @isset($discipline)
        <div class="rubrique">
            <h3>⚖️ Conseil de discipline</h3>
            @forelse($discipline as $d)
                <div class="ligne"><span><a href="{{ route('discipline.show', $d) }}">{{ $d->date_conseil?->format('d/m/Y') ?? $d->created_at->format('d/m/Y') }}</a> — {{ $d->motif }}</span>
                    <span>{{ $d->libelle_decision ?? 'En attente' }}</span></div>
            @empty
                <div style="color:#888; font-size:13px;">Jamais traduit au conseil de discipline.</div>
            @endforelse
        </div>
    @endisset

    @isset($certificats)
        <div class="rubrique">
            <h3>📄 Certificats d'inscription</h3>
            @forelse($certificats as $c)
                <div class="ligne">
                    <span><a href="{{ route('certificats.imprimer', $c) }}">{{ $c->numero }}</a> · {{ $c->date_delivrance->format('d/m/Y') }}</span>
                    <span>{{ $c->classe_nom }}
                        @droit('certificats', 'supprimer')
                            <form action="{{ route('certificats.destroy', $c) }}" method="POST" style="display:inline;" onsubmit="return confirm('Annuler ce certificat ?');">
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

    @isset($finances)
        <div class="rubrique">
            <h3>💰 Scolarité {{ $annee }}</h3>
            <div class="ligne"><span>Frais annuels</span><span>{{ $fcfa($finances['annuel']) }}</span></div>
            <div class="ligne"><span>Déjà versé</span><span>{{ $fcfa($finances['verse']) }}</span></div>
            <div class="ligne"><span>Reste à payer</span><span>{{ $eleve->exonere ? 'Exonéré' : $fcfa(max($finances['annuel'] - $finances['verse'], 0)) }}</span></div>
            @if(! $eleve->exonere && $finances['exigible'] - $finances['verse'] > 0)
                <div class="alerte-sante">En retard à ce jour : {{ $fcfa($finances['exigible'] - $finances['verse']) }}</div>
            @endif
            @foreach($finances['derniers'] as $p)
                <div class="ligne"><span><a href="{{ route('paiements.recu', $p) }}">{{ $p->numero_recu }}</a> · {{ $p->date_paiement->format('d/m/Y') }}</span><span>{{ $fcfa($p->montant) }}</span></div>
            @endforeach
        </div>
    @endisset
</div>
@endsection
