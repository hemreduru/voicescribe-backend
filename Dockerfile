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

# serversideup runs Laravel boot tasks automatically at container start.
#
# Migrations: AUTORUN_LARAVEL_MIGRATION runs `php artisan migrate --force` (the
# --force flag is explicit below). The seed migrations leave the lookup/reference
# data (incl. the 'local' LLM provider) in place, so no db:seed is needed.
# AUTORUN_LARAVEL_MIGRATION_ISOLATION stays off on purpose: this is a single
# replica, and isolated migrations take a cache lock, but the cache/lock tables
# are themselves created by these migrations (fails on a fresh database). Turn it
# on only if you scale to several replicas AFTER the first deploy.
#
# Limits: sized for the text payload caps in the FormRequests (<= 200k chars for
# summarization input, 16k-char chunks x 50-row sync batches, UTF-8 up to
# 4 bytes/char) with headroom -> 16M bodies. Execution time covers the
# synchronous LLM call (LLM_REQUEST_TIMEOUT=60s per attempt, with model fallback).
#
# Logs: LOG_CHANNEL=stderr sends Laravel errors/exceptions to the container logs.
# HEALTHCHECK_PATH is used by the image's built-in HEALTHCHECK (see below).
ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION=true \
    AUTORUN_LARAVEL_MIGRATION_FORCE=true \
    AUTORUN_LARAVEL_MIGRATION_ISOLATION=false \
    AUTORUN_LARAVEL_STORAGE_LINK=true \
    AUTORUN_LARAVEL_CONFIG_CACHE=true \
    AUTORUN_LARAVEL_ROUTE_CACHE=true \
    AUTORUN_LARAVEL_VIEW_CACHE=true \
    PHP_OPCACHE_ENABLE=1 \
    PHP_UPLOAD_MAX_FILE_SIZE=16M \
    PHP_POST_MAX_SIZE=16M \
    PHP_MAX_EXECUTION_TIME=180 \
    NGINX_CLIENT_MAX_BODY_SIZE=16M \
    LOG_CHANNEL=stderr \
    HEALTHCHECK_PATH=/api/v1/health

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

# Probe the app (nginx + php-fpm + Laravel routing) via the health endpoint.
# Same command the base image ships, restated here so the contract is explicit.
HEALTHCHECK --start-period=60s --start-interval=3s --interval=10s --timeout=3s --retries=3 \
    CMD [ "sh", "-c", "curl --silent --show-error --fail http://localhost:${NGINX_HTTP_PORT:-8080}${HEALTHCHECK_PATH} || exit 1" ]
