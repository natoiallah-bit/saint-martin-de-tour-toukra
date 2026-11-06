<?php

namespace Tests\Feature;

use App\Models\Paie;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Helpers\QrAuthenticite;
use Tests\TestCase;

class DroitsAccesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    // Pages internes qu'un parent ne doit jamais pouvoir ouvrir
    protected array $pagesInternes = [
        '/eleves', '/eleves/create', '/eleves/liste-imprimable', '/eleves/import/excel',
        '/notes', '/bulletins', '/presences', '/emplois-temps', '/classes', '/matieres-gestion',
        '/paiements', '/paiements/creer', '/frais', '/convocations/retards', '/convocations/reunion',
        '/personnels', '/paies', '/comptabilite', '/materiels', '/pointage',
        '/parametres', '/users', '/fiches-passage',
    ];

    public function test_les_pages_exigent_une_connexion(): void
    {
        foreach (['/', '/eleves', '/paiements', '/paies', '/parametres', '/mon-espace'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }

        $this->get('/login')->assertOk();
    }

    public function test_un_parent_n_accede_qu_a_son_espace(): void
    {
        $parent = $this->parentDe($this->eleve());

        foreach ($this->pagesInternes as $url) {
            $this->actingAs($parent)->get($url)->assertForbidden();
        }

        $this->actingAs($parent)->get('/mon-espace')->assertOk();
        $this->actingAs($parent)->get('/')->assertRedirect(route('parent.dashboard'));
    }

    public function test_un_parent_voit_les_documents_de_ses_enfants_seulement(): void
    {
        $cycle = $this->cycle();
        $enfant = $this->eleve($cycle, 'Enfant');
        $autre = $this->eleve($cycle, 'Autre');
        $parent = $this->parentDe($enfant);

        $this->actingAs($parent)->get(route('eleves.carte', $enfant))->assertOk();
        $this->actingAs($parent)->get(route('bulletins.show', [$enfant, 1]))->assertOk();
        $this->actingAs($parent)->get(route('paiements.recu', $this->paiement($enfant)))->assertOk();

        $this->actingAs($parent)->get(route('eleves.carte', $autre))->assertForbidden();
        $this->actingAs($parent)->get(route('bulletins.show', [$autre, 1]))->assertForbidden();
        $this->actingAs($parent)->get(route('paiements.recu', $this->paiement($autre)))->assertForbidden();
    }

    public function test_chaque_role_est_limite_a_ses_modules(): void
    {
        $matrice = [
            // Le comptable fait aussi le travail de caisse (ancien rôle Caissier)
            'comptable' => ['ok' => ['/comptabilite', '/paies', '/frais', '/materiels', '/paiements', '/paiements/creer', '/eleves'], 'interdit' => ['/personnels', '/parametres', '/eleves/create', '/notes']],
            'principal' => ['ok' => ['/', '/eleves', '/eleves/create', '/notes', '/bulletins', '/classes', '/matieres-gestion', '/presences', '/emplois-temps', '/fiches-passage'], 'interdit' => ['/paiements', '/personnels', '/paies', '/comptabilite', '/parametres', '/eleves/import/excel', '/pointage']],
            'surveillant' => ['ok' => ['/pointage', '/eleves', '/presences'], 'interdit' => ['/paiements', '/personnels', '/parametres', '/notes']],
            'secretariat' => ['ok' => ['/', '/eleves', '/eleves/create', '/personnels', '/paiements', '/eleves/import/excel'], 'interdit' => ['/parametres', '/users', '/fiches-passage']],
        ];

        foreach ($matrice as $role => $pages) {
            $utilisateur = $this->utilisateur($role);
            foreach ($pages['ok'] as $url) {
                $this->assertNotContains($this->actingAs($utilisateur)->get($url)->status(), [403, 500], "$role devrait pouvoir ouvrir $url");
            }
            foreach ($pages['interdit'] as $url) {
                $this->actingAs($utilisateur)->get($url)->assertStatus(403);
            }
        }
    }

    public function test_la_direction_a_acces_a_tout(): void
    {
        $direction = $this->utilisateur('direction');

        foreach ($this->pagesInternes as $url) {
            $statut = $this->actingAs($direction)->get($url)->status();
            $this->assertNotContains($statut, [403, 500], "La direction devrait pouvoir ouvrir $url (statut $statut)");
        }
    }

    public function test_liens_de_verification_signes_obligatoires(): void
    {
        $eleve = $this->eleve();
        $personnel = Personnel::create(['matricule' => 'PERS-0001', 'nom' => 'Ali', 'prenom' => 'Moussa', 'type' => 'administratif', 'salaire_base' => 150000]);
        $paie = Paie::create(['personnel_id' => $personnel->id, 'mois' => '2026-09', 'salaire_base' => 150000]);
        $recu = $this->paiement($eleve);

        $liens = [
            ['verification.bulletin', ['eleve' => $eleve->id, 'trimestre' => 1]],
            ['verification.carte', [$eleve->id]],
            ['verification.recu', [$recu->numero_recu]],
            ['verification.paie', [$paie->id]],
        ];

        foreach ($liens as [$nom, $parametres]) {
            // Adresse devinée, sans signature : refusée
            $this->get(route($nom, $parametres))->assertForbidden();
            // Lien imprimé par l'application : accepté, sans connexion
            $this->get(QrAuthenticite::lien($nom, $parametres))->assertOk();
        }
    }

    public function test_qr_code_imprime_depuis_localhost_valide_a_une_autre_adresse(): void
    {
        $eleve = $this->eleve();
        config(['app.url_documents' => 'http://192.168.1.10/saint-martin-toukra/public']);

        $lien = QrAuthenticite::lien('verification.carte', [$eleve->id]);
        $this->assertStringStartsWith('http://192.168.1.10/saint-martin-toukra/public/verification/carte/', $lien);

        // Le téléphone ouvre le même chemin signé, quelle que soit l'adresse du serveur
        $chemin = substr($lien, strlen('http://192.168.1.10/saint-martin-toukra/public'));
        $this->get('http://192.168.1.10' . $chemin)->assertOk();

        // Une signature modifiée est refusée
        $this->get('http://192.168.1.10' . str_replace('signature=', 'signature=x', $chemin))->assertForbidden();
    }

    public function test_verifier_un_document_depuis_l_application(): void
    {
        $eleve = $this->eleve();
        $comptable = $this->utilisateur('comptable');

        // QR code imprimé sur un autre réseau : seule la partie signée compte
        config(['app.url_documents' => 'http://192.168.0.25/ancienne-adresse']);
        $lien = QrAuthenticite::lien('verification.carte', [$eleve->id]);
        config(['app.url_documents' => null]);

        $reponse = $this->actingAs($comptable)->post('/verifier-document', ['code' => $lien]);
        $reponse->assertRedirect();
        $this->assertStringContainsString('/verification/carte/' . $eleve->id, $reponse->headers->get('Location'));
        $this->get($reponse->headers->get('Location'))->assertOk()->assertSee('Carte authentique');

        // Signature modifiée, ou lien qui ne vient pas de l'école : refusé avec un message
        $this->actingAs($comptable)->post('/verifier-document', ['code' => str_replace('signature=', 'signature=x', $lien)])
            ->assertSessionHasErrors(['code' => "Document NON AUTHENTIQUE : la signature du QR code n'est pas valide."]);
        $this->actingAs($comptable)->post('/verifier-document', ['code' => 'https://exemple.com/autre'])
            ->assertSessionHasErrors('code');
        $this->actingAs($comptable)->post('/verifier-document', ['code' => 'http://x/verification/carte/' . $eleve->id])
            ->assertSessionHasErrors('code');

        // Page réservée à l'administration
        $this->actingAs($comptable)->get('/verifier-document')->assertOk();
        $this->actingAs($this->utilisateur('secretariat'))->get('/verifier-document')->assertOk();
        $this->actingAs($this->utilisateur('enseignant'))->get('/verifier-document')->assertForbidden();
        $this->actingAs($this->parentDe($eleve))->get('/verifier-document')->assertForbidden();
    }
}
