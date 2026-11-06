@extends('layouts.app')
@section('title', 'Générer un bulletin')

@section('content')
    <h1>Générer un bulletin de paie</h1>

    <form action="{{ route('paies.store') }}" method="POST">
        @csrf

        <label>Personnel</label>
        <select name="personnel_id" id="personnel-select" required>
            <option value="">— Choisir —</option>
            @foreach($personnels as $p)
                <option value="{{ $p->id }}" @selected(request('personnel_id') == $p->id)
                    data-salaire="{{ $p->salaire_base }}"
                    data-horaire="{{ $p->paiement_horaire ? '1' : '0' }}"
                    data-taux="{{ $p->taux_horaire }}">
                    {{ $p->nom }} {{ $p->prenom }} — {{ $p->libelle_type }}
                    @if($p->paiement_horaire) (payé à l'heure) @endif
                </option>
            @endforeach
        </select>

        <label>Mois</label>
        <input type="month" name="mois" value="{{ $mois }}" required>

        <div id="bloc-salaire-fixe">
            <label>Salaire de base (FCFA)</label>
            <input type="number" name="salaire_base" id="salaire-input" min="0" step="500">
        </div>

        <div id="bloc-paiement-horaire" style="display:none;">
            <label>Nombre d'heures effectuées ce mois</label>
            <input type="number" name="nombre_heures" id="heures-input" min="0" step="0.5">
            <p id="calcul-horaire" style="font-size:12.5px; color:#5a5a5a; margin-top:4px;"></p>
        </div>

        <label>Primes (FCFA)</label>
        <input type="number" name="primes" min="0" step="500" value="0">

        <label>Acompte déjà versé ce mois (FCFA)</label>
        <input type="number" name="acompte" min="0" step="500" value="0">

        <label>Retenues (FCFA)</label>
        <input type="number" name="retenues" min="0" step="500" value="0">

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
            <div>
                <label>Salaire versé le</label>
                <input type="date" name="date_paiement" value="{{ old('date_paiement', now()->format('Y-m-d')) }}" required>
            </div>
            <div>
                <label>Mode de versement</label>
                <select name="mode_paiement" required>
                    @foreach(\App\Models\Paie::MODES as $m) <option value="{{ $m }}" @selected(old('mode_paiement') === $m)>{{ $m }}</option> @endforeach
                </select>
            </div>
        </div>
        <div style="font-size:12px; color:#5a5a5a; margin-top:6px;">Le net à payer est ajouté automatiquement aux dépenses « Salaires » de la comptabilité et sort de la caisse ou de la banque selon le mode.</div>

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Générer le bulletin</button>
            <a href="{{ route('paies.index') }}">Annuler</a>
        </div>
    </form>

    <script>
        const select = document.getElementById('personnel-select');
        const blocFixe = document.getElementById('bloc-salaire-fixe');
        const blocHoraire = document.getElementById('bloc-paiement-horaire');
        const salaireInput = document.getElementById('salaire-input');
        const heuresInput = document.getElementById('heures-input');
        const calculTexte = document.getElementById('calcul-horaire');

        function majCalculHoraire() {
            const taux = parseFloat(select.options[select.selectedIndex]?.getAttribute('data-taux')) || 0;
            const heures = parseFloat(heuresInput.value) || 0;
            const total = Math.round(heures * taux);
            calculTexte.textContent = heures + ' h × ' + taux.toLocaleString('fr-FR') + ' FCFA/h = ' + total.toLocaleString('fr-FR') + ' FCFA';
        }

        select.addEventListener('change', function () {
            const option = this.options[this.selectedIndex];
            const estHoraire = option.getAttribute('data-horaire') === '1';
            const salaire = option.getAttribute('data-salaire');

            if (estHoraire) {
                blocFixe.style.display = 'none';
                blocHoraire.style.display = 'block';
                salaireInput.removeAttribute('required');
                heuresInput.setAttribute('required', 'required');
                majCalculHoraire();
            } else {
                blocFixe.style.display = 'block';
                blocHoraire.style.display = 'none';
                salaireInput.setAttribute('required', 'required');
                heuresInput.removeAttribute('required');
                salaireInput.value = salaire || '';
            }
        });

        heuresInput.addEventListener('input', majCalculHoraire);
        // Membre déjà choisi (arrivée depuis sa fiche) : afficher son salaire
        if (select.value) select.dispatchEvent(new Event('change'));
    </script>
@endsection
