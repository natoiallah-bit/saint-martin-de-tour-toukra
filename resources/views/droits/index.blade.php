@extends('layouts.app')
@section('title', 'Droits des rôles')

@section('content')
    <h1>Droits des rôles</h1>

    <p style="max-width:820px; color:#5a5a5a;">
        Choisissez, pour chaque rôle, ce qu'il peut faire dans chaque partie de l'application.
        Chaque niveau comprend les précédents : <strong>Modifier</strong> permet aussi de lire,
        <strong>Supprimer</strong> permet aussi de modifier et de lire.
        Une personne qui a plusieurs rôles cumule leurs droits.
        La <strong>Direction</strong> a toujours tous les droits ; les <strong>parents</strong>
        ne voient que la carte, le bulletin et les reçus de leurs propres enfants.
    </p>

    @if($errors->any())
        <div style="background:#f8e1df; color:#9b2c22; padding:12px 16px; border-radius:6px; margin:12px 0;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('droits.update') }}" method="POST">
        @csrf @method('PUT')

        <div style="overflow-x:auto;">
            <table class="table-droits">
                <thead>
                    <tr>
                        <th style="text-align:left;">Module</th>
                        @foreach($roles as $role => $libelle)
                            <th>{{ $libelle }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($modules as $module => $nomModule)
                        <tr>
                            <td style="text-align:left; font-weight:bold;">{{ $nomModule }}</td>
                            @foreach($roles as $role => $libelle)
                                @php($actuel = (int) ($droits[$role][$module] ?? 0))
                                <td>
                                    <select name="droits[{{ $role }}][{{ $module }}]" class="niveau-{{ $actuel }}"
                                            onchange="this.className = 'niveau-' + this.value" aria-label="{{ $libelle }} — {{ $nomModule }}">
                                        @foreach($niveaux as $valeur => $nomNiveau)
                                            <option value="{{ $valeur }}" @selected($actuel === $valeur)>{{ $nomNiveau }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:20px; display:flex; gap:12px; align-items:center;">
            <button type="submit" class="btn">Enregistrer les droits</button>
        </div>
    </form>

    <form action="{{ route('droits.retablir') }}" method="POST" style="margin-top:12px;"
          onsubmit="return confirm('Remettre tous les droits comme à l\'installation ?');">
        @csrf
        <button type="submit" class="btn" style="background:#8a8a8a;">Rétablir les droits par défaut</button>
    </form>

    <style>
        .table-droits{ font-size:12px; }
        .table-droits th{ white-space:nowrap; font-size:11px; }
        .table-droits td{ text-align:center; padding:4px; }
        .table-droits select{ width:auto; min-width:110px; padding:4px 6px; font-size:12px; margin:0; }
        .table-droits select.niveau-0{ background:#f1ece0; color:#8a8a8a; }
        .table-droits select.niveau-1{ background:#e3edf7; color:#1f4e79; }
        .table-droits select.niveau-2{ background:#e2ede6; color:#2f5e45; }
        .table-droits select.niveau-3{ background:#f8e1df; color:#9b2c22; }
    </style>
@endsection
