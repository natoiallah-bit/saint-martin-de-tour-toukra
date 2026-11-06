@extends('layouts.app')
@section('title', 'Paramètres de l\'école')

@section('content')
    <h1>Paramètres de l'école</h1>

    @if($errors->any())
        <div style="background:#f8e1df; color:#9b2c22; padding:12px 16px; border-radius:6px; margin-bottom:16px;">
            {{ $errors->first() }}
        </div>
    @endif

    <form action="{{ route('parametres.update') }}" method="POST" enctype="multipart/form-data" style="background:#fff; padding:20px; border-radius:10px; max-width:640px;">
        @csrf @method('PUT')

        <label>Nom de l'école</label>
        <input type="text" name="nom_ecole" value="{{ $config->nom_ecole }}" required placeholder="Ex : Complexe Scolaire Saint Martin de Tours">
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Nom complet, imprimé sur les bulletins, reçus, certificats et cartes.</div>

        <label>Nom court</label>
        <input type="text" name="nom_court" value="{{ old('nom_court', $config->nom_court) }}" maxlength="60" placeholder="Ex : Saint Martin de Tours">
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Affiché dans le menu et sur l'écran de connexion. Vide : le nom complet est utilisé.</div>

        <label>Couleurs de l'application</label>
        <div style="display:flex; gap:18px; align-items:center; flex-wrap:wrap;">
            <span><input type="color" name="couleur_principale" value="{{ \App\Support\Etablissement::couleurs()['principale'] }}" style="width:52px; height:34px; padding:2px; vertical-align:middle;"> Principale (menu, barres)</span>
            <span><input type="color" name="couleur_secondaire" value="{{ \App\Support\Etablissement::couleurs()['secondaire'] }}" style="width:52px; height:34px; padding:2px; vertical-align:middle;"> Secondaire (boutons, touches de couleur)</span>
        </div>
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Choisissez les couleurs du logo de l'école. Une couleur principale foncée garde le menu lisible.</div>

        <label>Bannière des documents</label>
        @if(\App\Support\Etablissement::banniere())
            <div style="margin:6px 0;"><img src="{{ \App\Support\Etablissement::banniere() }}" style="max-width:100%; max-height:90px; border:1px solid #d6d3ec; border-radius:6px;"></div>
        @endif
        <input type="file" name="logo" accept="image/png,image/jpeg">
        <div style="font-size:11px; color:#5a5a5a; margin-top:4px;">Image en largeur (logo, nom, adresse, téléphones) en haut des bulletins et des reçus. PNG ou JPG, 2 Mo max. Sans bannière, le nom et l'adresse s'impriment en texte.</div>

        <label>Emblème (logo carré)</label>
        @if(\App\Support\Etablissement::embleme())
            <div style="margin:6px 0;"><img src="{{ \App\Support\Etablissement::embleme() }}" style="width:70px; height:70px; object-fit:contain; border:1px solid #d6d3ec; border-radius:8px;"></div>
        @endif
        <input type="file" name="embleme" accept="image/png,image/jpeg">
        <div style="font-size:11px; color:#5a5a5a; margin-top:4px;">Logo carré du menu, de l'écran de connexion et des cartes. PNG ou JPG, 2 Mo max.</div>

        <label>Photo de l'école</label>
        <input type="file" name="photo" accept="image/jpeg,image/png">
        <div style="font-size:11px; color:#5a5a5a; margin-top:4px;">Grande photo du tableau de bord. JPG ou PNG, 4 Mo max.</div>

        <label>Sigle de l'école</label>
        <input type="text" name="sigle_ecole" value="{{ old('sigle_ecole', $config->sigle_ecole) }}" maxlength="10" pattern="[A-Za-z0-9]+" placeholder="Ex : CDS">
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Début des matricules des nouveaux élèves (ex. SMT-0001). Les matricules déjà attribués ne changent pas.</div>

        <label>Quartier</label>
        <input type="text" name="quartier" value="{{ $config->quartier }}">

        <label>Ville</label>
        <input type="text" name="ville" value="{{ $config->ville }}" required>

        <label>Inspection de rattachement</label>
        <input type="text" name="inspection" value="{{ $config->inspection }}" placeholder="Ex : Inspection du 9e Arrondissement">

        <label>Téléphone</label>
        <input type="text" name="telephone" value="{{ $config->telephone }}">

        <label>Email</label>
        <input type="email" name="email" value="{{ $config->email }}">


        <label>Nom du Directeur / de la Directrice</label>
        <input type="text" name="nom_directeur" value="{{ old('nom_directeur', $config->nom_directeur) }}" placeholder="Ex : M. Abdoulaye MAHAMAT">
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">Imprimé sous la signature des certificats d'inscription.</div>

        <label>Année scolaire active</label>
        <input type="text" name="annee_scolaire_active" value="{{ $config->annee_scolaire_active }}" placeholder="Ex : 2025-2026" required>
        <div style="font-size:11px; color:#a8503c; margin-top:4px;">⚠️ Change ceci uniquement en début de nouvelle année scolaire.</div>

        <label>Coefficient de la conduite</label>
        <input type="number" name="coefficient_conduite" value="{{ old('coefficient_conduite', $config->coefficient_conduite ?? 1) }}" min="0" max="20" step="0.5" required>
        <div style="font-size:12px; color:#5a5a5a; margin-top:4px;">
            Poids de la note de conduite dans la moyenne générale et le rang des bulletins (0 = la conduite n'est pas comptée).
        </div>

        <label>Dates des trimestres</label>
        <div style="font-size:12px; color:#5a5a5a; margin-bottom:6px;">
            Servent à compter, pour chaque bulletin, les absences et retards notés lors de l'appel (page Présences).
        </div>
        <table style="max-width:520px;">
            <thead><tr><th style="text-align:left;">Trimestre</th><th>Début</th><th>Fin</th></tr></thead>
            <tbody>
                @foreach([1, 2, 3] as $t)
                    <tr>
                        <td>{{ $t }}{{ $t == 1 ? 'er' : 'e' }} trimestre</td>
                        <td><input type="date" name="trimestre{{ $t }}_debut" value="{{ old("trimestre{$t}_debut", $config->{"trimestre{$t}_debut"}?->format('Y-m-d')) }}" style="margin:0;"></td>
                        <td><input type="date" name="trimestre{{ $t }}_fin" value="{{ old("trimestre{$t}_fin", $config->{"trimestre{$t}_fin"}?->format('Y-m-d')) }}" style="margin:0;"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($cycles->isNotEmpty())
            <label>Couleur des cartes par cycle</label>
            <div style="font-size:12px; color:#5a5a5a; margin-bottom:6px;">
                Bande de couleur et titres des cartes scolaires et des cartes de retrait, pour reconnaître le cycle d'un coup d'œil.
            </div>
            <table style="max-width:520px;">
                <tbody>
                    @foreach($cycles as $cycle)
                        <tr>
                            <td>{{ $cycle->nom }}</td>
                            <td><input type="color" name="couleurs[{{ $cycle->id }}]" value="{{ old("couleurs.{$cycle->id}", $cycle->couleur_carte) }}" style="width:70px; height:34px; padding:2px; margin:0;"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div style="margin-top:20px;">
            <button type="submit" class="btn">Enregistrer</button>
        </div>
    </form>
@endsection
