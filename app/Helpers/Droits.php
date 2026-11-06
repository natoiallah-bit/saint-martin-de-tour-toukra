<?php

namespace App\Helpers;

/**
 * Définition des droits d'accès : modules de l'application, niveaux de droit,
 * rôles réglables et droits par défaut de chaque rôle.
 *
 * Les droits réellement appliqués sont ceux enregistrés dans la table
 * "droits_roles", que la Direction modifie depuis la page "Droits des rôles".
 * Les valeurs par défaut ci-dessous ne servent qu'à remplir cette table la
 * première fois (et au bouton "Rétablir les droits par défaut").
 *
 * - La Direction a toujours tous les droits (elle ne peut pas se bloquer).
 * - Le rôle Parent n'est pas réglable : il ne voit que son espace et les
 *   documents (carte, bulletin, reçus) de ses propres enfants.
 * - Un utilisateur qui a plusieurs rôles cumule leurs droits : pour chaque
 *   module, il obtient le niveau le plus élevé parmi ses rôles.
 */
class Droits
{
    public const AUCUN = 0;
    public const LECTURE = 1;
    public const MODIFIER = 2;
    public const SUPPRIMER = 3;

    // Chaque niveau inclut les précédents (Supprimer permet aussi de modifier et de lire)
    public const NIVEAUX = [
        self::AUCUN => 'Aucun accès',
        self::LECTURE => 'Lecture seule',
        self::MODIFIER => 'Modifier',
        self::SUPPRIMER => 'Supprimer',
    ];

    // Rôles dont la Direction règle les droits
    public const ROLES = [
        'secretariat' => 'Secrétariat',
        'comptable' => 'Comptable',
        'enseignant' => 'Enseignant',
        'surveillant' => 'Surveillant',
        'principal' => 'Principal (pédagogie)',
    ];

    // Modules de l'application, dans l'ordre du menu
    public const MODULES = [
        'eleves' => 'Élèves (liste, fiches, cartes)',
        'documents_eleves' => 'Documents des élèves',
        'certificats' => "Certificats d'inscription",
        'import_eleves' => 'Import Excel des élèves',
        'classes' => 'Classes et matières',
        'notes' => 'Saisie des notes',
        'bulletins' => 'Bulletins',
        'conseil_classe' => 'Conseil de classe (absences, conduite, appréciations)',
        'presences' => 'Présences des élèves',
        'emplois_temps' => 'Emplois du temps',
        'fiches_passage' => 'Fiches de passage',
        'scolarite' => 'Scolarité (paiements, reçus)',
        'frais' => 'Frais de scolarité',
        'convocations' => 'Convocations',
        'verification_documents' => 'Vérifier un document (QR code)',
        'personnel' => 'Personnel (fiches, badges, documents)',
        'attestations_travail' => 'Certificats de travail du personnel',
        'paie' => 'Paie du personnel',
        'pointage' => 'Pointage du personnel',
        'comptabilite' => 'Comptabilité',
        'logistique' => 'Logistique (matériel)',
        'cartes_retrait' => 'Cartes de retrait des enfants',
        'cahier_texte' => 'Cahier de texte (séances, suivi et visa)',
        'discipline' => 'Conseil de discipline',
        'rapport_pedagogique' => 'Rapports : volet pédagogique',
        'rapport_financier' => 'Rapports : volet financier',
    ];

    // Droits par défaut : reprennent les accès en place avant la page "Droits des rôles"
    public const DEFAUTS = [
        'secretariat' => [
            'eleves' => self::SUPPRIMER, 'documents_eleves' => self::SUPPRIMER, 'import_eleves' => self::MODIFIER,
            'classes' => self::SUPPRIMER, 'bulletins' => self::LECTURE, 'presences' => self::MODIFIER,
            'emplois_temps' => self::SUPPRIMER, 'scolarite' => self::SUPPRIMER, 'frais' => self::MODIFIER,
            'convocations' => self::MODIFIER, 'verification_documents' => self::MODIFIER,
            'personnel' => self::SUPPRIMER, 'paie' => self::SUPPRIMER, 'pointage' => self::MODIFIER,
            'comptabilite' => self::SUPPRIMER, 'logistique' => self::SUPPRIMER,
            'cartes_retrait' => self::SUPPRIMER, 'certificats' => self::SUPPRIMER,
            'attestations_travail' => self::SUPPRIMER,
        ],
        'comptable' => [
            'scolarite' => self::SUPPRIMER, 'frais' => self::MODIFIER, 'paie' => self::SUPPRIMER,
            'comptabilite' => self::SUPPRIMER, 'logistique' => self::SUPPRIMER,
            'rapport_financier' => self::LECTURE,
            // Le comptable fait aussi le travail de caisse (ancien rôle Caissier)
            'eleves' => self::LECTURE, 'convocations' => self::MODIFIER, 'verification_documents' => self::MODIFIER,
        ],
        'enseignant' => [
            'eleves' => self::LECTURE, 'notes' => self::MODIFIER, 'bulletins' => self::LECTURE,
            'conseil_classe' => self::MODIFIER, 'presences' => self::MODIFIER, 'emplois_temps' => self::LECTURE,
            'cahier_texte' => self::MODIFIER,
        ],
        'surveillant' => [
            'eleves' => self::LECTURE, 'presences' => self::MODIFIER, 'pointage' => self::MODIFIER,
            'cartes_retrait' => self::LECTURE, 'discipline' => self::MODIFIER,
        ],
        'principal' => [
            'eleves' => self::SUPPRIMER, 'documents_eleves' => self::SUPPRIMER, 'classes' => self::SUPPRIMER,
            'notes' => self::MODIFIER, 'bulletins' => self::LECTURE, 'conseil_classe' => self::MODIFIER,
            'presences' => self::MODIFIER, 'emplois_temps' => self::SUPPRIMER, 'fiches_passage' => self::LECTURE,
            'convocations' => self::MODIFIER, 'rapport_pedagogique' => self::LECTURE,
            'cahier_texte' => self::SUPPRIMER, 'discipline' => self::SUPPRIMER, 'certificats' => self::MODIFIER,
        ],
    ];

    // Page d'accueil de chaque module (pour rediriger un utilisateur après sa connexion)
    public const PAGES = [
        'eleves' => 'eleves.index',
        'scolarite' => 'paiements.index',
        'notes' => 'notes.choix',
        'bulletins' => 'bulletins.choix',
        'conseil_classe' => 'conseil-classe.choix',
        'presences' => 'presences.choix',
        'emplois_temps' => 'emplois-temps.choix',
        'classes' => 'classes.index',
        'fiches_passage' => 'fiches-passage.choix',
        'frais' => 'frais.index',
        'convocations' => 'convocations.retards.index',
        'verification_documents' => 'verification.document',
        'personnel' => 'personnels.index',
        'paie' => 'paies.index',
        'pointage' => 'pointage.index',
        'comptabilite' => 'operations.index',
        'logistique' => 'materiels.index',
        'import_eleves' => 'import.eleves.formulaire',
        'cartes_retrait' => 'cartes-retrait.index',
        'cahier_texte' => 'cahier-texte.index',
        'discipline' => 'discipline.index',
        'rapport_pedagogique' => 'rapports.index',
        'rapport_financier' => 'rapports.index',
    ];

    // Pages qui s'affichent en GET mais servent à ajouter ou modifier : elles
    // demandent le droit "Modifier" (repérées par la fin du nom de la route)
    protected const PAGES_DE_SAISIE = ['create', 'creer', 'edit', 'saisie', 'formulaire', 'suggestion'];

    // Valeur numérique d'un niveau écrit en toutes lettres dans les routes
    public static function niveau(string $nom): int
    {
        return match ($nom) {
            'lecture' => self::LECTURE,
            'modifier' => self::MODIFIER,
            'supprimer' => self::SUPPRIMER,
            default => throw new \InvalidArgumentException("Niveau de droit inconnu : $nom"),
        };
    }

    // Niveau demandé par une requête : lire (GET), modifier (POST/PUT, formulaires), supprimer (DELETE)
    public static function niveauRequis(string $methode, ?string $nomRoute): int
    {
        if ($methode === 'DELETE') {
            return self::SUPPRIMER;
        }

        if (! in_array($methode, ['GET', 'HEAD'], true)) {
            return self::MODIFIER;
        }

        $fin = $nomRoute ? substr(strrchr('.' . $nomRoute, '.'), 1) : '';

        return in_array($fin, self::PAGES_DE_SAISIE, true) ? self::MODIFIER : self::LECTURE;
    }
}
