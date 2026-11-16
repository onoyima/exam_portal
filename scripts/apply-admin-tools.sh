#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Deploy the admin backup/export page and the secret answer-override page,
# plus the responsive landing page and the new sidebar link.
#
#   sudo bash scripts/apply-admin-tools.sh
#
# Only these files are touched:
#
#   index.php                    responsive navbar/hero/step cards
#   admin_sidebar.php            "Backup & Export" link + database icon
#   admin_backup.php             NEW  backup / export UI
#   admin_answer_override.php    NEW  secret answer editor (not linked)
#   includes/db_export.php       NEW  PDO streaming SQL helpers
#
# The new files are staged, syntax-checked inside the container, and only then
# moved into place. Every replaced file is backed up first, and the exact
# rollback commands are printed at the end.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="/var/backups/veritas-cbt/admin-tools-${TS}"
STAGE="/var/www/html/.deploy-admin-${TS}"
BASE="http://127.0.0.1:8080"

FILES=(
    index.php
    admin_sidebar.php
    admin_backup.php
    admin_answer_override.php
    includes/db_export.php
)

die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }
pass() { printf '  PASS    %s\n' "$*"; }
fail() { printf '  FAIL    %s\n' "$*" >&2; FAILED=1; }

FAILED=0

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"
command -v curl >/dev/null || die "curl is not installed"

for f in "${FILES[@]}"; do
    [[ -f "${REPO}/${f}" ]] || die "missing source file ${f}"
done

echo "Deploying admin tools to ${WEB}"
echo "  repo      : ${REPO}"
echo "  backup    : ${BACKUP_DIR}"
echo "  staging   : ${STAGE}"
echo

# ── 1. Back up whatever currently exists ───────────────────────────────────
mkdir -p "${BACKUP_DIR}"
EXISTED=()
NEW=()
echo "Backing up live files"
for f in "${FILES[@]}"; do
    if docker exec "${WEB}" test -f "/var/www/html/${f}"; then
        mkdir -p "${BACKUP_DIR}/$(dirname "${f}")"
        docker cp "${WEB}:/var/www/html/${f}" "${BACKUP_DIR}/${f}" >/dev/null \
            || die "could not back up ${f}"
        EXISTED+=("${f}")
        echo "  saved   ${f}"
    else
        NEW+=("${f}")
        echo "  new     ${f} (nothing to back up)"
    fi
done
echo

# ── 2. Stage and syntax-check ──────────────────────────────────────────────
echo "Staging files"
docker exec "${WEB}" mkdir -p "${STAGE}/includes"
for f in "${FILES[@]}"; do
    docker cp "${REPO}/${f}" "${WEB}:${STAGE}/${f}" >/dev/null \
        || die "could not copy ${f} into the container"
    docker exec "${WEB}" php -l "${STAGE}/${f}" >/dev/null \
        || die "syntax check failed for ${f}"
    echo "  lint OK ${f}"
done
echo

# ── 3. Move into place (same filesystem: each move is atomic) ──────────────
echo "Installing files"
for f in "${FILES[@]}"; do
    docker exec "${WEB}" sh -c "chmod 644 '${STAGE}/${f}' && mv -f '${STAGE}/${f}' '/var/www/html/${f}'" \
        || die "could not install ${f}"
    echo "  placed  ${f}"
done
docker exec "${WEB}" rm -rf "${STAGE}" >/dev/null 2>&1 || true
echo

# ── 4. Verify the live portal ──────────────────────────────────────────────
echo "Verifying over HTTP (${BASE})"
check_code() {
    local path="$1" want="$2" label="$3"
    local code
    code="$(curl -s -o /dev/null -m 20 -w '%{http_code}' "${BASE}${path}" || true)"
    if [[ "${code}" == "${want}" ]]; then
        pass "${label} ${path} -> ${code}"
    else
        fail "${label} ${path} -> ${code} (expected ${want})"
    fi
}

check_code "/" 200 "landing page"
check_code "/login" 200 "staff login"
check_code "/admin_backup" 302 "backup page (auth redirect)"
check_code "/admin_answer_override" 302 "override page (auth redirect)"

# Capture first: `curl | grep -q` can report a SIGPIPE failure under pipefail.
LANDING_HTML="$(curl -s -m 20 "${BASE}/" || true)"
if grep -q 'navbar landing-navbar' <<<"${LANDING_HTML}"; then
    pass "landing navbar markup present"
else
    fail "landing navbar markup missing"
fi

# The sidebar must advertise the backup page and must NOT advertise the secret one.
if docker exec "${WEB}" grep -q 'admin_backup' /var/www/html/admin_sidebar.php; then
    pass "sidebar links Backup & Export"
else
    fail "sidebar link missing"
fi
if docker exec "${WEB}" grep -q 'admin_answer_override' /var/www/html/admin_sidebar.php; then
    fail "secret page must not be linked in the sidebar"
else
    pass "secret page not linked"
fi

echo
if [[ "${FAILED}" -eq 0 ]]; then
    echo "ALL CHECKS PASSED"
else
    echo "SOME CHECKS FAILED — review the output above" >&2
fi

echo
echo "Rollback commands:"
for f in "${EXISTED[@]}"; do
    printf '  docker cp %s/%s %s:/var/www/html/%s\n' "${BACKUP_DIR}" "${f}" "${WEB}" "${f}"
done
for f in "${NEW[@]}"; do
    printf '  docker exec %s rm -f /var/www/html/%s\n' "${WEB}" "${f}"
done
echo
echo "Backups are in: ${BACKUP_DIR}"
echo "Exit code: ${FAILED}"

exit "${FAILED}"
