#!/bin/sh
set -e
cd /app

if [ "${DB_CONNECTION:-sqlite}" = "pgsql" ]; then
    echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}…"
    until php -r 'new PDO("pgsql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 5432).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD"));' >/dev/null 2>&1; do
        sleep 2
    done
elif [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    touch "${DB_DATABASE:-/app/database/database.sqlite}"
fi

php artisan migrate --force

if [ "${SEED_ON_START:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
