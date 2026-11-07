@extends('layouts.app')
@section('title', 'Licence')

@section('content')
    <h1>Licence du logiciel</h1>

    <style>.ligne{ display:flex; justify-content:space-between; gap:12px; padding:8px 0; border-bottom:1px solid #eee; }</style>

    @php
        $libelles = ['valide' => 'Active', 'bientot' => 'Bientôt terminée', 'expiree' => 'Terminée (tolérance)', 'bloquee' => 'Terminée : lecture seule'];
    @endphp

    <div style="background:#fff; padding:20px; border-radius:10px; max-width:720px; margin-bottom:18px;">
        @if($etat['source'] === 'desactivee')
            <p>Le contrôle de licence est désactivé sur cette installation.</p>
        @else
            <div class="ligne"><span>État</span><span><b>{{ $libelles[$etat['statut']] }}</b></span></div>
            <div class="ligne"><span>Type</span><span>{{ $etat['source'] === 'cle' ? "Clé d'activation" : "Période d'essai" }}</span></div>
            <div class="ligne"><span>Fin</span><span>{{ $etat['fin']->format('d/m/Y') }}</span></div>
            <div class="ligne"><span>Jours restants</span><span>{{ max($etat['jours'], 0) }}</span></div>
            @if($etat['erreur'])
                <p style="color:#9b2c22; margin-top:10px;">La clé enregistrée n'est pas utilisable : {{ $etat['erreur'] }}</p>
            @endif
            @if($etat['message'])
                <p style="margin-top:10px;">{{ $etat['message'] }}</p>
            @endif
        @endif
        <p style="font-size:13px; color:#5a5a5a; margin-top:12px;">
            Après la fin de la licence, un délai de tolérance est laissé, puis l'application passe en lecture seule.
            Les données restent toujours consultables et sauvegardables.
            Pour obtenir ou renouveler une clé : {{ config('licence.contact_email') }}, {{ config('licence.contact_telephone') }}.
        </p>
    </div>

    <form action="{{ route('licence.activer') }}" method="POST" style="background:#fff; padding:20px; border-radius:10px; max-width:720px;">
        @csrf
        <label>Enregistrer une clé d'activation</label>
        <textarea name="cle" rows="4" required placeholder="Collez ici la clé reçue" style="font-family:monospace; font-size:12px;">{{ old('cle') }}</textarea>
        @error('cle')
            <div style="color:#9b2c22; margin-top:6px;">{{ $message }}</div>
        @enderror
        <p style="font-size:12px; color:#5a5a5a; margin-top:6px;">La clé est liée au sigle de l'école ({{ $config->sigle_ecole }}) : si vous changez le sigle dans Paramètres, demandez une nouvelle clé.</p>
        <button type="submit" class="btn" style="margin-top:10px;">Enregistrer la clé</button>
    </form>
@endsection
