# syntax=docker/dockerfile:1
#
# Image applicative de production de CESIZen.
#
# Construction en plusieurs étapes : les outils de compilation et les
# dépendances de développement ne se retrouvent pas dans l'image finale.
# L'image est construite une seule fois par la chaîne d'intégration, puis
# déployée telle quelle en recette puis en production : le binaire testé est
# exactement celui qui est mis en service.
#
# Le serveur web est FrankenPHP (Caddy + PHP embarqués) : certificats TLS
# obtenus et renouvelés automatiquement, en-têtes de sécurité publiés par la
# même configuration, un seul processus à superviser.

# ---------------------------------------------------------------------------
# Étape 1 — dépendances PHP (sans les paquets de développement)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --ignore-platform-reqs

# ---------------------------------------------------------------------------
# Étape 2 — image finale
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3 AS app

# V07 — l'environnement est figé à « prod » dans l'image : le profileur et les
# traces d'exception détaillées ne peuvent pas être activés par accident.
ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1 \
    SERVER_NAME=:8080

# L'image FrankenPHP officielle est basee sur Debian, pas sur Alpine : apk n'y
# existe pas. mysql-client sert aux sauvegardes (backup.sh), acl aux droits sur var/.
RUN install-php-extensions \
        pdo_mysql \
        intl \
        gd \
        zip \
        opcache \
        apcu \
        redis \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client acl \
    # Retrait de la chaîne de compilation, tirée par install-php-extensions et
    # inutile une fois les extensions compilées : g++, libc6-dev, linux-libc-dev
    # et leurs dépendances. Trois bénéfices — une image plus légère, trente
    # alertes Trivy de moins (les en-têtes de noyau de linux-libc-dev traînent
    # des dizaines de CVE alors qu'ils ne contiennent aucun code exécutable), et
    # surtout plus de compilateur disponible pour qui obtiendrait une exécution
    # de code dans le conteneur.
    && apt-get purge -y linux-libc-dev \
    && apt-get autoremove -y \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-cesizen.ini
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
COPY --from=vendor /app/vendor ./vendor
COPY . .

# Compilation des ressources statiques et préchauffage du cache : rien de tout
# cela n'est fait au démarrage du conteneur, donc la mise en service est immédiate.
RUN php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup \
    && mkdir -p var/log var/cache var/sessions public/uploads/profile \
    && chown -R www-data:www-data var public/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Le binaire FrankenPHP est livré avec la capacité de fichier
# cap_net_bind_service, qui lui permettrait de se lier au port 80 sans être
# root. L'application écoutant désormais sur 8080, elle est inutile — et elle
# est même bloquante : sous cap_drop ALL + no-new-privileges, le noyau refuse
# d'exécuter un binaire porteur d'une capacité qu'il ne peut pas accorder
# (« exec: frankenphp: Operation not permitted »). On la retire.
RUN setcap -r /usr/local/bin/frankenphp

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl --fail http://localhost:8080/health || exit 1

# Le conteneur ne tourne plus en root. C'est possible sans aucune capacité
# particulière parce que l'application écoute sur 8080, port non privilégié :
# se lier à 80 aurait exigé CAP_NET_BIND_SERVICE. Le compte www-data possède
# déjà var/ et public/uploads (chown ci-dessus).
#
# Ce n'est pas seulement une bonne pratique : avec cap_drop ALL, root perd
# CAP_DAC_OVERRIDE et ne peut donc plus écrire dans les fichiers appartenant à
# www-data. Tourner sous le compte propriétaire est la solution correcte —
# rendre DAC_OVERRIDE à root ne ferait que masquer le problème.
USER www-data

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
