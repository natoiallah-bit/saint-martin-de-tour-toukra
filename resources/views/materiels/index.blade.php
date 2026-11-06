@extends('layouts.app')
@section('title', 'Logistique')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Logistique / Matériel</h1>
        @droit('logistique', 'modifier')
        <a href="{{ route('materiels.create') }}" class="btn">+ Ajouter un matériel</a>
        @enddroit
    </div>

    <form method="GET" style="margin-bottom:12px;">
        @if($cycleImpose)
            Cycle : <b>{{ $cycleImpose->nom }}</b> (vous ne voyez que le matériel de votre cycle)
        @else
            <label>Cycle</label>
            <select name="cycle" onchange="this.form.submit()" style="max-width:240px;">
                <option value="">Toute l'école</option>
                @foreach($cycles as $c) <option value="{{ $c->id }}" @selected((string) $filtreCycle === (string) $c->id)>{{ $c->nom }}</option> @endforeach
                <option value="commun" @selected($filtreCycle === 'commun')>Commun (Direction)</option>
            </select>
        @endif
    </form>

    <div style="background:#141c33; color:#fff; padding:14px 18px; border-radius:8px; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
        <span>Valeur totale du parc</span>
        <b style="font-size:18px; color:#c9973b;">{{ number_format($valeurTotale, 0, ',', ' ') }} FCFA</b>
    </div>

    <h3>Répartition par catégorie</h3>
    <table style="margin-bottom:20px;">
        <thead><tr><th>Catégorie</th><th>Quantité totale</th><th>Valeur totale</th></tr></thead>
        <tbody>
            @forelse($parCategorie as $cat => $info)
                <tr>
                    <td>{{ $cat }}</td>
                    <td>{{ $info['quantite'] }}</td>
                    <td>{{ number_format($info['valeur'], 0, ',', ' ') }} FCFA</td>
                </tr>
            @empty
                <tr><td colspan="3">Aucun matériel enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h3>Inventaire détaillé</h3>
    <form method="GET" style="margin-bottom:16px;">
        @if($filtreCycle && ! $cycleImpose) <input type="hidden" name="cycle" value="{{ $filtreCycle }}"> @endif
        <select name="categorie" onchange="this.form.submit()" style="max-width:260px; padding:8px; border:1px solid #e3ddcf; border-radius:6px;">
            <option value="">— Toutes les catégories —</option>
            @foreach(\App\Models\Materiel::categories() as $cat)
                <option value="{{ $cat }}" @selected(request('categorie')==$cat)>{{ $cat }}</option>
            @endforeach
        </select>
    </form>

    <table>
        <thead>
            <tr><th>Nom</th><th>Catégorie</th><th>Cycle</th><th>Quantité</th><th>État</th><th>Localisation</th><th>Valeur totale</th><th>Actions</th></tr>
        </thead>
        <tbody>
            @forelse($materiels as $m)
                <tr>
                    <td>{{ $m->nom }}</td>
                    <td>{{ $m->categorie }}</td>
                    <td>{{ $m->cycle->nom ?? 'Commun' }}</td>
                    <td>{{ $m->quantite }}</td>
                    <td>{{ $m->libelle_etat }}</td>
                    <td>{{ $m->localisation ?? '—' }}</td>
                    <td>{{ number_format($m->valeur_totale, 0, ',', ' ') }} FCFA</td>
                    <td class="actions">
                        @droit('logistique', 'modifier')
                        <a href="{{ route('materiels.edit', $m) }}" class="btn">Modifier</a>
                        @enddroit
                        @droit('logistique', 'supprimer')
                        <form action="{{ route('materiels.destroy', $m) }}" method="POST" style="display:inline;" onsubmit="return confirm('Retirer ce matériel ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Retirer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">Aucun matériel enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div style="margin-top:16px;">{{ $materiels->links() }}</div>
@endsection
