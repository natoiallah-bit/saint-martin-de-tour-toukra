<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Support\Etablissement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class IdentiteEcoleTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function tearDown(): void
    {
        File::delete(glob(public_path('images/ecole/*.{png,jpg}'), GLOB_BRACE) ?: []);
        parent::tearDown();
    }

    public function test_identite_par_defaut_de_saint_martin_de_tour_de_toukra(): void
    {
        $this->assertSame('Complexe Scolaire Saint Martin de Tour de Toukra', Etablissement::nom());
        $this->assertSame('Saint Martin de Tour', Etablissement::nomCourt());
        $this->assertNull(Etablissement::banniere());
        $this->assertSame('SMT-', \App\Models\Eleve::prefixeMatricule());
        $this->assertStringStartsWith('SMT-2', \App\Models\Paiement::genererNumeroRecu());
        $this->get('/login')->assertOk()->assertSee('Saint Martin de Tour')->assertDontSee('Cité du Savoir');
    }

    public function test_changer_d_identite_ne_laisse_rien_de_l_ancienne(): void
    {
        $this->artisan('ecole:identite', [
            'nom' => 'Complexe Scolaire Saint Martin de Tours',
            '--court' => 'Saint Martin de Tours', '--sigle' => 'smt',
            '--quartier' => 'Moursal', '--ville' => "N'Djamena", '--telephone' => '66 00 00 01',
            '--couleur' => '#1F3A60', '--couleur2' => '#a3201f',
        ])->assertSuccessful();

        $config = ConfigurationEcole::actuelle();
        $this->assertSame('SMT', $config->sigle_ecole);
        $this->assertNull(Etablissement::banniere());
        $this->assertNull(Etablissement::embleme());
        $this->assertSame('SMT', Etablissement::initiales());
        $this->assertSame("Moursal, N'Djamena", Etablissement::adresse());
        $this->assertSame('SMT-', \App\Models\Eleve::prefixeMatricule());
        $this->assertStringStartsWith('SMT-2', \App\Models\Paiement::genererNumeroRecu());

        $this->assertSame('#1f3a60', Etablissement::couleurs()['principale']);
        $this->get('/login')->assertOk()->assertSee('Saint Martin de Tours')->assertDontSee('Cité du Savoir')
            ->assertSee('--indigo-950:#1f3a60', false)->assertDontSee('#312c63', false);

        $eleve = $this->eleve();
        $paiement = $this->paiement($eleve);
        $direction = $this->utilisateur('direction');
        foreach (['/', route('paiements.recu', $paiement), route('bulletins.show', [$eleve, 1]), route('eleves.carte', $eleve)] as $page) {
            $this->actingAs($direction)->get($page)->assertOk()
                ->assertSee('Saint Martin de Tours')
                ->assertDontSee('Cité du Savoir')->assertDontSee('Farcha')->assertDontSee('85 10 04');
        }
    }

    public function test_la_direction_envoie_banniere_et_embleme(): void
    {
        $this->actingAs($this->utilisateur('direction'))->put(route('parametres.update'), [
            'nom_ecole' => 'Complexe Scolaire Saint Martin de Tours', 'nom_court' => 'Saint Martin de Tours',
            'ville' => "N'Djamena", 'annee_scolaire_active' => '2026-2027', 'coefficient_conduite' => 1,
            'logo' => UploadedFile::fake()->image('banniere.png', 900, 200),
            'embleme' => UploadedFile::fake()->image('embleme.png', 300, 300),
        ])->assertRedirect(route('parametres.index'));

        $this->assertFileExists(public_path('images/ecole/banniere.png'));
        $this->assertStringContainsString('images/ecole/banniere.png', (string) Etablissement::banniere());
        $this->assertStringContainsString('images/ecole/embleme.png', (string) Etablissement::embleme());
        $this->assertSame('Saint Martin de Tours', Etablissement::nomCourt());
    }
}
