# ADR 0003 — Montée en charge horizontale plutôt que verticale seule

- **Statut** : accepté
- **Date** : 2026-08-27

## Contexte

L'application tourne dans un conteneur unique qui publie lui-même les ports
80 et 443 et stocke ses sessions en fichiers. Cette architecture interdit toute
réplication : `--scale app=3` échoue au deuxième conteneur sur un conflit de
port, et un utilisateur connecté serait déconnecté dès qu'une requête
atterrirait sur un autre réplica.

Le service doit rester disponible pendant les périodes de forte affluence, et
une coupure de service à chaque montée en charge n'est pas acceptable.

## Options envisagées

1. **Montée verticale seule** — augmenter le gabarit du serveur quand la charge
   monte. Écartée comme *unique* réponse : elle exige un redémarrage de la
   machine, donc une coupure ; elle plafonne au plus gros gabarit disponible ; et
   elle laisse la machine en point de défaillance unique. Elle reste le premier
   palier, moins coûteux et plus simple, mais elle ne suffit pas.
2. **Orchestrateur type Kubernetes** — répond au besoin, mais introduit une
   complexité d'exploitation sans commune mesure avec la taille du projet. À
   reconsidérer si le nombre de machines dépasse la poignée.
3. **Réplication de conteneurs derrière un reverse proxy** — retenue.

## Décision

Séparer le reverse proxy de l'application et rendre l'application sans état.

- Un service `proxy` (Caddy) devient le seul à publier 80/443 et à porter les
  certificats TLS. Les conteneurs applicatifs écoutent en interne sur 8080.
- La répartition s'appuie sur la résolution DNS dynamique de Docker
  (`dynamic a`, rafraîchie toutes les 5 s), et non sur une liste figée : un
  réplica ajouté ou retiré est pris en compte sans redémarrer le proxy.
- Les sessions passent dans Redis, via une variable `SESSION_DSN` dont la valeur
  par défaut reste le stockage fichier — le développement local n'a pas besoin
  de Redis.
- Les migrations sont extraites dans un service `migrate` qui s'exécute une fois
  et se termine, au lieu d'être jouées par chaque conteneur au démarrage.

## Conséquences

`docker compose up -d --scale app=3` fonctionne, et l'ajout ou le retrait d'un
réplica se fait sans coupure. Le proxy retire automatiquement de la rotation un
conteneur dont `/health` ne répond plus : la panne d'un réplica n'est plus une
panne de service.

En contrepartie, deux dépendances nouvelles à exploiter — Redis et le conteneur
proxy — et une règle à respecter dans le code : aucune donnée ne doit être écrite
sur le disque local d'un conteneur, sous peine d'être invisible depuis les
autres.

La base de données reste un point de défaillance unique. Ce n'est pas résolu
ici : l'étape suivante serait des réplicas de lecture (voir `docs/SCALING.md`).
