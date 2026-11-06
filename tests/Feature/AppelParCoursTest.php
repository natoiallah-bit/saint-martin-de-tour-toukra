<?php

namespace Tests\Feature;

use App\Helpers\Assiduite;
use App\Models\ConfigurationEcole;
use App\Models\EmploiTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use App\Models\Presence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppelParCoursTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected const LUNDI = '2026-10-05';

    protected function enseignant(string $nom): array
    {
        $personnel = Personnel::create(['matricule' => 'PERS-' . uniqid(), 'nom' => $nom, 'prenom' => 'Prof', 'type' => 'enseignant', 'salaire_base' => 0]);
        $user = $this->utilisateur('enseignant');
        $user->update(['personnel_id' => $personnel->id]);

        return [$personnel, $user->fresh()];
    }

    protected function preparer(): array
    {
        ConfigurationEcole::actuelle()->update(['trimestre1_debut' => '2026-10-01', 'trimestre1_fin' => '2026-12-21']);
        $eleve = $this->eleve();
        $maths = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Mathématiques', 'coefficient' => 4]);
        $francais = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Français', 'coefficient' => 4]);
        [$profMaths, $userMaths] = $this->enseignant('ABAKAR');
        [$profFr, $userFr] = $this->enseignant('DJIMET');

        $coursMaths = EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '07:00', 'heure_fin' => '09:00', 'matiere_id' => $maths->id, 'enseignant_id' => $profMaths->id]);
        $coursFr = EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'lundi', 'heure_debut' => '09:00', 'heure_fin' => '10:00', 'matiere_id' => $francais->id, 'enseignant_id' => $profFr->id]);

        return compact('eleve', 'coursMaths', 'coursFr', 'userMaths', 'userFr');
    }

    protected function appel(User $user, $eleve, $cours, string $statut, string $date = self::LUNDI)
    {
        return $this->actingAs($user)->post('/presences/enregistrer', [
            'classe_id' => $eleve->classe_id, 'date' => $date, 'cours' => $cours?->id, 'statuts' => [$eleve->id => $statut],
        ]);
    }

    public function test_l_enseignant_voit_et_fait_l_appel_de_ses_cours_seulement(): void
    {
        ['eleve' => $eleve, 'coursMaths' => $coursMaths, 'coursFr' => $coursFr, 'userMaths' => $userMaths] = $this->preparer();
        $url = '/presences/saisie?classe_id=' . $eleve->classe_id . '&date=' . self::LUNDI;

        $this->actingAs($userMaths)->get($url)->assertOk()
            ->assertSee('Mathématiques')->assertDontSee('Français')
            ->assertDontSee('toute la journée');
        $this->actingAs($userMaths)->get($url . '&cours=' . $coursMaths->id)->assertOk()->assertSee('07:00 à 09:00');

        $this->actingAs($userMaths)->get($url . '&cours=' . $coursFr->id)->assertNotFound();
        $this->actingAs($userMaths)->get($url . '&cours=journee')->assertForbidden();
        $this->appel($userMaths, $eleve, $coursFr, 'absent')->assertNotFound();
        $this->appel($userMaths, $eleve, null, 'absent')->assertForbidden();

        $this->appel($userMaths, $eleve, $coursMaths, 'absent')->assertRedirect();
        $presence = Presence::firstOrFail();
        $this->assertSame($coursMaths->id, $presence->emploi_temps_id);
        $this->assertSame(120, $presence->duree_minutes);

        // L'appel apparaît comme fait dans la liste des cours du jour
        $this->actingAs($userMaths)->get($url)->assertSee('✓ Fait', false);
    }

    public function test_heures_d_absence_somme_des_cours_manques(): void
    {
        ['eleve' => $eleve, 'coursMaths' => $coursMaths, 'coursFr' => $coursFr, 'userMaths' => $userMaths, 'userFr' => $userFr] = $this->preparer();

        $this->appel($userMaths, $eleve, $coursMaths, 'absent');   // 2 h
        $this->appel($userFr, $eleve, $coursFr, 'retard');         // retard, pas d'heure d'absence
        $this->appel($userMaths, $eleve, $coursMaths, 'absent', '2026-10-12');   // lundi suivant : 2 h
        $this->appel($userFr, $eleve, $coursFr, 'absent', '2026-10-12');         // 1 h
        $this->appel($userFr, $eleve, $coursFr, 'absent', '2026-12-21');         // dernier jour du trimestre : 1 h, compté
        $this->appel($userFr, $eleve, $coursFr, 'absent', '2026-12-28');         // après la fin du trimestre : non compté

        $a = Assiduite::pourEleve($eleve->id, $eleve->classe_id, 1);
        $this->assertEqualsWithDelta(6.0, $a['heures'], 0.001);
        $this->assertSame(1, $a['retards']);

        // L'emploi du temps change ensuite : les absences passées ne bougent pas
        $coursMaths->update(['heure_fin' => '11:00']);
        $this->assertEqualsWithDelta(6.0, Assiduite::pourEleve($eleve->id, $eleve->classe_id, 1)['heures'], 0.001);
    }

    public function test_absence_toute_la_journee_par_le_surveillant(): void
    {
        ['eleve' => $eleve, 'coursMaths' => $coursMaths, 'userMaths' => $userMaths] = $this->preparer();
        $surveillant = $this->utilisateur('surveillant');

        $this->actingAs($surveillant)->get('/presences/saisie?classe_id=' . $eleve->classe_id . '&date=' . self::LUNDI)
            ->assertOk()->assertSee('Mathématiques')->assertSee('Français')->assertSee('toute la journée');

        // Absent toute la journée (3 h de cours le lundi) + absence au cours de maths ce même jour : pas de double compte
        $this->appel($surveillant, $eleve, null, 'absent')->assertRedirect();
        $this->appel($userMaths, $eleve, $coursMaths, 'absent');

        $this->assertEqualsWithDelta(3.0, Assiduite::pourEleve($eleve->id, $eleve->classe_id, 1)['heures'], 0.001);
        $this->assertSame(2, Presence::count());
    }
}
