# Politique de sécurité — CESIZen

## Signaler une vulnérabilité

**Ne pas ouvrir de ticket public.** Les tickets sont visibles de tous : décrire
une faille exploitable dans un ticket revient à la publier.

Utiliser l'onglet **Security → Report a vulnerability** du dépôt (avis de
sécurité privé), ou écrire à `security@cesizen.fr`.

Merci d'indiquer : les étapes de reproduction, l'impact estimé, la version
concernée, et toute preuve de concept utile.

## Engagements

| Étape | Délai |
|---|---|
| Accusé de réception | 48 heures ouvrées |
| Qualification et évaluation d'impact | 5 jours ouvrés |
| Correctif pour une faille critique | 8 heures après qualification |
| Publication de l'avis | après déploiement du correctif |

Les données traitées par CESIZen relèvent de la santé mentale des utilisateurs.
Toute vulnérabilité permettant d'y accéder est traitée en priorité absolue et
déclenche la procédure de gestion de crise (dossier bloc 3, § 4.7), incluant
le cas échéant une notification à la CNIL sous 72 heures.

## Versions maintenues

Seule la dernière version mineure publiée reçoit des correctifs de sécurité.

## Périmètre

Sont concernés : l'application (`cesizen.fr`), son interface d'administration
et sa chaîne de déploiement. Sont hors périmètre : les rapports issus d'un
scanner automatique sans preuve d'exploitabilité, et les attaques nécessitant
un accès physique au serveur.
