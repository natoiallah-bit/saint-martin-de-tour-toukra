@extends('layouts.app')
@section('title', 'Frais de scolarité')

@section('content')
    <h1>Frais de scolarité — {{ $anneeCourante }}</h1>
    <p style="color:#666;">
        Pour chaque cycle, indiquez le montant et la date limite de l'inscription et de chaque tranche.
        Le montant annuel est alors calculé tout seul, et la page « Relances impayés » sait ce que chaque
        famille devait avoir payé à une date donnée. Sans échéancier, seul le montant annuel compte.
        Une échéance sans date limite est due dès l'inscription.
    </p>

    @if($errors->any())
        <div style="background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <form action="{{ route('frais.update') }}" method="POST">
        @csrf
        @forelse($cycles as $cycle)
            @php $f = $fraisParCycle[$cycle->id] ?? null; @endphp
            <div style="background:#fff; border:1px solid #e3ddcf; border-radius:8px; padding:14px 16px; margin-bottom:16px;">
                <h3 style="margin-bottom:8px;">{{ $cycle->nom }}</h3>
                <table>
                    <thead><tr><th>Échéance</th><th>Montant (FCFA)</th><th>Date limite</th></tr></thead>
                    <tbody>
                        @foreach(\App\Models\FraisScolarite::ECHEANCES as $code => $libelle)
                            <tr>
                                <td>{{ $libelle }}</td>
                                <td><input type="number" step="1" min="0" name="frais[{{ $cycle->id }}][montant_{{ $code }}]"
                                           value="{{ old("frais.{$cycle->id}.montant_{$code}", $f?->{"montant_{$code}"} !== null ? (int) $f->{"montant_{$code}"} : '') }}"
                                           class="montant-{{ $cycle->id }}" style="max-width:180px;"></td>
                                <td><input type="date" name="frais[{{ $cycle->id }}][date_{{ $code }}]"
                                           value="{{ old("frais.{$cycle->id}.date_{$code}", $f?->{"date_{$code}"}?->format('Y-m-d')) }}" style="max-width:180px;"></td>
                            </tr>
                        @endforeach
                        <tr>
                            <td><b>Montant annuel</b></td>
                            <td colspan="2">
                                <input type="number" step="1" min="0" name="frais[{{ $cycle->id }}][montant_annuel]" id="annuel-{{ $cycle->id }}"
                                       value="{{ old("frais.{$cycle->id}.montant_annuel", $f ? (int) $f->montant_annuel : '') }}" style="max-width:180px;">
                                <small style="color:#777;">calculé automatiquement si l'échéancier est rempli</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @empty
            <p>Aucun cycle n'est configuré pour le moment.</p>
        @endforelse

        @if($cycles->isNotEmpty())
            @droit('frais', 'modifier')
            <button type="submit" class="btn">Enregistrer les frais</button>
            @enddroit
        @endif
    </form>

    <script>
        // Montant annuel = somme de l'échéancier, dès qu'un montant est saisi
        @foreach($cycles as $cycle)
            document.querySelectorAll('.montant-{{ $cycle->id }}').forEach(function (champ) {
                champ.addEventListener('input', function () {
                    const montants = [...document.querySelectorAll('.montant-{{ $cycle->id }}')].filter(c => c.value !== '');
                    if (montants.length) {
                        document.getElementById('annuel-{{ $cycle->id }}').value = montants.reduce((t, c) => t + Number(c.value), 0);
                    }
                });
            });
        @endforeach
    </script>
@endsection
