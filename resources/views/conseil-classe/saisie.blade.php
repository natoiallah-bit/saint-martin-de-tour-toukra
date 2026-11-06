@extends('layouts.app')
@section('title', 'Conseil de classe')

@section('content')
    <h1>Conseil de classe — {{ $classe->nom }}, {{ $trimestre }}{{ $trimestre == 1 ? 'er' : 'e' }} trimestre</h1>

    <p style="color:#5a5a5a; max-width:820px;">
        Distinction : laissez « Automatique » pour l'attribuer d'après la moyenne
        (Félicitations dès 16, Encouragements dès 14, Tableau d'honneur dès 12),
        ou choisissez la décision du conseil.
        La note de conduite compte dans la moyenne avec le coefficient
        {{ rtrim(rtrim(number_format(\App\Models\ConfigurationEcole::coefficientConduite(), 2, ',', ''), '0'), ',') }}
        (réglable dans Paramètres) ; le commentaire sur la conduite s'imprime dans la colonne « Appréciation » de la ligne Conduite.
        <a href="{{ route('conseil-classe.choix') }}">Changer de classe</a>
    </p>

    @if($assiduite === null)
        <div style="background:#fbe6df; color:#a8503c; padding:12px 16px; border-radius:6px; margin:12px 0; max-width:820px;">
            Absences et retards : réglez les dates du {{ $trimestre }}{{ $trimestre == 1 ? 'er' : 'e' }} trimestre dans Paramètres
            pour qu'ils soient comptés à partir de l'appel (page Présences).
        </div>
    @endif

    @if($errors->any())
        <div style="background:#f8e1df; color:#9b2c22; padding:12px 16px; border-radius:6px; margin:12px 0;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('conseil-classe.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="classe_id" value="{{ $classe->id }}">
        <input type="hidden" name="trimestre" value="{{ $trimestre }}">

        <div style="overflow-x:auto;">
            <table class="table-conseil" style="width:100%;">
                <thead>
                    <tr>
                        <th style="text-align:left;">Élève</th>
                        <th>Moyenne</th>
                        <th>Absences<br><span style="font-weight:normal;">(appel)</span></th>
                        <th>Retards</th>
                        <th>Conduite<br>/ 20</th>
                        <th>Appréciation du conseil</th>
                        <th>Commentaire sur la conduite</th>
                        <th>Distinction</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($classe->eleves as $eleve)
                        @php
                            $c = $conseils[$eleve->id] ?? null;
                            $moyenne = $moyennes[$eleve->id] ?? null;
                            $auto = \App\Helpers\Appreciation::distinction($moyenne);
                            $nom = "conseils[{$eleve->id}]";
                        @endphp
                        <tr>
                            <td style="text-align:left; font-weight:bold; white-space:nowrap;">{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                            <td>{{ $moyenne ?? '—' }}</td>
                            @php($a = $assiduite === null ? null : ($assiduite[$eleve->id] ?? ['jours' => 0, 'heures' => $avecEmploiDuTemps ? 0 : null, 'retards' => 0]))
                            <td style="white-space:nowrap;">
                                @if($a === null) —
                                @elseif($a['heures'] !== null) {{ rtrim(rtrim(number_format($a['heures'], 2, ',', ''), '0'), ',') }} h
                                @else {{ $a['jours'] }} j
                                @endif
                            </td>
                            <td>{{ $a === null ? '—' : $a['retards'] }}</td>
                            <td><input type="number" name="{{ $nom }}[note_conduite]" min="0" max="20" step="0.25"
                                       value="{{ old("conseils.{$eleve->id}.note_conduite", $c?->note_conduite) }}" style="width:70px;"></td>
                            <td><textarea name="{{ $nom }}[appreciation]" rows="2" maxlength="500"
                                          placeholder="Ex. : Bon trimestre.">{{ old("conseils.{$eleve->id}.appreciation", $c?->appreciation) }}</textarea></td>
                            <td><textarea name="{{ $nom }}[commentaire_conduite]" rows="2" maxlength="500"
                                          placeholder="Ex. : Élève discipliné.">{{ old("conseils.{$eleve->id}.commentaire_conduite", $c?->commentaire_conduite) }}</textarea></td>
                            <td>
                                <select name="{{ $nom }}[distinction]">
                                    <option value="">Automatique</option>
                                    @foreach($distinctions as $d)
                                        <option value="{{ $d }}" @selected(old("conseils.{$eleve->id}.distinction", $c?->distinction) === $d)>{{ $d }}</option>
                                    @endforeach
                                </select>
                                <div style="font-size:11px; color:#5a5a5a; margin-top:3px;">Auto : {{ $auto ?? 'aucune' }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucun élève dans cette classe.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($classe->eleves->isNotEmpty())
            @droit('conseil_classe', 'modifier')
            <div style="margin-top:20px;">
                <button type="submit" class="btn">Enregistrer les décisions du conseil</button>
            </div>
            @enddroit
        @endif
    </form>

    <style>
        .table-conseil{ font-size:12px; }
        .table-conseil td{ text-align:center; padding:4px; vertical-align:top; }
        .table-conseil input, .table-conseil select, .table-conseil textarea{ margin:0; padding:4px 6px; font-size:12px; }
        .table-conseil textarea{ width:100%; min-width:150px; min-height:44px; font-family:inherit; }
        .table-conseil th:nth-child(5), .table-conseil th:nth-child(6){ width:24%; }
        .table-conseil select{ width:auto; max-width:150px; }
    </style>
@endsection
