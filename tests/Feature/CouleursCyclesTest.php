<?php

namespace Tests\Feature;

use App\Models\CarteRetrait;
use App\Models\ConfigurationEcole;
use App\Models\Cycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouleursCyclesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_couleur_par_defaut_selon_le_nom_du_cycle(): void
    {
        $this->assertSame('#6a2c91', Cycle::couleurParDefaut('Maternelle'));
        $this->assertSame('#1b7a3a', Cycle::couleurParDefaut('Primaire'));
        $this->assertSame('#1d4ea8', Cycle::couleurParDefaut('Collège'));
        $this->assertSame('#b0182e', Cycle::couleurParDefaut('LYCÉE'));
        $this->assertSame(Cycle::COULEUR_NEUTRE, Cycle::couleurParDefaut('Formation continue'));
    }

    public function test_la_carte_scolaire_porte_la_couleur_et_le_nom_du_cycle(): void
    {
        $lycee = $this->cycle('Lycée');
        $eleve = $this->eleve($lycee, 'Brahim');

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('eleves.carte', $eleve))
            ->assertOk()
            ->assertSee('--cycle:#b0182e', false)
            ->assertSee('<div class="bande"><div>Lycée</div></div>', false);
    }

    public function test_carte_de_retrait_d_une_famille_sur_deux_cycles(): void
    {
        $primaire = $this->eleve($this->cycle('Primaire'), 'Cadet');
        $college = $this->eleve($this->cycle('Collège'), 'Aine');
        $carte = CarteRetrait::create(['numero' => 'RET-0001', 'pere_nom' => 'Moussa', 'responsable_nom' => 'Moussa', 'responsable_lien' => 'Père', 'responsable_sexe' => 'M', 'responsable_telephone' => '1']);
        $carte->eleves()->attach([$primaire->id, $college->id]);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('cartes-retrait.imprimer', $carte))
            ->assertOk()
            ->assertSee('style="background:#1b7a3a">Primaire', false)
            ->assertSee('style="background:#1d4ea8">Collège', false)
            ->assertSee('--cycle:' . Cycle::COULEUR_NEUTRE, false);   // titre neutre quand les cycles sont mélangés
    }

    public function test_cartes_de_retrait_de_couleurs_differentes_selon_le_cycle(): void
    {
        $secretariat = $this->utilisateur('secretariat');
        foreach (['Maternelle' => '#6a2c91', 'Primaire' => '#1b7a3a', 'Collège' => '#1d4ea8'] as $nom => $couleur) {
            $carte = CarteRetrait::create(['pere_nom' => 'Parent', 'responsable_nom' => 'Parent', 'responsable_lien' => 'Père', 'responsable_sexe' => 'M', 'responsable_telephone' => '1']);
            $carte->eleves()->attach($this->eleve($this->cycle($nom), 'Enfant' . $nom));

            $this->actingAs($secretariat)->get(route('cartes-retrait.imprimer', $carte))
                ->assertOk()
                ->assertSee('--cycle:' . $couleur, false)                     // titre et filets
                ->assertSee('style="background:' . $couleur . '">' . $nom, false);   // bande avec le nom du cycle
        }
    }

    public function test_la_direction_change_la_couleur_d_un_cycle(): void
    {
        $cycle = $this->cycle('Primaire');
        $config = ConfigurationEcole::actuelle();

        $this->actingAs($this->utilisateur('direction'))
            ->put(route('parametres.update'), [
                'nom_ecole' => 'Saint Martin de Tour', 'ville' => "N'Djamena", 'annee_scolaire_active' => '2026-2027',
                'coefficient_conduite' => 1, 'couleurs' => [$cycle->id => '#FF8800'],
            ])
            ->assertRedirect(route('parametres.index'));

        $this->assertSame('#ff8800', $cycle->fresh()->couleur_carte);

        $this->actingAs($this->utilisateur('direction'))
            ->put(route('parametres.update'), [
                'nom_ecole' => 'Saint Martin de Tour', 'ville' => "N'Djamena", 'annee_scolaire_active' => '2026-2027',
                'coefficient_conduite' => 1, 'couleurs' => [$cycle->id => 'rouge'],
            ])
            ->assertSessionHasErrors('couleurs.' . $cycle->id);
    }
}
