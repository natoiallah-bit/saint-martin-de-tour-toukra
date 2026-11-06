@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto py-6">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Paiements — Scolarité</h1>
        @droit('scolarite', 'modifier')
        <a href="{{ route('paiements.create') }}" class="bg-indigo-800 text-white px-4 py-2 rounded-lg hover:bg-indigo-900">
            + Nouveau paiement
        </a>
        @enddroit
    </div>

    @if (session('success'))
        <div class="bg-green-100 text-green-800 px-4 py-3 rounded-lg mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-xl shadow p-5 mb-6">
        <form method="GET" class="flex items-end gap-3">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Filtrer par élève</label>
                <select name="eleve_id" class="w-full border rounded-lg px-3 py-2">
                    <option value="">-- Tous les élèves --</option>
                    @foreach ($eleves as $eleve)
                        <option value="{{ $eleve->id }}" @selected(request('eleve_id') == $eleve->id)>
                            {{ $eleve->nom }} {{ $eleve->prenom }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase mb-1">Année scolaire</label>
                <select name="annee" class="border rounded-lg px-3 py-2">
                    @foreach ($annees->push($annee)->unique()->filter(fn ($a) => $a !== 'toutes')->sortDesc() as $a)
                        <option value="{{ $a }}" @selected($annee === $a)>{{ $a }}</option>
                    @endforeach
                    <option value="toutes" @selected($annee === 'toutes')>Toutes les années</option>
                </select>
            </div>
            <button class="bg-gray-200 px-4 py-2 rounded-lg hover:bg-gray-300">Filtrer</button>
            @if (request('eleve_id') || request('annee'))
                <a href="{{ route('paiements.index') }}" class="px-4 py-2 text-gray-500 hover:underline">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="bg-indigo-950 text-white rounded-xl p-5 mb-6 flex items-center justify-between">
        <span class="uppercase text-xs tracking-wide text-gray-300">Total encaissé — {{ $annee === 'toutes' ? 'toutes les années' : $annee }}</span>
        <span class="text-2xl font-bold font-mono">{{ number_format($totalEncaisse, 0, ',', ' ') }} FCFA</span>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-100 text-gray-600 uppercase text-xs">
                <tr>
                    <th class="px-4 py-3 text-left">Reçu N°</th>
                    <th class="px-4 py-3 text-left">Élève</th>
                    <th class="px-4 py-3 text-left">Classe</th>
                    <th class="px-4 py-3 text-left">Type(s)</th>
                    <th class="px-4 py-3 text-right">Montant</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($paiements as $paiement)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-mono text-xs">{{ $paiement->numero_recu }}</td>
                        <td class="px-4 py-3 font-semibold">{{ $paiement->eleve->nom ?? '—' }} {{ $paiement->eleve->prenom ?? '' }}</td>
                        <td class="px-4 py-3">{{ $paiement->eleve->classe->nom ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $paiement->libelle_types }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ number_format($paiement->montant, 0, ',', ' ') }}</td>
                        <td class="px-4 py-3">{{ $paiement->libelle_mode_paiement }}</td>
                        <td class="px-4 py-3">{{ \Carbon\Carbon::parse($paiement->date_paiement)->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-center space-x-2">
                            <a href="{{ route('paiements.recu', $paiement) }}" class="text-indigo-700 hover:underline" target="_blank">Reçu</a>
                            @droit('scolarite', 'modifier')
                            <a href="{{ route('paiements.edit', $paiement) }}" class="text-indigo-700 hover:underline">Corriger</a>
                            @enddroit
                            @droit('scolarite', 'supprimer')
                            <form action="{{ route('paiements.destroy', $paiement) }}" method="POST" class="inline" onsubmit="return demanderMotif(this, 'Supprimer le paiement {{ $paiement->numero_recu }} ?');">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="motif">
                                <button class="text-red-600 hover:underline">Suppr.</button>
                            </form>
                            @enddroit
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-400">Aucun paiement enregistré.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $paiements->links() }}
    </div>
</div>
@endsection
