# Description

<!-- Que fait cette modification, et pourquoi ? -->

Ticket lié : closes #

## Type

- [ ] Correction d'anomalie (`fix`)
- [ ] Évolution fonctionnelle (`feat`)
- [ ] Correctif de sécurité (`fix(security)`)
- [ ] Technique / dette (`refactor`, `chore`)

## Grille de revue de sécurité

<!-- Cinq points contrôlés sur chaque modification, cf. dossier bloc 3 § 6.2 -->

- [ ] Les entrées utilisateur sont validées **côté serveur**, pas seulement dans le navigateur.
- [ ] Toute nouvelle route est couverte par une règle de contrôle d'accès, ou est publique volontairement.
- [ ] Aucun secret, jeton ni donnée personnelle n'est ajouté au dépôt ni écrit dans les journaux.
- [ ] Les actions sensibles (authentification, changement de mot de passe, suppression) sont journalisées.
- [ ] La modification est couverte par au moins un test ; une correction d'anomalie ajoute un test de non-régression.

## Vérifications

- [ ] `php bin/phpunit` passe en local.
- [ ] La chaîne d'intégration est verte.
- [ ] Une migration est fournie si le schéma change, et elle est rétrocompatible.
- [ ] La documentation (`README`, `docs/`) est à jour si nécessaire.
