#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Apply the lock-only proctoring policy to the RUNNING container.
#
#   sudo bash scripts/apply-lock-only.sh
#
# What it does (no restart, no rebuild):
#   - Backs up the live copies of the changed files.
#   - Copies the new code into veritas-cbt-web.
#   - Fixes ownership and lints the PHP.
#   - Verifies the new behaviour is present and the portal still answers.
#
# Policy: at 5 strikes the attempt is LOCKED, never auto-submitted. The
# invigilator unlocks it from Live Proctor and the student resumes with the
# same answers and remaining time.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/veritas-cbt/lock-only-${TS}"
HEALTH_URL="http://127.0.0.1:8080/student_login"

FILES=(
  "student_objective_exam.php"
  "assets/js/exam_player.js"
  "admin_exams.php"
  "admin_objective_exams.php"
  "admin_portal_settings.php"
  "admin_live_proctor.php"
)

say()  { printf '\n==> %s\n' "$*"; }
warn() { printf 'WARNING: %s\n' "$*" >&2; }
die()  { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
command -v docker >/dev/null || die "docker is not installed"
command -v curl   >/dev/null || die "curl is not installed"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"

say "Backing up live files to ${BACKUP_DIR}"
mkdir -p "${BACKUP_DIR}"
for f in "${FILES[@]}"; do
  mkdir -p "${BACKUP_DIR}/$(dirname "$f")"
  if docker cp "${WEB}:/var/www/html/${f}" "${BACKUP_DIR}/${f}" 2>/dev/null; then
    printf '  saved   %s\n' "$f"
  else
    warn "could not back up ${f} (continuing)"
  fi
done

say "Copying new code into ${WEB}"
i=0
for f in "${FILES[@]}"; do
  [[ -f "${REPO}/${f}" ]] || die "missing source file ${REPO}/${f}"
  i=$((i+1))
  tmp="/tmp/veritas-lock-${i}-$$"
  # Stage in /tmp then rename, so a live request never reads a partial file.
  docker cp "${REPO}/${f}" "${WEB}:${tmp}"
  docker exec "${WEB}" sh -c "mv -f '${tmp}' '/var/www/html/${f}'"
  printf '  copied  %s\n' "$f"
done

say "Fixing ownership"
paths=""
for f in "${FILES[@]}"; do paths="${paths} /var/www/html/${f}"; done
docker exec "${WEB}" sh -c "chown www-data:www-data ${paths}"

say "Linting PHP"
for f in "${FILES[@]}"; do
  case "$f" in
    *.php) docker exec "${WEB}" php -l "/var/www/html/${f}" >/dev/null \
             || die "syntax error in ${f}"; printf '  OK      %s\n' "$f" ;;
  esac
done

say "Verifying the lock-only policy is live"
check() {
  local label="$1" pattern="$2" file="$3"
  if docker exec "${WEB}" grep -qF -- "${pattern}" "/var/www/html/${file}"; then
    printf '  OK      %s\n' "$label"
  else
    die "verification failed: ${label}"
  fi
}
check "auto-submit disabled"          "'autoSubmitStrikes' => 0,"                 student_objective_exam.php
check "JS auto-submit path removed"   "never auto-submits"                        assets/js/exam_player.js
check "admin label: 5-Strike Lock"    "5-Strike Lock"                             admin_exams.php
check "admin label: invigilator reset" "5-Strike Lock (invigilator reset)"        admin_objective_exams.php
check "settings copy: locks at 5"     "Locks the assessment at the 5-strike"      admin_portal_settings.php
check "unlock restarts the clock"     "last_tick_at = NOW()"                      admin_live_proctor.php
if docker exec "${WEB}" grep -rqF "5-Strike Auto-Submit" /var/www/html/admin_exams.php /var/www/html/admin_objective_exams.php 2>/dev/null; then
  die "old '5-Strike Auto-Submit' label still present"
fi

say "Health check"
code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${HEALTH_URL}" || true)"
[[ "${code}" == "200" ]] || die "portal returned HTTP ${code} at ${HEALTH_URL}"
printf '  OK      %s -> HTTP %s\n' "${HEALTH_URL}" "${code}"

say "Done"
printf 'Lock-only policy is live in %s.\n' "${WEB}"
printf 'Rollback if needed:\n'
for f in "${FILES[@]}"; do
  printf '  sudo docker cp "%s/%s" "%s:/var/www/html/%s"\n' "${BACKUP_DIR}" "$f" "${WEB}" "$f"
done
