#!/bin/sh
# Boot script: bring DB schema up to date, ensure seed data exists, then serve.
# Idempotent — safe to run on every container start.
set -e

echo "[entrypoint] running migrations..."
php artisan migrate --force

echo "[entrypoint] seeding (idempotent)..."
php artisan db:seed --force

echo "[entrypoint] starting server..."
exec php artisan serve --host=0.0.0.0 --port=8000
