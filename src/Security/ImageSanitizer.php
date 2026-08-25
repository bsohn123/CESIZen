<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * V06 — Assainissement des images téléversées (photos de profil).
 *
 * Un contrôle de type MIME ne suffit pas : un fichier peut être une image
 * valide ET contenir une charge utile (code PHP dans un commentaire EXIF,
 * script dans un SVG, polyglotte GIF/PHP). Le fichier est donc entièrement
 * redécodé puis réencodé par GD : seuls les pixels survivent. Toute donnée
 * annexe — métadonnées EXIF, commentaires, code injecté — disparaît.
 *
 * Effet de bord utile pour le RGPD : les coordonnées GPS que les téléphones
 * inscrivent dans les photos sont supprimées.
 */
final class ImageSanitizer
{
    public const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_DIMENSION = 2000; // pixels : borne l'occupation mémoire de GD

    /**
     * Réécrit le fichier téléversé avec une version réencodée.
     *
     * @throws FileException si le fichier n'est pas une image exploitable
     */
    public static function sanitize(UploadedFile $file): void
    {
        $path = $file->getPathname();
        $info = @getimagesize($path);

        if (false === $info) {
            throw new FileException('Le fichier fourni n\'est pas une image valide.');
        }

        [$width, $height] = $info;

        if ($width < 1 || $height < 1 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new FileException(sprintf('L\'image doit faire au maximum %d x %d pixels.', self::MAX_DIMENSION, self::MAX_DIMENSION));
        }

        $source = match ($info['mime'] ?? null) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (false === $source || null === $source) {
            throw new FileException('Format d\'image non pris en charge.');
        }

        // Réencodage systématique en JPEG (qualité 85) : format unique, sans
        // couche alpha exploitable et sans métadonnées.
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $written = imagejpeg($canvas, $path, 85);
        imagedestroy($canvas);

        if (false === $written) {
            throw new FileException('L\'image n\'a pas pu être traitée.');
        }
    }
}
