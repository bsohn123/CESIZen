#!/usr/bin/env bash
#
# Déploiement de CESIZen en production.
#
#   ./deploy.sh v1.2.0
#
# Appelé par la chaîne GitHub Actions après validation manuelle, ou à la main
# sur le serveur. Chaque étape est vérifiée : au moindre échec, le script
# s'arrête avant d'avoir dégradé le service.
#
# Retour arrière : ./deploy.sh <version-précédente>

set -euo pipefail

VERSION="${1:-}"
APP_DIR="${APP_DIR:-/opt/cesizen}"
BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/backups}"
COMPOSE="docker compose -f $APP_DIR/compose.prod.yaml --env-file $APP_DIR/.env.prod"

log() { printf '\033[0;36m[%s]\033[0m %s\n' "$(date +%H:%M:%S)" "$1"; }
fail() { printf '\033[0;31m[ÉCHEC]\033[0m %s\n' "$1" >&2; exit 1; }

[[ -n "$VERSION" ]] || fail "Usage : ./deploy.sh <version>  (exemple : ./deploy.sh v1.2.0)"
[[ -f "$APP_DIR/.env.prod" ]] || fail "Fichier $APP_DIR/.env.prod introuvable."

cd "$APP_DIR"

# ---------------------------------------------------------------------------
# 1. Sauvegarde préalable — aucune modification sans filet de sécurité
# ---------------------------------------------------------------------------
log "Sauvegarde de la base de données avant déploiement..."
mkdir -p "$BACKUP_DIR"
"$APP_DIR/backup.sh" "avant-$VERSION" || fail "Sauvegarde impossible : déploiement annulé."

PREVIOUS_VERSION="$(grep -E '^APP_VERSION=' .env.prod | cut -d= -f2- || echo 'latest')"
log "Version actuellement déployée : $PREVIOUS_VERSION"

# ---------------------------------------------------------------------------
# 2. Récupération de l'image — jamais de construction sur le serveur
# ---------------------------------------------------------------------------
log "Récupération de l'image $VERSION depuis le registre..."
sed -i "s/^APP_VERSION=.*/APP_VERSION=$VERSION/" .env.prod || echo "APP_VERSION=$VERSION" >> .env.prod
$COMPOSE pull app worker || fail "Image $VERSION introuvable dans le registre."

# ---------------------------------------------------------------------------
# 3 & 4. Migrations et préchauffage : exécutés par le point d'entrée du conteneur
# 5.     Bascule
# ---------------------------------------------------------------------------
log "Bascule sur la nouvelle version..."
$COMPOSE up -d --remove-orphans

# ---------------------------------------------------------------------------
# 6. Vérification — test de fumée sur les parcours essentiels
# ---------------------------------------------------------------------------
log "Vérification de l'état de l'application..."
for attempt in {1..30}; do
	if curl -fsS --max-time 5 "http://localhost/health" >/dev/null 2>&1; then
		break
	fi
	if [[ $attempt -eq 30 ]]; then
		printf '\033[0;31m[ÉCHEC]\033[0m Sonde de santé négative après 60 s.\n' >&2
		printf 'Retour arrière : ./deploy.sh %s\n' "$PREVIOUS_VERSION" >&2
		exit 1
	fi
	sleep 2
done

for route in "/" "/login" "/inscription"; do
	code="$(curl -o /dev/null -s -w '%{http_code}' --max-time 10 "http://localhost$route")"
	[[ "$code" == "200" ]] || fail "Test de fumée négatif sur $route (code HTTP $code). Retour arrière : ./deploy.sh $PREVIOUS_VERSION"
	log "  $route → $code"
done

# ---------------------------------------------------------------------------
# 7. Nettoyage
# ---------------------------------------------------------------------------
docker image prune -f --filter "until=168h" >/dev/null 2>&1 || true

log "Déploiement de $VERSION terminé avec succès (version précédente : $PREVIOUS_VERSION)."
