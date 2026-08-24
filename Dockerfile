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
    SERVER_NAME=:80

RUN install-php-extensions \
        pdo_mysql \
        intl \
        gd \
        zip \
        opcache \
        apcu \
    && apk add --no-cache mysql-client acl

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
    && mkdir -p var/log var/cache public/uploads/profile \
    && chown -R www-data:www-data var public/uploads

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

HEALTHCHECK --interval=30s --timeout=5s --start-period=40s --retries=3 \
    CMD curl --fail http://localhost/health || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
