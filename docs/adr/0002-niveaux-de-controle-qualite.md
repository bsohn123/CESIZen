# ADR 0002 — Contrôles qualité progressivement bloquants

- **Statut** : accepté
- **Date** : 2026-08-24

## Contexte

La chaîne d'intégration mise en place exécute quatre contrôles : syntaxe PHP,
norme de code (php-cs-fixer), analyse statique (PHPStan) et tests fonctionnels.
La base de code existante n'a jamais été passée à php-cs-fixer ni à PHPStan :
rendre ces deux contrôles bloquants immédiatement produirait des centaines
d'écarts de style sans rapport avec les correctifs de sécurité en cours de
livraison, et inciterait à contourner la chaîne.

## Options envisagées

1. **Tout bloquant tout de suite**, avec un remaniement de style massif en
   préalable. Une pull request de plusieurs milliers de lignes, impossible à
   relire, et qui masquerait les modifications de fond.
2. **Fichier d'exclusions (baseline) PHPStan.** Fige la dette dans un fichier
   que plus personne ne relit ; les écarts existants deviennent invisibles.
3. **Contrôles informatifs puis bloquants.** Retenue.

## Décision

`php -l`, `composer audit` et `php bin/phpunit` sont bloquants dès maintenant :
ils portent sur la correction et la sécurité, où aucune tolérance n'est
acceptable.

`php-cs-fixer` et `phpstan` sont exécutés à chaque intégration mais déclarés
`continue-on-error` jusqu'à ce que la base soit normalisée. Deux tickets
`type:technique` portent cette normalisation ; ils retirent le
`continue-on-error` correspondant à leur clôture.

## Conséquences

Les écarts restent visibles dans le journal d'exécution — donc mesurables et
décroissants — sans bloquer la livraison de correctifs de sécurité. Le risque
est que la normalisation soit repoussée indéfiniment : elle est pour cela
inscrite au jalon de version, pas laissée à l'intention.

## Suite — normalisation effectuée (2026-08-25)

Les deux tickets `type:technique` sont clos et le `continue-on-error` a été
retiré : `php-cs-fixer` et `phpstan` sont désormais bloquants au même titre que
les autres contrôles.

- **Norme de code.** 38 fichiers sur 42 ont été normalisés par
  `php-cs-fixer fix`. La configuration reste en `setRiskyAllowed(false)` : seules
  des transformations sûres ont été appliquées, aucune modification de
  comportement.
- **Analyse statique.** Les neuf écarts de niveau 5 sont corrigés. Cinq d'entre
  eux (`$id` « jamais assigné ») n'étaient pas des défauts du code mais une
  méconnaissance de Doctrine par PHPStan : l'extension `phpstan/phpstan-doctrine`
  a été ajoutée avec un `objectManagerLoader`, plutôt que de dénaturer le type
  des identifiants. Seul `extension.neon` est inclus, pas `rules.neon`, dont les
  règles de cartographie condamneraient l'idiome nullable de MakerBundle sur
  l'ensemble des entités — ce serait une réécriture, pas une correction.
- **Un défaut fonctionnel a été révélé au passage** : `SecurityController`
  importait `TooManyLoginAttemptsAuthenticationException` depuis
  `Security\Http\Exception` alors que la classe se trouve dans
  `Security\Core\Exception`. La classe n'existant pas, le `instanceof` renvoyait
  toujours `false` sans lever d'erreur : le message de limitation des tentatives
  de connexion (V02) restait du code mort *après même* que la protection ait été
  configurée. Corrigé.

**Outillage.** `php-cs-fixer` et `phpstan` sont passés des outils flottants de
`setup-php` à des dépendances de développement verrouillées dans
`composer.lock`, exécutées depuis `vendor/bin`. Les postes de développement et la
chaîne analysent avec la même version : une montée de version de l'outil ne peut
plus rougir la chaîne sans qu'aucun code n'ait changé.
