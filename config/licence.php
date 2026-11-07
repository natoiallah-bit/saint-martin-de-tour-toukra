<?php

// Clé d'activation du logiciel (voir LICENSE et INSTALLATION.md, rubrique Licence).
// Les clés sont signées avec une clé privée gardée par l'auteur ; ici seule la clé PUBLIQUE
// sert à les vérifier, hors ligne. Une clé publique vide désactive le contrôle.
return [
    'cle_publique' => env('LICENCE_CLE_PUBLIQUE', 'LVz2emApbBw3gOl7mJnW/vNsozLzA2ei7kLtUWwzo6g='),

    // Jours d'essai à partir de l'installation, tant qu'aucune clé n'est enregistrée
    'essai_jours' => 30,
    // Avertissement affiché ce nombre de jours avant la fin
    'avertissement_jours' => 30,
    // Après la fin : délai de tolérance (avertissement rouge), puis lecture seule
    'tolerance_jours' => 30,

    // Coordonnées affichées pour obtenir ou renouveler une clé
    'contact_email' => env('LICENCE_CONTACT_EMAIL', 'natoiallah@gmail.com'),
    'contact_telephone' => env('LICENCE_CONTACT_TELEPHONE', '+235 66 46 22 57'),
];
