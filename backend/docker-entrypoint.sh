#!/bin/sh
# Boot script: bring DB schema up to date, ensure seed data exists, then serve.
# Idempotent — safe to run on every container start.
set -e

echo "[entrypoint] running migrations..."
php artisan migrate --force

echo "[entrypoint] seeding (idempotent)..."
php artisan db:seed --force

echo "[entrypoint] starting server..."
# `php artisan serve` spawns the actual PHP built-in server as a *child*
# process and only passes it a hardcoded handful of env vars (APP_ENV,
# LARAVEL_SAIL, ...) -- not the container's real environment. That silently
# dropped APP_KEY/SESSION_DRIVER/etc. for every served request while `artisan`
# CLI commands (which don't spawn a child) saw them fine. Running the built-in
# server directly makes it PID 1 with no re-exec, so it inherits the
# container's actual environment.
exec php -S 0.0.0.0:8000 -t public public/index.php
