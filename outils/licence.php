<?php

// Outil de l'AUTEUR pour fabriquer les clés d'activation. À ne pas donner à l'école.
//
//   php outils/licence.php paire                      crée licence-privee.key et affiche la clé publique
//   php outils/licence.php generer SIGLE "Nom" AAAA-MM-JJ [fichier-cle-privee]
//   php outils/licence.php verifier "CLE" [clé-publique-base64]
//
// licence-privee.key doit rester secrète (jamais sur GitHub, ni envoyée à l'école) et être sauvegardée :
// sans elle, plus aucune clé ne peut être émise. La clé publique se place dans config/licence.php
// (ou LICENCE_CLE_PUBLIQUE dans .env).

if (! function_exists('sodium_crypto_sign_keypair')) {
    fwrite(STDERR, "L'extension PHP sodium est nécessaire.\n");
    exit(1);
}

function b64u(string $brut): string
{
    return rtrim(strtr(base64_encode($brut), '+/', '-_'), '=');
}

function b64u_decode(string $texte): string
{
    return base64_decode(strtr($texte, '-_', '+/'), true) ?: '';
}

$commande = $argv[1] ?? '';

if ($commande === 'paire') {
    $fichier = $argv[2] ?? 'licence-privee.key';
    if (file_exists($fichier)) {
        fwrite(STDERR, "$fichier existe déjà : ne l'écrasez pas, vous ne pourriez plus émettre de clés avec l'ancienne.\n");
        exit(1);
    }
    $paire = sodium_crypto_sign_keypair();
    file_put_contents($fichier, base64_encode(sodium_crypto_sign_secretkey($paire)));
    @chmod($fichier, 0600);
    echo "Clé privée écrite dans $fichier : gardez-la secrète et sauvegardez-la.\n";
    echo "Clé publique à mettre dans config/licence.php :\n" . base64_encode(sodium_crypto_sign_publickey($paire)) . "\n";
    exit(0);
}

if ($commande === 'generer') {
    [$sigle, $ecole, $fin] = [$argv[2] ?? '', $argv[3] ?? '', $argv[4] ?? ''];
    $fichier = $argv[5] ?? 'licence-privee.key';
    if ($sigle === '' || $ecole === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) || ! checkdate((int) substr($fin, 5, 2), (int) substr($fin, 8, 2), (int) substr($fin, 0, 4))) {
        fwrite(STDERR, "Usage : php outils/licence.php generer SIGLE \"Nom de l'école\" AAAA-MM-JJ [fichier-cle-privee]\n");
        exit(1);
    }
    $privee = is_file($fichier) ? base64_decode(trim(file_get_contents($fichier)), true) : false;
    if ($privee === false || strlen($privee) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
        fwrite(STDERR, "Clé privée introuvable ou illisible ($fichier).\n");
        exit(1);
    }
    $charge = json_encode(['v' => 1, 'sigle' => $sigle, 'ecole' => $ecole, 'fin' => $fin], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo b64u($charge) . '.' . b64u(sodium_crypto_sign_detached($charge, $privee)) . "\n";
    exit(0);
}

if ($commande === 'verifier') {
    $cle = preg_replace('/\s+/', '', $argv[2] ?? '');
    $publique = base64_decode($argv[3] ?? '', true);
    if ($publique === false || $publique === '') {
        $config = @file_get_contents(__DIR__ . '/../config/licence.php');
        preg_match("/'cle_publique' => env\\('LICENCE_CLE_PUBLIQUE', '([^']*)'/", (string) $config, $m);
        $publique = base64_decode($m[1] ?? '', true);
    }
    $parties = explode('.', $cle);
    $signature = count($parties) === 2 ? b64u_decode($parties[1]) : '';
    if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES || ! $publique || ! sodium_crypto_sign_verify_detached($signature, b64u_decode($parties[0]), $publique)) {
        echo "Clé INVALIDE\n";
        exit(1);
    }
    echo "Clé valide : " . b64u_decode($parties[0]) . "\n";
    exit(0);
}

fwrite(STDERR, "Commandes : paire | generer SIGLE \"Nom\" AAAA-MM-JJ [fichier] | verifier \"CLE\" [clé-publique]\n");
exit(1);
