@extends('layouts.app')
@section('title', 'Relances des impayés')

@section('content')
    @php $fcfa = fn ($m) => number_format($m, 0, ',', ' ') . ' FCFA'; @endphp

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <h1>Relances des impayés — {{ $annee }}</h1>
        <a href="{{ route('convocations.retards.historique') }}" class="btn" style="background:#e3ddcf; color:#141c33;">Historique des relances</a>
    </div>

    @if(session('error'))
        <div style="background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px;">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div style="background:#fbe9e7; color:#a33; padding:10px 14px; border-radius:6px; margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    @unless($echeancierConfigure)
        <div style="background:#fff4dc; color:#7a5a12; padding:10px 14px; border-radius:6px; margin-bottom:14px;">
            L'échéancier (inscription et tranches avec dates limites) n'est pas encore réglé : le retard est calculé sur le montant annuel entier.
            @droit('frais', 'lecture') <a href="{{ route('frais.index') }}">Régler l'échéancier</a> @enddroit
        </div>
    @endunless

    <p style="color:#666;">
        Une famille est en retard quand elle a payé moins que ce qui était dû à ce jour selon l'échéancier.
        Les frères et sœurs sont regroupés (même carte de retrait, même compte parent ou même téléphone).
    </p>

    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; margin:12px 0;">
        <select name="classe_id" onchange="this.form.submit()" style="max-width:220px;">
            <option value="">Toutes les classes</option>
            @foreach($classes as $c)
                <option value="{{ $c->id }}" @selected(request('classe_id') == $c->id)>{{ $c->nom }}</option>
            @endforeach
        </select>
        <select name="niveau" onchange="this.form.submit()" style="max-width:260px;">
            <option value="">Toutes les relances</option>
            @foreach(\App\Models\RelancePaiement::NIVEAUX as $n => $libelle)
                <option value="{{ $n }}" @selected(request('niveau') == $n)>Prochaine lettre : {{ $libelle }}</option>
            @endforeach
        </select>
    </form>

    @if($familles->isEmpty())
        <div style="background:#e2ede6; color:#4c7a63; padding:14px; border-radius:8px;">✅ Aucune famille en retard de paiement.</div>
    @else
        <div style="display:flex; gap:24px; margin-bottom:12px; font-size:15px;">
            <div>Familles en retard : <b>{{ $familles->count() }}</b></div>
            <div>Total en retard : <b style="color:#a8503c;">{{ $fcfa($familles->sum('retard')) }}</b></div>
        </div>

        <form action="{{ route('convocations.retards.generer') }}" method="POST" target="_blank">
            @csrf
            <div style="margin-bottom:12px;">
                <button type="button" class="btn" onclick="toutCocher(true)">Tout sélectionner</button>
                <button type="button" class="btn" style="background:#e3ddcf; color:#141c33;" onclick="toutCocher(false)">Tout désélectionner</button>
            </div>

            <table>
                <thead>
                    <tr><th></th><th>Famille</th><th>Enfants</th><th>Dû à ce jour</th><th>Versé</th><th>Retard</th><th>Dernière relance</th><th>Prochaine lettre</th></tr>
                </thead>
                <tbody>
                    @foreach($familles as $famille)
                        <tr>
                            <td><input type="checkbox" name="familles[]" value="{{ $famille['cle'] }}" class="case-famille" style="width:auto;"></td>
                            <td><b>{{ $famille['destinataire'] }}</b>@if($famille['telephone'])<br><small>{{ $famille['telephone'] }}</small>@endif</td>
                            <td>
                                @foreach($famille['enfants'] as $s)
                                    {{ $s['eleve']->nom }} {{ $s['eleve']->prenom }} <small style="color:#777;">({{ $s['eleve']->classe->nom ?? '—' }})</small>@if(! $loop->last)<br>@endif
                                @endforeach
                            </td>
                            <td style="white-space:nowrap;">{{ $fcfa($famille['exigible']) }}</td>
                            <td style="white-space:nowrap;">{{ $fcfa($famille['verse']) }}</td>
                            <td style="color:#a8503c; font-weight:bold; white-space:nowrap;">{{ $fcfa($famille['retard']) }}</td>
                            <td>
                                @if($famille['derniere_relance'])
                                    {{ $famille['derniere_relance']->libelle_niveau }}<br><small>{{ $famille['derniere_relance']->date_relance->format('d/m/Y') }}</small>
                                @else
                                    —
                                @endif
                            </td>
                            <td><b @if($famille['niveau_suivant'] === 3) style="color:#a8503c;" @endif>{{ \App\Models\RelancePaiement::NIVEAUX[$famille['niveau_suivant']] }}</b></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            @droit('convocations', 'modifier')
            <div style="margin-top:16px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <label style="display:flex; align-items:center; gap:6px; margin:0;">
                    Délai pour régulariser
                    <input type="number" name="delai" value="8" min="1" max="60" style="width:70px;"> jours
                </label>
                <button type="submit" name="action" value="apercu" class="btn" style="background:#e3ddcf; color:#141c33;">Aperçu des lettres</button>
                <button type="submit" name="action" value="enregistrer" class="btn"
                        onclick="return confirm('Les lettres seront enregistrées dans l\'historique : la prochaine lettre de ces familles sera du niveau suivant. Continuer ?');">
                    Générer et enregistrer les lettres
                </button>
            </div>
            @enddroit
        </form>

        <script>
            function toutCocher(etat) {
                document.querySelectorAll('.case-famille').forEach(c => c.checked = etat);
            }
        </script>
    @endif
@endsection
