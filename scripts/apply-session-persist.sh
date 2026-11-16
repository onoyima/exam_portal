#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Make PHP sessions use the mounted, persistent directory — everywhere.
#
#   sudo bash scripts/apply-session-persist.sh
#
# The web entry points used to call session_start() BEFORE config.php ran, so
# the session.save_path override was too late and sessions landed in /tmp.
# This patches the live files in place (only inserting the config load, no
# restart) and then proves, over real HTTP, that:
#
#   1. a fresh request writes its session file to /var/lib/php/sessions
#   2. pages that require auth.php read that session (dashboard stays logged in)
#   3. pages that start the session directly (index.php) read the same session
#   4. student_logout.php destroys that same session
#
# Existing logins in /tmp end once; new sessions survive container recreates.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/veritas-cbt/session-persist-${TS}"
BASE="http://127.0.0.1:8080"
SESS_DIR="/var/lib/php/sessions"
PATCHER="/tmp/patch-session-boot-$$.php"

FILES=(
    auth.php
    index.php
    services.php
    logout.php
    system_chat.php
    all_students_score.php
    chat_backend.php
    chat_status.php
    student_logout.php
    search_students.php
)

die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
pass() { printf '  PASS    %s\n' "$*"; }

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"
command -v curl >/dev/null || die "curl is not installed"

mkdir -p "${BACKUP_DIR}"
JAR="$(mktemp)"
SID_FAKE="$(php -r 'echo bin2hex(random_bytes(16));')"
cleanup() {
    rm -f "${JAR}"
    docker exec "${WEB}" rm -f "${SESS_DIR}/sess_${SID_FAKE}" "${PATCHER}" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker cp "${REPO}/scripts/patch-session-boot.php" "${WEB}:${PATCHER}"
PATHS=()
for f in "${FILES[@]}"; do
    PATHS+=("/var/www/html/${f}")
done

echo "Checking which files still need the patch"
DRY="$(docker exec "${WEB}" php "${PATCHER}" --dry-run "${PATHS[@]}")"
printf '%s\n' "${DRY}"

ROLLBACK_DIR=""
if grep -q '^patched: ' <<<"${DRY}"; then
    echo
    echo "Backing up ${#FILES[@]} live files to ${BACKUP_DIR}"
    for f in "${FILES[@]}"; do
        docker cp "${WEB}:/var/www/html/${f}" "${BACKUP_DIR}/${f}" 2>/dev/null \
            || die "could not back up the live ${f}"
    done
    echo "  backup  OK"

    echo
    echo "Patching the live files (config.php load before session_start)"
    docker exec "${WEB}" php "${PATCHER}" "${PATHS[@]}"
    docker exec "${WEB}" chown www-data:www-data "${PATHS[@]}"
    ROLLBACK_DIR="${BACKUP_DIR}"
else
    echo "  all files already patched; nothing to change"
fi

echo
echo "Linting the patched files"
for f in "${FILES[@]}"; do
    docker exec "${WEB}" php -l "/var/www/html/${f}" >/dev/null || die "syntax error in ${f}"
done
pass "all ${#FILES[@]} files lint clean"

echo
echo "1. Fresh request must create its session in ${SESS_DIR}"
curl -s -c "${JAR}" -o /dev/null --max-time 10 "${BASE}/student_login" || die "portal did not answer"
SID="$(awk '/PHPSESSID/ {print $7}' "${JAR}" | tail -n1)"
[[ -n "${SID}" ]] || die "portal did not set a PHPSESSID cookie"
docker exec "${WEB}" test -f "${SESS_DIR}/sess_${SID}" || die "session file not in ${SESS_DIR}; fix did not take effect"
pass "new session written to ${SESS_DIR} (sess_${SID})"
docker exec "${WEB}" rm -f "${SESS_DIR}/sess_${SID}" >/dev/null 2>&1 || true

echo
echo "2. A session in ${SESS_DIR} must be seen by auth.php pages"
docker exec -u www-data "${WEB}" sh -c \
    "printf 'role|s:7:\"student\";student_id|i:999999;student_name|s:12:\"Session Test\";matric_no|s:8:\"ZZ/TEST1\";' > '${SESS_DIR}/sess_${SID_FAKE}' && chmod 600 '${SESS_DIR}/sess_${SID_FAKE}'"
# GET /proctor_ping (extensionless: .php 301-redirects) needs a student
# session but has no side effects when there is no attempt: it answers
# {"status":"NO_ATTEMPT"}. A 302 would mean auth.php did not see the session.
BODY="$(curl -s -b "PHPSESSID=${SID_FAKE}" --max-time 10 "${BASE}/proctor_ping" || true)"
[[ "${BODY}" == *'"NO_ATTEMPT"'* ]] || die "proctor_ping did not see the session (got: ${BODY:0:200})"
pass "proctor_ping -> NO_ATTEMPT (session honoured)"

echo
echo "3. index.php must see the same session (it starts the session itself)"
LOC="$(curl -s -b "PHPSESSID=${SID_FAKE}" -o /dev/null -w '%{redirect_url}' --max-time 10 "${BASE}/" || true)"
[[ "${LOC}" == *student_dashboard* ]] || die "index.php did not redirect the session to the dashboard (got '${LOC}')"
pass "index.php -> ${LOC}"

echo
echo "4. student_logout must destroy that same session"
LOC="$(curl -s -b "PHPSESSID=${SID_FAKE}" -o /dev/null -w '%{redirect_url}' --max-time 10 "${BASE}/student_logout" || true)"
[[ "${LOC}" == *student_login* ]] || die "student_logout did not redirect to student_login (got '${LOC}')"
docker exec "${WEB}" test ! -f "${SESS_DIR}/sess_${SID_FAKE}" || die "session file still exists after logout"
pass "student_logout -> ${LOC}, session file removed"

echo
echo "5. Portal health"
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE}/student_login" || true)"
[[ "${CODE}" == "200" ]] || die "portal returned HTTP ${CODE} after the change"
pass "${BASE}/student_login -> HTTP ${CODE}"

printf '\nDone. Sessions now live in %s and survive container recreates.\n' "${SESS_DIR}"
if [[ -n "${ROLLBACK_DIR}" ]]; then
    printf 'Existing logins were signed out once; log in again.\n\n'
    printf 'Rollback:\n'
    for f in "${FILES[@]}"; do
        printf '  sudo docker cp "%s/%s" "%s:/var/www/html/%s"\n' "${ROLLBACK_DIR}" "${f}" "${WEB}" "${f}"
    done
else
    rmdir "${BACKUP_DIR}" 2>/dev/null || true
    printf 'No files were changed on this run.\n'
fi
