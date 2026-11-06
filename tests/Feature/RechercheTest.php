<?php

namespace Tests\Feature;

use App\Models\EmploiTemps;
use App\Models\Matiere;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechercheTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_le_principal_recherche_eleves_et_enseignants_de_son_cycle(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $eleve = $this->eleve($primaire, 'MOUSSA');
        $eleve->update(['prenom' => 'Ali', 'date_entree' => '2024-09-02', 'ancienne_ecole' => 'École de Chagoua', 'aptitude_physique' => 'inapte_partiel', 'aptitude_remarque' => 'Asthme']);
        $this->eleve($college, 'MOUSSAKOLL');

        $instit = Personnel::create(['matricule' => 'P-1', 'nom' => 'MOUSSAINSTIT', 'prenom' => 'Paul', 'type' => 'enseignant', 'salaire_base' => 90000, 'actif' => true, 'cycle_id' => $primaire->id, 'statut' => 'permanent']);
        $profCollege = Personnel::create(['matricule' => 'P-2', 'nom' => 'MOUSSAPROF', 'prenom' => 'Jean', 'type' => 'enseignant', 'salaire_base' => 0, 'actif' => true, 'cycle_id' => $college->id]);
        // Enseignant du collège qui fait aussi un cours au primaire : visible du directeur du primaire
        $matiere = Matiere::create(['cycle_id' => $primaire->id, 'nom' => 'Anglais', 'coefficient' => 1, 'categorie' => 'Langue']);
        EmploiTemps::create(['classe_id' => $eleve->classe_id, 'jour' => 'mardi', 'heure_debut' => '10:00', 'heure_fin' => '11:00', 'matiere_id' => $matiere->id, 'enseignant_id' => $profCollege->id]);
        $gardienCollege = Personnel::create(['matricule' => 'P-3', 'nom' => 'MOUSSAGARDIEN', 'prenom' => 'X', 'type' => 'securite', 'salaire_base' => 0, 'actif' => true, 'cycle_id' => $college->id]);

        $principal = $this->utilisateur('principal', $primaire);

        // La barre de recherche est en haut de la page d'accueil
        $this->actingAs($principal)->get(route('dashboard'))->assertOk()->assertSee('class="recherche-haut"', false);

        $this->actingAs($principal)->get(route('recherche', ['q' => 'moussa']))->assertOk()
            ->assertSee('MOUSSA')->assertDontSee('MOUSSAKOLL')
            ->assertSee('MOUSSAINSTIT')->assertSee('MOUSSAPROF')->assertDontSee('MOUSSAGARDIEN');
        $this->actingAs($principal)->get(route('recherche', ['q' => 'ali moussa']))->assertOk()->assertSee('MOUSSA Ali', false);

        // Fiche élève : scolarité, ancienne école, aptitude ; pas de finances pour le Principal
        $this->actingAs($principal)->get(route('recherche.eleve', $eleve))->assertOk()
            ->assertSee('02/09/2024')->assertSee('École de Chagoua')->assertSee('Inapte partiel')->assertSee('Asthme')
            ->assertSee('Résultats')->assertDontSee('Déjà versé');

        // Fiche enseignant : sans le salaire ni les documents
        $this->actingAs($principal)->get(route('personnels.show', $instit))->assertOk()
            ->assertSee('Permanent')->assertDontSee('90 000 FCFA')->assertDontSee('Accès et documents');
        $this->actingAs($principal)->get(route('personnels.show', $gardienCollege))->assertForbidden();
        $this->actingAs($principal)->get(route('recherche.eleve', $this->eleve($college, 'Autre')))->assertForbidden();
    }

    public function test_le_comptable_voit_les_finances_et_pas_le_personnel(): void
    {
        $primaire = $this->cycle('Primaire');
        $eleve = $this->eleve($primaire, 'MOUSSA');
        $this->paiement($eleve);
        $comptable = $this->utilisateur('comptable', $primaire);

        $this->actingAs($comptable)->get(route('recherche', ['q' => 'mous']))->assertOk()->assertSee('MOUSSA')->assertDontSee('Personnel (');
        $this->actingAs($comptable)->get(route('recherche.eleve', $eleve))->assertOk()->assertSee('Déjà versé')->assertDontSee('Résultats');

        // Les parents et les élèves n'ont pas de recherche
        $parent = $this->parentDe($eleve);
        $this->actingAs($parent)->get(route('recherche', ['q' => 'mous']))->assertForbidden();
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertDontSee('class="recherche-haut"', false);
    }

    public function test_date_d_entree_et_aptitude_enregistrees_avec_la_fiche(): void
    {
        $cycle = $this->cycle();
        $eleve = $this->eleve($cycle, 'DOE');
        $this->actingAs($this->utilisateur('secretariat'))->put(route('eleves.update', $eleve), [
            'classe_id' => $eleve->classe_id, 'matricule' => $eleve->matricule, 'nom' => 'DOE', 'prenom' => 'John', 'tuteur_nom' => 'X',
            'date_entree' => '2025-09-01', 'ancienne_ecole' => 'Lycée Félix Éboué', 'aptitude_physique' => 'inapte_total', 'aptitude_jusqu_au' => '2026-12-31',
        ])->assertRedirect();

        $eleve->refresh();
        $this->assertSame('2025-09-01', $eleve->date_entree->format('Y-m-d'));
        $this->assertSame('Lycée Félix Éboué', $eleve->ancienne_ecole);
        $this->assertSame('Inapte total', $eleve->libelle_aptitude);
    }

    public function test_la_liste_n_affiche_que_fiche_et_la_fiche_regroupe_les_actions(): void
    {
        $eleve = $this->eleve(null, 'DJIMET');
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get(route('eleves.index'))->assertOk()
            ->assertSee(route('recherche.eleve', $eleve), false)
            ->assertSee('Fiche')
            ->assertDontSee(route('eleves.carte', $eleve), false)
            ->assertDontSee(route('eleves.edit', $eleve), false);

        $this->actingAs($secretariat)->get(route('recherche.eleve', $eleve))->assertOk()
            ->assertSee('DJIMET')
            ->assertSee(route('eleves.carte', $eleve), false)
            ->assertSee(route('eleves.documents.index', $eleve), false)
            ->assertSee(route('eleves.edit', $eleve), false)
            ->assertSee(route('eleves.destroy', $eleve), false)
            ->assertSee('Supprimer')
            ->assertSee(route('certificats.store', $eleve), false);

        // L'enseignant (lecture seule) ne voit que la carte
        $this->actingAs($this->utilisateur('enseignant'))->get(route('recherche.eleve', $eleve))->assertOk()
            ->assertSee(route('eleves.carte', $eleve), false)
            ->assertDontSee(route('eleves.edit', $eleve), false)
            ->assertDontSee('Supprimer')
            ->assertDontSee(route('certificats.store', $eleve), false);
    }
}
