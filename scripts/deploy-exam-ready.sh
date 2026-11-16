#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Deploy the exam-ready CBT portal.
#
#   sudo bash scripts/deploy-exam-ready.sh
#
# What it does:
#   1. Backs up the database, the current container config, and runtime files.
#   2. Copies uploads/, assets/img/ and logs/ out of the running container so
#      they survive the recreate.
#   3. Builds a thin image FROM the current image and copies the new code in.
#   4. Recreates veritas-cbt-web with persistent mounts and a health check.
#   5. Verifies the portal and the security blocks, and rolls back on failure.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
DB="veritas-cbt-db"
ENV_FILE="/etc/veritas-cbt/.env"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/veritas-cbt/${TS}"
SESSIONS_DIR="/var/lib/veritas-cbt/sessions"
NEW_TAG="veritas-cbt-portal:exam-${TS}"
HEALTH_URL="http://127.0.0.1:8080/student_login"

say()  { printf '\n==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
command -v docker >/dev/null || die "docker is not installed"
command -v curl   >/dev/null || die "curl is not installed"
[[ -f "${ENV_FILE}" ]] || die "missing ${ENV_FILE}"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"
docker inspect "${DB}"  >/dev/null 2>&1 || die "container ${DB} not found"

OLD_IMAGE="$(docker inspect -f '{{.Image}}' "${WEB}")"
NETWORK="$(docker inspect -f '{{range $k,$v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}' "${WEB}" | head -n1)"
[[ -n "${NETWORK}" ]] || die "could not determine the web container network"

say "Pre-flight"
printf '  web image : %s\n' "${OLD_IMAGE}"
printf '  network   : %s\n' "${NETWORK}"
printf '  backup    : %s\n' "${BACKUP_DIR}"
df -h / | awk 'NR==1 || NR==2'
AVAIL_KB="$(df --output=avail / | tail -n1 | tr -d ' ')"
[[ "${AVAIL_KB}" -gt 1048576 ]] || die "less than 1 GB free on /"
curl -fsS -o /dev/null --max-time 10 "${HEALTH_URL}" || warn "the portal is not healthy before deploy"

mkdir -p "${BACKUP_DIR}"
say "Backing up database and container configuration"
docker exec "${DB}" sh -c 'exec mysqldump --single-transaction --quick --routines --triggers -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
    > "${BACKUP_DIR}/database.sql"
gzip -f "${BACKUP_DIR}/database.sql"
docker inspect "${WEB}" > "${BACKUP_DIR}/web-inspect.json"
docker inspect "${DB}"  > "${BACKUP_DIR}/db-inspect.json"
docker tag "${OLD_IMAGE}" "veritas-cbt-portal:pre-exam-${TS}"
BASE_TAG="veritas-cbt-portal:pre-exam-${TS}"
printf '  database dump: %s\n' "${BACKUP_DIR}/database.sql.gz"

say "Copying runtime files out of the container"
mkdir -p "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs" "${SESSIONS_DIR}"
docker cp "${WEB}:/var/www/html/uploads/."    "${REPO}/uploads/"    2>/dev/null || warn "could not copy uploads"
docker cp "${WEB}:/var/www/html/assets/img/." "${REPO}/assets/img/" 2>/dev/null || warn "could not copy assets/img"
docker cp "${WEB}:/var/www/html/logs/."       "${REPO}/logs/"       2>/dev/null || warn "could not copy logs"
tar -czf "${BACKUP_DIR}/runtime-files.tar.gz" -C "${REPO}" uploads assets/img logs 2>/dev/null || true

say "Fixing ownership and permissions for the web user (uid/gid 33)"
OWNER="${SUDO_USER:-$(stat -c '%U' "${REPO}")}"
GROUP="$(id -gn "${OWNER}" 2>/dev/null || printf '%s' "${OWNER}")"
chown -R "${OWNER}:${GROUP}" "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs"
chown -R 33:33 "${SESSIONS_DIR}"
find "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs" "${SESSIONS_DIR}" -type d -exec chmod 775 {} \;
find "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs" "${SESSIONS_DIR}" -type f -exec chmod 664 {} \;
if command -v setfacl >/dev/null && \
   setfacl -R -m u:33:rwx -m d:u:33:rwx "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs" 2>/dev/null; then
    printf '  ACLs granted to uid 33\n'
else
    warn "setfacl unavailable; falling back to www-data ownership"
    chown -R 33:33 "${REPO}/uploads" "${REPO}/assets/img" "${REPO}/logs"
fi

say "Building the new image (thin layer on top of the current one)"
cat > "${BACKUP_DIR}/Dockerfile.deploy" <<EOF
FROM ${BASE_TAG}
COPY . /var/www/html/
RUN rm -rf /var/www/html/.env /var/www/html/logs /var/www/html/uploads \\
           /var/www/html/migrations /var/www/html/database /var/www/html/tests \\
           /var/www/html/tmp /var/www/html/scratch /var/www/html/tools \\
           /var/www/html/Models /var/www/html/modules /var/www/html/scripts \\
           /var/www/html/seb-client/src \\
    && rm -f /var/www/html/init_questions.php /var/www/html/quick_check.php \\
             /var/www/html/simple_check.php /var/www/html/check_exam_students.php \\
             /var/www/html/check_exeat2.php /var/www/html/check_enrollment.php \\
             /var/www/html/check_enrollment2.php /var/www/html/check_exams_table.php \\
             /var/www/html/check_exams.php /var/www/html/db_test.php \\
             /var/www/html/db_backup.php /var/www/html/load_test.php \\
             /var/www/html/simulate_student.php /var/www/html/run_migration.php \\
             /var/www/html/fix_admin_dashboard.php /var/www/html/fix_dashboard.php \\
             /var/www/html/exam_lms_export.sql /var/www/html/backup_db_pre_implementation.sql \\
    && mkdir -p /var/www/html/logs /var/www/html/uploads /var/lib/php/sessions \\
    && chown -R www-data:www-data /var/www/html /var/lib/php/sessions
EOF
docker build -f "${BACKUP_DIR}/Dockerfile.deploy" -t "${NEW_TAG}" "${REPO}"

say "Replacing the web container"
docker stop "${WEB}" >/dev/null 2>&1 || true
docker rename "${WEB}" "${WEB}-old-${TS}"
if ! docker run -d \
        --name "${WEB}" \
        --restart unless-stopped \
        -p 8080:80 \
        --network "${NETWORK}" \
        -v "${ENV_FILE}:/etc/veritas-cbt/.env:ro" \
        -v "${REPO}/uploads:/var/www/html/uploads" \
        -v "${REPO}/assets/img:/var/www/html/assets/img" \
        -v "${REPO}/logs:/var/www/html/logs" \
        -v "${SESSIONS_DIR}:/var/lib/php/sessions" \
        --health-cmd "php -r 'exit(@file_get_contents(\"http://127.0.0.1/student_login\") === false ? 1 : 0);'" \
        --health-interval 30s \
        --health-timeout 5s \
        --health-retries 3 \
        --health-start-period 20s \
        "${NEW_TAG}" >/dev/null; then
    warn "docker run failed; restoring the previous container"
    docker rm -f "${WEB}" >/dev/null 2>&1 || true
    docker rename "${WEB}-old-${TS}" "${WEB}"
    docker start "${WEB}" >/dev/null
    die "deploy failed and was rolled back"
fi

say "Waiting for the portal to answer"
OK=0
for _ in $(seq 1 45); do
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "${HEALTH_URL}" || true)"
    if [[ "${CODE}" == "200" ]]; then OK=1; break; fi
    sleep 2
done

if [[ "${OK}" -ne 1 ]]; then
    warn "the new container did not become healthy; rolling back"
    docker logs --tail 60 "${WEB}" || true
    docker rm -f "${WEB}" >/dev/null 2>&1 || true
    docker rename "${WEB}-old-${TS}" "${WEB}"
    docker start "${WEB}" >/dev/null
    die "deploy failed and was rolled back"
fi

say "Post-deploy verification"
check_code() {
    local path="$1" want="$2" label="$3"
    local code
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "http://127.0.0.1:8080${path}" || echo ERR)"
    if [[ ",${want}," == *",${code},"* ]]; then
        printf '  OK    %-58s %s\n' "${label}" "${code}"
    else
        printf '  WARN  %-58s %s (wanted %s)\n' "${label}" "${code}" "${want}"
    fi
}

check_code "/"                                            200 "landing page"
check_code "/student_login"                               200 "student login"
check_code "/login"                                       200 "staff/admin login"
check_code "/.env"                                        "403,404" ".env blocked"
check_code "/migrations/full_export_20261110_215555.sql"  "403,404" "database dump blocked"
check_code "/logs/php_errors.log"                         "403,404" "error log blocked"
check_code "/database/export.php"                         "403,404" "database tool blocked"
check_code "/modules/secure_gate/index.php"               "403,404" "secure_gate module blocked"
check_code "/seb-client/README.md"                        200 "SEB setup guide still reachable"

if docker exec "${WEB}" test -e /var/www/html/.env; then
    warn ".env is still present in the web root inside the container"
else
    printf '  OK    %-58s\n' ".env is outside the web root"
fi

if docker exec "${WEB}" php -r 'require "/var/www/html/config.php"; exit(VERITAS_ENV_LOADED ? 0 : 1);' >/dev/null 2>&1; then
    printf '  OK    %-58s\n' "application loaded the env file"
else
    warn "the application could not load /etc/veritas-cbt/.env"
fi

say "Done"
cat <<EOF
New image : ${NEW_TAG}
Old image : veritas-cbt-portal:pre-exam-${TS}
Backup    : ${BACKUP_DIR}
Old container (kept for rollback): ${WEB}-old-${TS}

Rollback:
  docker rm -f ${WEB}
  docker rename ${WEB}-old-${TS} ${WEB}
  docker start ${WEB}

Remove the old container once you are happy:
  docker rm ${WEB}-old-${TS}
EOF
