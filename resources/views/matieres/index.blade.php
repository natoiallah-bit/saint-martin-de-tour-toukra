@extends('layouts.app')
@section('title', 'Matières')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Matières</h1>
        @droit('classes', 'modifier')
        <a href="{{ route('matieres-gestion.create') }}" class="btn">+ Ajouter une matière</a>
        @enddroit
    </div>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <table>
        <thead><tr><th>Cycle</th><th>Matière</th><th>Coefficient</th><th>Catégorie</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($matieres as $matiere)
                <tr>
                    <td>{{ $matiere->cycle->nom ?? '—' }}</td>
                    <td>{{ $matiere->nom }}</td>
                    <td>{{ $matiere->coefficient }}</td>
                    <td>{{ $matiere->categorie ?? '—' }}</td>
                    <td class="actions">
                        @droit('classes', 'modifier')
                        <a href="{{ route('matieres-gestion.edit', $matiere->id) }}" class="btn">Modifier</a>
                        @enddroit
                        @droit('classes', 'supprimer')
                        <form action="{{ route('matieres-gestion.destroy', $matiere->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette matière ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
