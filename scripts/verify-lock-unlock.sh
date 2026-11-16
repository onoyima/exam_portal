#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# End-to-end check of the lock-only proctoring policy.
#
#   sudo bash scripts/verify-lock-unlock.sh
#
# Creates a throwaway exam/student/attempt inside the real database, drives the
# real /proctor_ping and /admin_live_proctor?action=unlock endpoints over HTTP,
# asserts lock-without-submit and resume-after-unlock, then deletes everything.
# The live exam (id 89) is never touched.
# ---------------------------------------------------------------------------
set -Eeuo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB="veritas-cbt-web"
TEST="/tmp/verify_lock_unlock_$$.php"

die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ "${EUID}" -eq 0 ]] || die "run this script with sudo"
docker inspect "${WEB}" >/dev/null 2>&1 || die "container ${WEB} not found"

cleanup() { docker exec "${WEB}" rm -f "${TEST}" >/dev/null 2>&1 || true; }
trap cleanup EXIT

docker cp "${REPO}/scripts/verify_lock_unlock.php" "${WEB}:${TEST}"
docker exec -u www-data "${WEB}" php "${TEST}"
