#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Report (and optionally remove) duplicate questions left behind by
# init_questions.php. Always takes a questions-table backup first.
#
#   sudo bash scripts/cleanup-questions.sh            # report only
#   sudo bash scripts/cleanup-questions.sh --apply    # delete duplicates
# ---------------------------------------------------------------------------
set -Eeuo pipefail

DB="veritas-cbt-db"
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="/var/backups/veritas-cbt"
TS="$(date +%Y%m%d-%H%M%S)"

[[ "${EUID}" -eq 0 ]] || { echo "run this script with sudo" >&2; exit 1; }
mkdir -p "${BACKUP_DIR}"

echo "==> Backing up the questions table"
docker exec "${DB}" sh -c 'exec mysqldump --single-transaction --quick -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" questions' \
    > "${BACKUP_DIR}/questions-${TS}.sql"
gzip -f "${BACKUP_DIR}/questions-${TS}.sql"
echo "    ${BACKUP_DIR}/questions-${TS}.sql.gz"

echo
echo "==> Duplicate rows with no exam_id"
docker exec "${DB}" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -t -e "
SELECT text, COUNT(*) AS copies
FROM questions
WHERE exam_id IS NULL
GROUP BY text
HAVING COUNT(*) > 1
ORDER BY copies DESC, text;"'

if [[ "${1:-}" != "--apply" ]]; then
    echo
    echo "Report only. Re-run with --apply to delete all but one copy of each text."
    exit 0
fi

echo
echo "==> Deleting duplicates"
docker exec -i "${DB}" sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    < "${HERE}/cleanup-questions.sql"

echo
echo "==> Remaining duplicate rows"
docker exec "${DB}" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -t -e "
SELECT text, COUNT(*) AS copies
FROM questions
WHERE exam_id IS NULL
GROUP BY text
HAVING COUNT(*) > 1
ORDER BY copies DESC, text;"'

echo
echo "Done. Backup kept at ${BACKUP_DIR}/questions-${TS}.sql.gz"
