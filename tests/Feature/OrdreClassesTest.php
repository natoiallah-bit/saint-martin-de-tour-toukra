<?php

namespace Tests\Feature;

use App\Models\ClasseScolaire;
use App\Models\Cycle;
use App\Models\Ecole;
use App\Models\Eleve;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrdreClassesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function classe(string $nom, Cycle $cycle): ClasseScolaire
    {
        return ClasseScolaire::create(['cycle_id' => $cycle->id, 'nom' => $nom]);
    }

    protected function inscrire(ClasseScolaire $classe, string $nom, string $prenom): Eleve
    {
        return Eleve::create(['classe_id' => $classe->id, 'matricule' => 'ELV-' . uniqid(), 'nom' => $nom, 'prenom' => $prenom]);
    }

    public function test_les_classes_suivent_l_ordre_de_l_ecole(): void
    {
        $maternelle = $this->cycle('Maternelle');
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $lycee = $this->cycle('Lycée');

        // Créées volontairement dans le désordre
        foreach ([['Tle D', $lycee], ['6ème B', $college], ['CM2', $primaire], ['Grande section', $maternelle], ['CE1', $primaire],
                  ['3ème', $college], ['CP1', $primaire], ['6ème A', $college], ['2nde', $lycee], ['Petite section', $maternelle], ['1ère D', $lycee]] as [$nom, $cycle]) {
            $this->classe($nom, $cycle);
        }

        $this->assertSame(
            ['Petite section', 'Grande section', 'CP1', 'CE1', 'CM2', '6ème A', '6ème B', '3ème', '2nde', '1ère D', 'Tle D'],
            ClasseScolaire::ordonnees()->pluck('nom')->all()
        );
    }

    public function test_renommer_une_classe_ou_un_cycle_met_l_ordre_a_jour(): void
    {
        $cycle = $this->cycle('Section A');
        $classe = $this->classe('CM1', $cycle);
        $this->assertSame(514, $classe->fresh()->ordre);

        $cycle->update(['nom' => 'Primaire']);
        $this->assertSame(214, $classe->fresh()->ordre);

        $classe->update(['nom' => 'CP2']);
        $this->assertSame(211, $classe->fresh()->ordre);
    }

    public function test_liste_des_eleves_par_classe_puis_ordre_alphabetique_avec_filtre_et_recherche(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $sixieme = $this->classe('6ème A', $college);
        $cm2 = $this->classe('CM2', $primaire);

        $this->inscrire($sixieme, 'ABAKAR', 'Ali');
        $this->inscrire($cm2, 'ZAKARIA', 'Moussa');
        $this->inscrire($cm2, 'BRAHIM', 'Zara');
        $this->inscrire($cm2, 'BRAHIM', 'Amina');   // même nom : départagé par le prénom

        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get(route('eleves.index'))
            ->assertOk()
            ->assertSeeInOrder(['Amina', 'Zara', 'ZAKARIA', 'ABAKAR']);   // CM2 avant 6ème A

        $this->actingAs($secretariat)->get(route('eleves.index', ['classe_id' => $sixieme->id]))
            ->assertOk()->assertSee('ABAKAR')->assertDontSee('ZAKARIA');

        $this->actingAs($secretariat)->get(route('eleves.index', ['q' => 'brah']))
            ->assertOk()->assertSee('Amina')->assertDontSee('ABAKAR')->assertSee('2 élève(s)');

        $this->actingAs($secretariat)->get(route('eleves.liste-imprimable'))
            ->assertOk()->assertSeeInOrder(['CM2', 'Amina', 'Zara', 'ZAKARIA', '6ème A', 'ABAKAR']);
    }
}
