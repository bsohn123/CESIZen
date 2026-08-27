# Raccourcis d'exploitation de CESIZen.
#
#   make <cible>        exécute une cible
#   make                affiche cette aide

COMPOSE_PROD := docker compose -f compose.prod.yaml --env-file .env.prod.example

.DEFAULT_GOAL := help
.PHONY: help up down test audit scale hooks lint build logs shell clean

## Affiche la liste des cibles disponibles
help:
	@awk 'BEGIN { FS = ":" } \
		/^## / { desc = substr($$0, 4); next } \
		/^[a-z][a-z-]*:/ { if (desc != "") { printf "  %-12s %s\n", $$1, desc; desc = "" } }' \
		$(MAKEFILE_LIST)

## Démarre l'environnement de développement local (base, courriel)
up:
	docker compose up -d
	@echo "Application : http://localhost:8000  (php -S ou symfony serve)"

## Arrête l'environnement de développement et supprime les conteneurs
down:
	docker compose down --remove-orphans

## Lance la suite de tests
test:
	php bin/phpunit

## Recherche les dépendances vulnérables (bloquant dans la chaîne)
audit:
	composer audit

## Démontre la répartition de charge sur trois conteneurs applicatifs
scale:
	./scripts/demo-scaling.sh 3

## Arrête la composition de production lancée par « make scale »
scale-down:
	$(COMPOSE_PROD) down --remove-orphans

## Active le hook de pré-commit (secrets et syntaxe PHP)
hooks:
	git config core.hooksPath .githooks
	@echo "Hook activé. Vérification : git config core.hooksPath"

## Contrôle la norme de code et l'analyse statique, sans rien modifier
lint:
	vendor/bin/php-cs-fixer check --diff --config=.php-cs-fixer.dist.php
	vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress

## Applique la norme de code aux fichiers qui s'en écartent
lint-fix:
	vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

## Construit l'image de production en local
build:
	docker build -t ghcr.io/bsohn123/cesizen:latest .

## Affiche les journaux de la composition de production
logs:
	$(COMPOSE_PROD) logs -f --tail=100

## Ouvre un shell dans un conteneur applicatif
shell:
	$(COMPOSE_PROD) exec app sh

## Purge le cache Symfony local
clean:
	rm -rf var/cache
	php bin/console cache:clear
