# --- Laravel API container ---
# PHP 8.2 (Laravel 10 requirement). Alpine = small image.
FROM php:8.2-cli-alpine

# Install OS packages needed by PHP extensions
RUN apk add --no-cache libpq-dev

# Install the pdo_pgsql extension so Laravel can talk to Postgres
RUN docker-php-ext-install pdo_pgsql

# Copy Composer from its official image (multi-stage trick)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy dependency files first — Docker caches this layer so
# 'composer install' only re-runs when composer.json/lock change.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader

# Copy the rest of the app
COPY . .

# Now finish autoload (needs the full source tree)
RUN composer dump-autoload --optimize

# Make storage writable (Laravel writes logs/cache/sessions) and boot script executable
RUN chmod -R 775 storage bootstrap/cache && chmod +x docker-entrypoint.sh

EXPOSE 8000

# Migrate/seed on boot, then run built-in dev server. Fine for local dev — NOT for production.
ENTRYPOINT ["sh", "docker-entrypoint.sh"]
