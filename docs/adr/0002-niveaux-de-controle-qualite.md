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
