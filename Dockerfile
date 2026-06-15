# Production image for the VoiceScribe Laravel API.
# Single web container (nginx + php-fpm) — summarization/chat are synchronous and
# cache/session/queue use the DB driver, so no queue worker is needed.

# --- Composer deps (no dev) ---------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# --- Runtime ------------------------------------------------------------------
FROM serversideup/php:8.3-fpm-nginx

# serversideup runs Laravel boot tasks automatically at container start. This is
# how migrations run on a managed-MySQL deploy (no db:seed) — the seed migrations
# leave the lookup/reference data (incl. the 'local' LLM provider) in place.
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true \
    PHP_OPCACHE_ENABLE=1

WORKDIR /var/www/html

USER root
COPY --chown=www-data:www-data . /var/www/html
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor
# The repo .gitignore excludes /storage, so a fresh git clone (Dokploy build)
# ships without the framework writable dirs. Without storage/framework/views,
# `php artisan optimize` (view:cache) fatals with "View path not found" and the
# container restart-loops. Recreate the standard Laravel writable tree.
RUN mkdir -p \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        storage/app/public \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
