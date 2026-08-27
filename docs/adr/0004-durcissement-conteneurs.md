# ADR 0004 — Durcissement des conteneurs de production

- **Statut** : accepté
- **Date** : 2026-08-27

## Contexte

Les conteneurs tournaient en root, avec l'intégralité des capacités Linux, un
système de fichiers écrivable, aucune limite de ressources et un réseau unique
où la base de données était joignable depuis n'importe quel service.

Chacun de ces points transforme une faille applicative en compromission de la
machine : une exécution de code arbitraire dans le conteneur applicatif donnait
un accès root, la possibilité de charger des modules noyau, de modifier le code
de l'application, et un accès direct à la base.

## Options envisagées

1. **Ne rien faire** et s'en remettre à l'isolation par défaut de Docker.
   Écartée : les valeurs par défaut de Docker sont permissives par commodité, pas
   sûres par conception.
2. **Passer à des conteneurs sans distribution (`distroless`)**. Plus radical,
   mais incompatible avec FrankenPHP et les outils d'exploitation embarqués
   (`mysql-client` pour les sauvegardes). Écartée pour l'instant.
3. **Appliquer les garde-fous standard de Docker** : utilisateur non privilégié,
   suppression des capacités, système de fichiers en lecture seule, limites de
   ressources, cloisonnement réseau. Retenue.

## Décision

Cinq mesures, appliquées service par service et vérifiées.

**Utilisateur non privilégié.** L'image se termine par `USER www-data`. C'est
possible sans capacité particulière parce que l'application écoute désormais sur
8080 : se lier à 80 aurait exigé `CAP_NET_BIND_SERVICE`. Redis est lancé
directement sous son propre compte plutôt que de lui rendre `SETUID` pour qu'il
abandonne ses privilèges lui-même.

**Capacités supprimées.** `cap_drop: ALL` partout, avec deux exceptions
documentées et minimales :
- le proxy conserve `NET_BIND_SERVICE`, sans quoi il ne peut pas écouter sur
  80/443 ;
- la base conserve `CHOWN`, `SETGID`, `SETUID` et `DAC_OVERRIDE`, dont son point
  d'entrée a besoin pour initialiser son répertoire de données et abandonner les
  privilèges root — sans elles, MySQL s'arrête sur `setgid: Operation not
  permitted`.

`no-new-privileges:true` sur tous les services empêche l'obtention de privilèges
supplémentaires via un binaire `setuid`.

**Système de fichiers en lecture seule** sur les services applicatifs, avec des
tmpfs pour `/tmp`, `var/cache`, `var/log` et `var/sessions`. Le cache Symfony est
préchauffé par le point d'entrée à chaque démarrage : il est donc chaud avant la
première requête, et la mesure ne coûte rien en latence (17 à 105 ms observés).

**Limites de ressources** (`mem_limit`, `cpus`) sur chaque service, dimensionnées
pour un serveur 4 vCPU / 8 Go. Sans elles, un seul conteneur qui dérape emporte
toute la machine.

**Cloisonnement réseau** en deux réseaux : `frontend` (proxy ↔ application) et
`backend`, déclaré `internal`, où vivent la base et Redis. Le proxy — le seul
service exposé à Internet — n'a pas accès à `backend`.

## Conséquences

Une exécution de code arbitraire dans le conteneur applicatif se heurte
désormais à un compte non privilégié, sans capacité, sur un disque en lecture
seule. Le code de l'application ne peut pas être modifié depuis le conteneur qui
l'exécute.

Le cloisonnement a été vérifié : depuis le proxy, `database` et `redis` ne sont
même pas **résolus** par le DNS. L'isolation ne dépend donc pas d'un filtrage de
ports, elle est structurelle.

Trois pièges rencontrés, qui valent d'être notés car ils ne se voient qu'à
l'exécution :

1. `cap_drop: ALL` retire à root `CAP_DAC_OVERRIDE`, la capacité qui lui permet
   d'ignorer les permissions. Root ne pouvait donc plus écrire dans les fichiers
   appartenant à `www-data`. La solution correcte est de tourner sous le compte
   propriétaire, pas de rendre la capacité.
2. Le binaire FrankenPHP porte la capacité de fichier `cap_net_bind_service`.
   Sous `cap_drop: ALL` et `no-new-privileges`, le noyau **refuse de l'exécuter**
   (`exec: frankenphp: Operation not permitted`). Elle est retirée du binaire à
   la construction, l'écoute se faisant sur un port non privilégié.
3. Un tmpfs est monté `root:root 0755` par défaut, donc inaccessible en écriture
   à `www-data`. Le mode doit être forcé — et Compose l'interprète en décimal :
   il faut écrire `1023`, pas `1777`.

Le conteneur proxy reste en root, avec une seule capacité. C'est l'image
officielle `caddy:2-alpine` ; l'exécuter sous un compte non privilégié
demanderait de reconstruire une image maison, pour un gain limité puisque son
jeu de capacités est déjà réduit à une seule.
