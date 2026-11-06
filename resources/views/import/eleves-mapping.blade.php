@extends('layouts.app')
@section('title', 'Import Excel — Correspondance des colonnes')

@section('content')
<div style="max-width:900px; margin:0 auto;">
    <h1>Fais correspondre les colonnes de ton fichier</h1>

    <p style="margin-bottom:16px; font-size:13px; color:#5b5d6b;">
        {{ $totalLignes }} ligne(s) détectée(s) dans le fichier (hors en-tête). Pour chaque champ de
        l'application, choisis la colonne du fichier qui lui correspond — ou "Ne pas importer" si le
        fichier n'a pas cette information. Les champs marqués <b>*</b> sont obligatoires.
    </p>

    <div style="background:#fff; border-radius:8px; padding:18px; margin-bottom:20px; overflow-x:auto;">
        <h3 style="margin-bottom:10px;">Aperçu des premières lignes de ton fichier</h3>
        <table style="font-size:11px;">
            <thead>
                <tr>
                    @foreach ($entetes as $i => $entete)
                        <th>{{ $entete ?: '(colonne ' . ($i + 1) . ')' }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($apercu as $ligne)
                    <tr>
                        @foreach ($entetes as $i => $entete)
                            <td>{{ $ligne[$i] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <form action="{{ route('import.eleves.importer') }}" method="POST">
        @csrf
        <input type="hidden" name="nom_stocke" value="{{ $nomStocke }}">

        <div style="background:#fff; border-radius:8px; padding:18px;">
            @foreach ($champs as $cle => $info)
                <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid #e3ddcf;">
                    <label style="margin:0; font-weight:600; font-size:13px;">{{ $info['label'] }}</label>
                    <select name="mapping[{{ $cle }}]" style="width:320px; margin:0;">
                        <option value="">— Ne pas importer —</option>
                        @foreach ($entetes as $i => $entete)
                            <option value="{{ $i }}" @selected($suggestions[$cle] === $i)>
                                {{ $entete ?: '(colonne ' . ($i + 1) . ')' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>

        <button type="submit" class="btn" style="margin-top:18px;">Lancer l'import</button>
        <a href="{{ route('import.eleves.formulaire') }}" style="margin-left:12px;">Annuler</a>
    </form>
</div>
@endsection
