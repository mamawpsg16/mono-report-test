#!/bin/sh
# Boot script: bring DB schema up to date, ensure seed data exists, then serve.
# Idempotent — safe to run on every container start.
set -e

echo "[entrypoint] running migrations..."
php artisan migrate --force

echo "[entrypoint] seeding (idempotent)..."
php artisan db:seed --force

# storage/ is bind-mounted, so Spatie's permission cache file
# (storage/framework/cache/data/...) survives every container crash and
# rebuild. It's only flushed on Role writes (RoleSeeder), not User writes
# (UserRoleSeeder) -- so a boot that crashes mid-seed can leave a stale but
# still-valid (24h TTL) cache serving old permissions to real requests even
# though the DB is already correct. Clear it every boot to close that gap.
echo "[entrypoint] clearing cache..."
php artisan cache:clear

echo "[entrypoint] starting server..."
# `php artisan serve` spawns the actual PHP built-in server as a *child*
# process and only passes it a hardcoded handful of env vars (APP_ENV,
# LARAVEL_SAIL, ...) -- not the container's real environment. That silently
# dropped APP_KEY/SESSION_DRIVER/etc. for every served request while `artisan`
# CLI commands (which don't spawn a child) saw them fine. Running the built-in
# server directly makes it PID 1 with no re-exec, so it inherits the
# container's actual environment.
exec php -S 0.0.0.0:8000 -t public public/index.php
