#!/bin/bash
# Runs once, on the first start with an empty volume.
# The image already created ${MYSQL_DATABASE} (catalog) and ${MYSQL_USER}.
set -euo pipefail

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS catalog_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON catalog_test.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON blissim.* TO '${MYSQL_USER}'@'%';
SQL
