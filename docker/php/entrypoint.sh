#!/bin/sh
set -e

if [ "${RUN_MIGRATIONS:-0}" = "1" ]; then
    php bin/console doctrine:migrations:migrate -n
fi

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
