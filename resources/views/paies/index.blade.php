@extends('layouts.app')
@section('title', 'Paie')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Bulletins de paie</h1>
        @droit('paie', 'modifier')
        <a href="{{ route('paies.create', ['mois' => $mois]) }}" class="btn">+ Générer un bulletin</a>
        @enddroit
    </div>

    <form method="GET" style="margin-bottom:16px;">
        <label>Mois</label>
        <input type="month" name="mois" value="{{ $mois }}" onchange="this.form.submit()" style="max-width:220px;">
    </form>

    <table>
        <thead>
            <tr>
                <th>Personnel</th>
                <th>Salaire de base</th>
                <th>Primes</th>
                <th>Acompte</th>
                <th>Retenues</th>
                <th>Net à payer</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($paies as $paie)
                <tr>
                    <td>{{ $paie->personnel->nom }} {{ $paie->personnel->prenom }}</td>
                    <td>{{ number_format($paie->salaire_base, 0, ',', ' ') }}</td>
                    <td>{{ number_format($paie->primes, 0, ',', ' ') }}</td>
                    <td>{{ number_format($paie->acompte, 0, ',', ' ') }}</td>
                    <td>{{ number_format($paie->retenues, 0, ',', ' ') }}</td>
                    <td><b>{{ number_format($paie->net_a_payer, 0, ',', ' ') }} FCFA</b></td>
                    <td class="actions">
                        <a href="{{ route('paies.bulletin', $paie) }}" class="btn">Bulletin</a>
                        @droit('paie', 'supprimer')
                        <form action="{{ route('paies.destroy', $paie) }}" method="POST" style="display:inline;" onsubmit="return demanderMotif(this, 'Supprimer ce bulletin ? Le salaire sera retiré des dépenses.');">
                            <input type="hidden" name="motif">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucun bulletin généré pour ce mois.</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($personnelsSansPaie->count())
        <div style="margin-top:20px; background:#f6ead0; border:1px solid #c9973b; padding:12px 16px; border-radius:8px; font-size:13px;">
            <b>{{ $personnelsSansPaie->count() }} membre(s) du personnel sans bulletin ce mois-ci :</b>
            {{ $personnelsSansPaie->pluck('nom')->implode(', ') }}
        </div>
    @endif
@endsection
