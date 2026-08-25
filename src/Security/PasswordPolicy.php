<?php

namespace App\Security;

/**
 * V03 — Politique de mot de passe centralisée.
 *
 * La règle était auparavant dupliquée dans trois contrôleurs (inscription,
 * changement de mot de passe, réinitialisation) avec une longueur minimale de
 * 8 caractères et aucun contrôle de complexité. Elle est ici unique, testable
 * et alignée sur les recommandations de l'ANSSI pour une authentification par
 * mot de passe seul : 12 caractères minimum et trois classes de caractères.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 4096; // borne haute : évite un déni de service au hachage

    /**
     * Retourne la liste des messages d'erreur ; un tableau vide vaut « conforme ».
     *
     * @return list<string>
     */
    public static function validate(string $password, ?string $confirmation = null): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_LENGTH);
        }

        if (mb_strlen($password) > self::MAX_LENGTH) {
            $errors[] = 'Le mot de passe est trop long.';
        }

        $classes = 0;
        $classes += preg_match('/[a-z]/', $password) ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $password) ? 1 : 0;
        $classes += preg_match('/[0-9]/', $password) ? 1 : 0;
        $classes += preg_match('/[^a-zA-Z0-9]/', $password) ? 1 : 0;

        if ($classes < 3) {
            $errors[] = 'Le mot de passe doit combiner au moins trois types de caractères parmi : minuscules, majuscules, chiffres et caractères spéciaux.';
        }

        if (null !== $confirmation && $password !== $confirmation) {
            $errors[] = 'La confirmation du mot de passe ne correspond pas.';
        }

        return $errors;
    }
}
