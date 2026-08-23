#!/bin/bash
#
# Custom Laravel start script for Railpack PHP.
#
# Why this exists:
#   Railpack's default start-container.sh runs `php artisan optimize` which
#   calls `config:cache`, `event:cache`, `route:cache`, `view:cache`. Those
#   bake the build-time env values into PHP files that get shipped inside
#   the image. The build runs with .env.example values (APP_ENV=local,
#   DB_CONNECTION=sqlite), so the shipped cached config points at SQLite
#   and APP_DEBUG=true. At runtime on Railway, the real env vars are
#   injected (APP_ENV=production, DB_CONNECTION=pgsql), but Laravel reads
#   the BAKED config and ignores them. The healthcheck then fails because
#   the app tries to open a SQLite file that doesn't exist on disk.
#
# What we do instead:
#   1. Skip the cached configs (config:clear, route:clear, view:clear)
#      so runtime env vars are read on every request.
#   2. Run migrations against the REAL database (the env-driven one).
#   3. Create the storage symlink for public/uploads.
#   4. Hand off to Caddy/FrankenPHP via the standard docker-php-entrypoint.
#
# We do NOT optimize at runtime because the MVP is small and the cache
# trade-off (no live env var changes) is worse than the per-request
# config-load cost (~5-10ms with OPcache).

set -e

echo "Running migrations..."
php artisan migrate --force --no-interaction

echo "Linking storage..."
php artisan storage:link || true

echo "Clearing cached configs..."
php artisan config:clear
php artisan event:clear
php artisan route:clear
php artisan view:clear

echo "Starting Laravel server..."
exec docker-php-entrypoint --config /Caddyfile --adapter caddyfile 2>&1