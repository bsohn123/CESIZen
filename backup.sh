#!/usr/bin/env bash
#
# Sauvegarde chiffrée de la base de production (V10).
#
#   ./backup.sh                 # sauvegarde quotidienne
#   ./backup.sh avant-v1.2.0    # sauvegarde étiquetée, avant un déploiement
#
# À planifier : 0 3 * * * /opt/cesizen/backup.sh >> /var/log/cesizen-backup.log 2>&1
#
# Restauration :
#   gpg --decrypt cesizen-2026-08-24.sql.gz.gpg | gunzip | \
#     docker compose -f compose.prod.yaml exec -T database mysql -u root -p"$MYSQL_ROOT_PASSWORD" cesizen

set -euo pipefail

LABEL="${1:-quotidienne}"
APP_DIR="${APP_DIR:-/opt/cesizen}"
BACKUP_DIR="${BACKUP_DIR:-$APP_DIR/backups}"
RETENTION_DAYS="${RETENTION_DAYS:-30}"
STAMP="$(date +%Y-%m-%d_%H%M%S)"
ARCHIVE="$BACKUP_DIR/cesizen_${STAMP}_${LABEL}.sql.gz"

# shellcheck disable=SC1091
set -a && source "$APP_DIR/.env.prod" && set +a

mkdir -p "$BACKUP_DIR"

docker compose -f "$APP_DIR/compose.prod.yaml" --env-file "$APP_DIR/.env.prod" \
	exec -T database mysqldump \
		--user=root \
		--password="$MYSQL_ROOT_PASSWORD" \
		--single-transaction \
		--quick \
		--routines \
		--default-character-set=utf8mb4 \
		"$MYSQL_DATABASE" | gzip -9 > "$ARCHIVE"

# Une sauvegarde vide est un échec silencieux : on le rend bruyant.
if [[ ! -s "$ARCHIVE" ]] || [[ "$(stat -c%s "$ARCHIVE")" -lt 1024 ]]; then
	rm -f "$ARCHIVE"
	echo "ERREUR : la sauvegarde est vide ou anormalement petite." >&2
	exit 1
fi

# Chiffrement au repos : la clé publique est celle de l'exploitant, la clé
# privée n'est pas sur le serveur. Une compromission du VPS ne donne donc pas
# accès à l'historique des sauvegardes.
if command -v gpg >/dev/null 2>&1 && [[ -n "${BACKUP_GPG_RECIPIENT:-}" ]]; then
	gpg --batch --yes --encrypt --recipient "$BACKUP_GPG_RECIPIENT" "$ARCHIVE"
	rm -f "$ARCHIVE"
	ARCHIVE="$ARCHIVE.gpg"
fi

# Externalisation : une sauvegarde qui ne quitte pas la machine ne protège pas
# de la perte de la machine.
if [[ -n "${BACKUP_REMOTE:-}" ]]; then
	rsync -a --quiet "$ARCHIVE" "$BACKUP_REMOTE/" || echo "AVERTISSEMENT : copie distante impossible." >&2
fi

find "$BACKUP_DIR" -name 'cesizen_*.sql.gz*' -mtime "+$RETENTION_DAYS" -delete

echo "Sauvegarde terminée : $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
