#!/bin/sh
set -e

composer install --no-interaction
npm install
npm run build

exec docker-php-entrypoint "$@"
