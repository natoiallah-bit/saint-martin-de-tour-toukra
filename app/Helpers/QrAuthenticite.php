<?php

namespace App\Helpers;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Facades\URL;

class QrAuthenticite
{
    /**
     * Lien de vérification signé à mettre dans un QR code.
     *
     * La signature ne porte que sur le chemin (/verification/...), pas sur l'adresse
     * du serveur : un document imprimé depuis "localhost" reste vérifiable quand on
     * le scanne avec un téléphone. L'adresse mise devant le chemin est celle de
     * URL_DOCUMENTS dans .env (ex. http://192.168.1.10/saint-martin-toukra/public) ;
     * à défaut, celle par laquelle la page a été ouverte.
     */
    public static function lien(string $route, mixed $parametres = []): string
    {
        $chemin = URL::signedRoute($route, $parametres, null, false);
        $base = rtrim(config('app.url_documents') ?: url('/'), '/');

        return $base . $chemin;
    }

    /**
     * Image du QR code, générée directement par l'application (aucune connexion
     * internet nécessaire), à mettre dans l'attribut src d'une balise <img>.
     * $taille ne sert plus qu'à la compatibilité : la taille affichée est réglée
     * par le CSS de chaque document (image vectorielle, nette à l'impression).
     */
    public static function imageUrl(string $contenuUrl, int $taille = 120): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($contenuUrl);
    }
}
