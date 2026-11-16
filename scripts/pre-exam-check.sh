#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Read-only pre-exam health check.
#
#   sudo bash scripts/pre-exam-check.sh
#
# Prints PASS / WARN / FAIL for the things that matter on exam day. It does
# not change anything.
# ---------------------------------------------------------------------------
set -uo pipefail

WEB="veritas-cbt-web"
DB="veritas-cbt-db"
BASE="http://127.0.0.1:8080"
PASS=0; WARN=0; FAIL=0

ok()   { PASS=$((PASS+1)); printf '  PASS  %s\n' "$*"; }
warn() { WARN=$((WARN+1)); printf '  WARN  %s\n' "$*"; }
bad()  { FAIL=$((FAIL+1)); printf '  FAIL  %s\n' "$*"; }

section() { printf '\n== %s ==\n' "$*"; }

[[ "${EUID}" -eq 0 ]] || { echo "run this script with sudo"; exit 1; }

section "Host"
printf '  time   : %s\n' "$(date)"
if timedatectl show -p NTPSynchronized --value 2>/dev/null | grep -qi yes; then
    ok "clock is synchronised"
else
    warn "clock may not be synchronised"
fi
if [[ -r /sys/class/power_supply/AC/online ]] && [[ "$(cat /sys/class/power_supply/AC/online)" == "1" ]]; then
    ok "AC power connected"
else
    warn "AC power not detected (running on battery?)"
fi
AVAIL_KB="$(df --output=avail / | tail -n1 | tr -d ' ')"
if [[ "${AVAIL_KB}" -gt 2097152 ]]; then ok "disk free: $((AVAIL_KB/1024/1024)) GB"; else warn "low disk: $((AVAIL_KB/1024)) MB free"; fi
MEM_AVAIL="$(awk '/MemAvailable/ {print int($2/1024)}' /proc/meminfo)"
if [[ "${MEM_AVAIL}" -gt 512 ]]; then ok "memory available: ${MEM_AVAIL} MB"; else warn "low memory: ${MEM_AVAIL} MB available"; fi

section "Containers"
for c in "${WEB}" "${DB}"; do
    if docker inspect "${c}" >/dev/null 2>&1; then
        state="$(docker inspect -f '{{.State.Status}}' "${c}")"
        restart="$(docker inspect -f '{{.HostConfig.RestartPolicy.Name}}' "${c}")"
        if [[ "${state}" == "running" ]]; then ok "${c} is running (restart=${restart})"; else bad "${c} is ${state}"; fi
        if [[ "${restart}" == "unless-stopped" || "${restart}" == "always" ]]; then ok "${c} restart policy is ${restart}"; else warn "${c} restart policy is ${restart}"; fi
        if docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{end}}' "${c}" 2>/dev/null | grep -q healthy; then
            ok "${c} health check is healthy"
        fi
    else
        bad "${c} does not exist"
    fi
done

section "HTTP"
for path in / /student_login /login; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "${BASE}${path}" || echo ERR)"
    if [[ "${code}" == "200" ]]; then ok "${path} -> ${code}"; else bad "${path} -> ${code}"; fi
done
for path in /.env /logs/php_errors.log /migrations/full_export_20261110_215555.sql /database/export.php; do
    code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 "${BASE}${path}" || echo ERR)"
    if [[ "${code}" == "403" || "${code}" == "404" ]]; then ok "${path} is blocked (${code})"; else bad "${path} is exposed (${code})"; fi
done

section "Database"
if docker exec "${DB}" mysqladmin ping -uroot -p"$(docker exec "${DB}" printenv MYSQL_ROOT_PASSWORD)" --silent >/dev/null 2>&1; then
    ok "MySQL responds to ping"
else
    bad "MySQL ping failed"
fi
docker exec "${DB}" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "
SELECT CONCAT(\"active exams: \", COUNT(*)) FROM exams WHERE is_active=1 AND deleted_at IS NULL;
SELECT CONCAT(\"approved devices: \", COUNT(*)) FROM approved_devices WHERE status=\"approved\";
SELECT CONCAT(\"pending devices: \", COUNT(*)) FROM approved_devices WHERE status=\"pending\";
SELECT CONCAT(\"duplicate null-exam questions: \", COUNT(*)) FROM (
  SELECT text FROM questions WHERE exam_id IS NULL GROUP BY text HAVING COUNT(*) > 1
) d;
"' 2>/dev/null | sed 's/^/  /'

section "Upcoming / active exams"
docker exec "${DB}" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -t -e "
SELECT id, name, is_active, duration_minutes, start_time, end_time
FROM exams
WHERE is_active=1 AND deleted_at IS NULL
ORDER BY start_time;"' 2>/dev/null | sed 's/^/  /'

section "Device approval mode"
PAUSED="$(docker exec "${DB}" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -N -e "SELECT setting_value FROM system_settings WHERE setting_key=\"device_approval_paused\" LIMIT 1;"' 2>/dev/null | tr -d '[:space:]')"
if [[ "${PAUSED}" == "0" ]]; then
    ok "approval is ON (new devices wait for approval)"
else
    ok "approval is PAUSED (accept-any mode — expected during exams)"
fi

section "Runtime files"
for path in uploads assets/img logs; do
    if docker exec -u 33 "${WEB}" test -w "/var/www/html/${path}"; then ok "${path} is writable by www-data"; else bad "${path} is not writable by www-data"; fi
done
if docker exec -u 33 "${WEB}" test -f /var/www/html/assets/img/login_bg_1794222632.jpeg; then
    ok "login background is present"
else
    warn "configured login background is missing (fallback will be used)"
fi
if docker exec -u 33 "${WEB}" test -d /var/lib/php/sessions && docker exec -u 33 "${WEB}" test -w /var/lib/php/sessions; then
    ok "session directory is writable"
else
    warn "session directory is not writable; sessions may use /tmp"
fi

section "Failed systemd units"
systemctl --failed --no-legend --plain 2>/dev/null | awk '{print "  "$1" "$2" "$3}' | head -10

printf '\n== Summary ==\n  PASS=%d  WARN=%d  FAIL=%d\n' "${PASS}" "${WARN}" "${FAIL}"
[[ "${FAIL}" -eq 0 ]] && exit 0 || exit 1
