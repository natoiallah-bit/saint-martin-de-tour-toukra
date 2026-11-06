@extends('layouts.app')
@section('title', 'Historique des relances')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>Historique des relances — {{ $annee }}</h1>
        <a href="{{ route('convocations.retards.index') }}" class="btn">Retour aux impayés</a>
    </div>

    <table>
        <thead><tr><th>Date</th><th>Élève</th><th>Classe</th><th>Lettre</th><th>Montant dû</th><th>À régler avant le</th><th>Par</th></tr></thead>
        <tbody>
            @forelse($relances as $r)
                <tr>
                    <td>{{ $r->date_relance->format('d/m/Y') }}</td>
                    <td>{{ $r->eleve->nom ?? '—' }} {{ $r->eleve->prenom ?? '' }}</td>
                    <td>{{ $r->eleve->classe->nom ?? '—' }}</td>
                    <td><b @if($r->niveau === 3) style="color:#a8503c;" @endif>{{ $r->libelle_niveau }}</b></td>
                    <td>{{ number_format($r->montant_du, 0, ',', ' ') }} FCFA</td>
                    <td>{{ $r->date_limite?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $auteurs[$r->envoyee_par] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune relance enregistrée cette année.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px;">{{ $relances->links() }}</div>
@endsection
