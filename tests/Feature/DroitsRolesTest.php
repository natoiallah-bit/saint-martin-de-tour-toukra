<?php

namespace Tests\Feature;

use App\Helpers\Droits;
use App\Models\ClasseScolaire;
use App\Models\DroitRole;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroitsRolesTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    protected function regler(string $role, string $module, int $niveau): void
    {
        DroitRole::where(['role' => $role, 'module' => $module])->update(['niveau' => $niveau]);
    }

    protected function avecRoles(string $role, array $supplementaires): User
    {
        $user = $this->utilisateur($role);
        foreach ($supplementaires as $r) {
            $user->rolesSupplementaires()->create(['role' => $r]);
        }

        return $user->fresh();
    }

    public function test_les_droits_par_defaut_sont_installes_pour_chaque_role_et_module(): void
    {
        $this->assertSame(count(Droits::ROLES) * count(Droits::MODULES), DroitRole::count());
        $this->assertSame(Droits::SUPPRIMER, DroitRole::where(['role' => 'comptable', 'module' => 'scolarite'])->value('niveau'));
        $this->assertSame(Droits::AUCUN, DroitRole::where(['role' => 'comptable', 'module' => 'personnel'])->value('niveau'));
    }

    public function test_plusieurs_roles_cumulent_leurs_droits(): void
    {
        $user = $this->avecRoles('comptable', ['surveillant']);

        $this->assertSame('Comptable, Surveillant', $user->libelle_roles);
        $this->actingAs($user)->get('/paiements')->assertOk();   // droit du rôle Comptable
        $this->actingAs($user)->get('/pointage')->assertOk();    // droit du rôle Surveillant
        $this->actingAs($user)->get('/personnels')->assertForbidden(); // aucun des deux

        // Le niveau le plus élevé l'emporte
        $this->regler('surveillant', 'eleves', Droits::SUPPRIMER);
        $this->assertSame(Droits::SUPPRIMER, $user->fresh()->niveau('eleves'));
    }

    public function test_lecture_seule(): void
    {
        $this->regler('secretariat', 'eleves', Droits::LECTURE);
        $eleve = $this->eleve();
        $secretariat = $this->utilisateur('secretariat');

        $this->actingAs($secretariat)->get('/eleves')->assertOk()
            ->assertSee($eleve->nom)
            ->assertDontSee('Ajouter un élève')
            ->assertDontSee('>Modifier<', false)
            ->assertDontSee('Supprimer');
        $this->actingAs($secretariat)->get(route('eleves.carte', $eleve))->assertOk();
        $this->actingAs($secretariat)->get('/eleves/create')->assertForbidden();
        $this->actingAs($secretariat)->get(route('eleves.edit', $eleve))->assertForbidden();
        $this->actingAs($secretariat)->post('/eleves', ['classe_id' => $eleve->classe_id, 'nom' => 'X', 'prenom' => 'Y'])->assertForbidden();
        $this->actingAs($secretariat)->delete(route('eleves.destroy', $eleve))->assertForbidden();
        $this->assertModelExists($eleve);
    }

    public function test_modifier_sans_supprimer(): void
    {
        $this->regler('comptable', 'scolarite', Droits::MODIFIER);
        $eleve = $this->eleve();
        $paiement = $this->paiement($eleve);
        $comptable = $this->utilisateur('comptable');

        $this->actingAs($comptable)->get('/paiements')->assertOk()->assertSee('Nouveau paiement')->assertDontSee('Supprimer');
        $this->actingAs($comptable)->get('/paiements/creer')->assertOk();
        $this->actingAs($comptable)->post('/paiements', [
            'eleve_id' => $eleve->id, 'types' => ['tranche2'], 'montant' => 5000,
            'mode_paiement' => 'especes', 'date_paiement' => now()->toDateString(),
        ])->assertRedirect();
        $this->actingAs($comptable)->delete(route('paiements.destroy', $paiement))->assertForbidden();
        $this->assertModelExists($paiement);
    }

    public function test_aucun_acces_masque_le_menu(): void
    {
        $this->regler('comptable', 'scolarite', Droits::AUCUN);
        $comptable = $this->utilisateur('comptable');

        $this->actingAs($comptable)->get('/paiements')->assertForbidden();
        $this->actingAs($comptable)->get('/eleves')->assertOk()
            ->assertDontSee('>Scolarité<', false)
            ->assertSee('>Élèves<', false);
    }

    public function test_la_direction_regle_les_droits(): void
    {
        $direction = $this->utilisateur('direction');
        $comptable = $this->utilisateur('comptable');

        $this->actingAs($direction)->get('/droits-roles')->assertOk()->assertSee('Saisie des notes');
        $this->actingAs($comptable)->get('/droits-roles')->assertForbidden();
        $this->actingAs($comptable)->put('/droits-roles', ['droits' => ['comptable' => ['personnel' => 3]]])->assertForbidden();

        // La Direction donne au comptable la lecture du Personnel
        $this->actingAs($direction)->put('/droits-roles', ['droits' => ['comptable' => ['personnel' => Droits::LECTURE]]])
            ->assertRedirect(route('droits.index'));
        $this->actingAs($comptable->fresh())->get('/personnels')->assertOk();
        $this->actingAs($comptable->fresh())->get('/personnels/create')->assertForbidden();

        // Valeur hors liste refusée
        $this->actingAs($direction)->put('/droits-roles', ['droits' => ['comptable' => ['personnel' => 9]]])
            ->assertSessionHasErrors();

        // Rétablir les droits par défaut
        $this->actingAs($direction)->post('/droits-roles/defaut')->assertRedirect(route('droits.index'));
        $this->actingAs($comptable->fresh())->get('/personnels')->assertForbidden();
    }

    public function test_la_direction_garde_toujours_tous_les_droits(): void
    {
        DroitRole::query()->update(['niveau' => Droits::AUCUN]);
        $direction = $this->utilisateur('direction');

        foreach (['/eleves', '/eleves/create', '/paiements', '/personnels', '/comptabilite', '/droits-roles', '/users'] as $url) {
            $this->actingAs($direction)->get($url)->assertOk();
        }
    }

    public function test_creation_d_un_compte_avec_plusieurs_roles(): void
    {
        $direction = $this->utilisateur('direction');

        $this->actingAs($direction)->get('/users/create')->assertOk()->assertSee('Rôles supplémentaires');

        $this->actingAs($direction)->post('/users', [
            'name' => 'Ali', 'email' => 'ali@test.td', 'password' => 'caisse2026', 'role' => 'comptable', 'cycle_id' => $this->cycle()->id,
            'roles_supplementaires' => ['surveillant', 'comptable', 'principal'],
        ])->assertSessionHasNoErrors();

        $ali = User::where('email', 'ali@test.td')->firstOrFail();
        $this->assertEqualsCanonicalizing(['comptable', 'surveillant', 'principal'], $ali->roles());

        // Modification : on retire le rôle Principal
        $this->actingAs($direction)->put(route('users.update', $ali), [
            'name' => 'Ali', 'email' => 'ali@test.td', 'role' => 'comptable', 'cycle_id' => $ali->cycle_id, 'roles_supplementaires' => ['surveillant'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['comptable', 'surveillant'], $ali->fresh()->roles());

        // Pas de rôle supplémentaire pour un parent ni pour la Direction ; rôle inconnu refusé
        $this->actingAs($direction)->put(route('users.update', $ali), [
            'name' => 'Ali', 'email' => 'ali@test.td', 'role' => 'parent', 'roles_supplementaires' => ['surveillant'],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['parent'], $ali->fresh()->roles());
        $this->actingAs($direction)->put(route('users.update', $ali), [
            'name' => 'Ali', 'email' => 'ali@test.td', 'role' => 'comptable', 'roles_supplementaires' => ['direction'],
        ])->assertSessionHasErrors();
    }

    public function test_enseignant_limite_a_ses_classes_sauf_si_un_autre_role_donne_le_droit(): void
    {
        $eleve = $this->eleve();
        $enseignant = Enseignant::create(['nom' => 'Prof', 'prenom' => 'Test']);
        ClasseScolaire::whereKey($eleve->classe_id)->update(['prof_principal_id' => null]);

        $prof = $this->utilisateur('enseignant');
        $prof->update(['enseignant_id' => $enseignant->id]);
        $this->actingAs($prof->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertForbidden();

        // Enseignant + Principal : le rôle Principal donne déjà la lecture de tous les bulletins
        $profPrincipal = $this->avecRoles('enseignant', ['principal']);
        $profPrincipal->update(['enseignant_id' => $enseignant->id]);
        $this->actingAs($profPrincipal->fresh())->get(route('bulletins.show', [$eleve, 1]))->assertOk();
    }

    public function test_connexion_redirige_vers_une_page_autorisee(): void
    {
        // Sans la comptabilité, le comptable arrive sur la première page autorisée
        $this->regler('comptable', 'comptabilite', Droits::AUCUN);
        $this->regler('comptable', 'scolarite', Droits::AUCUN);
        $comptable = $this->utilisateur('comptable');

        $this->post('/login', ['email' => $comptable->email, 'password' => 'secret123'])
            ->assertRedirect(route('eleves.index'));

        // Aucun droit du tout : message clair au lieu d'une boucle de redirection
        DroitRole::where('role', 'comptable')->update(['niveau' => Droits::AUCUN]);
        $this->actingAs($comptable->fresh())->get('/')->assertForbidden();
    }
}
