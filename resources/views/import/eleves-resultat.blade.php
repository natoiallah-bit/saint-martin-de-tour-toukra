@extends('layouts.app')
@section('title', 'Import Excel — Résultat')

@section('content')
<div style="max-width:720px; margin:0 auto;">
    <h1>Résultat de l'import</h1>

    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:20px;">
        <div style="background:#4c7a63; color:#fff; border-radius:8px; padding:14px; text-align:center;">
            <div style="font-size:11px; text-transform:uppercase;">Élèves créés</div>
            <div style="font-size:24px; font-weight:bold;">{{ $crees }}</div>
        </div>
        <div style="background:#c9973b; color:#141c33; border-radius:8px; padding:14px; text-align:center;">
            <div style="font-size:11px; text-transform:uppercase;">Doublons ignorés</div>
            <div style="font-size:24px; font-weight:bold;">{{ $doublons }}</div>
        </div>
        <div style="background:#a8503c; color:#fff; border-radius:8px; padding:14px; text-align:center;">
            <div style="font-size:11px; text-transform:uppercase;">Lignes en erreur</div>
            <div style="font-size:24px; font-weight:bold;">{{ count($erreurs) }}</div>
        </div>
    </div>

    <p style="font-size:13px; color:#5b5d6b; margin-bottom:14px;">
        {{ $total }} ligne(s) traitée(s) au total.
    </p>

    @if (count($erreurs))
        <div style="background:#fff; border-radius:8px; padding:16px; margin-bottom:20px;">
            <h3 style="margin-bottom:8px;">Détail des lignes non importées</h3>
            <ul style="font-size:12.5px; color:#5b5d6b; padding-left:18px;">
                @foreach ($erreurs as $erreur)
                    <li style="margin-bottom:4px;">{{ $erreur }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <a href="{{ route('eleves.index') }}" class="btn">Voir la liste des élèves</a>
    <a href="{{ route('import.eleves.formulaire') }}" style="margin-left:12px;">Importer un autre fichier</a>
</div>
@endsection
