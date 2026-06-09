#!/usr/bin/env bash
# Nightly MySQL backups for the droplet (all app databases, not just thepiste).
# Installed via the thepiste repo; cron runs it daily. Keeps 14 days locally.
# Restore: gunzip < FILE.sql.gz | mysql DBNAME
set -euo pipefail

DEST=/var/backups/mysql
KEEP_DAYS=14
STAMP=$(date +%Y%m%d-%H%M)

mkdir -p "$DEST"

DBS=$(mysql -N -e "SHOW DATABASES" | grep -vE '^(information_schema|performance_schema|mysql|sys)$')

for DB in $DBS; do
    FILE="$DEST/${DB}-${STAMP}.sql.gz"
    mysqldump --single-transaction --quick --routines --triggers "$DB" | gzip > "$FILE"
    gzip -t "$FILE"   # integrity check; non-zero exit fails the run
done

# rotate
find "$DEST" -name '*.sql.gz' -mtime +"$KEEP_DAYS" -delete

echo "$(date -Is) backup ok: $(ls -1 "$DEST" | wc -l) files, latest: ${STAMP}"
