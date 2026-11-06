@extends('layouts.app')
@section('title', 'Enseignants')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Enseignants</h1>
        <a href="{{ route('enseignants.create') }}" class="btn">+ Ajouter un enseignant</a>
    </div>

    <table>
        <thead><tr><th>Nom</th><th>Prénom</th><th>Téléphone</th><th>Email</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($enseignants as $e)
                <tr>
                    <td>{{ $e->nom }}</td>
                    <td>{{ $e->prenom }}</td>
                    <td>{{ $e->telephone ?? '—' }}</td>
                    <td>{{ $e->email ?? '—' }}</td>
                    <td class="actions">
                        <form action="{{ route('enseignants.destroy', $e) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cet enseignant ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">Aucun enseignant enregistré.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
