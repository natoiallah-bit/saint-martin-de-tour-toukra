@extends('layouts.app')
@section('title', 'Ajouter un créneau')

@section('content')
    <h1>Ajouter un créneau — {{ $classe->cycle->nom }} {{ $classe->nom }}</h1>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('emplois-temps.store', $classe) }}" method="POST">
        @csrf

        <label>Jour</label>
        <select name="jour" required>
            @foreach($jours as $jour)
                <option value="{{ $jour }}" style="text-transform:capitalize;">{{ ucfirst($jour) }}</option>
            @endforeach
        </select>

        <div style="display:flex; gap:16px;">
            <div style="flex:1;">
                <label>Heure de début</label>
                <input type="time" name="heure_debut" required>
            </div>
            <div style="flex:1;">
                <label>Heure de fin</label>
                <input type="time" name="heure_fin" required>
            </div>
        </div>

        <label>Matière</label>
        <select name="matiere_id" id="matiere-select" required>
            <option value="">— Choisir —</option>
            @foreach($matieres as $matiere)
                <option value="{{ $matiere->id }}">{{ $matiere->nom }}</option>
            @endforeach
        </select>

        <label>Enseignant <span style="font-weight:normal; color:#5a5a5a;">(se pré-remplit automatiquement si cette matière a déjà été programmée pour cette classe)</span></label>
        <select name="enseignant_id" id="enseignant-select">
            <option value="">— Aucun / à définir —</option>
            @foreach($enseignants as $enseignant)
                <option value="{{ $enseignant->id }}">{{ $enseignant->nom }} {{ $enseignant->prenom }}</option>
            @endforeach
        </select>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
            <a href="{{ route('emplois-temps.index', $classe) }}">Annuler</a>
        </div>
    </form>

    <script>
        document.getElementById('matiere-select').addEventListener('change', function () {
            const matiereId = this.value;
            const enseignantSelect = document.getElementById('enseignant-select');
            if (!matiereId) return;

            fetch(`{{ url('/emplois-temps') }}/{{ $classe->id }}/suggestion/${matiereId}`)
                .then(r => r.json())
                .then(data => {
                    if (data.enseignant_id) {
                        enseignantSelect.value = data.enseignant_id;
                    }
                });
        });
    </script>
@endsection
