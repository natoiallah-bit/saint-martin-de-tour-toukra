<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SauvegardeTest extends TestCase
{
    use Fabrique;

    private string $base;

    // Base SQLite dans un fichier, sans transaction englobante (VACUUM INTO n'y fonctionne pas),
    // comme sur le serveur de l'école
    protected function setUp(): void
    {
        parent::setUp();
        $this->base = sys_get_temp_dir() . '/sauvegarde-test-' . uniqid() . '.sqlite';
        touch($this->base);
        config(['database.connections.sqlite.database' => $this->base]);
        DB::purge('sqlite');
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        @unlink($this->base);
        parent::tearDown();
    }

    public function test_la_sauvegarde_copie_la_base_et_la_fiche_de_controle(): void
    {
        $this->eleve();
        $this->eleve(null, 'Second');
        $cle = sys_get_temp_dir() . '/cle-usb-' . uniqid();
        File::ensureDirectoryExists($cle);

        $this->artisan('ecole:sauvegarder', ['destination' => $cle])->assertSuccessful();

        $dossiers = glob($cle . '/Sauvegardes-*/*');
        $this->assertCount(1, $dossiers);
        $this->assertFileExists($dossiers[0] . '/database.sqlite');
        $this->assertGreaterThan(0, filesize($dossiers[0] . '/database.sqlite'));
        $this->assertStringContainsString('Élèves : 2', file_get_contents($dossiers[0] . '/LISEZMOI.txt'));

        // La copie est une vraie base, lisible avec ses élèves
        $copie = new \PDO('sqlite:' . $dossiers[0] . '/database.sqlite');
        $this->assertSame(2, (int) $copie->query('select count(*) from eleves')->fetchColumn());

        File::deleteDirectory($cle);
    }

    public function test_cle_absente_message_clair(): void
    {
        $this->artisan('ecole:sauvegarder', ['destination' => '/dossier/qui/n/existe/pas'])
            ->expectsOutputToContain('La clé USB est-elle branchée')
            ->assertFailed();
    }
}
