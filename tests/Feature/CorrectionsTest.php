<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Models\DocumentEleve;
use App\Models\Eleve;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CorrectionsTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected string $dossierTest;

    protected function setUp(): void
    {
        parent::setUp();

        // Dossiers storage/ et public/ temporaires : les tests ne touchent jamais
        // aux vrais fichiers de l'école (fichiers:proteger déplace tout public/uploads)
        $this->dossierTest = sys_get_temp_dir() . '/saint-martin-tests-' . uniqid();
        $this->app->useStoragePath($this->dossierTest . '/storage');
        $this->app->usePublicPath($this->dossierTest . '/public');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dossierTest);
        parent::tearDown();
    }

    public function test_ajout_eleve_genere_le_matricule(): void
    {
        $classeId = $this->eleve()->classe_id;
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get('/eleves/create')->assertOk()->assertSee('SMT-0002');

        $this->actingAs($secretariat)->post('/eleves', ['classe_id' => $classeId, 'nom' => 'Nouveau', 'prenom' => 'Eleve'])
            ->assertRedirect(route('eleves.index'));

        $eleve = Eleve::where('nom', 'Nouveau')->firstOrFail();
        $this->assertSame('SMT-' . str_pad($eleve->id, 4, '0', STR_PAD_LEFT), $eleve->matricule);
    }

    public function test_ajout_personnel_genere_le_matricule(): void
    {
        $this->actingAs($this->utilisateur('secretariat'))
            ->post('/personnels', ['nom' => 'Mahamat', 'prenom' => 'Idriss', 'type' => 'administratif', 'salaire_base' => 150000])
            ->assertRedirect(route('personnels.index'));

        $personnel = Personnel::firstOrFail();
        $this->assertSame('PERS-' . str_pad($personnel->id, 4, '0', STR_PAD_LEFT), $personnel->matricule);
    }

    public function test_numero_de_recu_non_reutilise_apres_suppression(): void
    {
        $eleve = $this->eleve();

        $premier = $this->paiement($eleve);
        $deuxieme = $this->paiement($eleve);
        $premier->delete();
        $troisieme = $this->paiement($eleve);

        // La numérotation suit l'année scolaire active (SMT-2025-… pour 2025-2026)
        $annee = substr(\App\Models\ConfigurationEcole::anneeActive(), 0, 4);
        $this->assertSame("SMT-$annee-0002", $deuxieme->numero_recu);
        $this->assertSame("SMT-$annee-0003", $troisieme->numero_recu);
    }

    public function test_paiement_enregistre_sur_l_annee_des_parametres(): void
    {
        $eleve = $this->eleve();
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027']);

        $this->actingAs($this->utilisateur('comptable'))->post('/paiements', [
            'eleve_id' => $eleve->id,
            'types' => ['inscription'],
            'montant' => 25000,
            'mode_paiement' => 'especes',
            'date_paiement' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-2027', Paiement::firstOrFail()->annee_scolaire);
    }

    public function test_document_eleve_stocke_hors_du_dossier_public_et_protege(): void
    {
        $eleve = $this->eleve();
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->post(route('eleves.documents.store', $eleve), [
            'type' => 'Acte de naissance',
            'fichier' => UploadedFile::fake()->create('acte.pdf', 10, 'application/pdf'),
        ])->assertRedirect();

        $chemin = DocumentEleve::firstOrFail()->chemin_fichier;
        $this->assertFileExists($this->dossierTest . '/storage/app/private/' . $chemin);
        $this->assertFileDoesNotExist(public_path($chemin));

        $url = route('fichiers.afficher', ['chemin' => $chemin]);
        $this->actingAs($secretariat)->get($url)->assertOk();
        $this->actingAs($this->utilisateur('comptable'))->get($url)->assertForbidden();
        $this->actingAs($this->parentDe($eleve))->get($url)->assertForbidden();

        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_photo_visible_par_le_parent_de_l_eleve_seulement(): void
    {
        $chemin = 'uploads/photos_eleves/eleve_test.jpg';
        File::ensureDirectoryExists(dirname(storage_path('app/private/' . $chemin)));
        File::put(storage_path('app/private/' . $chemin), 'image');

        $enfant = $this->eleve();
        $enfant->update(['photo' => $chemin]);
        $url = route('fichiers.afficher', ['chemin' => $chemin]);

        $this->actingAs($this->parentDe($enfant))->get($url)->assertOk();
        $this->actingAs($this->parentDe($this->eleve()))->get($url)->assertForbidden();
        $this->actingAs($this->utilisateur('enseignant'))->get($url)->assertOk();
    }

    public function test_ancien_fichier_public_toujours_lisible_puis_deplace(): void
    {
        $relatif = 'uploads/pieces_justificatives/piece_ancienne.pdf';
        File::ensureDirectoryExists(dirname(public_path($relatif)));
        File::put(public_path($relatif), 'pdf');

        $url = route('fichiers.afficher', ['chemin' => $relatif]);
        $comptable = $this->utilisateur('comptable');
        $this->actingAs($comptable)->get($url)->assertOk();

        $this->artisan('fichiers:proteger')->assertSuccessful();

        $this->assertFileDoesNotExist(public_path($relatif));
        $this->assertFileExists($this->dossierTest . '/storage/app/private/' . $relatif);
        $this->actingAs($comptable)->get($url)->assertOk();
    }

    public function test_chemin_de_fichier_malveillant_refuse(): void
    {
        $this->actingAs($this->utilisateur('direction'))
            ->get('/fichiers/uploads/photos_eleves/../../../.env')
            ->assertNotFound();
    }

    public function test_utilisateur_limite_a_son_cycle(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $eleveDuPrimaire = $this->eleve($primaire, 'Primus');
        $eleveDuCollege = $this->eleve($college, 'Collegus');
        $secretariat = $this->utilisateur('secretariat', $primaire);

        $this->actingAs($secretariat)->get('/eleves')->assertOk()->assertSee('Primus')->assertDontSee('Collegus');
        $this->actingAs($secretariat)->get(route('eleves.carte', $eleveDuPrimaire))->assertOk();
        $this->actingAs($secretariat)->get(route('eleves.carte', $eleveDuCollege))->assertForbidden();
        $this->actingAs($secretariat)->get(route('eleves.edit', $eleveDuCollege))->assertForbidden();
        $this->actingAs($secretariat)->get(route('eleves.documents.index', $eleveDuCollege))->assertForbidden();
        $this->actingAs($secretariat)->get(route('bulletins.show', [$eleveDuCollege, 1]))->assertForbidden();

        // Impossible d'inscrire un élève dans une classe d'un autre cycle
        $this->actingAs($secretariat)->post('/eleves', [
            'classe_id' => $eleveDuCollege->classe_id, 'nom' => 'Intrus', 'prenom' => 'Test',
        ])->assertSessionHasErrors('classe_id');
    }

    public function test_rang_et_moyenne_du_bulletin(): void
    {
        $cycle = $this->cycle();
        $math = Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Maths', 'coefficient' => 2, 'categorie' => 'Scientifique']);
        $francais = Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Français', 'coefficient' => 1, 'categorie' => 'Littéraire']);

        $notes = ['Premier' => [18, 16], 'Deuxieme' => [12, 14], 'Troisieme' => [8, 10]];
        $eleves = [];
        foreach ($notes as $nom => [$noteMath, $noteFrancais]) {
            $eleves[$nom] = $this->eleve($cycle, $nom);
            foreach ([[$math, $noteMath], [$francais, $noteFrancais]] as [$matiere, $note]) {
                Note::create([
                    'eleve_id' => $eleves[$nom]->id, 'matiere_id' => $matiere->id, 'trimestre' => 1,
                    'moyenne_classe' => $note, 'moyenne_composition' => $note,
                ]);
            }
        }

        $reponse = $this->actingAs($this->utilisateur('direction'))
            ->get(route('bulletins.show', [$eleves['Deuxieme'], 1]))->assertOk();

        $this->assertSame(2, $reponse->viewData('rang'));
        $this->assertSame(3, $reponse->viewData('effectifClasse'));
        $this->assertEqualsWithDelta(12.67, $reponse->viewData('moyenneGenerale'), 0.001);
        $this->assertSame(2, $reponse->viewData('lignes')->firstWhere('matiere', 'Maths')['rang_matiere']);
    }

    public function test_mot_de_passe_trop_faible_refuse(): void
    {
        $direction = $this->utilisateur('direction');
        $donnees = ['name' => 'Caisse', 'email' => 'caisse@test.td', 'role' => 'comptable', 'cycle_id' => $this->cycle()->id];

        $this->actingAs($direction)->post('/users', $donnees + ['password' => 'abcdef'])->assertSessionHasErrors('password');
        $this->actingAs($direction)->post('/users', $donnees + ['password' => 'abcdefgh'])->assertSessionHasErrors('password');

        // Message d'erreur en français
        $this->app->setLocale('fr');
        $this->actingAs($direction)->post('/users', $donnees + ['password' => 'abcdefgh'])
            ->assertSessionHasErrors(['password' => 'Le champ mot de passe doit contenir au moins un chiffre.']);

        $this->actingAs($direction)->post('/users', $donnees + ['password' => 'caisse2026'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'caisse@test.td']);
    }

    public function test_qr_code_genere_sans_internet_sur_les_documents(): void
    {
        $eleve = $this->eleve();
        $direction = $this->utilisateur('direction');

        foreach ([route('bulletins.show', [$eleve, 1]), route('eleves.carte', $eleve), route('paiements.recu', $this->paiement($eleve))] as $page) {
            $this->actingAs($direction)->get($page)
                ->assertOk()
                ->assertSee('src="data:image/svg+xml;base64,', false)
                ->assertDontSee('qrserver.com', false);
        }
    }
}
