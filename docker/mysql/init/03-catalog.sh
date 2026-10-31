#!/bin/bash
# Runs once, on the first start with an empty volume, after 02-blissim.sql.
# Loads the application schema into catalog and catalog_test, once database/catalog.sql exists (US-30).
# After a schema change: `docker compose down -v && docker compose up -d` (this deletes the data).
set -euo pipefail

if [ -f /schema/catalog.sql ]; then
    for database in "${MYSQL_DATABASE}" catalog_test; do
        mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" "${database}" < /schema/catalog.sql
    done
fi
