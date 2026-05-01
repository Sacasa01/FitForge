#!/bin/sh
set -e

cd /var/www/app

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec /usr/bin/supervisord -c /etc/supervisord.conf
