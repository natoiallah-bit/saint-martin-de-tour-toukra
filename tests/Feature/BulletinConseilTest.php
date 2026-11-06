<?php

namespace Tests\Feature;

use App\Helpers\Droits;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\ConseilClasse;
use App\Models\DroitRole;
use App\Models\EmploiTemps;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use App\Models\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletinConseilTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function preparerClasse(): array
    {
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027']);
        $cycle = $this->cycle('Collège');
        $profPrincipal = Enseignant::create(['nom' => 'MAHAMAT', 'prenom' => 'Idriss']);
        $profMaths = Enseignant::create(['nom' => 'ABAKAR', 'prenom' => 'Ali']);

        $eleve = $this->eleve($cycle, 'Moussa');
        ClasseScolaire::whereKey($eleve->classe_id)->update(['prof_principal_id' => $profPrincipal->id]);
        $maths = Matiere::create(['cycle_id' => $cycle->id, 'enseignant_id' => $profMaths->id, 'nom' => 'Mathématiques', 'coefficient' => 4, 'categorie' => 'Scientifique']);
        Note::create(['eleve_id' => $eleve->id, 'matiere_id' => $maths->id, 'trimestre' => 1, 'moyenne_classe' => 15, 'moyenne_composition' => 14]);

        return [$eleve->fresh(), $profPrincipal];
    }

    public function test_le_bulletin_affiche_enseignants_appreciations_et_conseil(): void
    {
        [$eleve] = $this->preparerClasse();
        ConseilClasse::create([
            'eleve_id' => $eleve->id, 'trimestre' => 1, 'annee_scolaire' => '2026-2027',
            'note_conduite' => 15.5,
            'appreciation' => 'Bon trimestre, continuez.', 'commentaire_conduite' => 'Élève poli et assidu.',
        ]);

        // Appel : absent un lundi (4 h de cours) et un mardi (2 h), en retard une fois ;
        // une absence hors du trimestre n'est pas comptée
        ConfigurationEcole::actuelle()->update(['trimestre1_debut' => '2026-10-01', 'trimestre1_fin' => '2026-12-20']);
        $maths = Matiere::first();
        EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '07:00', 'heure_fin' => '09:00', 'matiere_id' => $maths->id]);
        EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '09:00', 'heure_fin' => '11:00', 'matiere_id' => $maths->id]);
        EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'mardi', 'heure_debut' => '07:00', 'heure_fin' => '09:00', 'matiere_id' => $maths->id]);
        foreach ([['2026-10-05', 'absent'], ['2026-10-06', 'absent'], ['2026-10-07', 'retard'], ['2026-10-08', 'present'], ['2027-01-11', 'absent']] as [$date, $statut]) {
            Presence::create(['eleve_id' => $eleve->id, 'classe_id' => $eleve->classe_id, 'date' => $date, 'statut' => $statut]);
        }

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertOk()
            ->assertSee('Ali ABAKAR')                 // enseignant de la matière
            ->assertSee('Idriss MAHAMAT')             // professeur principal
            ->assertSee('Très bien')                  // appréciation de la matière (14,5)
            ->assertSee('6 h')                        // 4 h (lundi) + 2 h (mardi)
            ->assertSee('15,5')                       // note de conduite, ligne Conduite du tableau
            ->assertSee('Bon trimestre, continuez.')
            ->assertSee('Élève poli et assidu.')      // appréciation de la ligne Conduite
            ->assertSee('Encouragements')             // distinction automatique (14,7)
            ->assertDontSee('Avant d\'imprimer')       // tout est saisi : pas d'avertissement
            ->assertSee('Le Principal')
            ->assertDontSee('Directeur du Complexe');  // une seule signature
    }

    public function test_la_distinction_choisie_par_le_conseil_remplace_le_calcul(): void
    {
        [$eleve] = $this->preparerClasse();
        ConseilClasse::create(['eleve_id' => $eleve->id, 'trimestre' => 1, 'annee_scolaire' => '2026-2027', 'distinction' => 'Avertissement']);

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertSee('Avertissement')
            ->assertDontSee('Encouragements');
    }

    public function test_saisie_du_conseil_par_le_principal(): void
    {
        [$eleve] = $this->preparerClasse();
        $principal = $this->utilisateur('principal');

        $this->actingAs($principal)->get('/conseil-classe')->assertOk();
        $this->actingAs($principal)->get('/conseil-classe/saisie?classe_id=' . $eleve->classe_id . '&trimestre=1')
            ->assertOk()->assertSee('Moussa');

        $autre = $this->eleve($this->cycle('Primaire'), 'Autre');
        $this->actingAs($principal)->post('/conseil-classe', [
            'classe_id' => $eleve->classe_id, 'trimestre' => 1,
            'conseils' => [
                $eleve->id => ['note_conduite' => 16, 'appreciation' => 'Très bon travail.', 'commentaire_conduite' => 'Exemplaire.', 'distinction' => ''],
                $autre->id => ['note_conduite' => 20],   // élève d'une autre classe : ignoré
            ],
        ])->assertRedirect();

        $conseil = ConseilClasse::where('eleve_id', $eleve->id)->firstOrFail();
        $this->assertSame(16.0, $conseil->note_conduite);
        $this->assertSame('2026-2027', $conseil->annee_scolaire);
        $this->assertNull($conseil->distinction);
        $this->assertDatabaseMissing('conseils_classe', ['eleve_id' => $autre->id]);

        // Valeurs hors limites refusées
        $this->actingAs($principal)->post('/conseil-classe', [
            'classe_id' => $eleve->classe_id, 'trimestre' => 1,
            'conseils' => [$eleve->id => ['note_conduite' => 25, 'distinction' => 'Médaille']],
        ])->assertSessionHasErrors(['conseils.' . $eleve->id . '.note_conduite', 'conseils.' . $eleve->id . '.distinction']);
    }

    public function test_un_enseignant_ne_saisit_que_pour_sa_classe_de_professeur_principal(): void
    {
        [$eleve, $profPrincipal] = $this->preparerClasse();

        $prof = $this->utilisateur('enseignant');
        $prof->update(['enseignant_id' => $profPrincipal->id]);
        $this->actingAs($prof->fresh())->get('/conseil-classe/saisie?classe_id=' . $eleve->classe_id . '&trimestre=1')->assertOk();

        $autreProf = $this->utilisateur('enseignant');
        $autreProf->update(['enseignant_id' => Enseignant::create(['nom' => 'X', 'prenom' => 'Y'])->id]);
        $this->actingAs($autreProf->fresh())->get('/conseil-classe/saisie?classe_id=' . $eleve->classe_id . '&trimestre=1')->assertNotFound();
        $this->actingAs($autreProf->fresh())->post('/conseil-classe', ['classe_id' => $eleve->classe_id, 'trimestre' => 1, 'conseils' => []])->assertNotFound();
    }

    public function test_droits_du_module_conseil_de_classe(): void
    {
        $this->assertSame(Droits::MODIFIER, DroitRole::where(['role' => 'principal', 'module' => 'conseil_classe'])->value('niveau'));
        $this->actingAs($this->utilisateur('surveillant'))->get('/conseil-classe')->assertForbidden();

        // Lecture seule : accès à la page du module, mais pas à l'enregistrement
        DroitRole::where(['role' => 'secretariat', 'module' => 'conseil_classe'])->update(['niveau' => Droits::LECTURE]);
        $secretariat = $this->utilisateur('secretariat');
        $this->actingAs($secretariat)->get('/conseil-classe')->assertOk();
        $this->actingAs($secretariat)->post('/conseil-classe', ['classe_id' => 1, 'trimestre' => 1])->assertForbidden();
    }

    public function test_la_conduite_compte_dans_la_moyenne_avec_le_coefficient_des_parametres(): void
    {
        [$eleve] = $this->preparerClasse();   // Maths coef 4 : moyenne 14,5
        ConseilClasse::create(['eleve_id' => $eleve->id, 'trimestre' => 1, 'annee_scolaire' => '2026-2027', 'note_conduite' => 19]);
        $direction = $this->utilisateur('direction');

        // Coefficient 1 (par défaut) : (14,5 x 4 + 19 x 1) / 5 = 15,4
        $this->assertEqualsWithDelta(15.4, $this->actingAs($direction)->get(route('bulletins.show', [$eleve, 1]))->viewData('moyenneGenerale'), 0.001);

        // L'administration fixe le coefficient à 2 : (58 + 38) / 6 = 16
        $this->actingAs($direction)->put('/parametres', [
            'nom_ecole' => 'Saint Martin de Tour', 'ville' => 'N\'Djamena', 'annee_scolaire_active' => '2026-2027', 'coefficient_conduite' => 2,
        ])->assertSessionHasNoErrors();
        $reponse = $this->actingAs($direction)->get(route('bulletins.show', [$eleve, 1]));
        $this->assertEqualsWithDelta(16.0, $reponse->viewData('moyenneGenerale'), 0.001);
        $reponse->assertSee('Félicitations');

        // Coefficient 0 : la conduite est affichée mais pas comptée
        ConfigurationEcole::actuelle()->update(['coefficient_conduite' => 0]);
        $this->assertEqualsWithDelta(14.5, $this->actingAs($direction)->get(route('bulletins.show', [$eleve, 1]))->viewData('moyenneGenerale'), 0.001);
    }

    public function test_la_conduite_change_le_rang(): void
    {
        [$premier] = $this->preparerClasse();   // 14,5 en maths
        $second = $this->eleve($premier->classe->cycle, 'Second');
        Note::create(['eleve_id' => $second->id, 'matiere_id' => Matiere::first()->id, 'trimestre' => 1, 'moyenne_classe' => 15, 'moyenne_composition' => 15]);
        ConfigurationEcole::actuelle()->update(['coefficient_conduite' => 4]);
        ConseilClasse::create(['eleve_id' => $premier->id, 'trimestre' => 1, 'annee_scolaire' => '2026-2027', 'note_conduite' => 18]);
        ConseilClasse::create(['eleve_id' => $second->id, 'trimestre' => 1, 'annee_scolaire' => '2026-2027', 'note_conduite' => 10]);

        // Sans la conduite, Second (15) serait devant ; avec elle : 16,25 contre 12,5
        $reponse = $this->actingAs($this->utilisateur('direction'))->get(route('bulletins.show', [$premier, 1]));
        $this->assertSame(1, $reponse->viewData('rang'));
        $this->assertSame(1, $reponse->viewData('conduite')['rang']);
    }

    public function test_avertissement_avant_impression_si_le_conseil_n_a_pas_tout_saisi(): void
    {
        [$eleve] = $this->preparerClasse();

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertSee('Avant d\'imprimer, complétez dans « Conseil de classe »', false)
            ->assertSee('Ouvrir le conseil de classe');
    }

    public function test_l_enseignant_de_la_matiere_vient_de_l_emploi_du_temps_de_la_classe(): void
    {
        [$eleve] = $this->preparerClasse();   // fiche matière : Ali ABAKAR
        $prof = Personnel::create(['matricule' => 'PERS-0009', 'nom' => 'HAROUN', 'prenom' => 'Fatimé', 'type' => 'enseignant', 'salaire_base' => 0]);
        EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'jeudi', 'heure_debut' => '07:00', 'heure_fin' => '09:00', 'matiere_id' => Matiere::first()->id, 'enseignant_id' => $prof->id]);

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertSee('Fatimé HAROUN')
            ->assertDontSee('Ali ABAKAR');
    }

    public function test_absences_non_comptees_sans_dates_de_trimestre(): void
    {
        [$eleve] = $this->preparerClasse();
        Presence::create(['eleve_id' => $eleve->id, 'classe_id' => $eleve->classe_id, 'date' => '2026-10-05', 'statut' => 'absent']);

        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertSee('réglez les dates du 1er trimestre dans Paramètres', false);

        // Sans emploi du temps, les absences sont comptées en jours
        ConfigurationEcole::actuelle()->update(['trimestre1_debut' => '2026-10-01', 'trimestre1_fin' => '2026-12-20']);
        $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleve, 1]))
            ->assertSee('1 jour')
            ->assertSee('comptées en jours', false);
    }

    public function test_dates_de_trimestre_dans_les_parametres(): void
    {
        $direction = $this->utilisateur('direction');
        $donnees = ['nom_ecole' => 'Saint Martin de Tour', 'ville' => 'N\'Djamena', 'annee_scolaire_active' => '2026-2027', 'coefficient_conduite' => 1];

        $this->actingAs($direction)->put('/parametres', $donnees + ['trimestre1_debut' => '2026-10-01', 'trimestre1_fin' => '2026-12-20'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-12-20', ConfigurationEcole::actuelle()->trimestre1_fin->toDateString());

        // Fin avant le début : refusé
        $this->actingAs($direction)->put('/parametres', $donnees + ['trimestre2_debut' => '2027-03-01', 'trimestre2_fin' => '2027-01-01'])
            ->assertSessionHasErrors('trimestre2_fin');
    }
}
