@extends('layouts.app')
@section('title', 'Saisie des notes')

@section('content')
@php
    $totalPoids = $evaluations->sum('poids');
    $pct = fn ($n) => rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    $contexte = ['classe_id' => $classe->id, 'matiere_id' => $matiere->id, 'trimestre' => $trimestre];
@endphp
<style>
    .devoirs{ background:#fff; border:1px solid #e3ddcf; border-radius:10px; padding:14px 16px; margin-bottom:18px; }
    .devoir{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; padding:6px 0; border-top:1px solid #efe9dc; }
    .devoir:first-of-type{ border-top:0; }
    .devoir input{ margin:0; }
    .total-ok{ color:#3f6b55; font-weight:700; } .total-ko{ color:#a8503c; font-weight:700; }
    table.grille input{ width:72px; margin:0; padding:5px; text-align:center; }
    table.grille td.calc{ font-weight:700; text-align:center; background:#faf8f3; }
    table.grille th small{ display:block; font-weight:400; opacity:.8; }
</style>

<h1>{{ $matiere->nom }} — {{ $classe->cycle->nom }} {{ $classe->nom }} — Trimestre {{ $trimestre }}</h1>
<p style="margin-top:-10px; color:#5a5a5a;">
    Coefficient de la matière : <b>{{ $matiere->coefficient }}</b> ·
    Moyenne de la matière = (moyenne des devoirs + composition) ÷ 2
</p>

@if($errors->any())
    <div style="background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px;">{{ $errors->first() }}</div>
@endif

{{-- Devoirs du trimestre et leurs pourcentages --}}
<div class="devoirs">
    <h3 style="margin-bottom:6px;">Devoirs du trimestre</h3>
    <div style="font-size:13px; margin-bottom:8px;">
        Total des pourcentages :
        <span class="{{ abs($totalPoids - 100) < 0.01 ? 'total-ok' : 'total-ko' }}">{{ $pct($totalPoids) }} %</span>
        @if($evaluations->isNotEmpty() && abs($totalPoids - 100) >= 0.01)
            — il faut arriver à 100 %. En attendant, la moyenne est calculée sur les devoirs saisis.
        @endif
    </div>

    @foreach($evaluations as $evaluation)
        <form action="{{ route('notes.devoirs.update', $evaluation) }}" method="POST" class="devoir">
            @csrf @method('PUT')
            <input type="text" name="libelle" value="{{ $evaluation->libelle }}" required maxlength="100" style="width:200px;">
            <input type="date" name="date" value="{{ $evaluation->date?->format('Y-m-d') }}" style="width:160px;">
            <input type="number" name="poids" value="{{ $pct($evaluation->poids) }}" min="1" max="100" step="0.5" required style="width:80px;"> %
            <button type="submit" class="btn" style="padding:5px 10px;">Modifier</button>
            <button type="submit" form="supprimer-{{ $evaluation->id }}" class="btn btn-danger" style="padding:5px 10px;"
                    onclick="return confirm('Supprimer ce devoir et ses notes ?');">Supprimer</button>
        </form>
        <form id="supprimer-{{ $evaluation->id }}" action="{{ route('notes.devoirs.destroy', $evaluation) }}" method="POST" style="display:none;">@csrf</form>
    @endforeach

    <form action="{{ route('notes.devoirs.store') }}" method="POST" class="devoir" style="border-top:1px dashed #c9973b; margin-top:6px; padding-top:10px;">
        @csrf
        @foreach($contexte as $cle => $valeur) <input type="hidden" name="{{ $cle }}" value="{{ $valeur }}"> @endforeach
        <b>Nouveau devoir :</b>
        <input type="text" name="libelle" value="Devoir {{ $evaluations->count() + 1 }}" required maxlength="100" style="width:200px;">
        <input type="date" name="date" value="{{ now()->format('Y-m-d') }}" style="width:160px;">
        <input type="number" name="poids" value="{{ $totalPoids < 100 ? $pct(100 - $totalPoids) : '' }}" min="1" max="100" step="0.5" required style="width:80px;" placeholder="%"> %
        <button type="submit" class="btn" style="padding:5px 10px;">+ Ajouter</button>
    </form>
</div>

{{-- Notes des élèves --}}
<form action="{{ route('notes.enregistrer') }}" method="POST">
    @csrf
    @foreach($contexte as $cle => $valeur) <input type="hidden" name="{{ $cle }}" value="{{ $valeur }}"> @endforeach

    <div style="overflow-x:auto;">
    <table class="grille">
        <thead>
            <tr>
                <th>Élève</th>
                @foreach($evaluations as $evaluation)
                    <th style="text-align:center;">{{ $evaluation->libelle }}<small>{{ $pct($evaluation->poids) }} % · /20</small></th>
                @endforeach
                <th style="text-align:center;">Moy. devoirs</th>
                <th style="text-align:center;">Composition<small>100 % · /20</small></th>
                <th style="text-align:center;">Moy. matière</th>
            </tr>
        </thead>
        <tbody>
            @foreach($classe->eleves as $eleve)
                @php $existante = $notesExistantes->get($eleve->id); $siennes = $notesDevoirs->get($eleve->id, []); @endphp
                <tr class="ligne-eleve">
                    <td>{{ $eleve->nom }} {{ $eleve->prenom }}</td>
                    @foreach($evaluations as $evaluation)
                        <td style="text-align:center;">
                            <input type="number" step="0.25" min="0" max="20" data-poids="{{ $evaluation->poids }}" class="note-devoir"
                                   name="notes[{{ $eleve->id }}][devoirs][{{ $evaluation->id }}]" value="{{ $siennes[$evaluation->id] ?? '' }}">
                        </td>
                    @endforeach
                    <td class="calc moy-devoirs">—</td>
                    <td style="text-align:center;">
                        <input type="number" step="0.25" min="0" max="20" class="note-compo"
                               name="notes[{{ $eleve->id }}][moyenne_composition]" value="{{ $existante->moyenne_composition ?? '' }}">
                    </td>
                    <td class="calc moy-matiere">—</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    <p style="font-size:12px; color:#666; margin-top:6px;">Laissez vide un devoir non fait (absence) : il n'est pas compté et les autres pourcentages sont ramenés sur 100.</p>

    <div style="margin-top:16px;">
        <button type="submit" class="btn">Enregistrer les notes</button>
        <a href="{{ route('notes.choix') }}">Retour</a>
    </div>
</form>

<script>
    // Moyennes recalculées pendant la saisie (le calcul officiel est refait à l'enregistrement)
    function calculer(ligne) {
        let points = 0, poids = 0;
        ligne.querySelectorAll('.note-devoir').forEach(champ => {
            if (champ.value !== '') { points += parseFloat(champ.value) * parseFloat(champ.dataset.poids); poids += parseFloat(champ.dataset.poids); }
        });
        const devoirs = poids > 0 ? points / poids : null;
        const compo = ligne.querySelector('.note-compo').value;
        const format = n => n === null ? '—' : (Math.round(n * 100) / 100).toLocaleString('fr-FR');
        ligne.querySelector('.moy-devoirs').textContent = format(devoirs);
        ligne.querySelector('.moy-matiere').textContent = devoirs !== null && compo !== '' ? format((devoirs + parseFloat(compo)) / 2) : '—';
    }
    document.querySelectorAll('.ligne-eleve').forEach(ligne => {
        calculer(ligne);
        ligne.querySelectorAll('input').forEach(champ => champ.addEventListener('input', () => calculer(ligne)));
    });
</script>
@endsection
