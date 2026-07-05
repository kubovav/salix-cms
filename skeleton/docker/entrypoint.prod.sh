#!/bin/sh
set -e

if [ -z "$APP_SECRET" ]; then
    echo "ERROR: APP_SECRET must be set (e.g. openssl rand -hex 32)" >&2
    exit 1
fi

if [ "$RUN_MIGRATIONS" = "1" ]; then
    echo "Waiting for the database..."
    tries=0
    until php bin/console dbal:run-sql "SELECT 1" >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -ge 30 ]; then
            echo "ERROR: database is not reachable after 60s" >&2
            exit 1
        fi
        sleep 2
    done
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

exec docker-php-entrypoint "$@"
