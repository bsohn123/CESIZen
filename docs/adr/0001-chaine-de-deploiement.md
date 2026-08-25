# ADR 0001 — Chaîne de déploiement conteneurisée sur VPS

- **Statut** : accepté
- **Date** : 2026-08-24
- **Contexte du bloc** : INFCDAAL3 — Déployer et sécuriser les applications informatiques

## Contexte

CESIZen n'avait aucune chaîne de livraison : pas d'intégration continue, pas
d'image applicative, aucun environnement déployé. Le projet est mené par une
seule personne, pour une application à trafic modéré, mais traitant des données
relatives à la santé mentale — donc à forte exigence de confidentialité.

## Options envisagées

1. **Dépôt de fichiers par FTP/rsync sur un hébergement mutualisé.** Coût nul,
   mais aucune reproductibilité, aucun retour arrière, aucune maîtrise de la
   version de PHP ni des extensions.
2. **PaaS (Scalingo, Clever Cloud).** Déploiement par `git push`, hébergement
   européen. Exploitation très simple, mais peu de choses à démontrer sur le
   plan « mise en place et configuration d'un environnement », et coût mensuel
   supérieur à trafic équivalent.
3. **VPS + conteneurs Docker + chaîne GitHub Actions.** Retenue.

## Décision

Option 3. L'image applicative est construite une seule fois par la chaîne
d'intégration, poussée sur GHCR, puis déployée telle quelle : l'artefact testé
est exactement celui qui est mis en service. Le serveur web est FrankenPHP
(Caddy + PHP dans un seul processus), qui apporte le TLS automatique et la
publication des en-têtes de sécurité sans composant supplémentaire à superviser.

L'hébergement est situé en France, ce qui simplifie l'analyse de conformité
RGPD pour des données de santé.

## Conséquences

**Positives** — déploiement reproductible et réversible en moins de cinq
minutes ; parité entre développement, recette et production ; aucun secret dans
le dépôt ; coût d'exploitation d'environ 320 € par an.

**Négatives** — l'exploitation du serveur (mises à jour système, pare-feu,
sauvegardes) reste à la charge du projet, là où un PaaS l'aurait absorbée. Le
risque est traité par les mises à jour automatiques, `fail2ban` et la sauvegarde
quotidienne chiffrée testée mensuellement.

**Point de vigilance** — en cas de passage à l'échelle, un hébergement certifié
HDS devra être étudié, la plateforme traitant des données de santé.
