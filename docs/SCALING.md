# Montée en charge de CESIZen

Ce document décrit quand augmenter la capacité de l'application, et comment.

## Quand monter en charge

Les seuils ci-dessous déclenchent une décision. Aucun n'est un couperet : c'est
leur **persistance** qui compte. Un pic de trois minutes ne justifie pas de
changer d'architecture ; trente minutes au-dessus du seuil, si.

| Indicateur | Seuil de vigilance | Seuil d'action |
|---|---|---|
| Temps de réponse médian | > 300 ms | > 800 ms pendant 15 min |
| Temps de réponse au 95e centile | > 1 s | > 3 s pendant 15 min |
| Charge CPU du conteneur applicatif | > 60 % | > 80 % pendant 30 min |
| Mémoire du conteneur applicatif | > 70 % de `mem_limit` | > 85 % soutenu |
| Taux d'erreurs 5xx | > 0,1 % | > 1 % |
| Sonde `/health` | latence > 500 ms | échecs répétés |

Avant de monter en charge, **vérifier que le problème est bien un problème de
capacité**. Une requête SQL sans index ou un cache mal configuré ressemblent à
une saturation, mais ajouter des serveurs ne fait que déplacer le coût.

## Palier 1 — montée verticale

Augmenter le gabarit de la machine : passer de 4 vCPU / 8 Go à 8 vCPU / 16 Go,
et ajuster `mem_limit` et `cpus` dans `compose.prod.yaml`.

**Pour :** aucune modification d'architecture, aucune contrainte nouvelle sur le
code, effet immédiat. C'est le bon premier réflexe.

**Contre :** plafonné — au-delà d'un certain gabarit le coût devient
disproportionné, et il existe toujours une taille maximale. Surtout, le
redimensionnement d'une machine **impose un redémarrage** : une coupure de
service, courte mais réelle. Et la machine reste un point de défaillance unique.

## Palier 2 — montée horizontale

Plusieurs conteneurs applicatifs derrière le reverse proxy :

```sh
docker compose -f compose.prod.yaml up -d --scale app=3
```

**Pour :** ajout et retrait sans coupure, la charge est absorbée par des
instances supplémentaires, et la panne d'un conteneur ne fait plus tomber le
service — le proxy le retire de la rotation via `/health`.

**Contre :** impose une architecture sans état. C'est ce que le présent lot met
en place :

- **Un seul point d'entrée réseau.** Le service `proxy` publie 80/443 ; les
  conteneurs applicatifs écoutent en interne sur 8080 et ne publient rien. Sans
  cela, le deuxième réplica échouerait sur un conflit de port.
- **Sessions partagées.** `SESSION_DSN` pointe vers Redis en production. En
  stockage fichier, un utilisateur connecté sur un conteneur serait déconnecté
  dès que le proxy l'orienterait vers un autre.
- **Fichiers téléversés partagés.** Le volume `uploads` est monté dans tous les
  réplicas. Sans cela, une photo de profil envoyée sur un conteneur serait
  introuvable depuis les deux autres.
- **Migrations jouées une seule fois.** Le service `migrate` s'exécute avant les
  réplicas et se termine. Trois conteneurs appliquant les migrations en
  parallèle, c'est un risque réel de corruption du schéma.
- **Répartition réellement dynamique.** Le bloc `dynamic a` du
  `Caddyfile.proxy` réinterroge le DNS Docker toutes les 5 secondes. Sans lui,
  Caddy résout le nom `app` une seule fois au démarrage et envoie tout le trafic
  vers le même conteneur — la montée en charge serait cosmétique.

Vérification : `./scripts/demo-scaling.sh` envoie six requêtes et affiche le
conteneur qui a répondu à chacune.

## Limites connues

**La base de données reste un point unique.** La montée horizontale multiplie
les conteneurs applicatifs, pas la base : passé un certain volume, c'est elle
qui sature. L'étape suivante serait des **réplicas de lecture** — un serveur
primaire pour les écritures, un ou plusieurs secondaires pour les lectures, avec
routage au niveau de Doctrine. Cela suppose d'accepter une réplication
asynchrone, donc de courts décalages de cohérence sur les lectures.

**Le stockage des fichiers ne survit pas au passage sur plusieurs machines.** Le
volume `uploads` est partagé entre conteneurs d'**une même machine**. Dès que les
réplicas s'étalent sur plusieurs serveurs, il faut un stockage objet de type S3
avec `league/flysystem`. Hors périmètre du lot actuel, mais c'est le prochain
verrou.

**L'en-tête `X-Served-By`** expose le nom du conteneur ayant répondu. Il sert la
démonstration ; en production réelle, il vaut mieux le retirer au niveau du
proxy, une information d'infrastructure n'ayant pas à être publiée.
