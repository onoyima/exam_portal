#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Deploy the server-authoritative (wall-clock) exam timing fix.
#
#   sudo bash scripts/apply-timing-fix.sh            # refuses during an exam
#   sudo bash scripts/apply-timing-fix.sh --force    # deploy anyway
#
# What it does
#   1. Backs up the live files to /var/backups/veritas-cbt/timing-fix-<ts>
#   2. Stages the new files inside the container, lints them, then moves them
#      into place atomically (no restart, no container recreate)
#   3. Runs the schema v8 migration (excused_seconds, locked_at,
#      last_question_index) and corrects stale time_used counters upward
#   4. Runs the disposable end-to-end timing/lock/submit test
#   5. Checks the portal still answers
#
# The live exam is never modified by the test; it creates and deletes its own
# throwaway exam, student and attempts.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
BASE="http://127.0.0.1:8080"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="/var/backups/veritas-cbt/timing-fix-${TS}"
STAGE="/tmp/.timing-fix-${TS}"
TEST="/tmp/verify_lock_unlock_${TS}.php"
FORCE=0
[[ "${1:-}" == "--force" ]] && FORCE=1

FILES=(
    includes/question_engine.php
    config.php
    auth.php
    exam_logic.php
    objective_exam_api.php
    proctor_ping.php
    admin_live_proctor.php
    student_objective_exam.php
    assets/js/exam_player.js
    assets/css/exam_player.css
)

die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
pass() { printf '  PASS    %s\n' "$*"; }

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"
command -v curl >/dev/null || die "curl is not installed"

# ---------------------------------------------------------------------------
# Preflight: do not recreate/replace files while a real attempt is in progress.
# ---------------------------------------------------------------------------
PREFLIGHT="$(docker exec -u www-data "${WEB}" php -r '
require "/var/www/html/config.php";
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("SET time_zone = " . $db->quote(date("P")));
$sql = "SELECT e.name, COUNT(*) AS c
        FROM objective_attempts oa
        JOIN exams e ON e.id = oa.exam_id
        WHERE oa.finished_at IS NULL
          AND e.is_active = 1
          AND (e.end_time IS NULL OR NOW() <= DATE_ADD(e.end_time, INTERVAL 2 HOUR))
          AND oa.last_active > DATE_SUB(NOW(), INTERVAL 2 HOUR)
        GROUP BY e.id, e.name";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$n = 0;
foreach ($rows as $r) { $n += (int) $r["c"]; }
// The marker makes the count impossible to confuse with a PHP warning above it.
echo "OPEN=" . $n . "\n";
foreach ($rows as $r) { echo "  " . $r["name"] . " (" . $r["c"] . " open)\n"; }
' 2>&1)" || die "could not inspect open attempts: ${PREFLIGHT}"

OPEN="$(grep -m1 '^OPEN=' <<<"${PREFLIGHT}" | cut -d= -f2 | tr -dc '0-9')"
[[ -n "${OPEN}" ]] || die "could not determine open attempts: ${PREFLIGHT}"
if [[ "${OPEN}" -gt 0 && "${FORCE}" -ne 1 ]]; then
    printf 'Refusing to deploy: %s attempt(s) are still open.\n' "${OPEN}" >&2
    printf '%s\n' "${PREFLIGHT}" | tail -n +2 >&2
    printf 'Wait for them to finish, or re-run with --force.\n' >&2
    exit 1
fi
if [[ "${OPEN}" -gt 0 ]]; then
    printf 'WARNING: deploying with %s open attempt(s) because --force was given.\n' "${OPEN}" >&2
fi

# ---------------------------------------------------------------------------
# Back up and stage
# ---------------------------------------------------------------------------
echo "Backing up ${#FILES[@]} live files to ${BACKUP}"
for f in "${FILES[@]}"; do
    mkdir -p "${BACKUP}/$(dirname "${f}")"
    docker cp "${WEB}:/var/www/html/${f}" "${BACKUP}/${f}" 2>/dev/null \
        || die "could not back up the live ${f}"
done
echo "  backup  OK"

docker exec "${WEB}" mkdir -p "${STAGE}"
for f in "${FILES[@]}"; do
    docker exec "${WEB}" mkdir -p "${STAGE}/$(dirname "${f}")"
    docker cp "${REPO}/${f}" "${WEB}:${STAGE}/${f}" >/dev/null \
        || die "could not stage ${f}"
done

echo
echo "Linting the staged files"
for f in "${FILES[@]}"; do
    case "${f}" in
        *.php)
            OUT="$(docker exec "${WEB}" php -l "${STAGE}/${f}" 2>&1)" \
                || die "lint failed for ${f}: ${OUT}"
            ;;
    esac
done
pass "all staged PHP files lint clean"

# ---------------------------------------------------------------------------
# Schema v8 migration — BEFORE any file is replaced.
#
# The new columns have defaults, so the currently-running (old) code keeps
# working while they exist. Doing it in this order means a live request can
# never hit the new code before its columns are there.
# ---------------------------------------------------------------------------
echo
echo "Running the schema v8 migration"
# The engine is required from inside the real includes/ directory so that its
# file-scope require of center_sync.php resolves; a copy in /tmp would not.
MIGFILE="/var/www/html/includes/.qe-migrate-${TS}.php"
docker cp "${REPO}/includes/question_engine.php" "${WEB}:${MIGFILE}" >/dev/null \
    || die "could not stage the migration engine"
set +e
MIG="$(docker exec -e MIGFILE="${MIGFILE}" -u www-data "${WEB}" php -r '
require "/var/www/html/config.php";
require getenv("MIGFILE");
$db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
    DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec("SET time_zone = " . $db->quote(date("P")));
qe_run_migrations($db);
$cols = [];
foreach ($db->query("SHOW COLUMNS FROM objective_attempts")->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $cols[$c["Field"]] = true;
}
foreach (["excused_seconds", "locked_at", "last_question_index"] as $need) {
    if (!isset($cols[$need])) { fwrite(STDERR, "missing column: $need\n"); exit(2); }
}
$flag = "/var/www/html/logs/.qe_schema_v" . QE_SCHEMA_VERSION;
if (!is_dir(dirname($flag))) { @mkdir(dirname($flag), 0755, true); }
if (@file_put_contents($flag, date("c")) === false) { fwrite(STDERR, "cannot write schema flag\n"); exit(3); }
echo "schema=" . QE_SCHEMA_VERSION . " columns=ok\n";
' 2>&1)"
MIG_RC=$?
set -e
docker exec "${WEB}" rm -f "${MIGFILE}" >/dev/null 2>&1 || true
[[ ${MIG_RC} -eq 0 ]] || die "schema migration failed: ${MIG}"
pass "${MIG}"

# ---------------------------------------------------------------------------
# Move into place (same filesystem: a rename, not a partial write)
# ---------------------------------------------------------------------------
echo
echo "Installing the new files"
for f in "${FILES[@]}"; do
    docker exec "${WEB}" sh -c \
        "mkdir -p '/var/www/html/$(dirname "${f}")' && mv '${STAGE}/${f}' '/var/www/html/${f}'" \
        || die "could not install ${f}"
done
docker exec "${WEB}" chown www-data:www-data $(printf '/var/www/html/%q ' "${FILES[@]}") 2>/dev/null || true
docker exec "${WEB}" rm -rf "${STAGE}"
pass "files installed without restarting the container"

# ---------------------------------------------------------------------------
# End-to-end test (creates and deletes its own data)
# ---------------------------------------------------------------------------
echo
echo "Running the timing / lock / submit test"
docker cp "${REPO}/scripts/verify_lock_unlock.php" "${WEB}:${TEST}" >/dev/null
docker exec -u www-data "${WEB}" php "${TEST}" || die "verification test failed"
docker exec "${WEB}" rm -f "${TEST}" >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# Portal health
# ---------------------------------------------------------------------------
echo
echo "Portal health"
CODE="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE}/student_login" || true)"
[[ "${CODE}" == "200" ]] || die "portal returned HTTP ${CODE} after the change"
pass "${BASE}/student_login -> HTTP 200"

cat <<EOF

Done. Exam time is now wall-clock based and cannot roll backwards.

Rollback (restore every file from the backup):
$(for f in "${FILES[@]}"; do printf '  sudo docker cp "%s/%s" "%s:/var/www/html/%s"\n' "${BACKUP}" "${f}" "${WEB}" "${f}"; done)

Notes:
  - Locked time is still excused (locked_at / excused_seconds).
  - Sessions now last at least 4 hours (gc_maxlifetime 8 hours).
  - Submission is retried by the browser until the server confirms it.
EOF
