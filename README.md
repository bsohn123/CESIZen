# CESIZen

Plateforme grand public de gestion du stress et d'information sur la santé
mentale : contenus de prévention, exercices de cohérence cardiaque, suivi
personnel de la pratique.

Projet du bloc 3 « Déployer et sécuriser les applications informatiques »
(INFCDAAL3 — Concepteur Développeur d'Applications, CESI).

| | |
|---|---|
| Cadriciel | Symfony 7.4 (LTS) / PHP 8.2+ |
| Base de données | MySQL 8.4 |
| Administration | EasyAdmin 4 |
| Front-end | Twig, AssetMapper, Stimulus, Turbo |
| Production | FrankenPHP (Caddy + PHP) en conteneur, sur VPS |

## Démarrage rapide

```bash
git clone https://github.com/bsohn123/CESIZen.git && cd CESIZen

cp .env.local.example .env.local
php -r 'echo "APP_SECRET=", bin2hex(random_bytes(16)), PHP_EOL;'   # à recopier dans .env.local

docker compose up -d              # MySQL 8.4 + Mailpit
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php bin/console importmap:install

symfony server:start              # ou : php -S localhost:8000 -t public
```

- Application : <http://localhost:8000>
- Administration : <http://localhost:8000/admin>
- Courriels capturés (Mailpit) : <http://localhost:8025>

### Poste Windows partagé avec XAMPP

Si le poste héberge déjà un MySQL XAMPP servant d'autres projets, publier la
base du projet sur le port 3306 provoquerait un conflit. Elle est alors exposée
sur **3307**, XAMPP restant intact :

```powershell
docker run -d --name cesizen-mysql84 --restart unless-stopped ``
  -p 127.0.0.1:3307:3306 ``
  -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=projet_solo ``
  -e MYSQL_USER=cesizen -e MYSQL_PASSWORD=cesizen ``
  -v cesizen_mysql84_data:/var/lib/mysql ``
  mysql:8.4 --character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci
```

Reportez alors le port 3307 dans le `DATABASE_URL` de `.env.local` **et** de
`.env.test.local`. Le moteur reste MySQL 8.4, identique à la chaîne
d'intégration et à la production.

Une fois le conteneur créé, `.\scripts\dev.ps1` démarre la base puis le serveur
en une commande.

## Tests

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test
php bin/phpunit
```

La suite couvre l'authentification, le contrôle d'accès et les correctifs de
sécurité (`SecurityHardeningTest`). Toute correction d'anomalie doit être
accompagnée d'un test de non-régression.

## Qualité

```bash
php-cs-fixer fix                                    # norme PSR-12 + Symfony
phpstan analyse --configuration=phpstan.dist.neon   # analyse statique
composer audit                                      # CVE des dépendances
```

## Secrets

Aucun secret n'est versionné. En local, tout va dans `.env.local` (ignoré par
Git) ; en production, les valeurs sont injectées par variables d'environnement
du conteneur depuis `/opt/cesizen/.env.prod` (droits `600`).

Le conteneur **refuse de démarrer** si `APP_ENV` ne vaut pas `prod` ou si
`APP_SECRET` fait moins de 32 caractères.

## Déploiement

Automatisé par GitHub Actions (`.github/workflows/ci-cd.yml`) :

| Déclencheur | Effet |
|---|---|
| Pull request | qualité, audit des dépendances, tests |
| Fusion sur `develop` | + construction de l'image et déploiement en recette |
| Étiquette `v*.*.*` | + déploiement en production après validation manuelle |

Procédures détaillées (déploiement, retour arrière, restauration, incident) :
[`docs/EXPLOITATION.md`](docs/EXPLOITATION.md).

## Contribution

- Branches : `feature/*`, `fix/*`, `hotfix/*` — jamais d'envoi direct sur `main`.
- Messages de validation : [Conventional Commits](https://www.conventionalcommits.org/fr/).
- Toute modification passe par une pull request et la grille de revue de sécurité.
- Vulnérabilité : ne pas ouvrir de ticket public, voir [`SECURITY.md`](SECURITY.md).
