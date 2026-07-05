#!/bin/sh
set -e

composer install --no-interaction
npm install

exec docker-php-entrypoint "$@"
