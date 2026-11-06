@extends('layouts.app')
@section('title', 'Convocation réunion')

@section('content')
    <h1>Convocation — Réunion ou autre événement</h1>

    <form action="{{ route('convocations.reunion.generer') }}" method="POST" target="_blank">
        @csrf

        <label>Destinataires</label>
        <select name="cible" id="cible-select" required>
            <option value="toute_ecole">Toute l'école (tous les élèves)</option>
            <option value="classe">Une classe précise</option>
        </select>

        <div id="bloc-classe" style="display:none;">
            <label>Classe</label>
            <select name="classe_id">
                <option value="">— Choisir —</option>
                @foreach($classes as $classe)
                    <option value="{{ $classe->id }}">{{ $classe->cycle->nom }} — {{ $classe->nom }}</option>
                @endforeach
            </select>
        </div>

        <label>Sujet de la convocation</label>
        <input type="text" name="sujet" placeholder="Ex : Réunion de rentrée des parents d'élèves" required>

        <label>Date</label>
        <input type="date" name="date_convocation" required>

        <label>Heure</label>
        <input type="time" name="heure" required>

        <label>Lieu</label>
        <input type="text" name="lieu" placeholder="Ex : Cour du complexe" required>

        <label>Message complémentaire (optionnel)</label>
        <input type="text" name="message" placeholder="Précisions supplémentaires si besoin">

        <div style="margin-top:20px;">
            @droit('convocations', 'modifier')
            <button type="submit" class="btn">Générer les convocations</button>
            @enddroit
        </div>
    </form>

    <script>
        document.getElementById('cible-select').addEventListener('change', function () {
            document.getElementById('bloc-classe').style.display = this.value === 'classe' ? 'block' : 'none';
        });
    </script>
@endsection
