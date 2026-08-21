#!/bin/sh
set -e

mkdir -p var/log

# Idempotent: safe to run on every container start (e.g. one-shot cron runs).
php bin/console doctrine:migrations:migrate -n --allow-no-migration --no-interaction

exec "$@"
