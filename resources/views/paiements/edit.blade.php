@extends('layouts.app')
@section('title', 'Corriger le paiement ' . $paiement->numero_recu)

@section('content')
    <h1>Corriger le paiement {{ $paiement->numero_recu }}</h1>
    <p style="margin-bottom:14px;">
        Élève : <b>{{ $paiement->eleve->nom }} {{ $paiement->eleve->prenom }}</b> — {{ $paiement->eleve->classe->nom ?? 'Sans classe' }} · Année {{ $paiement->annee_scolaire }}<br>
        <span style="font-size:13px; color:#5a5a5a;">Le numéro de reçu ne change pas. Paiement enregistré pour le mauvais élève ? Supprimez-le (avec un motif) puis saisissez-le à nouveau.</span>
    </p>

    <form action="{{ route('paiements.update', $paiement) }}" method="POST" style="max-width:640px;">
        @csrf @method('PUT')

        <label>Type(s) de paiement</label>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
            @foreach ($typesDisponibles as $cle => $libelle)
                <label style="display:flex; gap:8px; align-items:center; font-weight:400; margin:0;">
                    <input type="checkbox" name="types[]" value="{{ $cle }}" style="width:auto; margin:0;" @checked(in_array($cle, old('types', $paiement->types ?? [])))> {{ $libelle }}
                </label>
            @endforeach
        </div>

        <label>Montant (FCFA)</label>
        <input type="number" name="montant" min="1" step="1" value="{{ old('montant', (float) $paiement->montant) }}" required>

        <label>Date du paiement</label>
        <input type="date" name="date_paiement" value="{{ old('date_paiement', \Carbon\Carbon::parse($paiement->date_paiement)->format('Y-m-d')) }}" required>

        <label>Mode de paiement</label>
        <select name="mode_paiement" required>
            @foreach (['especes' => 'Espèces', 'cheque' => 'Chèque', 'virement' => 'Virement bancaire', 'mobile_money' => 'Mobile Money'] as $cle => $libelle)
                <option value="{{ $cle }}" @selected(old('mode_paiement', $paiement->mode_paiement) === $cle)>{{ $libelle }}</option>
            @endforeach
        </select>

        <label>Description (optionnel)</label>
        <input type="text" name="description" value="{{ old('description', $paiement->description) }}" maxlength="255">

        <label>Motif de la correction (obligatoire)</label>
        <input type="text" name="motif" value="{{ old('motif') }}" maxlength="255" required placeholder="Ex : montant mal saisi (25 000 au lieu de 20 000)">
        <div style="font-size:12px; color:#a8503c; margin-top:4px;">La correction, son motif et les anciennes valeurs sont gardés dans le journal consulté par la Direction. Le reçu indiquera la date de correction.</div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer la correction</button>
            <a href="{{ route('paiements.index') }}">Annuler</a>
        </div>
    </form>
@endsection
