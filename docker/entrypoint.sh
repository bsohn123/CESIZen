#!/bin/sh
set -e

# Point d'entrée du conteneur applicatif.
#
# Il applique deux garde-fous avant de démarrer quoi que ce soit : un conteneur
# mal configuré doit refuser de servir plutôt que de servir mal.

# --- Garde-fou 1 : environnement (V07) --------------------------------------
if [ "${APP_ENV}" != "prod" ]; then
	echo "ERREUR : APP_ENV vaut '${APP_ENV}' au lieu de 'prod'." >&2
	echo "Démarrage refusé : le profileur et les traces détaillées ne doivent" >&2
	echo "jamais être exposés sur un environnement accessible." >&2
	exit 1
fi

# --- Garde-fou 2 : secret applicatif (V01) ----------------------------------
# APP_SECRET signe les cookies « remember me » et les URL signées. Vide, il
# rendrait ces signatures prévisibles.
if [ -z "${APP_SECRET}" ] || [ ${#APP_SECRET} -lt 32 ]; then
	echo "ERREUR : APP_SECRET absent ou trop court (32 caractères minimum)." >&2
	echo "Démarrage refusé : renseignez le secret par variable d'environnement." >&2
	exit 1
fi

if [ -z "${DATABASE_URL}" ]; then
	echo "ERREUR : DATABASE_URL n'est pas défini." >&2
	exit 1
fi

# --- Attente de la base de données ------------------------------------------
echo "Attente de la base de données..."
i=0
until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
	i=$((i + 1))
	if [ "$i" -ge 30 ]; then
		echo "ERREUR : base de données injoignable après 60 secondes." >&2
		exit 1
	fi
	sleep 2
done

# --- Migrations -------------------------------------------------------------
# Les migrations sont appliquées par le conteneur applicatif uniquement
# (le worker ne doit pas les jouer en parallèle).
if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
	echo "Application des migrations Doctrine..."
	php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

php bin/console cache:warmup --no-interaction

exec "$@"
