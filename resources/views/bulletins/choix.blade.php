@extends('layouts.app')
@section('title', 'Bulletins')

@section('content')
    <h1>Voir un bulletin</h1>

    <div style="background:#fff; padding:20px; border-radius:10px;">
        <label>Classe</label>
        <select id="classe-select">
            <option value="">— Choisir une classe —</option>
            @foreach($classes as $classe)
                <option value="{{ $classe->id }}">{{ $classe->cycle->nom }} — {{ $classe->nom }}</option>
            @endforeach
        </select>

        <label>Élève</label>
        <select id="eleve-select">
            <option value="">— Choisir d'abord une classe —</option>
        </select>

        <label>Trimestre</label>
        <select id="trimestre-select">
            <option value="1">1er trimestre</option>
            <option value="2">2e trimestre</option>
            <option value="3">3e trimestre</option>
        </select>

        <div style="margin-top:20px;">
            <button type="button" class="btn" onclick="voirBulletin()">Voir le bulletin</button>
        </div>
    </div>

    <script>
        const elevesParClasse = @json(
            $classes->mapWithKeys(fn($c) => [$c->id => $c->eleves->map(fn($e) => ['id' => $e->id, 'nom' => $e->nom.' '.$e->prenom])])
        );

        document.getElementById('classe-select').addEventListener('change', function () {
            const select = document.getElementById('eleve-select');
            select.innerHTML = '';
            const eleves = elevesParClasse[this.value] || [];
            eleves.forEach(e => {
                const opt = document.createElement('option');
                opt.value = e.id;
                opt.textContent = e.nom;
                select.appendChild(opt);
            });
        });

        function voirBulletin() {
            const eleveId = document.getElementById('eleve-select').value;
            const trimestre = document.getElementById('trimestre-select').value;
            if (!eleveId) { alert('Choisis un élève.'); return; }
            window.location.href = `/bulletins/${eleveId}/${trimestre}`;
        }
    </script>
@endsection
