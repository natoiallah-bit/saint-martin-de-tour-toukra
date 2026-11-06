@extends('layouts.app')
@section('title', 'Documents — ' . $personnel->nom)

@section('content')
    <h1>Documents de {{ $personnel->prenom }} {{ $personnel->nom }}</h1>

    @droit('personnel', 'modifier')
    <form action="{{ route('personnels.documents.store', $personnel) }}" method="POST" enctype="multipart/form-data" style="background:#fff; padding:16px; border-radius:10px; margin-bottom:20px;">
        @csrf
        <label>Type de document</label>
        <select name="type" required>
            @foreach(\App\Models\DocumentPersonnel::typesDisponibles() as $type)
                <option value="{{ $type }}">{{ $type }}</option>
            @endforeach
        </select>

        <label>Fichier (PDF, JPG ou PNG, max 5 Mo)</label>
        <input type="file" name="fichier" accept=".pdf,.jpg,.jpeg,.png" required>

        <div style="margin-top:14px;">
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
                        @droit('personnel', 'supprimer')
                        <form action="{{ route('personnels.documents.destroy', [$personnel, $doc]) }}" method="POST" style="display:inline;" onsubmit="return confirm('Supprimer ce document ?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger">Supprimer</button>
                        </form>
                        @enddroit
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun document téléversé pour ce membre du personnel.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top:16px;"><a href="{{ route('personnels.index') }}">← Retour à la liste du personnel</a></div>
@endsection
