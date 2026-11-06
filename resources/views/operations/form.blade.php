@extends('layouts.app')
@section('title', $operation->exists ? 'Corriger une opération' : 'Enregistrer une opération')

@section('content')
    <h1>{{ $operation->exists ? 'Corriger une opération' : 'Enregistrer une opération' }}</h1>

    <form action="{{ $operation->exists ? route('operations.update', $operation) : route('operations.store') }}" method="POST" enctype="multipart/form-data" style="max-width:640px;">
        @csrf
        @if($operation->exists) @method('PUT') @endif

        <label>Type</label>
        <select name="type" id="type-select" required>
            <option value="recette" @selected(old('type', $operation->type) === 'recette')>Recette (argent qui entre)</option>
            <option value="depense" @selected(old('type', $operation->type) === 'depense')>Dépense (argent qui sort)</option>
        </select>

        <label>Catégorie</label>
        <input type="text" name="categorie" id="categorie" list="categories-{{ old('type', $operation->type) }}" value="{{ old('categorie', $operation->categorie) }}" maxlength="100" required placeholder="Choisissez dans la liste ou tapez une catégorie">
        <datalist id="categories-recette">@foreach($categoriesRecette as $cat)<option value="{{ $cat }}">@endforeach</datalist>
        <datalist id="categories-depense">@foreach($categoriesDepense as $cat)<option value="{{ $cat }}">@endforeach</datalist>
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Les salaires ne se saisissent pas ici : ils sont ajoutés automatiquement depuis la Paie du personnel.</div>

        @if($cycles->isNotEmpty())
            <label>Cycle</label>
            <select name="cycle_id">
                <option value="">Commun (toute l'école)</option>
                @foreach($cycles as $c) <option value="{{ $c->id }}" @selected(old('cycle_id', $operation->cycle_id) == $c->id)>{{ $c->nom }}</option> @endforeach
            </select>
        @endif

        <label>Montant (FCFA)</label>
        <input type="number" name="montant" min="1" step="any" value="{{ old('montant', $operation->montant !== null ? (float) $operation->montant : '') }}" required>

        <label>Mode de paiement</label>
        <select name="mode_paiement" required>
            @foreach($modesPaiement as $mode)
                <option value="{{ $mode }}" @selected(old('mode_paiement', $operation->mode_paiement) === $mode)>{{ $mode }}</option>
            @endforeach
        </select>
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Espèces → caisse · Chèque ou virement → banque · Mobile Money → compte Mobile Money.</div>

        <label>Date</label>
        <input type="date" name="date_operation" value="{{ old('date_operation', $operation->date_operation?->format('Y-m-d')) }}" required>

        <label>Description (optionnel)</label>
        <input type="text" name="description" value="{{ old('description', $operation->description) }}" placeholder="Ex : Achat de craies et cahiers">

        <label>Pièce justificative (optionnel — PDF, JPG ou PNG, max 5 Mo)</label>
        @if($operation->piece_justificative)
            <div style="font-size:13px; margin-bottom:4px;">Pièce actuelle : <a href="{{ \App\Helpers\Fichier::url($operation->piece_justificative) }}" target="_blank">voir</a> (choisissez un fichier pour la remplacer)</div>
        @endif
        <input type="file" name="piece_justificative" accept=".pdf,.jpg,.jpeg,.png">

        @if($operation->exists)
            <label>Motif de la correction (obligatoire)</label>
            <input type="text" name="motif" value="{{ old('motif') }}" maxlength="255" required placeholder="Ex : erreur de montant à la saisie">
            <div style="font-size:12px; color:#a8503c; margin-top:4px;">La correction, son motif et les anciennes valeurs sont gardés dans le journal consulté par la Direction.</div>
        @endif

        <div style="margin-top:20px;">
            <button type="submit" class="btn">{{ $operation->exists ? 'Enregistrer la correction' : 'Enregistrer' }}</button>
            <a href="{{ route('operations.index') }}">Annuler</a>
        </div>
    </form>

    <script>
        document.getElementById('type-select').addEventListener('change', function () {
            document.getElementById('categorie').setAttribute('list', 'categories-' + this.value);
        });
    </script>
@endsection
