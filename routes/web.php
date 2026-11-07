<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EleveController;
use App\Http\Controllers\NoteController;
use App\Http\Controllers\BulletinController;
use App\Http\Controllers\PaiementController;
use App\Http\Controllers\FraisScolariteController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\PaieController;
use App\Http\Controllers\PointageController;
use App\Http\Controllers\ComptabiliteController;
use App\Http\Controllers\JournalFinancierController;
use App\Http\Controllers\TresorerieController;
use App\Http\Controllers\MaterielController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ParametresController;
use App\Http\Controllers\DocumentEleveController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmploiTempsController;
use App\Http\Controllers\ConvocationController;
use App\Http\Controllers\DocumentPersonnelController;
use App\Http\Controllers\ClasseScolaireController;
use App\Http\Controllers\MatiereGestionController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\AttestationTravailController;
use App\Http\Controllers\CarteRetraitController;
use App\Http\Controllers\CertificatScolariteController;
use App\Http\Controllers\RapportController;
use App\Http\Controllers\RechercheController;
use App\Http\Controllers\CahierTexteController;
use App\Http\Controllers\DisciplineController;
use App\Http\Controllers\ImportEleveController;
use App\Http\Controllers\ParentDashboardController;
use App\Http\Controllers\PresenceController;
use App\Http\Controllers\FichePassageController;
use App\Http\Controllers\FichierController;
use App\Http\Controllers\DroitController;
use App\Http\Controllers\LicenceController;
use App\Http\Controllers\ConseilClasseController;

/*
|--------------------------------------------------------------------------
| Routes PUBLIQUES (aucune connexion requise)
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
// 10 essais par minute au plus (contre les essais de mots de passe en série)
Route::post('/login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Déconnexion de secours : permet de se déconnecter même si une page plante.
Route::get('/deconnexion-urgence', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect('/login')->with('success', 'Vous avez été déconnecté.');
})->name('logout.urgence');

// Vérification d'authenticité des documents (QR code) — accessible à tous,
// y compris sans connexion, puisque n'importe qui peut scanner un document imprimé.
// Les liens sont SIGNÉS (liens créés avec QrAuthenticite::lien) : seul un lien
// imprimé par l'application est valide. Sans signature, on ne peut pas parcourir
// /verification/paie/1, 2, 3... pour lire les salaires ou les moyennes.
// "relative" : la signature ne dépend pas de l'adresse du serveur (localhost, IP...).
Route::middleware('signed:relative')->group(function () {
    Route::get('/verification/bulletin/{eleve}/{trimestre}', [VerificationController::class, 'bulletin'])
        ->name('verification.bulletin');
    Route::get('/verification/recu/{numero}', [VerificationController::class, 'recu'])
        ->name('verification.recu');
    Route::get('/verification/carte/{eleve}', [VerificationController::class, 'carte'])
        ->name('verification.carte');
    Route::get('/verification/badge/{personnel}', [VerificationController::class, 'badge'])
        ->name('verification.badge');
    Route::get('/verification/paie/{id}', [VerificationController::class, 'paie'])
        ->name('verification.paie');
    Route::get('/verification/retrait/{carte}', [VerificationController::class, 'retrait'])
        ->name('verification.retrait');
    Route::get('/verification/certificat/{numero}', [VerificationController::class, 'certificat'])
        ->name('verification.certificat');
    Route::get('/verification/attestation/{numero}', [VerificationController::class, 'attestation'])
        ->name('verification.attestation');
});

/*
|--------------------------------------------------------------------------
| Routes PROTÉGÉES (connexion obligatoire)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Droits d'accès : chaque section est rattachée à un module ("droit:module").
    // Les droits de chaque rôle sur chaque module (Aucun, Lecture, Modifier,
    // Supprimer) sont réglés par la Direction dans "Droits des rôles"
    // (voir App\Helpers\Droits). Le niveau demandé dépend de l'action :
    // ouvrir une page = Lecture, formulaire ou enregistrement = Modifier,
    // suppression = Supprimer. La Direction a toujours tous les droits.

    // Pas de contrôle de droit ici : DashboardController::index() redirige
    // chaque compte vers une page qu'il a le droit d'ouvrir.
    Route::get('/', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Système — Direction uniquement (non réglable, pour ne jamais s'enfermer dehors)
    Route::middleware('role:direction')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/droits-roles', [DroitController::class, 'index'])->name('droits.index');
        Route::put('/droits-roles', [DroitController::class, 'update'])->name('droits.update');
        Route::post('/droits-roles/defaut', [DroitController::class, 'retablir'])->name('droits.retablir');
        Route::get('/licence', [LicenceController::class, 'index'])->name('licence.index');
        Route::post('/licence', [LicenceController::class, 'activer'])->name('licence.activer');
        Route::get('/parametres', [ParametresController::class, 'index'])->name('parametres.index');
        Route::put('/parametres', [ParametresController::class, 'update'])->name('parametres.update');
        Route::get('/appareils', [\App\Http\Controllers\AppareilController::class, 'index'])->name('appareils.index');
        Route::post('/appareils/{appareil}/deconnecter', [\App\Http\Controllers\AppareilController::class, 'deconnecter'])->name('appareils.deconnecter');
    });

    // Photos et documents envoyés (stockés hors du dossier public ; droits par
    // dossier dans FichierController)
    Route::get('/fichiers/{chemin}', [FichierController::class, 'afficher'])
        ->where('chemin', 'uploads/.+')
        ->name('fichiers.afficher');

    // Élèves
    // IMPORTANT : cette route doit être déclarée AVANT Route::resource('eleves', ...),
    // sinon elle serait interceptée par la route "show" du resource
    // (GET /eleves/{eleve}) qui tenterait de chercher un élève avec
    // l'identifiant littéral "liste-imprimable" et renverrait une 404.
    Route::get('/eleves/liste-imprimable', [EleveController::class, 'listeImprimable'])
        ->name('eleves.liste-imprimable')->middleware('droit:eleves');
    // Cartes scolaires de toute une classe (impression en série)
    Route::get('/eleves/cartes', [EleveController::class, 'cartes'])
        ->name('eleves.cartes')->middleware('droit:eleves');
    Route::resource('eleves', EleveController::class)
        ->parameters(['eleves' => 'eleve'])
        ->except(['show'])
        ->middleware('droit:eleves');
    // Carte scolaire : aussi accessible aux parents, pour leurs propres
    // enfants uniquement (contrôle dans EleveController::carte)
    Route::get('/eleves/{eleve}/carte', [EleveController::class, 'carte'])
        ->name('eleves.carte')->middleware('droit:eleves,parent');

    // Certificats d'inscription : délivrés depuis la fiche, visibles aussi par le parent de l'élève
    Route::post('/eleves/{eleve}/certificats', [CertificatScolariteController::class, 'store'])
        ->name('certificats.store')->middleware('droit:certificats');
    Route::get('/certificats/{certificat}', [CertificatScolariteController::class, 'imprimer'])
        ->name('certificats.imprimer')->middleware('droit:certificats,parent');
    Route::delete('/certificats/{certificat}', [CertificatScolariteController::class, 'destroy'])
        ->name('certificats.destroy')->middleware('droit:certificats');

    Route::middleware('droit:documents_eleves')->group(function () {
        Route::get('/eleves/{eleve}/documents', [DocumentEleveController::class, 'index'])->name('eleves.documents.index');
        Route::post('/eleves/{eleve}/documents', [DocumentEleveController::class, 'store'])->name('eleves.documents.store');
        Route::delete('/eleves/{eleve}/documents/{document}', [DocumentEleveController::class, 'destroy'])->name('eleves.documents.destroy');
    });

    Route::middleware('droit:import_eleves')->group(function () {
        Route::get('/eleves/import/excel', [ImportEleveController::class, 'formulaireUpload'])->name('import.eleves.formulaire');
        Route::post('/eleves/import/excel', [ImportEleveController::class, 'televerser'])->name('import.eleves.televerser');
        Route::post('/eleves/import/excel/importer', [ImportEleveController::class, 'importer'])->name('import.eleves.importer');
    });

    // Classes et matières
    Route::resource('classes', ClasseScolaireController::class)->except(['show'])->middleware('droit:classes');
    Route::resource('matieres-gestion', MatiereGestionController::class)->except(['show'])->middleware('droit:classes');

    // Notes et bulletins
    Route::middleware('droit:notes')->group(function () {
        Route::get('/notes', [NoteController::class, 'choix'])->name('notes.choix');
        Route::get('/notes/saisie', [NoteController::class, 'saisie'])->name('notes.saisie');
        Route::post('/notes/enregistrer', [NoteController::class, 'enregistrer'])->name('notes.enregistrer');
        Route::post('/notes/devoirs', [NoteController::class, 'evaluationStore'])->name('notes.devoirs.store');
        Route::put('/notes/devoirs/{evaluation}', [NoteController::class, 'evaluationUpdate'])->name('notes.devoirs.update');
        // Supprimer un devoir demande le droit « Modifier » (comme l'ajouter)
        Route::post('/notes/devoirs/{evaluation}/supprimer', [NoteController::class, 'evaluationDestroy'])->name('notes.devoirs.destroy');
    });
    Route::get('/bulletins', [BulletinController::class, 'choix'])
        ->name('bulletins.choix')->middleware('droit:bulletins');
    // Bulletin : aussi accessible aux parents, pour leurs propres enfants
    // uniquement (contrôle dans BulletinController::show)
    Route::get('/bulletins/{eleve}/{trimestre}', [BulletinController::class, 'show'])
        ->name('bulletins.show')->middleware('droit:bulletins,parent');

    // Conseil de classe : absences, conduite, appréciation et distinction de chaque élève
    Route::middleware('droit:conseil_classe')->group(function () {
        Route::get('/conseil-classe', [ConseilClasseController::class, 'choix'])->name('conseil-classe.choix');
        Route::get('/conseil-classe/saisie', [ConseilClasseController::class, 'saisie'])->name('conseil-classe.saisie');
        Route::post('/conseil-classe', [ConseilClasseController::class, 'enregistrer'])->name('conseil-classe.enregistrer');
    });

    // Présences des élèves
    Route::middleware('droit:presences')->group(function () {
        Route::get('/presences', [PresenceController::class, 'choix'])->name('presences.choix');
        Route::get('/presences/saisie', [PresenceController::class, 'saisie'])->name('presences.saisie');
        Route::post('/presences/enregistrer', [PresenceController::class, 'enregistrer'])->name('presences.enregistrer');
    });

    // Emplois du temps
    Route::middleware('droit:emplois_temps')->group(function () {
        Route::get('/emplois-temps', [EmploiTempsController::class, 'choix'])->name('emplois-temps.choix');
        Route::get('/emplois-temps/{classe}', [EmploiTempsController::class, 'index'])->name('emplois-temps.index');
        Route::get('/emplois-temps/{classe}/creer', [EmploiTempsController::class, 'create'])->name('emplois-temps.create');
        Route::post('/emplois-temps/{classe}', [EmploiTempsController::class, 'store'])->name('emplois-temps.store');
        Route::get('/emplois-temps/{classe}/suggestion/{matiere}', [EmploiTempsController::class, 'suggestionEnseignant'])->name('emplois-temps.suggestion');
        Route::delete('/emplois-temps/{classe}/{emploiTemp}', [EmploiTempsController::class, 'destroy'])->name('emplois-temps.destroy');
    });

    // Emploi du temps personnel de l'enseignant
    Route::get('/mon-emploi-du-temps', [EmploiTempsController::class, 'mesCours'])
        ->name('emplois-temps.mes-cours')->middleware('role:enseignant');

    // Fiches de passage de fin d'année
    Route::middleware('droit:fiches_passage')->group(function () {
        Route::get('/fiches-passage', [FichePassageController::class, 'choix'])->name('fiches-passage.choix');
        Route::get('/fiches-passage/{classe}', [FichePassageController::class, 'afficher'])->name('fiches-passage.afficher');
    });

    // Scolarité (paiements)
    Route::middleware('droit:scolarite')->group(function () {
        Route::get('/paiements', [PaiementController::class, 'index'])->name('paiements.index');
        Route::get('/paiements/creer', [PaiementController::class, 'create'])->name('paiements.create');
        Route::get('/paiements/statut/{eleve}', [PaiementController::class, 'statutEleve'])->name('paiements.statut');
        Route::post('/paiements', [PaiementController::class, 'store'])->name('paiements.store');
        Route::get('/paiements/{paiement}/edit', [PaiementController::class, 'edit'])->name('paiements.edit');
        Route::put('/paiements/{paiement}', [PaiementController::class, 'update'])->name('paiements.update');
        Route::delete('/paiements/{paiement}', [PaiementController::class, 'destroy'])->name('paiements.destroy');
    });
    // Reçu : aussi accessible aux parents, pour leurs propres enfants
    // uniquement (contrôle dans PaiementController::recu)
    Route::get('/paiements/{paiement}/recu', [PaiementController::class, 'recu'])
        ->name('paiements.recu')->middleware('droit:scolarite,parent');

    // Frais de scolarité
    Route::middleware('droit:frais')->group(function () {
        Route::get('/frais', [FraisScolariteController::class, 'index'])->name('frais.index');
        Route::post('/frais', [FraisScolariteController::class, 'update'])->name('frais.update');
    });

    // Convocations
    Route::middleware('droit:convocations')->group(function () {
        Route::get('/convocations/retards', [ConvocationController::class, 'retardsIndex'])->name('convocations.retards.index');
        Route::post('/convocations/retards/generer', [ConvocationController::class, 'retardsGenerer'])->name('convocations.retards.generer');
        Route::get('/convocations/retards/historique', [ConvocationController::class, 'retardsHistorique'])->name('convocations.retards.historique');
        Route::get('/convocations/reunion', [ConvocationController::class, 'reunionForm'])->name('convocations.reunion.form');
        Route::post('/convocations/reunion/generer', [ConvocationController::class, 'reunionGenerer'])->name('convocations.reunion.generer');
    });

    // Vérifier un document imprimé en scannant son QR code depuis l'application
    Route::middleware('droit:verification_documents')->group(function () {
        Route::get('/verifier-document', [VerificationController::class, 'formulaire'])->name('verification.document');
        Route::post('/verifier-document', [VerificationController::class, 'verifier'])->name('verification.document.verifier');
    });

    // Recherche d'un élève ou d'un membre du personnel, et fiches (accès vérifié dans les contrôleurs)
    Route::get('/recherche', [RechercheController::class, 'index'])->name('recherche');
    Route::get('/recherche/eleves/{eleve}', [RechercheController::class, 'eleve'])->name('recherche.eleve');
    Route::get('/personnels/{personnel}', [PersonnelController::class, 'show'])->whereNumber('personnel')->name('personnels.show');

    // Certificats de travail : délivrées depuis la fiche du membre du personnel
    Route::middleware('droit:attestations_travail')->group(function () {
        Route::post('/personnels/{personnel}/attestations', [AttestationTravailController::class, 'store'])->name('attestations.store');
        Route::get('/attestations/{attestation}', [AttestationTravailController::class, 'imprimer'])->name('attestations.imprimer');
        Route::delete('/attestations/{attestation}', [AttestationTravailController::class, 'destroy'])->name('attestations.destroy');
    });

    // Personnel : fiches, badges, documents
    Route::resource('personnels', PersonnelController::class)->except(['show'])->middleware('droit:personnel');
    Route::middleware('droit:personnel')->group(function () {
        Route::get('/personnels/badges', [PersonnelController::class, 'badges'])->name('personnels.badges');
        Route::get('/personnels/{personnel}/badge', [PersonnelController::class, 'badge'])->name('personnels.badge');
        Route::get('/personnels/{personnel}/documents', [DocumentPersonnelController::class, 'index'])->name('personnels.documents.index');
        Route::post('/personnels/{personnel}/documents', [DocumentPersonnelController::class, 'store'])->name('personnels.documents.store');
        Route::delete('/personnels/{personnel}/documents/{document}', [DocumentPersonnelController::class, 'destroy'])->name('personnels.documents.destroy');
    });

    // Paie du personnel
    Route::middleware('droit:paie')->group(function () {
        Route::get('/paies', [PaieController::class, 'index'])->name('paies.index');
        Route::get('/paies/creer', [PaieController::class, 'create'])->name('paies.create');
        Route::post('/paies', [PaieController::class, 'store'])->name('paies.store');
        Route::get('/paies/{paie}/bulletin', [PaieController::class, 'bulletin'])->name('paies.bulletin');
        Route::delete('/paies/{paie}', [PaieController::class, 'destroy'])->name('paies.destroy');
    });

    // Pointage du personnel (présence des employés, pas des élèves)
    Route::middleware('droit:pointage')->group(function () {
        Route::get('/pointage', [PointageController::class, 'index'])->name('pointage.index');
        Route::post('/pointage', [PointageController::class, 'enregistrer'])->name('pointage.enregistrer');
    });

    // Comptabilité
    // Journal des corrections financières : réservé à la Direction
    Route::get('/journal-financier', [JournalFinancierController::class, 'index'])->name('journal-financier.index')->middleware('role:direction');

    Route::middleware('droit:comptabilite')->group(function () {
        Route::get('/comptabilite', [ComptabiliteController::class, 'index'])->name('operations.index');
        Route::get('/comptabilite/creer', [ComptabiliteController::class, 'create'])->name('operations.create');
        Route::post('/comptabilite', [ComptabiliteController::class, 'store'])->name('operations.store');
        Route::get('/caisse-banque', [TresorerieController::class, 'index'])->name('tresorerie.index');
        Route::post('/caisse-banque/soldes', [TresorerieController::class, 'soldes'])->name('tresorerie.soldes');
        Route::post('/caisse-banque/versements', [TresorerieController::class, 'versement'])->name('tresorerie.versements.store');
        Route::delete('/caisse-banque/versements/{mouvement}', [TresorerieController::class, 'supprimerVersement'])->name('tresorerie.versements.destroy');
        Route::get('/comptabilite/{operation}/edit', [ComptabiliteController::class, 'edit'])->name('operations.edit');
        Route::put('/comptabilite/{operation}', [ComptabiliteController::class, 'update'])->name('operations.update');
        Route::delete('/comptabilite/{operation}', [ComptabiliteController::class, 'destroy'])->name('operations.destroy');
    });

    // Logistique / Matériel
    Route::resource('materiels', MaterielController::class)->except(['show'])->middleware('droit:logistique');

    // Cahier de texte (séances des enseignants, suivi et visa du Principal)
    Route::middleware('droit:cahier_texte')->prefix('cahier-texte')->name('cahier-texte.')->group(function () {
        Route::get('/', [CahierTexteController::class, 'index'])->name('index');
        Route::get('/create', [CahierTexteController::class, 'create'])->name('create');
        Route::post('/', [CahierTexteController::class, 'store'])->name('store');
        Route::get('/{seance}/edit', [CahierTexteController::class, 'edit'])->name('edit');
        Route::put('/{seance}', [CahierTexteController::class, 'update'])->name('update');
        Route::delete('/{seance}', [CahierTexteController::class, 'destroy'])->name('destroy');
        Route::post('/{seance}/viser', [CahierTexteController::class, 'viser'])->name('viser');
    });

    // Conseil de discipline
    Route::middleware('droit:discipline')->prefix('discipline')->name('discipline.')->group(function () {
        Route::get('/', [DisciplineController::class, 'index'])->name('index');
        Route::get('/create', [DisciplineController::class, 'create'])->name('create');
        Route::post('/', [DisciplineController::class, 'store'])->name('store');
        Route::get('/{dossier}', [DisciplineController::class, 'show'])->name('show');
        Route::get('/{dossier}/edit', [DisciplineController::class, 'edit'])->name('edit');
        Route::put('/{dossier}', [DisciplineController::class, 'update'])->name('update');
        Route::delete('/{dossier}', [DisciplineController::class, 'destroy'])->name('destroy');
        Route::post('/{dossier}/decision', [DisciplineController::class, 'decider'])->name('decider');
        Route::get('/{dossier}/convocation', [DisciplineController::class, 'convocation'])->name('convocation');
    });
    Route::post('/mon-espace/discipline/{dossier}/lu', [DisciplineController::class, 'lu'])->name('discipline.lu')->middleware('role:parent');

    // Rapports trimestriels et annuels (l'accès à chaque volet est vérifié dans le contrôleur)
    Route::get('/rapports', [RapportController::class, 'index'])->name('rapports.index');

    // Cartes de retrait des enfants (une carte par famille)
    Route::middleware('droit:cartes_retrait')->group(function () {
        Route::get('/cartes-retrait', [CarteRetraitController::class, 'index'])->name('cartes-retrait.index');
        Route::get('/cartes-retrait/creer', [CarteRetraitController::class, 'create'])->name('cartes-retrait.create');
        Route::post('/cartes-retrait', [CarteRetraitController::class, 'store'])->name('cartes-retrait.store');
        Route::get('/cartes-retrait/imprimer', [CarteRetraitController::class, 'imprimerTout'])->name('cartes-retrait.imprimer-tout');
        Route::get('/cartes-retrait/{carte}/imprimer', [CarteRetraitController::class, 'imprimer'])->name('cartes-retrait.imprimer');
        Route::get('/cartes-retrait/{carte}/edit', [CarteRetraitController::class, 'edit'])->name('cartes-retrait.edit');
        Route::put('/cartes-retrait/{carte}', [CarteRetraitController::class, 'update'])->name('cartes-retrait.update');
        Route::delete('/cartes-retrait/{carte}', [CarteRetraitController::class, 'destroy'])->name('cartes-retrait.destroy');
    });

    // Espace parents
    Route::get('/mon-espace', [ParentDashboardController::class, 'index'])
        ->name('parent.dashboard')
        ->middleware('role:parent');
});
