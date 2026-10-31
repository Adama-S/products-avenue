#!/bin/sh
# Prepares the mounted project, then hands over to the official PHP entrypoint (Apache).
set -e
cd /var/www/html

# On Linux hosts, give www-data the uid/gid of the project owner, so files created
# in the container (vendor/, var/, composer.lock) stay editable on the host.
HOST_UID=$(stat -c %u .)
HOST_GID=$(stat -c %g .)
if [ "$HOST_UID" != "0" ] && [ "$(id -u www-data)" != "$HOST_UID" ]; then
    groupmod -o -g "$HOST_GID" www-data
    usermod -o -u "$HOST_UID" www-data
fi

run_as_owner() {
    if [ "$HOST_UID" = "0" ]; then
        sh -c "$1"
    else
        su -s /bin/sh www-data -c "$1"
    fi
}

# var/ holds the cache and logs: Apache (www-data) must be able to write there.
mkdir -p var/cache var/log
chown -R www-data:www-data var

# A fresh clone has no vendor/: install it, so `docker compose up` is the only step.
if [ ! -f vendor/autoload.php ]; then
    run_as_owner "composer install --no-interaction --prefer-dist --no-progress"
fi

exec docker-php-entrypoint "$@"
