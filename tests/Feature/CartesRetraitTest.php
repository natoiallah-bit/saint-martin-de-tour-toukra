<?php

namespace Tests\Feature;

use App\Helpers\QrAuthenticite;
use App\Models\CarteRetrait;
use App\Models\ConfigurationEcole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CartesRetraitTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function setUp(): void
    {
        parent::setUp();
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027', 'trimestre3_fin' => '2027-06-30']);
        $this->poserLogosDeTest();
    }

    protected function tearDown(): void
    {
        $this->retirerLogosDeTest();
        parent::tearDown();
    }

    // Logos de test : les pages (cartes, badges) affichent le logo de l'école en filigrane quand il existe
    protected function poserLogosDeTest(): void
    {
        File::ensureDirectoryExists(public_path('images/ecole'));
        foreach (['banniere.png', 'embleme.png'] as $nom) {
            File::put(public_path('images/ecole/' . $nom), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
        }
    }

    protected function retirerLogosDeTest(): void
    {
        File::delete([public_path('images/ecole/banniere.png'), public_path('images/ecole/embleme.png')]);
    }

    protected function donnees(array $eleveIds, array $autres = []): array
    {
        return $autres + [
            'pere_nom' => 'Moussa Ali', 'pere_telephone' => '+235 66 12 34 56', 'mere_nom' => 'Fatimé Abakar',
            'quartier' => 'Farcha',
            'responsable_nom' => 'Fatimé Abakar', 'responsable_lien' => 'Mère', 'responsable_sexe' => 'F',
            'responsable_telephone' => '+235 66 00 11 22', 'eleves' => $eleveIds,
        ];
    }

    public function test_creation_d_une_carte_par_famille_avec_numero_automatique(): void
    {
        $cycle = $this->cycle();
        $aine = $this->eleve($cycle, 'Aine');
        $cadet = $this->eleve($cycle, 'Cadet');

        $this->actingAs($this->utilisateur('secretariat'))
            ->post(route('cartes-retrait.store'), $this->donnees([$aine->id, $cadet->id], [
                'second_nom' => 'Ali Mahamat', 'second_lien' => 'Oncle', 'second_telephone' => '+235 99 00 11 22',
            ]))
            ->assertRedirect(route('cartes-retrait.index'));

        $carte = CarteRetrait::first();
        $this->assertSame('RET-0001', $carte->numero);
        $this->assertEqualsCanonicalizing([$aine->id, $cadet->id], $carte->eleves->pluck('id')->all());
        $this->assertSame('Ali Mahamat', $carte->second_nom);
        $this->assertSame('Farcha', $carte->quartier);
        $this->assertSame('Moussa Ali · Fatimé Abakar', $carte->parents);
    }

    public function test_formulaires_de_creation_et_modification(): void
    {
        $eleve = $this->eleve(null, 'Moussa');
        $eleve->update(['quartier' => 'Farcha', 'pere_nom' => 'Moussa Ali']);
        $carte = CarteRetrait::create($this->donnees([]) + ['numero' => 'RET-0001']);
        $carte->eleves()->attach($eleve);
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get(route('cartes-retrait.create'))
            ->assertOk()->assertSee('Enfants en charge')->assertSee('Farcha');   // données de la fiche pour pré-remplir
        $this->actingAs($secretariat)->get(route('cartes-retrait.edit', $carte))
            ->assertOk()->assertSee('Modifier la carte RET-0001');
        $this->actingAs($secretariat)->get(route('cartes-retrait.index'))
            ->assertOk()->assertSee('RET-0001')->assertSee('Moussa Ali');
    }

    public function test_validation_enfant_obligatoire_et_seconde_personne_complete(): void
    {
        $eleve = $this->eleve();
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->post(route('cartes-retrait.store'), $this->donnees([]))
            ->assertSessionHasErrors('eleves');

        $this->actingAs($secretariat)->post(route('cartes-retrait.store'), $this->donnees([$eleve->id], ['pere_nom' => null, 'mere_nom' => null]))
            ->assertSessionHasErrors('pere_nom');

        $this->actingAs($secretariat)->post(route('cartes-retrait.store'), $this->donnees([$eleve->id], ['second_nom' => 'Ali']))
            ->assertSessionHasErrors(['second_lien', 'second_telephone']);

        $this->assertSame(0, CarteRetrait::count());
    }

    public function test_carte_une_seule_face_avec_famille_emoji_et_qr_valide(): void
    {
        $eleve = $this->eleve(null, 'Moussa');
        $frere = $this->eleve(null, 'Frere');
        $femme = CarteRetrait::create($this->donnees([]) + ['numero' => 'RET-0001']);
        $femme->eleves()->attach([$eleve->id, $frere->id]);
        $homme = CarteRetrait::create(['responsable_nom' => 'Idriss', 'responsable_lien' => 'Père', 'responsable_sexe' => 'M', 'responsable_telephone' => '1', 'numero' => 'RET-0002']);
        $homme->eleves()->attach($eleve);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('cartes-retrait.imprimer', $femme))
            ->assertOk()
            ->assertSee("\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}")
            ->assertDontSee("\u{1F468}\u{200D}\u{1F467}\u{200D}\u{1F466}")
            ->assertSee('Carte de retrait des enfants 2026-2027')
            ->assertSee('Moussa Ali')                   // père
            ->assertSee('+235 66 12 34 56')
            ->assertSee('Farcha')                       // quartier
            ->assertSee('Fatimé Abakar')                // personne autorisée
            ->assertSee('Mère')                         // lien avec la famille
            ->assertSee('enfants en charge')
            ->assertSee("propriété de l'école", false)
            ->assertDontSee('class="card verso', false) // une seule face
            ->assertSee('RET-0001')
            ->assertSee('class="filigrane"', false)
            ->assertSee('src="data:image/svg+xml;base64,', false);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('cartes-retrait.imprimer-tout'))
            ->assertOk()
            ->assertSee("\u{1F468}\u{200D}\u{1F467}\u{200D}\u{1F466}")
            ->assertSee('RET-0002');

        // Le QR code mène à une page publique signée qui confirme la carte
        $this->get(QrAuthenticite::lien('verification.retrait', $femme->id))
            ->assertOk()->assertSee('Carte authentique')->assertSee('Fatimé Abakar');
        $this->get('/verification/retrait/' . $femme->id)->assertForbidden();
    }

    public function test_droits_surveillant_en_lecture_et_enseignant_sans_acces(): void
    {
        $eleve = $this->eleve();
        $carte = CarteRetrait::create($this->donnees([]) + ['numero' => 'RET-0001']);
        $carte->eleves()->attach($eleve);

        $surveillant = $this->utilisateur('surveillant');
        $this->actingAs($surveillant)->get(route('cartes-retrait.index'))->assertOk()->assertSee('RET-0001');
        $this->actingAs($surveillant)->get(route('cartes-retrait.create'))->assertForbidden();
        $this->actingAs($surveillant)->delete(route('cartes-retrait.destroy', $carte))->assertForbidden();

        $this->actingAs($this->utilisateur('enseignant'))->get(route('cartes-retrait.index'))->assertForbidden();
    }

    public function test_un_utilisateur_limite_a_un_cycle_ne_voit_pas_les_autres_familles(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $eleveCollege = $this->eleve($college, 'Collegien');
        $carte = CarteRetrait::create($this->donnees([]) + ['numero' => 'RET-0001']);
        $carte->eleves()->attach($eleveCollege);

        $secretariatPrimaire = $this->utilisateur('secretariat', $primaire);
        $this->actingAs($secretariatPrimaire)->get(route('cartes-retrait.index'))->assertOk()->assertDontSee('RET-0001');
        $this->actingAs($secretariatPrimaire)->get(route('cartes-retrait.imprimer', $carte))->assertForbidden();
        $this->actingAs($secretariatPrimaire)->post(route('cartes-retrait.store'), $this->donnees([$eleveCollege->id]))
            ->assertSessionHasErrors('eleves.0');
    }
}
