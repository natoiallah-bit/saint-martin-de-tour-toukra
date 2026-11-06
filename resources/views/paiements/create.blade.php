@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto py-6">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Nouveau paiement</h1>
        <a href="{{ route('paiements.index') }}" class="text-gray-500 hover:underline">&larr; Retour</a>
    </div>

    @if ($errors->any())
        <div class="bg-red-100 text-red-800 px-4 py-3 rounded-lg mb-4">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('paiements.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Élève *</label>
            <select name="eleve_id" id="eleve_id" required class="w-full border rounded-lg px-3 py-2">
                <option value="">-- Sélectionner un élève --</option>
                @foreach ($eleves as $eleve)
                    <option value="{{ $eleve->id }}" @selected(old('eleve_id') == $eleve->id)>
                        {{ $eleve->nom }} {{ $eleve->prenom }} — {{ $eleve->classe->nom ?? 'Sans classe' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div id="statut-eleve" class="hidden bg-gray-50 border rounded-lg p-4 grid grid-cols-4 gap-4 text-sm">
            <div>
                <div class="text-xs text-gray-500 uppercase">Frais annuel</div>
                <div class="font-bold font-mono" id="statut-annuel">—</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase">Déjà versé</div>
                <div class="font-bold font-mono" id="statut-verse">—</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase">Restant à payer</div>
                <div class="font-bold font-mono text-clay-600" id="statut-restant">—</div>
            </div>
            <div>
                <div class="text-xs text-gray-500 uppercase">En retard à ce jour</div>
                <div class="font-bold font-mono text-clay-600" id="statut-retard">—</div>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-2">Type(s) de paiement *</label>
            <div class="grid grid-cols-2 gap-3">
                @foreach ($typesDisponibles as $cle => $libelle)
                    <label class="flex items-center gap-2 border rounded-lg px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="types[]" value="{{ $cle }}" @checked(is_array(old('types')) && in_array($cle, old('types')))>
                        <span>{{ $libelle }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Montant (FCFA) *</label>
                <input type="number" step="1" min="1" name="montant" value="{{ old('montant') }}" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Date du paiement *</label>
                <input type="date" name="date_paiement" value="{{ old('date_paiement', date('Y-m-d')) }}" required class="w-full border rounded-lg px-3 py-2">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Mode de paiement *</label>
            <select name="mode_paiement" required class="w-full border rounded-lg px-3 py-2">
                <option value="especes" @selected(old('mode_paiement') == 'especes')>Espèces</option>
                <option value="cheque" @selected(old('mode_paiement') == 'cheque')>Chèque</option>
                <option value="virement" @selected(old('mode_paiement') == 'virement')>Virement bancaire</option>
                <option value="mobile_money" @selected(old('mode_paiement') == 'mobile_money')>Mobile Money</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Pièce justificative (optionnel)</label>
            <input type="file" name="piece_justificative" accept=".pdf,.jpg,.jpeg,.png" class="w-full border rounded-lg px-3 py-2">
            <p class="text-xs text-gray-400 mt-1">PDF, JPG ou PNG — 5 Mo max.</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Description / Note</label>
            <textarea name="description" rows="2" maxlength="255" class="w-full border rounded-lg px-3 py-2">{{ old('description') }}</textarea>
        </div>

        <button type="submit" class="w-full bg-indigo-800 text-white py-3 rounded-lg font-semibold hover:bg-indigo-900">
            Enregistrer le paiement
        </button>
    </form>
</div>

<script>
document.getElementById('eleve_id').addEventListener('change', function () {
    const eleveId = this.value;
    const box = document.getElementById('statut-eleve');

    if (!eleveId) {
        box.classList.add('hidden');
        return;
    }

    fetch(`{{ url('/paiements/statut') }}/${eleveId}`)
        .then(res => res.json())
        .then(data => {
            document.getElementById('statut-annuel').textContent = Number(data.montant_annuel).toLocaleString('fr-FR') + ' FCFA';
            document.getElementById('statut-verse').textContent = Number(data.verse).toLocaleString('fr-FR') + ' FCFA';
            document.getElementById('statut-restant').textContent = data.exonere
                ? 'Exonéré'
                : Number(data.restant).toLocaleString('fr-FR') + ' FCFA';
            document.getElementById('statut-retard').textContent = data.exonere
                ? '—'
                : Number(data.en_retard).toLocaleString('fr-FR') + ' FCFA';
            box.classList.remove('hidden');
        })
        .catch(() => box.classList.add('hidden'));
});
</script>
@endsection
