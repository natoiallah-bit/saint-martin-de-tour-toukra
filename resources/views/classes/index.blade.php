@extends('layouts.app')
@section('title', 'Classes')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Classes</h1>
        @droit('classes', 'modifier')
        <a href="{{ route('classes.create') }}" class="btn">+ Ajouter une classe</a>
        @enddroit
    </div>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <table>
        <thead><tr><th>Cycle</th><th>Classe</th><th>Professeur principal</th><th>Effectif</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($classes as $classe)
                <tr>
                    <td>{{ $classe->cycle->nom ?? '—' }}</td>
                    <td>{{ $classe->nom }}</td>
                    <td>{{ $classe->nomProfesseurPrincipal() ?? '—' }}</td>
                    <td>{{ $classe->eleves_count }}</td>
                    <td class="actions">
                        @droit('classes', 'modifier')
                        <a href="{{ route('classes.edit', $classe->id) }}" class="btn">Modifier</a>
                        @enddroit
                        @droit('classes', 'supprimer')
                        <form action="{{ route('classes.destroy', $classe->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette classe ?');">
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
