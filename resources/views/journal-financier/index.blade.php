@extends('layouts.app')
@section('title', 'Journal des corrections')

@section('content')
@php
    $champs = ['montant' => 'Montant', 'mode_paiement' => 'Mode', 'date_paiement' => 'Date', 'date_operation' => 'Date', 'types' => 'Objet du paiement',
        'description' => 'Description', 'type' => 'Type', 'categorie' => 'Catégorie', 'cycle_id' => 'Cycle', 'salaire_base' => 'Salaire de base',
        'nombre_heures' => 'Heures', 'primes' => 'Primes', 'acompte' => 'Acompte', 'retenues' => 'Retenues', 'net_a_payer' => 'Net à payer',
        'compte_source' => 'De', 'compte_destination' => 'Vers', 'date_mouvement' => 'Date', 'compte' => 'Compte'];
    $valeur = fn ($v) => is_numeric($v) ? number_format((float) $v, 0, ',', ' ') : ($v === null || $v === '' ? '—' : $v);
@endphp
    <h1>Journal des corrections financières</h1>
    <p style="color:#5a5a5a; margin-bottom:14px;">Chaque correction ou suppression d'un paiement, d'une opération, d'une paie ou d'un versement est gardée ici avec son auteur, son motif et les anciennes valeurs. Ce journal ne peut pas être modifié.</p>

    <form method="GET" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end; margin-bottom:16px;">
        <div><label>Donnée</label>
            <select name="objet" onchange="this.form.submit()">
                <option value="">Toutes</option>
                @foreach(\App\Models\JournalFinancier::OBJETS as $cle => $libelle) <option value="{{ $cle }}" @selected(request('objet') === $cle)>{{ $libelle }}</option> @endforeach
            </select></div>
        <div><label>Action</label>
            <select name="action" onchange="this.form.submit()">
                <option value="">Toutes</option>
                <option value="modification" @selected(request('action') === 'modification')>Corrections</option>
                <option value="suppression" @selected(request('action') === 'suppression')>Suppressions</option>
            </select></div>
        <div><label>Du</label><input type="date" name="du" value="{{ request('du') }}"></div>
        <div><label>Au</label><input type="date" name="au" value="{{ request('au') }}"></div>
        <div><button type="submit" class="btn">Filtrer</button></div>
    </form>

    <table>
        <thead><tr><th>Date</th><th>Par</th><th>Action</th><th>Donnée</th><th>Détail</th><th>Motif</th></tr></thead>
        <tbody>
            @forelse($journal as $j)
                <tr>
                    <td style="white-space:nowrap;">{{ $j->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $j->auteur->name ?? '—' }}</td>
                    <td><b style="color:{{ $j->action === 'suppression' ? '#a8503c' : '#9a6b12' }};">{{ $j->action === 'suppression' ? 'Suppression' : 'Correction' }}</b></td>
                    <td>{{ $j->libelle_objet }}<br><span style="font-size:12px; color:#5a5a5a;">{{ $j->reference }}{{ $j->cycle ? ' · ' . $j->cycle->nom : '' }}</span></td>
                    <td style="font-size:13px;">
                        @foreach(($j->avant ?? []) as $champ => $ancien)
                            <div>{{ $champs[$champ] ?? $champ }} :
                                <s style="color:#a8503c;">{{ $valeur($ancien) }}</s>
                                @if($j->action !== 'suppression') → <b style="color:#3f6b55;">{{ $valeur($j->apres[$champ] ?? null) }}</b> @endif
                            </div>
                        @endforeach
                    </td>
                    <td>{{ $j->motif }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Aucune correction ni suppression enregistrée.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px;">{{ $journal->links() }}</div>
@endsection
