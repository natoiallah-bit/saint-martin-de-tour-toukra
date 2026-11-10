<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HebergementTest extends TestCase
{
    use Fabrique;

    private string $base;

    // Base SQLite dans un fichier : la sauvegarde utilise VACUUM INTO, impossible dans une transaction
    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/hebergement-test-' . uniqid() . '.sqlite';
        touch($this->base);
        config(['database.connections.sqlite.database' => $this->base]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');
        @unlink($this->base);
        File::delete(glob(public_path('images/ecole/test-*')) ?: []);
        parent::tearDown();
    }

    public function test_premier_compte_cree_la_direction_une_seule_fois(): void
    {
        $this->artisan('ecole:premier-compte', ['email' => 'direction@ecole.td', 'motdepasse' => 'unSecretLong2026'])->assertSuccessful();
        $compte = User::where('email', 'direction@ecole.td')->first();
        $this->assertSame('direction', $compte->role);
        $this->assertTrue(\Hash::check('unSecretLong2026', $compte->password));

        $this->artisan('ecole:premier-compte', ['email' => 'autre@ecole.td', 'motdepasse' => 'unAutreSecret2026'])
            ->expectsOutputToContain('existe déjà')->assertSuccessful();
        $this->assertSame(1, User::where('role', 'direction')->count());
    }

    public function test_premier_compte_refuse_un_mot_de_passe_court_ou_un_email_invalide(): void
    {
        $this->artisan('ecole:premier-compte', ['email' => 'direction@ecole.td', 'motdepasse' => 'court'])->assertFailed();
        $this->artisan('ecole:premier-compte', ['email' => 'pas-un-email', 'motdepasse' => 'unSecretLong2026'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_premier_compte_sans_donnees_ne_fait_rien(): void
    {
        $this->artisan('ecole:premier-compte')->expectsOutputToContain('Aucun compte Direction')->assertSuccessful();
        $this->assertSame(0, User::count());
    }

    public function test_la_direction_telecharge_une_sauvegarde_zip(): void
    {
        $this->eleve();
        File::ensureDirectoryExists(public_path('images/ecole'));
        File::put(public_path('images/ecole/test-logo.txt'), 'logo');
        $direction = $this->utilisateur('direction');

        $reponse = $this->actingAs($direction)->get(route('sauvegarde.index'));
        $reponse->assertOk()->assertSee('Télécharger la sauvegarde');

        $reponse = $this->actingAs($direction)->post(route('sauvegarde.telecharger'));
        $reponse->assertOk()->assertHeader('content-disposition');
        $fichier = $reponse->baseResponse->getFile()->getPathname();
        $copie = sys_get_temp_dir() . '/copie-' . uniqid() . '.zip';
        copy($fichier, $copie);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($copie));
        $noms = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $noms[] = $zip->getNameIndex($i);
        }
        $this->assertContains('database.sqlite', $noms);
        $this->assertContains('LISEZMOI.txt', $noms);
        $this->assertContains('images-ecole/test-logo.txt', $noms);
        $this->assertStringContainsString('Élèves : 1', $zip->getFromName('LISEZMOI.txt'));
        $zip->close();
        @unlink($copie);
    }

    public function test_la_sauvegarde_est_reservee_a_la_direction(): void
    {
        $this->actingAs($this->utilisateur('secretariat'))->get(route('sauvegarde.index'))->assertForbidden();
        $this->actingAs($this->utilisateur('secretariat'))->post(route('sauvegarde.telecharger'))->assertForbidden();
    }

    public function test_la_sauvegarde_reste_possible_en_lecture_seule(): void
    {
        // Licence terminée depuis longtemps : lecture seule, mais la sauvegarde (POST) reste ouverte
        Carbon::setTestNow(Carbon::now()->addDays(200));
        $direction = $this->utilisateur('direction');
        $this->actingAs($direction)->get(route('licence.index'))->assertSee('lecture seule');
        $this->actingAs($direction)->post(route('sauvegarde.telecharger'))->assertOk()->assertHeader('content-disposition');
    }
}
