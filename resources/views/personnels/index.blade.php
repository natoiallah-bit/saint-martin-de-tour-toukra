@extends('layouts.app')
@section('title', 'Personnel')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Personnel</h1>
        <div>
            <a href="{{ route('personnels.badges') }}" class="btn" style="background:#4c7a63; color:#fff;">🪪 Badges de tout le personnel</a>
            @droit('personnel', 'modifier')
            <a href="{{ route('personnels.create') }}" class="btn">+ Ajouter un membre</a>
            @enddroit
        </div>
    </div>

    {{-- Recherche et filtres --}}
    <form method="GET" style="margin-bottom:16px; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
        <input type="search" name="q" value="{{ $recherche }}" placeholder="🔎 Rechercher : nom, prénom, matricule, téléphone, fonction…" style="max-width:360px; margin:0;">
        <select name="statut" onchange="this.form.submit()" style="max-width:180px; margin:0;">
            <option value="">— Tous les statuts —</option>
            @foreach(\App\Models\Personnel::STATUTS as $code => $libelle) <option value="{{ $code }}" @selected(request('statut') === $code)>{{ $libelle }}</option> @endforeach
        </select>
        <select name="cycle" onchange="this.form.submit()" style="max-width:200px; margin:0;">
            <option value="">— Tous les cycles —</option>
            @foreach($cycles as $c) <option value="{{ $c->id }}" @selected(request('cycle') == $c->id)>{{ $c->nom }}</option> @endforeach
            <option value="commun" @selected(request('cycle') === 'commun')>Commun</option>
        </select>
        <select name="type" onchange="this.form.submit()" style="max-width:280px; padding:8px; border:1px solid #e3ddcf; border-radius:6px;">
            <option value="">— Tous les types —</option>
            <option value="enseignant" @selected(request('type')=='enseignant')>Enseignant</option>
            <option value="administratif" @selected(request('type')=='administratif')>Administratif</option>
            <option value="securite" @selected(request('type')=='securite')>Sécurité</option>
            <option value="entretien" @selected(request('type')=='entretien')>Entretien</option>
            <option value="autre" @selected(request('type')=='autre')>Autre</option>
        </select>
        <button type="submit" class="btn">Rechercher</button>
        @if(request()->hasAny(['q', 'statut', 'cycle', 'type'])) <a href="{{ route('personnels.index') }}">Effacer</a> @endif
        <span style="margin-left:auto; color:#666; font-size:13px;">{{ $personnels->total() }} membre(s)</span>
    </form>

    <table>
        <thead>
            <tr>
                <th>Matricule</th>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Type</th>
                <th>Fonction</th>
                <th>Statut</th>
                <th>Cycle</th>
                <th>Embauche</th>
                <th>État</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($personnels as $p)
                <tr>
                    <td>{{ $p->matricule }}</td>
                    <td><a href="{{ route('personnels.show', $p) }}"><b>{{ $p->nom }}</b></a></td>
                    <td>{{ $p->prenom }}</td>
                    <td>{{ $p->libelle_type }}</td>
                    <td>{{ $p->fonction ?? '—' }}</td>
                    <td>{{ $p->libelle_statut }}</td>
                    <td>{{ $p->cycle->nom ?? 'Commun' }}</td>
                    <td>{{ $p->date_embauche?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $p->actif ? 'Actif' : 'Inactif' }}</td>
                    <td class="actions">
                        <a href="{{ route('personnels.show', $p) }}" class="btn" style="background:#141c33; color:#fff;">📋 Fiche</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10">Aucun membre du personnel trouvé.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;">{{ $personnels->links() }}</div>
@endsection
