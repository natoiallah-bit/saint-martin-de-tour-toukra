@extends('layouts.app')
@section('title', 'Import Excel — Élèves')

@section('content')
<div class="max-w-2xl" style="max-width:640px; margin:0 auto;">
    <h1>Import d'élèves depuis un fichier Excel</h1>

    @if ($errors->any())
        <div class="alert" style="background:#a8503c;">
            @foreach ($errors->all() as $error)
                {{ $error }}
            @endforeach
        </div>
    @endif

    <div style="background:#fff; border-radius:8px; padding:20px; box-shadow:0 2px 10px rgba(0,0,0,0.06);">
        <p style="margin-bottom:14px; font-size:13px; color:#5b5d6b;">
            Téléverse ton fichier Excel (.xlsx, .xls) ou CSV, quelle que soit la disposition de ses colonnes.
            À l'étape suivante, tu indiqueras toi-même quelle colonne du fichier correspond à quel champ
            (nom, prénom, classe, etc.) — l'application essaiera de deviner automatiquement, tu pourras
            corriger si besoin.
        </p>

        <form action="{{ route('import.eleves.televerser') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <label>Fichier Excel ou CSV</label>
            <input type="file" name="fichier" accept=".xlsx,.xls,.csv" required>

            <button type="submit" class="btn" style="margin-top:16px;">Continuer</button>
        </form>
    </div>

    <p style="margin-top:14px; font-size:12px; color:#999;">
        La classe indiquée dans le fichier doit correspondre au nom exact d'une classe déjà créée
        dans l'application (ex: "CM2", "6ème A") — sinon la ligne sera ignorée et signalée à la fin.
    </p>
</div>
@endsection
