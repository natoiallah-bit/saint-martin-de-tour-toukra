@extends('layouts.app')
@section('title', 'Emploi du temps — ' . $classe->nom)

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Emploi du temps — {{ $classe->cycle->nom }} {{ $classe->nom }}</h1>
        <div>
            <a href="{{ route('emplois-temps.choix') }}" class="btn" style="background:#e3ddcf; color:#141c33;">Changer de classe</a>
            @droit('emplois_temps', 'modifier')
            <a href="{{ route('emplois-temps.create', $classe) }}" class="btn">+ Ajouter un créneau</a>
            @enddroit
        </div>
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; margin-top:16px;">
        @foreach($jours as $jour)
            <div style="background:#fff; border-radius:10px; overflow:hidden; border:1px solid #e3ddcf;">
                <div style="background:#141c33; color:#fff; padding:8px 12px; font-family:'Fraunces', serif; font-weight:600; text-transform:capitalize; font-size:14px;">
                    {{ $jour }}
                </div>
                <div style="padding:8px;">
                    @forelse($creneaux->get($jour, collect()) as $c)
                        <div style="border-bottom:1px dashed #e3ddcf; padding:8px 4px; font-size:12.5px;">
                            <div style="font-weight:bold; color:#141c33;">{{ substr($c->heure_debut,0,5) }} – {{ substr($c->heure_fin,0,5) }}</div>
                            <div style="color:#c9973b; font-weight:600;">{{ $c->matiere->nom }}</div>
                            <div style="color:#5a5a5a;">{{ $c->enseignant ? $c->enseignant->nom.' '.$c->enseignant->prenom : 'Enseignant non assigné' }}</div>
                            @droit('emplois_temps', 'supprimer')
                            <form action="{{ route('emplois-temps.destroy', [$classe, $c]) }}" method="POST" onsubmit="return confirm('Supprimer ce créneau ?');" style="margin-top:4px;">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none; border:none; color:#a8503c; font-size:11px; cursor:pointer; padding:0;">Supprimer</button>
                            </form>
                            @enddroit
                        </div>
                    @empty
                        <div style="font-size:12px; color:#999; padding:8px 4px;">Aucun cours</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endsection
