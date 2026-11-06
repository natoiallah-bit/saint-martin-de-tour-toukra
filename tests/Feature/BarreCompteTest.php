<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BarreCompteTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_le_nom_de_l_utilisateur_et_la_deconnexion_sont_en_haut_de_page(): void
    {
        $user = $this->utilisateur('secretariat', $this->cycle('Primaire'));
        $user->update(['name' => 'Amina Secrétaire']);

        $this->actingAs($user)->get(route('eleves.index'))
            ->assertOk()
            ->assertSee('class="barre-compte"', false)
            ->assertSee('Amina Secrétaire')
            ->assertSee('Cycle : Primaire')
            ->assertSee('Déconnexion');
    }

    public function test_le_lien_accueil_est_sur_toutes_les_pages(): void
    {
        $secretariat = $this->utilisateur('secretariat');
        $eleve = $this->eleve();

        // Pages de l'application : lien vers la page d'accueil du compte
        foreach ([route('eleves.index'), route('classes.index'), route('cartes-retrait.index')] as $page) {
            $this->actingAs($secretariat)->get($page)->assertOk()
                ->assertSee('class="btn-accueil"', false)
                ->assertSee('href="' . route('dashboard') . '" class="btn-accueil"', false);
        }

        // Pages d'impression autonomes
        $this->actingAs($secretariat)->get(route('eleves.carte', $eleve))->assertOk()->assertSee('🏠 Accueil');

        // L'accueil dépend du compte : espace parent pour un parent
        $parent = $this->parentDe($eleve);
        $this->actingAs($parent)->get(route('parent.dashboard'))->assertOk()
            ->assertSee('href="' . route('parent.dashboard') . '" class="btn-accueil"', false);
    }

    public function test_la_deconnexion_ramene_a_la_page_de_connexion_avec_un_message(): void
    {
        $this->actingAs($this->utilisateur('secretariat'))
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('login'))->assertSee('Vous êtes déconnecté');
    }
}
