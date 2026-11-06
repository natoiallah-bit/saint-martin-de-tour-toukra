@extends('layouts.app')
@section('title', 'Comptes utilisateurs')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Comptes utilisateurs</h1>
        <a href="{{ route('users.create') }}" class="btn">+ Créer un compte</a>
    </div>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <table>
        <thead><tr><th>Nom</th><th>Email</th><th>Rôle</th><th>Cycle</th><th>Actions</th></tr></thead>
        <tbody>
            @foreach($users as $u)
                <tr>
                    <td>{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->libelle_roles }}</td>
                    <td>{{ $u->cycle->nom ?? ($u->role === 'direction' ? 'Tous les cycles' : '⚠️ Non assigné') }}</td>
                    <td class="actions">
                        <a href="{{ route('users.edit', $u) }}" class="btn">Modifier</a>
                        @if($u->id !== auth()->id())
                            <form action="{{ route('users.destroy', $u) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce compte ?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger">Supprimer</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
