<?php

namespace Tests\Feature;

use App\Helpers\QrAuthenticite;
use App\Models\ConfigurationEcole;
use App\Models\Eleve;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CartesBadgesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected string $dossierTest;

    protected function setUp(): void
    {
        parent::setUp();
        // Stockage temporaire : les photos envoyées pendant les tests ne vont pas dans le vrai dossier
        $this->dossierTest = sys_get_temp_dir() . '/saint-martin-tests-' . uniqid();
        $this->app->useStoragePath($this->dossierTest . '/storage');
        $this->poserLogosDeTest();
        ConfigurationEcole::actuelle()->update([
            'annee_scolaire_active' => '2026-2027', 'nom_ecole' => 'Saint Martin de Tour',
            'telephone' => '+235 85 10 04 02', 'trimestre3_fin' => '2027-06-30',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dossierTest);
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

    protected function personnel(array $attributs = []): Personnel
    {
        return Personnel::create($attributs + [
            'matricule' => 'PERS-' . uniqid(), 'nom' => 'DJIMET', 'prenom' => 'Paul',
            'type' => 'enseignant', 'fonction' => 'Professeur de français', 'salaire_base' => 0,
        ]);
    }

    public function test_la_carte_scolaire_utilise_les_parametres_et_les_contacts_utiles(): void
    {
        $eleve = $this->eleve();
        $eleve->update([
            'lieu_naissance' => 'Moundou', 'pere_nom' => 'Moussa Ali', 'pere_telephone' => '+235 66 12 34 56',
            'mere_telephone' => '+235 99 88 77 66', 'tuteur_nom' => 'Hassan', 'tuteur_telephone' => null,
        ]);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('eleves.carte', $eleve))
            ->assertOk()
            ->assertSee('Carte scolaire 2026-2027')
            ->assertDontSee('2025')
            ->assertSee('Moundou')
            ->assertSee('Valable jusqu\'au 30/06/2027')
            ->assertSee('+235 66 12 34 56')
            ->assertSee('+235 99 88 77 66')
            ->assertDontSee('Tuteur')                  // tuteur sans numéro : non affiché
            ->assertSee('+235 85 10 04 02')            // téléphone de l'école
            ->assertSee('size:85.6mm 54mm', false)        // carte scolaire en paysage
            ->assertSee('class="filigrane"', false)        // logo en filigrane (si le logo existe)
            ->assertSee('src="data:image/svg+xml;base64,', false);
    }

    public function test_cartes_de_toute_une_classe(): void
    {
        $cycle = $this->cycle('Primaire');
        $premier = $this->eleve($cycle, 'Premier');
        $second = $this->eleve($cycle, 'Second');
        $autreClasse = $this->eleve($this->cycle('Collège'), 'Autre');

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('eleves.cartes', ['classe_id' => $premier->classe_id]))
            ->assertOk()
            ->assertSee('Premier')->assertSee('Second')->assertDontSee('Autre')
            ->assertSee('(2 élèves)', false);

        // Un compte limité au primaire ne peut pas imprimer les cartes d'une classe du collège
        $this->actingAs($this->utilisateur('secretariat', $cycle))
            ->get(route('eleves.cartes', ['classe_id' => $autreClasse->classe_id]))
            ->assertForbidden();
    }

    public function test_nouveaux_telephones_mere_et_tuteur_sur_la_fiche_eleve(): void
    {
        $classeId = $this->eleve()->classe_id;

        $this->actingAs($this->utilisateur('secretariat'))->post('/eleves', [
            'classe_id' => $classeId, 'nom' => 'Nouvel', 'prenom' => 'Élève',
            'mere_telephone' => '+235 63 45 67 89', 'tuteur_telephone' => '+235 62 00 11 22',
        ])->assertSessionHasNoErrors();

        $eleve = Eleve::where('nom', 'Nouvel')->firstOrFail();
        $this->assertSame('+235 63 45 67 89', $eleve->mere_telephone);
        $this->assertSame('+235 62 00 11 22', $eleve->tuteur_telephone);
    }

    public function test_photo_du_personnel_sur_le_badge(): void
    {
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->post('/personnels', [
            'nom' => 'DJIMET', 'prenom' => 'Paul', 'type' => 'enseignant', 'salaire_base' => 100000,
            'photo' => UploadedFile::fake()->image('photo.jpg', 300, 370),
        ])->assertRedirect();

        $personnel = Personnel::firstOrFail();
        $this->assertStringStartsWith('uploads/photos_personnel/', $personnel->photo);
        $this->assertFileExists($this->dossierTest . '/storage/app/private/' . $personnel->photo);

        $this->actingAs($secretariat)->get(route('personnels.badge', $personnel))
            ->assertOk()
            ->assertSee('Badge du personnel<br>2026-2027', false)
            ->assertSee('size:54mm 85.6mm', false)        // badge en portrait, une seule face
            ->assertSee('class="filigrane"', false)
            ->assertSee(route('fichiers.afficher', ['chemin' => $personnel->photo]), false);

        $this->actingAs($secretariat)->get(route('fichiers.afficher', ['chemin' => $personnel->photo]))->assertOk();
        $this->actingAs($this->utilisateur('surveillant'))->get(route('fichiers.afficher', ['chemin' => $personnel->photo]))->assertForbidden();
    }

    public function test_badges_de_tout_le_personnel_actif(): void
    {
        $this->personnel(['nom' => 'ACTIF']);
        $this->personnel(['nom' => 'PARTI', 'actif' => false]);

        $this->actingAs($this->utilisateur('secretariat'))
            ->get(route('personnels.badges'))
            ->assertOk()
            ->assertSee('ACTIF')
            ->assertDontSee('PARTI');
    }

    public function test_verification_du_badge_par_qr_code(): void
    {
        $actif = $this->personnel();
        $parti = $this->personnel(['nom' => 'PARTI', 'actif' => false]);

        $this->get(route('verification.badge', $actif))->assertForbidden();   // adresse devinée, sans signature
        $this->get(QrAuthenticite::lien('verification.badge', $actif->id))->assertOk()->assertSee('Badge authentique');
        $this->get(QrAuthenticite::lien('verification.badge', $parti->id))->assertOk()->assertSee('plus en activité');

        // Depuis la page "Vérifier un document" de l'administration
        $reponse = $this->actingAs($this->utilisateur('comptable'))
            ->post('/verifier-document', ['code' => QrAuthenticite::lien('verification.badge', $actif->id)]);
        $this->get($reponse->headers->get('Location'))->assertOk()->assertSee('DJIMET');
    }
    public function test_carte_de_la_maternelle_en_portrait_une_seule_face(): void
    {
        $eleve = $this->eleve($this->cycle('Maternelle'), 'HAWA');
        $eleve->update(['pere_nom' => 'MAHAMAT Ali', 'pere_telephone' => '66 11 22 33']);
        $primaire = $this->eleve($this->cycle('Primaire'), 'MOUSSA');
        $direction = $this->utilisateur('direction');

        $this->actingAs($direction)->get(route('eleves.carte', $eleve))->assertOk()
            ->assertSee('card portrait maternelle', false)
            ->assertSee('Maternelle')->assertSee("En cas d'urgence", false)
            ->assertSee('MAHAMAT Ali')->assertSee('66 11 22 33')
            ->assertSee('size:54mm 85.6mm', false)
            ->assertDontSee('card verso', false);

        // Les autres cycles gardent la carte paysage recto verso
        $this->actingAs($direction)->get(route('eleves.carte', $primaire))->assertOk()
            ->assertSee('card verso', false)->assertDontSee('card portrait maternelle', false);
    }
    public function test_carte_maternelle_n_affiche_pas_deux_fois_le_meme_numero(): void
    {
        $eleve = $this->eleve($this->cycle('Maternelle'), 'DIONKOUI');
        $eleve->update(['pere_nom' => 'DIONKOUI Eric', 'pere_telephone' => '66462257', 'mere_nom' => 'DIONKOUI Eric', 'mere_telephone' => '66 46 22 57']);

        $page = $this->actingAs($this->utilisateur('direction'))->get(route('eleves.carte', $eleve))->assertOk()->getContent();

        $this->assertSame(1, substr_count($page, 'class="tel"'));
    }
    public function test_carte_maternelle_numeros_de_l_ecole_et_personne_qui_vient_chercher(): void
    {
        ConfigurationEcole::actuelle()->update(['telephone' => '+235 85 10 04 02 / 85 10 04 03']);
        $eleve = $this->eleve($this->cycle('Maternelle'), 'HAWA');
        $eleve->update(['pere_nom' => 'MAHAMAT Ali', 'pere_telephone' => '66 11 22 33']);
        $carte = \App\Models\CarteRetrait::create(['numero' => 'RET-0001', 'responsable_nom' => 'ACHTA Mariam', 'responsable_lien' => 'Tante', 'responsable_sexe' => 'F', 'responsable_telephone' => '90 00 11 22']);
        $carte->eleves()->attach($eleve);

        $this->actingAs($this->utilisateur('direction'))->get(route('eleves.carte', $eleve))->assertOk()
            ->assertSee("Personne autorisée à retirer l'enfant", false)->assertSee('ACHTA Mariam')->assertSee('90 00 11 22')
            ->assertSee('Contact école : 85 10 04 02 · 85 10 04 03', false)
            ->assertDontSee('Carte scolaire ' . ConfigurationEcole::anneeActive());
    }
    public function test_carte_maternelle_sans_bloc_si_le_pere_ou_la_mere_ramene_l_enfant(): void
    {
        $eleve = $this->eleve($this->cycle('Maternelle'), 'HAWA');
        $eleve->update(['pere_nom' => 'MAHAMAT Ali', 'pere_telephone' => '66 11 22 33']);
        $carte = \App\Models\CarteRetrait::create(['numero' => 'RET-0001', 'responsable_nom' => 'MAHAMAT Ali', 'responsable_lien' => 'Père', 'responsable_sexe' => 'M', 'responsable_telephone' => '66112233']);
        $carte->eleves()->attach($eleve);

        $this->actingAs($this->utilisateur('direction'))->get(route('eleves.carte', $eleve))->assertOk()
            ->assertDontSee('Personne autorisée', false)->assertSee('MAHAMAT Ali');
    }
}
