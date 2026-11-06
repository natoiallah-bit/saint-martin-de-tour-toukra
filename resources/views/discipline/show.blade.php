@extends('layouts.app')
@section('title', 'Conseil de discipline — ' . $dossier->eleve->nom)

@section('content')
    @include('cours._style')
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>{{ $dossier->eleve->nom }} {{ $dossier->eleve->prenom }} — {{ $dossier->eleve->classe->nom ?? '' }}</h1>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <a href="{{ route('discipline.index') }}">← Tous les dossiers</a>
            <a href="{{ route('discipline.convocation', $dossier) }}" class="btn" target="_blank">🖨️ Convocation à imprimer</a>
            @droit('discipline', 'modifier') <a href="{{ route('discipline.edit', $dossier) }}" class="btn">Modifier</a> @enddroit
        </div>
    </div>
    @include('cours._erreurs')

    <div class="bloc">
        <h3>Comparution</h3>
        <div class="element"><div class="infos"><b>Motif :</b> {{ $dossier->motif }}</div></div>
        @if($dossier->faits) <div class="element"><div class="infos" style="white-space:pre-line;"><b>Faits :</b> {{ $dossier->faits }}</div></div> @endif
        <div class="element"><div class="infos">
            <b>Date des faits :</b> {{ $dossier->date_faits?->format('d/m/Y') ?? '—' }} ·
            <b>Conseil :</b> {{ $dossier->date_conseil?->format('d/m/Y à H:i') ?? 'date à fixer' }} {{ $dossier->lieu ? '· ' . $dossier->lieu : '' }}
            <small>Enregistré le {{ $dossier->created_at->format('d/m/Y') }} par {{ $dossier->createur->name ?? '—' }}</small>
        </div></div>
        <div class="element"><div class="infos"><b>Parent :</b>
            @if($dossier->convocation_lue_le) <span class="pastille vert">✔ convocation lue le {{ $dossier->convocation_lue_le->format('d/m/Y à H:i') }}</span>
            @else <span class="pastille rouge">convocation pas encore lue dans son espace</span> @endif
            @if($dossier->estDecide())
                @if($dossier->decision_lue_le) <span class="pastille vert">✔ décision lue le {{ $dossier->decision_lue_le->format('d/m/Y') }}</span>
                @else <span class="pastille rouge">décision pas encore lue</span> @endif
            @endif
        </div></div>
    </div>

    <div class="bloc">
        <h3>Décision du conseil</h3>
        @if($dossier->estDecide())
            <div style="background:#f3efe6; padding:10px 14px; border-radius:8px; margin:8px 0;">
                <b style="font-size:16px;">{{ $dossier->libelle_decision }}</b> — le {{ $dossier->date_decision?->format('d/m/Y') }}
                @if($dossier->decision_details) <div style="margin-top:4px; white-space:pre-line;">{{ $dossier->decision_details }}</div> @endif
                <small style="color:#777;">Enregistrée par {{ $dossier->decideur->name ?? '—' }}</small>
            </div>
        @endif
        @droit('discipline', 'modifier')
            <form action="{{ route('discipline.decider', $dossier) }}" method="POST" style="display:grid; grid-template-columns:1fr 1fr; gap:0 16px; max-width:760px;">
                @csrf
                <div><label>Décision</label>
                    <select name="decision" required>
                        @foreach(\App\Models\DossierDiscipline::DECISIONS as $code => $libelle)
                            <option value="{{ $code }}" @selected($dossier->decision === $code)>{{ $libelle }}</option>
                        @endforeach
                    </select></div>
                <div><label>Date de la décision</label><input type="date" name="date_decision" value="{{ $dossier->date_decision?->format('Y-m-d') ?? now()->format('Y-m-d') }}" required></div>
                <div style="grid-column:1 / -1;"><label>Précisions (durée d'exclusion, conditions…) — visibles par le parent</label>
                    <textarea name="decision_details" rows="3">{{ $dossier->decision_details }}</textarea></div>
                <div style="margin-top:12px;"><button type="submit" class="btn">{{ $dossier->estDecide() ? 'Modifier la décision' : 'Enregistrer la décision et alerter le parent' }}</button></div>
            </form>
        @enddroit
    </div>

    @if($historique->isNotEmpty())
        <div class="bloc">
            <h3>Autres passages de l'élève au conseil</h3>
            @foreach($historique as $h)
                <div class="element"><div class="infos"><a href="{{ route('discipline.show', $h) }}"><b>{{ $h->date_conseil?->format('d/m/Y') ?? $h->created_at->format('d/m/Y') }}</b> — {{ $h->motif }}</a>
                    <small>{{ $h->libelle_decision ?? 'En attente' }}</small></div></div>
            @endforeach
        </div>
    @endif

    @droit('discipline', 'supprimer')
        <form action="{{ route('discipline.destroy', $dossier) }}" method="POST" onsubmit="return confirm('Supprimer définitivement ce dossier ?');">@csrf @method('DELETE')
            <button class="btn btn-danger">Supprimer le dossier</button></form>
    @enddroit
@endsection
