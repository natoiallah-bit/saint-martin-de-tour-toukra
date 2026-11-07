<?php

namespace Tests\Feature;

use App\Models\ConfigurationEcole;
use App\Support\Licence;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenceTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected string $privee;

    protected function setUp(): void
    {
        parent::setUp();
        // Paire de clés propre aux tests : la vraie clé privée n'est jamais dans le dépôt
        $paire = sodium_crypto_sign_keypair();
        $this->privee = sodium_crypto_sign_secretkey($paire);
        config(['licence.cle_publique' => base64_encode(sodium_crypto_sign_publickey($paire))]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function cle(string $fin, string $sigle = 'SMT', ?string $secrete = null): string
    {
        $b64u = fn (string $t) => rtrim(strtr(base64_encode($t), '+/', '-_'), '=');
        $charge = json_encode(['v' => 1, 'sigle' => $sigle, 'ecole' => 'Test', 'fin' => $fin]);

        return $b64u($charge) . '.' . $b64u(sodium_crypto_sign_detached($charge, $secrete ?? $this->privee));
    }

    protected function parametres(): array
    {
        $c = ConfigurationEcole::actuelle();

        return ['nom_ecole' => 'Autre nom', 'ville' => 'N\'Djamena', 'annee_scolaire_active' => $c->annee_scolaire_active, 'coefficient_conduite' => 0];
    }

    protected function enregistrer(string $cle): void
    {
        ConfigurationEcole::actuelle()->forceFill(['licence_cle' => $cle])->save();
    }

    public function test_installation_neuve_en_periode_d_essai(): void
    {
        $etat = Licence::etat();
        $this->assertSame('essai', $etat['source']);
        $this->assertSame('bientot', $etat['statut']);          // 30 jours d'essai, avertissement à 30 jours
        $this->assertSame(30, $etat['jours']);
    }

    public function test_cle_valide_et_loin_de_la_fin(): void
    {
        $this->enregistrer($this->cle(now()->addDays(200)->toDateString()));
        $etat = Licence::etat();
        $this->assertSame('cle', $etat['source']);
        $this->assertSame('valide', $etat['statut']);
        $this->assertNull($etat['message']);
    }

    public function test_les_etapes_apres_la_fin(): void
    {
        $this->enregistrer($this->cle('2027-01-31'));
        foreach ([
            '2027-01-01' => 'bientot', '2027-01-31' => 'bientot',
            '2027-02-01' => 'expiree', '2027-03-02' => 'expiree',
            '2027-03-03' => 'bloquee',
        ] as $jour => $statut) {
            Carbon::setTestNow(Carbon::parse($jour . ' 10:00'));
            $this->assertSame($statut, Licence::etat()['statut'], $jour);
        }
    }

    public function test_cle_falsifiee_ou_signee_par_un_autre_est_refusee(): void
    {
        $autre = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());
        $faux = $this->cle('2030-01-01', 'SMT', $autre);
        $this->enregistrer($faux);
        $etat = Licence::etat();
        $this->assertSame('essai', $etat['source']);
        $this->assertNotNull($etat['erreur']);

        // Date modifiée dans la charge, signature d'origine conservée
        [$charge, $signature] = explode('.', $this->cle('2026-12-01'));
        $modifiee = rtrim(strtr(base64_encode(str_replace('2026-12-01', '2099-12-01', base64_decode(strtr($charge, '-_', '+/')))), '+/', '-_'), '=');
        $this->enregistrer($modifiee . '.' . $signature);
        $this->assertNotNull(Licence::etat()['erreur']);
    }

    public function test_cle_d_une_autre_ecole_est_refusee(): void
    {
        $this->enregistrer($this->cle('2030-01-01', 'CDS'));
        $etat = Licence::etat();
        $this->assertStringContainsString('CDS', $etat['erreur']);
        $this->assertSame('essai', $etat['source']);
    }

    public function test_la_direction_enregistre_une_cle_depuis_la_page(): void
    {
        $direction = $this->utilisateur('direction');
        $this->actingAs($direction)->get(route('licence.index'))->assertOk()->assertSee("Période d'essai");

        $this->actingAs($direction)->post(route('licence.activer'), ['cle' => 'nimporte quoi'])->assertSessionHasErrors('cle');

        $this->actingAs($direction)->post(route('licence.activer'), ['cle' => $this->cle('2030-05-20')])
            ->assertRedirect(route('licence.index'))->assertSessionHas('success');
        $this->actingAs($direction)->get(route('licence.index'))->assertOk()->assertSee('20/05/2030')->assertSee("Clé d'activation");
    }

    public function test_la_page_licence_est_reservee_a_la_direction(): void
    {
        $this->actingAs($this->utilisateur('secretariat'))->get(route('licence.index'))->assertForbidden();
    }

    public function test_bandeau_d_avertissement_avant_la_fin(): void
    {
        $this->enregistrer($this->cle(now()->addDays(10)->toDateString()));
        $direction = $this->utilisateur('direction');
        $this->actingAs($direction)->get(route('dashboard'))->assertOk()->assertSee('se termine dans 10 jours');
        $this->actingAs($this->utilisateur('secretariat'))->get(route('eleves.index'))->assertOk()
            ->assertSee('se termine dans 10 jours')->assertSee('Prévenez la Direction');
    }

    public function test_lecture_seule_apres_la_tolerance_mais_donnees_consultables(): void
    {
        $this->enregistrer($this->cle('2027-01-31'));
        $direction = $this->utilisateur('direction');
        $eleve = $this->eleve();
        Carbon::setTestNow(Carbon::parse('2027-03-10 09:00'));

        // Consultation et export toujours possibles
        $this->actingAs($direction)->get(route('eleves.index'))->assertOk();
        $this->actingAs($direction)->get(route('licence.index'))->assertOk()->assertSee('lecture seule');

        // Modification refusée, avec un message
        $this->actingAs($direction)->from(route('eleves.index'))
            ->put(route('parametres.update'), $this->parametres())
            ->assertRedirect(route('eleves.index'))->assertSessionHas('erreur');
        $this->assertNotSame('Autre nom', ConfigurationEcole::actuelle()->nom_ecole);

        // Renouveler reste possible, et rouvre l'écriture
        $this->actingAs($direction)->post(route('licence.activer'), ['cle' => $this->cle('2028-03-10')])->assertSessionHas('success');
        $this->actingAs($direction)->put(route('parametres.update'), $this->parametres())->assertSessionHasNoErrors();
        $this->assertSame('Autre nom', ConfigurationEcole::actuelle()->nom_ecole);
    }

    public function test_connexion_possible_meme_en_lecture_seule(): void
    {
        $this->enregistrer($this->cle('2027-01-31'));
        Carbon::setTestNow(Carbon::parse('2027-06-01 09:00'));
        $direction = $this->utilisateur('direction');

        $this->post('/login', ['email' => $direction->email, 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticatedAs($direction);
        $this->post('/logout')->assertRedirect();
    }

    public function test_le_controle_est_desactive_sans_cle_publique(): void
    {
        config(['licence.cle_publique' => '']);
        Carbon::setTestNow(Carbon::now()->addYears(3));
        $this->assertSame('valide', Licence::etat()['statut']);
    }
}
