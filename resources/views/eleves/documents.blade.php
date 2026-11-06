@extends('layouts.app')
@section('title', 'Documents de l\'élève')

@section('content')
    <h1>Documents — {{ $eleve->nom }} {{ $eleve->prenom }}</h1>

    @if ($errors->any())
        <div style="background:#f8d7da; color:#842029; padding:10px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    @droit('documents_eleves', 'modifier')
    <form action="{{ route('eleves.documents.store', $eleve) }}" method="POST" enctype="multipart/form-data" style="background:#fff; padding:18px; border-radius:10px; margin-bottom:24px; max-width:520px;">
        @csrf
        <label>Type de document</label>
        <select name="type" required>
            @foreach(\App\Models\DocumentEleve::typesDisponibles() as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>

        <label>Fichier (PDF, JPG ou PNG — 5 Mo max)</label>
        <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png" required>

        <div style="margin-top:16px;">
            <button type="submit" class="btn">Téléverser</button>
        </div>
    </form>
    @enddroit

    <table>
        <thead><tr><th>Type</th><th>Fichier</th><th>Ajouté le</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse($documents as $doc)
                <tr>
                    <td>{{ $doc->type }}</td>
                    <td>{{ $doc->nom_fichier }}</td>
                    <td>{{ $doc->created_at->format('d/m/Y') }}</td>
                    <td class="actions">
                        <a href="{{ \App\Helpers\Fichier::url($doc->chemin_fichier) }}" target="_blank" class="btn">Voir / Télécharger</a>
                        @droit('documents_eleves', 'supprimer')
                        <form action="{{ route('eleves.documents.destroy', [$eleve, $doc]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce document ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun document téléversé pour cet élève.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;"><a href="{{ route('eleves.index') }}">← Retour à la liste des élèves</a></div>
@endsection
