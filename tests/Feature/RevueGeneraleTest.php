<?php

namespace Tests\Feature;

use App\Helpers\BulletinCalcul;
use App\Models\ClasseScolaire;
use App\Models\ConfigurationEcole;
use App\Models\EmploiTemps;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Personnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Corrections issues du parcours général de l'application
class RevueGeneraleTest extends TestCase
{
    use RefreshDatabase, Fabrique;

    public function test_les_notes_suivent_l_annee_scolaire(): void
    {
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2026-2027']);
        $eleve = $this->eleve();
        $matiere = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Calcul', 'coefficient' => 2]);
        Note::create(['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'moyenne_classe' => 12, 'moyenne_composition' => 14]);
        $this->assertSame('2026-2027', Note::first()->annee_scolaire);

        // Nouvelle année : le bulletin repart de zéro, l'ancienne note reste en base
        ConfigurationEcole::actuelle()->update(['annee_scolaire_active' => '2027-2028']);
        $this->assertSame(0, Note::count());
        $this->assertSame(1, Note::toutesAnnees()->count());
        Note::create(['eleve_id' => $eleve->id, 'matiere_id' => $matiere->id, 'trimestre' => 1, 'moyenne_classe' => 8, 'moyenne_composition' => 9]);
        $this->assertSame(2, Note::toutesAnnees()->count());

        // Une matière déjà notée ne peut plus être supprimée (ses notes seraient effacées)
        $this->actingAs($this->utilisateur('direction'))->delete(route('matieres-gestion.destroy', $matiere))->assertSessionHasErrors();
        $this->assertNotNull($matiere->fresh());
    }

    public function test_rangs_ex_aequo(): void
    {
        $this->assertSame(1, BulletinCalcul::rangParmi([1 => 12.5, 2 => 12.5, 3 => 10], 1));
        $this->assertSame(1, BulletinCalcul::rangParmi([1 => 12.5, 2 => 12.5, 3 => 10], 2));
        $this->assertSame(3, BulletinCalcul::rangParmi([1 => 12.5, 2 => 12.5, 3 => 10], 3));
        $this->assertNull(BulletinCalcul::rangParmi([1 => 12.5, 3 => null], 3));
    }

    public function test_le_principal_reste_dans_son_cycle_pour_classes_matieres_emplois_du_temps(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        $classeCollege = ClasseScolaire::create(['cycle_id' => $college->id, 'nom' => '6e A']);
        $matiereCollege = Matiere::create(['cycle_id' => $college->id, 'nom' => 'Physique', 'coefficient' => 2]);
        $creneau = EmploiTemps::create(['classe_id' => $classeCollege->id, 'jour' => 'lundi', 'heure_debut' => '08:00', 'heure_fin' => '09:00', 'matiere_id' => $matiereCollege->id]);
        $classePrimaire = ClasseScolaire::create(['cycle_id' => $primaire->id, 'nom' => 'CM2']);
        $principal = $this->utilisateur('principal', $primaire);

        $this->actingAs($principal)->get(route('classes.edit', $classeCollege))->assertForbidden();
        $this->actingAs($principal)->put(route('classes.update', $classePrimaire), ['cycle_id' => $college->id, 'nom' => 'CM2'])->assertSessionHasErrors('cycle_id');
        $this->actingAs($principal)->delete(route('matieres-gestion.destroy', $matiereCollege))->assertForbidden();
        $this->actingAs($principal)->get(route('emplois-temps.index', $classeCollege))->assertForbidden();
        $this->actingAs($principal)->delete(route('emplois-temps.destroy', [$classePrimaire, $creneau]))->assertNotFound();
        $this->actingAs($principal)->get(route('fiches-passage.afficher', $classeCollege))->assertForbidden();
        $this->assertNotNull($creneau->fresh());
    }

    public function test_emploi_du_temps_chevauchements_et_enseignant_en_lecture(): void
    {
        $eleve = $this->eleve();
        $classe = $eleve->classe;
        $matiere = Matiere::create(['cycle_id' => $classe->cycle_id, 'nom' => 'Calcul', 'coefficient' => 2]);
        $prof = Personnel::create(['matricule' => 'P-1', 'nom' => 'PROF', 'prenom' => 'X', 'type' => 'enseignant', 'salaire_base' => 0, 'actif' => true]);
        $autreClasse = ClasseScolaire::create(['cycle_id' => $classe->cycle_id, 'nom' => 'CM1']);
        EmploiTemps::create(['classe_id' => $autreClasse->id, 'jour' => 'lundi', 'heure_debut' => '08:00', 'heure_fin' => '10:00', 'matiere_id' => $matiere->id, 'enseignant_id' => $prof->id]);
        $direction = $this->utilisateur('direction');
        $creneau = fn ($debut, $fin, $ens = null) => ['jour' => 'lundi', 'heure_debut' => $debut, 'heure_fin' => $fin, 'matiere_id' => $matiere->id, 'enseignant_id' => $ens];

        $this->actingAs($direction)->post(route('emplois-temps.store', $classe), $creneau('09:00', '11:00', $prof->id))->assertSessionHasErrors('enseignant_id');
        $this->actingAs($direction)->post(route('emplois-temps.store', $classe), $creneau('10:00', '11:00', $prof->id))->assertSessionHasNoErrors();
        $this->actingAs($direction)->post(route('emplois-temps.store', $classe), $creneau('10:30', '11:30'))->assertSessionHasErrors('heure_debut');

        // Un enseignant ne peut pas s'ajouter un cours
        $enseignant = $this->utilisateur('enseignant');
        $this->actingAs($enseignant)->get(route('emplois-temps.index', $classe))->assertOk();
        $this->actingAs($enseignant)->post(route('emplois-temps.store', $classe), $creneau('14:00', '15:00'))->assertForbidden();
    }

    public function test_enseignant_sans_fiche_ne_saisit_aucune_note(): void
    {
        $eleve = $this->eleve();
        $matiere = Matiere::create(['cycle_id' => $eleve->classe->cycle_id, 'nom' => 'Calcul', 'coefficient' => 2]);
        $enseignant = $this->utilisateur('enseignant');

        $this->actingAs($enseignant)->get(route('notes.saisie', ['classe_id' => $eleve->classe_id, 'matiere_id' => $matiere->id, 'trimestre' => 1]))->assertForbidden();
        $this->actingAs($enseignant)->get(route('bulletins.show', [$eleve, 1]))->assertForbidden();
    }

    public function test_import_excel_matricule_dates_et_cycle(): void
    {
        $primaire = $this->cycle('Primaire');
        $college = $this->cycle('Collège');
        ClasseScolaire::create(['cycle_id' => $primaire->id, 'nom' => 'CM2']);
        ClasseScolaire::create(['cycle_id' => $college->id, 'nom' => '6e A']);
        \App\Models\Eleve::create(['classe_id' => ClasseScolaire::first()->id, 'nom' => 'ANCIEN', 'prenom' => 'X', 'matricule' => 'M-1']);

        $feuille = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $feuille->getActiveSheet()->fromArray([
            ['Nom', 'Prénom', 'Classe', 'Date de naissance', 'Matricule'],
            ['ABAKAR', 'Fatimé', 'CM2', '25/05/2015', ''],
            ['OUMAR', 'Ali', 'CM2', '05/06/2015', 'M-1'],
            ['DJIMET', 'Paul', '6e A', '01/01/2012', ''],
        ]);
        $fichier = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($feuille))->save($fichier);

        $secretariat = $this->utilisateur('secretariat', $primaire);
        $reponse = $this->actingAs($secretariat)->post(route('import.eleves.televerser'), [
            'fichier' => new \Illuminate\Http\UploadedFile($fichier, 'eleves.xlsx', null, null, true),
        ])->assertOk();
        $this->actingAs($secretariat)->post(route('import.eleves.importer'), [
            'nom_stocke' => $reponse->viewData('nomStocke'),
            'mapping' => ['nom' => 0, 'prenom' => 1, 'classe' => 2, 'date_naissance' => 3, 'matricule' => 4],
        ])->assertOk()->assertSee('dans votre cycle');

        $fatime = \App\Models\Eleve::where('nom', 'ABAKAR')->firstOrFail();
        $this->assertSame('2015-05-25', $fatime->date_naissance->format('Y-m-d'));
        $this->assertStringStartsWith('SMT-', $fatime->matricule);
        $ali = \App\Models\Eleve::where('nom', 'OUMAR')->firstOrFail();
        $this->assertSame('2015-06-05', $ali->date_naissance->format('Y-m-d'));
        $this->assertNotSame('M-1', $ali->matricule);
        $this->assertNull(\App\Models\Eleve::where('nom', 'DJIMET')->first());   // classe d'un autre cycle
    }

    public function test_suppression_bloquee_si_donnees_financieres(): void
    {
        $eleve = $this->eleve();
        $this->paiement($eleve);
        $personnel = Personnel::create(['matricule' => 'P-1', 'nom' => 'PROF', 'prenom' => 'X', 'type' => 'enseignant', 'salaire_base' => 0, 'actif' => true]);
        \App\Models\Paie::create(['personnel_id' => $personnel->id, 'mois' => '2026-10', 'salaire_base' => 1000, 'primes' => 0, 'acompte' => 0, 'retenues' => 0]);
        $direction = $this->utilisateur('direction');

        $this->actingAs($direction)->delete(route('eleves.destroy', $eleve))->assertSessionHas('erreur');
        $this->actingAs($direction)->delete(route('personnels.destroy', $personnel))->assertSessionHas('erreur');
        $this->assertNotNull($eleve->fresh());
        $this->assertNotNull($personnel->fresh());
    }

    public function test_verification_qr_du_certificat_depuis_l_application(): void
    {
        $eleve = $this->eleve();
        $certificat = \App\Models\CertificatScolarite::create(['eleve_id' => $eleve->id, 'numero' => 'CERT-2026-0001', 'annee_scolaire' => '2026-2027', 'classe_nom' => 'CM2', 'date_delivrance' => now()]);
        $lien = \App\Helpers\QrAuthenticite::lien('verification.certificat', $certificat->numero);
        $this->actingAs($this->utilisateur('secretariat'))->post(route('verification.document.verifier'), ['code' => $lien])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_matricule_avec_le_sigle_de_l_ecole(): void
    {
        $eleve = $this->eleve();
        $eleve->update(['matricule' => null]);
        $eleve->attribuerMatricule();
        $this->assertSame('SMT-' . str_pad($eleve->id, 4, '0', STR_PAD_LEFT), $eleve->matricule);

        \App\Models\ConfigurationEcole::actuelle()->update(['sigle_ecole' => 'CSCS']);
        $autre = $this->eleve(null, 'AUTRE');
        $autre->update(['matricule' => null]);
        $autre->attribuerMatricule();
        $this->assertStringStartsWith('CSCS-', $autre->matricule);
    }

    public function test_bulletin_moyennes_sur_une_ligne_rang_annuel_et_conduite_automatique(): void
    {
        $cycle = $this->cycle('Collège');
        $premier = $this->eleve($cycle, 'PREMIER');
        $second = $this->eleve($cycle, 'SECOND');
        $maths = Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Maths', 'coefficient' => 2, 'categorie' => 'Scientifique']);
        foreach ([1, 2, 3] as $t) {
            Note::create(['eleve_id' => $premier->id, 'matiere_id' => $maths->id, 'trimestre' => $t, 'moyenne_classe' => 15, 'moyenne_composition' => 15]);
            Note::create(['eleve_id' => $second->id, 'matiere_id' => $maths->id, 'trimestre' => $t, 'moyenne_classe' => 10, 'moyenne_composition' => 10]);
        }
        \App\Models\ConseilClasse::create(['eleve_id' => $second->id, 'trimestre' => 3, 'annee_scolaire' => ConfigurationEcole::anneeActive(), 'note_conduite' => 17]);

        $reponse = $this->actingAs($this->utilisateur('direction'))->get(route('bulletins.show', [$second, 3]))->assertOk()
            ->assertSee('Moyenne T1')->assertSee('Moyenne T3')->assertSee('Rang annuel')
            ->assertSee('Excellente conduite')
            ->assertDontSee('▲')->assertDontSee('▼')
            ->assertSee('<th class="col-n col-rappel">T1</th>', false)->assertSee('<th class="col-n col-rappel">T2</th>', false);
        $ligne = $reponse->viewData('lignes')->first();
        $this->assertEquals([1 => 10, 2 => 10], $ligne['rappel']);   // moyennes de Maths aux T1 et T2
        $this->assertSame(2, $reponse->viewData('rangAnnuel'));
        $this->assertSame(1, \App\Helpers\BulletinCalcul::rangAnnuel($premier->classe_id, $premier->id));
    }

    public function test_bulletin_maternelle_sans_categories(): void
    {
        $cycle = $this->cycle('Maternelle');
        $eleve = $this->eleve($cycle, 'PETIT');
        $chant = Matiere::create(['cycle_id' => $cycle->id, 'nom' => 'Chant', 'coefficient' => 1, 'categorie' => 'Accessoire']);
        Note::create(['eleve_id' => $eleve->id, 'matiere_id' => $chant->id, 'trimestre' => 1, 'moyenne_classe' => 14, 'moyenne_composition' => 16]);

        $this->actingAs($this->utilisateur('direction'))->get(route('bulletins.show', [$eleve, 1]))->assertOk()
            ->assertSee('Chant')
            ->assertDontSee('Bilan accessoire')
            ->assertDontSee('<tr class="ligne-categorie">', false)
            ->assertSee('Le Directeur du Primaire');
    }
}
