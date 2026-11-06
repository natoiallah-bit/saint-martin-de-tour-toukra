@extends('layouts.app')
@section('title', 'Comptabilité')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Comptabilité générale <span style="font-size:15px; color:#5a5a5a;">— {{ $anneeScolaire }}{{ $mois ? ' · ' . (new \App\Models\Paie(['mois' => $mois]))->mois_libelle : '' }}</span></h1>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            @if(Route::has('tresorerie.index'))
                <a href="{{ route('tresorerie.index') }}" class="btn" style="background:#243365; color:#fff;">🏦 Caisse et banque</a>
            @endif
            @droit('comptabilite', 'modifier')
            <a href="{{ route('operations.create') }}" class="btn">+ Enregistrer une opération</a>
            @enddroit
        </div>
    </div>

    <form method="GET" style="margin-bottom:16px; display:flex; gap:14px; flex-wrap:wrap; align-items:flex-end;">
        <div><label>Filtrer par mois</label>
            <input type="month" name="mois" value="{{ $mois }}" onchange="this.form.submit()" style="max-width:220px;"></div>
        @if($mois) <div style="padding-bottom:8px;"><a href="{{ route('operations.index', array_filter(['cycle' => request('cycle')])) }}">Voir toute l'année</a></div> @endif
        @if($cycleImpose)
            <div style="padding-bottom:8px;">Cycle : <b>{{ $cycleImpose->nom }}</b> (vous ne voyez que les opérations de votre cycle)</div>
        @else
            <div><label>Cycle</label>
                <select name="cycle" onchange="this.form.submit()" style="max-width:220px;">
                    <option value="">Toute l'école</option>
                    @foreach($cycles as $c) <option value="{{ $c->id }}" @selected((string) $filtreCycle === (string) $c->id)>{{ $c->nom }}</option> @endforeach
                    <option value="commun" @selected($filtreCycle === 'commun')>Commun (Direction)</option>
                </select></div>
        @endif
    </form>

    <div style="font-size:13px; color:#5a5a5a; margin-bottom:10px;">Les totaux portent sur l'année scolaire {{ $anneeScolaire }}{{ $mois ? ', pour le mois choisi' : '' }}.</div>
    @if($salairesManuels)
        <div style="background:#fbe6df; color:#7a2e1c; padding:10px 14px; border-radius:8px; margin-bottom:14px; font-weight:600;">
            ⚠️ {{ $salairesManuels }} dépense(s) « Salaires » ont été saisies à la main. Les salaires sont désormais ajoutés automatiquement depuis la Paie du personnel :
            supprimez ces saisies manuelles si elles correspondent à des bulletins de paie, pour ne pas compter deux fois.
        </div>
    @endif
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-bottom:24px;">
        <div style="background:#e2ede6; border-radius:10px; padding:16px;">
            <div style="font-size:11px; text-transform:uppercase; color:#4c7a63; font-weight:bold;">Recettes totales</div>
            <div style="font-size:22px; font-weight:bold; color:#141c33;">{{ number_format($totalRecettes, 0, ',', ' ') }} FCFA</div>
            <div style="font-size:11px; color:#5a5a5a; margin-top:4px;">dont {{ number_format($totalScolarite, 0, ',', ' ') }} FCFA de scolarité</div>
        </div>
        <div style="background:#fbe6df; border-radius:10px; padding:16px;">
            <div style="font-size:11px; text-transform:uppercase; color:#a8503c; font-weight:bold;">Dépenses totales</div>
            <div style="font-size:22px; font-weight:bold; color:#141c33;">{{ number_format($totalDepenses, 0, ',', ' ') }} FCFA</div>
        </div>
        <div style="background:#141c33; border-radius:10px; padding:16px;">
            <div style="font-size:11px; text-transform:uppercase; color:#c9973b; font-weight:bold;">Solde (reste)</div>
            <div style="font-size:22px; font-weight:bold; color:#fff;">{{ number_format($solde, 0, ',', ' ') }} FCFA</div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px;">
        <div>
            <h3 style="color:#4c7a63;">Recettes par catégorie</h3>
            <table>
                <thead><tr><th>Catégorie</th><th>Montant</th></tr></thead>
                <tbody>
                    <tr><td>Scolarité (élèves)</td><td>{{ number_format($totalScolarite, 0, ',', ' ') }} FCFA</td></tr>
                    @forelse($recettesParCategorie as $cat => $montant)
                        <tr><td>{{ $cat }}</td><td>{{ number_format($montant, 0, ',', ' ') }} FCFA</td></tr>
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <h3 style="color:#a8503c;">Dépenses par catégorie</h3>
            <table>
                <thead><tr><th>Catégorie</th><th>Montant</th></tr></thead>
                <tbody>
                    @forelse($depensesParCategorie as $cat => $montant)
                        <tr><td>{{ $cat }}</td><td>{{ number_format($montant, 0, ',', ' ') }} FCFA</td></tr>
                    @empty
                        <tr><td colspan="2">Aucune dépense enregistrée.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h3>Historique des opérations</h3>
    <table>
        <thead>
            <tr><th>Date</th><th>Type</th><th>Catégorie</th><th>Cycle</th><th>Mode</th><th>Description</th><th>Pièce</th><th>Montant</th><th>Actions</th></tr>

        </thead>
        <tbody>
            @forelse($operations as $op)
                <tr>
                    <td>{{ $op->date_operation->format('d/m/Y') }}</td>
                    <td>{{ $op->type === 'recette' ? '↗ Recette' : '↘ Dépense' }}</td>
                    <td>{{ $op->categorie }}</td>
                    <td>{{ $op->cycle->nom ?? 'Commun' }}</td>
                     <td>{{ $op->mode_paiement ?? '—' }}</td>
    <td>{{ $op->description ?? '—' }} @if($op->paie_id) <span style="font-size:11px; background:#eef1f8; color:#243365; padding:2px 6px; border-radius:10px; font-weight:700;">Paie</span> @endif</td>
    <td>
        @if($op->piece_justificative)
            <a href="{{ \App\Helpers\Fichier::url($op->piece_justificative) }}" target="_blank" class="btn">Voir</a>
        @else
            —
        @endif
    </td>
                    <td style="color:{{ $op->type === 'recette' ? '#4c7a63' : '#a8503c' }}; font-weight:bold;">
                        {{ $op->type === 'recette' ? '+' : '−' }} {{ number_format($op->montant, 0, ',', ' ') }} FCFA
                    </td>
                    <td class="actions">
                        @if($op->paie_id)
                            @droit('paie') <a href="{{ route('paies.bulletin', $op->paie_id) }}" class="btn">Bulletin</a> @enddroit
                        @else
                            @droit('comptabilite', 'modifier')
                            <a href="{{ route('operations.edit', $op) }}" class="btn">Corriger</a>
                            @enddroit
                            @droit('comptabilite', 'supprimer')
                            <form action="{{ route('operations.destroy', $op) }}" method="POST" style="display:inline;" onsubmit="return demanderMotif(this, 'Supprimer cette opération ?');">
                                @csrf @method('DELETE')
                                <input type="hidden" name="motif">
                                <button type="submit" class="btn btn-danger">Supprimer</button>
                            </form>
                            @enddroit
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">Aucune opération sur cette période. (Les paiements de scolarité s'affichent uniquement dans les totaux ci-dessus.)</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px;">{{ $operations->links() }}</div>
@endsection
