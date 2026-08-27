#!/bin/sh
set -eu

# Démonstration de la répartition de charge horizontale.
#
#   ./scripts/demo-scaling.sh [nombre_de_repliques]
#
# Démarre la composition de production avec plusieurs conteneurs applicatifs,
# puis envoie six requêtes et affiche, pour chacune, le conteneur qui a répondu.
# L'en-tête X-Served-By est posé par le Caddyfile interne de l'image.
#
# Ce script sert de démonstration : il montre à l'écran que le trafic est
# effectivement réparti, et non traité par un seul conteneur.

REPLICAS="${1:-3}"
COMPOSE="docker compose -f compose.prod.yaml --env-file .env.prod.example"
BASE_URL="${BASE_URL:-http://localhost}"

vert() { printf '\033[0;32m%s\033[0m\n' "$1"; }
rouge() { printf '\033[0;31m%s\033[0m\n' "$1" >&2; }
titre() { printf '\n\033[1m%s\033[0m\n' "$1"; }

cd "$(dirname "$0")/.."

titre "1. Démarrage de la composition avec $REPLICAS conteneurs applicatifs"
$COMPOSE up -d --scale "app=$REPLICAS" --remove-orphans

titre "2. Attente de la sonde de santé"
i=0
until curl -fsS --max-time 3 "$BASE_URL/health" >/dev/null 2>&1; do
	i=$((i + 1))
	if [ "$i" -ge 60 ]; then
		rouge "ÉCHEC : $BASE_URL/health ne répond pas après 120 s."
		rouge "Journaux : $COMPOSE logs --tail=50 proxy app"
		exit 1
	fi
	sleep 2
done
vert "  Sonde de santé positive après $((i * 2)) s."

titre "3. Six requêtes, et le conteneur qui a répondu à chacune"
HOTES=""
n=0
while [ "$n" -lt 6 ]; do
	n=$((n + 1))
	hote="$(curl -s -o /dev/null -D - --max-time 10 "$BASE_URL/" \
		| tr -d '\r' \
		| awk 'tolower($1) == "x-served-by:" { print $2 }')"
	[ -n "$hote" ] || hote='(en-tête absent)'
	printf '  requête %d → %s\n' "$n" "$hote"
	HOTES="$HOTES$hote\n"
done

titre "4. Résultat"
DISTINCTS="$(printf '%b' "$HOTES" | sed '/^$/d' | sort -u | wc -l | tr -d ' ')"
printf '%b' "$HOTES" | sed '/^$/d' | sort | uniq -c | while read -r nb hote; do
	printf '  %s : %s requête(s)\n' "$hote" "$nb"
done

if [ "$DISTINCTS" -ge 2 ]; then
	vert "6 requêtes réparties sur $DISTINCTS conteneurs distincts."
	exit 0
fi

rouge "Une seule instance a répondu : la répartition ne fonctionne pas."
rouge "Vérifier le bloc « dynamic a » de docker/Caddyfile.proxy — sans lui,"
rouge "Caddy résout le nom « app » une seule fois au démarrage."
exit 1
