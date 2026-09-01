#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

# A Windows bind mount may contain Laravel's host-generated package cache.
# Clear it before Composer runs so the container never loads stale providers.
rm -f bootstrap/cache/*.php

# Keep the named vendor volume synchronized with composer.lock. This is quick
# after the first run and repairs volumes created by an older image.
composer install --no-interaction --no-progress --prefer-dist

if ! grep -Eq '^APP_KEY=.+$' .env; then
    php artisan key:generate --force --no-ansi
fi

mkdir -p \
    bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

chmod -R ug+rwX bootstrap/cache storage

php artisan config:clear --no-ansi

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force --no-interaction
fi

if [ "${SEED_DATABASE:-false}" = "true" ]; then
    if php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); exit(App\Models\User::query()->exists() ? 0 : 1);'; then
        echo "ElectoFlow database already contains users; skipping seed data."
    else
        php artisan db:seed --force --no-interaction
    fi
fi

exec "$@"
