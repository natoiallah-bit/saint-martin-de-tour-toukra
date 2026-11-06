@extends('layouts.app')
@section('title', 'Saisir des notes')

@section('content')
    <h1>Saisir des notes</h1>

    <form action="{{ route('notes.saisie') }}" method="GET" style="background:#fff; padding:20px; border-radius:10px;">
        <label>Classe</label>
        <select name="classe_id" required>
            <option value="">— Choisir une classe —</option>
            @foreach($classes as $classe)
                <option value="{{ $classe->id }}">{{ $classe->cycle->nom }} — {{ $classe->nom }}</option>
            @endforeach
        </select>

        <label>Matière</label>
        <select name="matiere_id" id="matiere-select" required>
            <option value="">— Choisir d'abord une classe —</option>
        </select>

        <label>Trimestre</label>
        <select name="trimestre" required>
            <option value="1">1er trimestre</option>
            <option value="2">2e trimestre</option>
            <option value="3">3e trimestre</option>
        </select>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Afficher la grille de saisie</button>
        </div>
    </form>

    @if($matieresAutorisees->isEmpty())
        <div style="background:#fbe6df; color:#a8503c; padding:14px; border-radius:8px; margin-top:16px;">
            Aucune matière ne vous est actuellement assignée. Contactez la direction.
        </div>
    @endif

    <script>
        // Seules les matières autorisées pour l'utilisateur connecté sont proposées
        const matieresParCycle = @json(
            $matieresAutorisees->groupBy('cycle_id')->map(fn($group) => $group->map(fn($m) => ['id' => $m->id, 'nom' => $m->nom]))
        );
        const classes = @json($classes->map(fn($c) => ['id' => $c->id, 'cycle_id' => $c->cycle_id]));

        document.querySelector('[name=classe_id]').addEventListener('change', function () {
            const classe = classes.find(c => c.id == this.value);
            const select = document.getElementById('matiere-select');
            select.innerHTML = '';
            if (classe && matieresParCycle[classe.cycle_id]) {
                matieresParCycle[classe.cycle_id].forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.nom;
                    select.appendChild(opt);
                });
            } else {
                select.innerHTML = '<option value="">Aucune matière disponible pour cette classe</option>';
            }
        });
    </script>
@endsection
