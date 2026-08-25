<?php

namespace App\Security;

/**
 * V04 — Jeton de réinitialisation de mot de passe.
 *
 * Le jeton en clair n'existe que dans le courriel envoyé à l'utilisateur ;
 * la base ne conserve que son empreinte SHA-256. Un attaquant disposant d'une
 * copie de la table `user` ne peut donc pas forger un lien valide.
 *
 * SHA-256 est suffisant ici (et non un hachage lent type Argon2) car le jeton
 * est aléatoire sur 256 bits : il n'est pas devinable par force brute, la
 * lenteur du hachage n'apporterait rien.
 */
final class ResetTokenHasher
{
    /** Jeton aléatoire de 64 caractères hexadécimaux (256 bits d'entropie). */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** Empreinte stockée en base (64 caractères hexadécimaux). */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
