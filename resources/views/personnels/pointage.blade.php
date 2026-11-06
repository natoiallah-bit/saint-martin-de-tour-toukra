@extends('layouts.app')
@section('title', 'Pointage')

@section('content')
    <h1>Fiche de pointage</h1>

    <form method="GET" style="margin-bottom:16px;">
        <label>Date</label>
        <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" style="max-width:220px;">
    </form>

    <form action="{{ route('pointage.enregistrer') }}" method="POST">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        <table>
            <thead>
                <tr>
                    <th>Personnel</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Remarque</th>
                </tr>
            </thead>
            <tbody>
                @foreach($personnels as $personnel)
                    @php $existant = $pointagesExistants->get($personnel->id); @endphp
                    <tr>
                        <td>{{ $personnel->nom }} {{ $personnel->prenom }}</td>
                        <td>{{ $personnel->libelle_type }}</td>
                        <td>
                            <select name="statuts[{{ $personnel->id }}]">
                                <option value="present" @selected(!$existant || $existant->statut=='present')>Présent</option>
                                <option value="absent" @selected($existant?->statut=='absent')>Absent</option>
                                <option value="retard" @selected($existant?->statut=='retard')>Retard</option>
                                <option value="conge" @selected($existant?->statut=='conge')>Congé</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="remarques[{{ $personnel->id }}]" value="{{ $existant->remarque ?? '' }}" placeholder="(optionnel)">
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div style="margin-top:20px;">
            @droit('pointage', 'modifier')
            <button type="submit" class="btn">Enregistrer le pointage du {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</button>
            @enddroit
        </div>
    </form>
@endsection
