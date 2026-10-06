#!/bin/sh
set -eu

if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

mkdir -p /var/lib/inventory
touch /var/lib/inventory/database.sqlite
php artisan migrate --seed --no-interaction
# Preserve Docker environment variables in the development server process.
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
