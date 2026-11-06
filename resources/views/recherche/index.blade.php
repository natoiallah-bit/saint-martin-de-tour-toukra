@extends('layouts.app')
@section('title', 'Recherche')

@section('content')
    @include('cours._style')
    <h1>Recherche{{ $q !== '' ? ' : « ' . $q . ' »' : '' }}</h1>

    <form method="GET" style="display:flex; gap:10px; margin-bottom:18px; max-width:620px;">
        <input type="search" name="q" value="{{ $q }}" placeholder="Nom, prénom, matricule, téléphone…" minlength="2" required autofocus style="margin:0;">
        <button type="submit" class="btn">Rechercher</button>
    </form>

    @if(mb_strlen($q) < 2)
        <p class="vide">Tapez au moins 2 lettres.</p>
    @else
        @if(\App\Http\Controllers\RechercheController::peutRechercherEleves(auth()->user()))
            <div class="bloc">
                <h3>🎓 Élèves ({{ $eleves->count() }})</h3>
                @forelse($eleves as $e)
                    <a href="{{ route('recherche.eleve', $e) }}" class="element" style="text-decoration:none; color:inherit;">
                        <div class="infos"><b>{{ $e->nom }} {{ $e->prenom }}</b><small>{{ $e->matricule }} · {{ $e->classe->nom ?? 'Sans classe' }} @if($e->classe?->cycle) · {{ $e->classe->cycle->nom }} @endif</small></div>
                        <span class="pastille">Voir la fiche →</span>
                    </a>
                @empty
                    <div class="vide">Aucun élève trouvé.</div>
                @endforelse
            </div>
        @endif
        @if(\App\Http\Controllers\RechercheController::peutRechercherPersonnel(auth()->user()))
            <div class="bloc">
                <h3>👥 Personnel ({{ $personnels->count() }})</h3>
                @forelse($personnels as $p)
                    <a href="{{ route('personnels.show', $p) }}" class="element" style="text-decoration:none; color:inherit;">
                        <div class="infos"><b>{{ $p->nom }} {{ $p->prenom }}</b><small>{{ $p->matricule }} · {{ $p->fonction ?: $p->libelle_type }} · {{ $p->cycle->nom ?? 'Commun' }}{{ $p->actif ? '' : ' · inactif' }}</small></div>
                        <span class="pastille">Voir la fiche →</span>
                    </a>
                @empty
                    <div class="vide">Aucun membre du personnel trouvé.</div>
                @endforelse
            </div>
        @endif
    @endif
@endsection
