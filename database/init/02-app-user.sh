#!/bin/bash
# Creates the least-privilege MySQL account used by the PHP app.
# Runs once, on first start of the db container (empty data volume).
set -euo pipefail

# Only allow a safe character set so values can't break out of the SQL string.
for v in "$APP_DB_USER" "$APP_DB_PASSWORD" "$MYSQL_DATABASE"; do
    if [[ ! "$v" =~ ^[A-Za-z0-9_-]+$ ]]; then
        echo "APP_DB_USER / APP_DB_PASSWORD / MYSQL_DATABASE may only contain [A-Za-z0-9_-]" >&2
        exit 1
    fi
done

mysql -uroot -p"$MYSQL_ROOT_PASSWORD" <<SQL
CREATE USER IF NOT EXISTS '${APP_DB_USER}'@'%' IDENTIFIED BY '${APP_DB_PASSWORD}';
-- No DROP/ALTER/CREATE/FILE: an SQL injection can't destroy the schema or read files.
GRANT SELECT, INSERT ON \`${MYSQL_DATABASE}\`.* TO '${APP_DB_USER}'@'%';
-- UPDATE/DELETE only per table; audit_logs is left append-only for the app.
GRANT UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.users      TO '${APP_DB_USER}'@'%';
GRANT UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.pins       TO '${APP_DB_USER}'@'%';
GRANT UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.boards     TO '${APP_DB_USER}'@'%';
GRANT UPDATE, DELETE ON \`${MYSQL_DATABASE}\`.board_pins TO '${APP_DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL
