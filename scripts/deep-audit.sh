#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# READ-ONLY deep readiness audit for the CBT stack.
# Changes nothing: only inspect, SELECT, SHOW, and read logs.
# Run:  sudo bash scripts/deep-audit.sh
# ---------------------------------------------------------------------------
set -u

WEB=veritas-cbt-web
DB=veritas-cbt-db
REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
line() { printf '\n\n========== %s ==========\n' "$*"; }
sub()  { printf '\n--- %s ---\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Run with sudo: sudo bash $0" >&2; exit 1; }

line "HOST"
date
uptime
echo
free -h
echo
df -hT / | tail -2
echo
echo "Load: $(cat /proc/loadavg)"
echo "CPU: $(nproc) logical"
echo
echo "Top CPU processes:"
ps -eo pcpu,pmem,rss,comm --sort=-pcpu | head -12
echo
echo "Top memory processes:"
ps -eo pcpu,pmem,rss,comm --sort=-rss | head -12

line "POWER / SLEEP / AUTO-UPDATES"
for f in /sys/class/power_supply/*/online /sys/class/power_supply/*/status /sys/class/power_supply/*/capacity; do
  [ -f "$f" ] && echo "$f = $(cat "$f")"
done
echo "sleep.target=$(systemctl is-active sleep.target 2>/dev/null) suspend.target=$(systemctl is-active suspend.target 2>/dev/null)"
echo "unattended-upgrades: enabled=$(systemctl is-enabled unattended-upgrades 2>/dev/null) active=$(systemctl is-active unattended-upgrades 2>/dev/null)"
echo
echo "Upcoming timers:"
systemctl list-timers --all --no-pager 2>/dev/null | head -18

line "NETWORK"
ip -br addr
echo
ip route
echo
echo "Ethernet speed: $(cat /sys/class/net/enp0s31f6/speed 2>/dev/null) Mb/s"
echo "Ethernet carrier: $(cat /sys/class/net/enp0s31f6/carrier 2>/dev/null) operstate: $(cat /sys/class/net/enp0s31f6/operstate 2>/dev/null)"
ip -s link show enp0s31f6
echo
echo "WiFi speed: $(cat /sys/class/net/wlp2s0/speed 2>/dev/null) Mb/s"
echo
ss -s
echo
echo "Listening 80/8080/3306:"
ss -tlnp 2>/dev/null | grep -E ':(80|8080|3306)\b' || echo none

line "DOCKER"
docker info --format 'Storage={{.Driver}} Logging={{.LoggingDriver}} LiveRestore={{.LiveRestoreEnabled}} Containers={{.Containers}} Running={{.ContainersRunning}} Images={{.Images}}' 2>&1
echo
echo "daemon.json:"
cat /etc/docker/daemon.json 2>/dev/null || echo "  (none)"
echo
docker ps -a --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>&1
echo
sub "container settings"
for c in "$WEB" "$DB"; do
  echo "[$c]"
  docker inspect -f 'image={{.Config.Image}} imageID={{.Image}} created={{.Created}} started={{.State.StartedAt}} restart={{.HostConfig.RestartPolicy.Name}} restartCount={{.RestartCount}} running={{.State.Running}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}n/a{{end}}' "$c" 2>&1
  docker inspect -f 'memLimit={{.HostConfig.Memory}} memSwap={{.HostConfig.MemorySwap}} nanoCpus={{.HostConfig.NanoCpus}} pidsLimit={{.HostConfig.PidsLimit}}' "$c" 2>&1
  docker inspect -f 'cmd={{json .Config.Cmd}} entrypoint={{json .Config.Entrypoint}}' "$c" 2>&1
  echo "port bindings:"
  docker inspect -f '{{json .HostConfig.PortBindings}}' "$c" 2>&1
  echo "env (values masked):"
  docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$c" 2>/dev/null | sed -E 's/^([A-Za-z_][A-Za-z0-9_]*(PASS|PASSWORD|SECRET|KEY|TOKEN)[A-Za-z0-9_]*)=.*/\1=<redacted>/I'
  echo "mounts:"
  docker inspect -f '{{range .Mounts}}mount {{.Type}} {{.Source}} -> {{.Destination}} rw={{.RW}}{{println}}{{end}}' "$c" 2>&1
  echo "binds:"
  docker inspect -f '{{json .HostConfig.Binds}}' "$c" 2>&1
  p=$(docker inspect -f '{{.LogPath}}' "$c" 2>/dev/null)
  [ -n "$p" ] && ls -lh "$p" 2>/dev/null
  echo
done

sub "docker stats"
docker stats --no-stream --format 'table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}\t{{.PIDs}}' 2>&1

sub "docker disk"
docker system df 2>&1

line "CONTAINER LOGS (last 24h, errors only)"
for c in "$WEB" "$DB"; do
  sub "$c"
  docker logs --since 24h "$c" 2>&1 | grep -iE 'error|fatal|exception|refused|timeout|too many|denied|warning' | tail -40 || echo "  no matching lines"
done

line "WEB CONTAINER: CODE + RUNTIME"
sub "device gate present in live code?"
docker exec "$WEB" sh -c 'grep -n "check_device_auth" /var/www/html/student_login.php /var/www/html/student_objective_exam.php 2>/dev/null || echo "not found"'

sub "live container code vs repo (sha256)"
for f in student_login.php auth.php db.php config.php index.php exam_logic.php includes/question_engine.php student_objective_exam.php; do
  h="$(sha256sum "$REPO_DIR/$f" 2>/dev/null | cut -d' ' -f1)"
  c="$(docker exec "$WEB" sha256sum "/var/www/html/$f" 2>/dev/null | cut -d' ' -f1)"
  if [ -n "$h" ] && [ "$h" = "$c" ]; then
    echo "  SAME  $f"
  else
    echo "  DIFF  $f  repo=${h:-missing} container=${c:-missing}"
  fi
done

sub "SEB / login background assets"
echo "host SEB file:"; ls -lh "$REPO_DIR/downloads/seb/VeritasSEB-1.0.0-win.exe" 2>/dev/null || echo "  missing"
head -c 120 "$REPO_DIR/downloads/seb/VeritasSEB-1.0.0-win.exe" 2>/dev/null; echo
echo "container SEB file:"; docker exec "$WEB" sh -c 'ls -lh /var/www/html/downloads/seb/VeritasSEB-1.0.0-win.exe 2>/dev/null || echo missing'
echo "login background:"; docker exec "$WEB" sh -c 'ls -lh /var/www/html/assets/img/login_bg.jpg 2>/dev/null || echo "  MISSING /var/www/html/assets/img/login_bg.jpg"'

sub "logs dir / qe schema flag / php errors"
docker exec "$WEB" sh -c '
  echo "logs dir:"; ls -ld /var/www/html/logs 2>/dev/null || echo "  MISSING";
  echo "qe flag:"; ls -la /var/www/html/logs/.qe_schema_v* 2>/dev/null || echo "  MISSING (migrations may run every request)";
  echo "php_errors.log:"; tail -40 /var/www/html/logs/php_errors.log 2>/dev/null || echo "  none";
'

sub "runtime-uploaded assets (persist across recreate?)"
docker exec "$WEB" sh -c '
  echo "assets/img count: $(ls /var/www/html/assets/img 2>/dev/null | wc -l)";
  echo "login_bg files in container:"; ls -la /var/www/html/assets/img/login_bg* 2>/dev/null || echo "  none";
  echo "uploads tree:"; ls -la /var/www/html/uploads 2>/dev/null | head -20;
  echo "uploads file count: $(find /var/www/html/uploads -type f 2>/dev/null | wc -l)";
'

sub ".env inside container (perms + keys only)"
docker exec "$WEB" sh -c '
  ls -l /var/www/html/.env 2>/dev/null || echo "  no .env";
  echo "keys:"; grep -oE "^[A-Za-z_][A-Za-z0-9_]*=" /var/www/html/.env 2>/dev/null | tr -d "=";
'
echo
echo "www-data write test:"
docker exec -u www-data "$WEB" sh -c '
  for d in /var/www/html/logs /var/www/html/uploads /var/lib/php/sessions; do
    if [ -w "$d" ]; then echo "  WRITABLE $d"; else echo "  NOT-WRITABLE $d"; fi
  done
' 2>&1

sub "PHP version + key modules"
docker exec "$WEB" php -v 2>&1 | head -2
docker exec "$WEB" php -m 2>/dev/null | grep -iE 'pdo_mysql|mysqli|opcache|gd|intl|mbstring|curl|zip|exif|redis|memcached' | sed 's/^/  /'

sub "PHP limits / opcache / sessions"
docker exec "$WEB" php -r '
$keys=["memory_limit","max_execution_time","max_input_time","upload_max_filesize","post_max_size","max_file_uploads","max_input_vars","opcache.enable","opcache.enable_cli","opcache.memory_consumption","opcache.interned_strings_buffer","opcache.max_accelerated_files","opcache.validate_timestamps","opcache.revalidate_freq","session.save_handler","session.save_path","session.gc_maxlifetime","session.gc_probability","session.gc_divisor","realpath_cache_size","realpath_cache_ttl","display_errors","log_errors","error_log"];
foreach($keys as $k){echo str_pad($k,38)."= ".ini_get($k).PHP_EOL;}
$s=@opcache_get_status(false);
echo str_pad("opcache_status",38)."= ".((is_array($s)&&!empty($s["opcache_enabled"]))?"ENABLED":"DISABLED").PHP_EOL;
' 2>&1

sub "Apache MPM + worker limits"
docker exec "$WEB" apache2ctl -V 2>/dev/null | grep -E 'Server version|Server MPM|Server built'
echo
docker exec "$WEB" apache2ctl -M 2>/dev/null | grep -iE 'mpm|rewrite|headers|expires' | sed 's/^/  /'
echo
docker exec "$WEB" sh -c 'grep -rE "^[[:space:]]*(MaxRequestWorkers|ServerLimit|StartServers|MinSpareServers|MaxSpareServers|MaxConnectionsPerChild|ThreadsPerChild)" /etc/apache2/ 2>/dev/null' || echo "  using defaults"

sub "session files inside container"
docker exec "$WEB" sh -c 'ls /var/lib/php/sessions 2>/dev/null | wc -l' 2>&1

line "DATABASE"
DB_NAME="$(docker exec "$WEB" sh -c "grep -E '^DB_NAME=' /var/www/html/.env 2>/dev/null | head -1 | cut -d= -f2-" | tr -d '"' | tr -d '\r')"
if [ -z "${DB_NAME:-}" ]; then
  DB_NAME="$(docker exec "$DB" sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -N -e "SHOW DATABASES"' 2>/dev/null | grep -vE '^(information_schema|mysql|performance_schema|sys)$' | head -1)"
fi
echo "Database name: ${DB_NAME:-<not found>}"

run_sql() {
  docker exec -i "$DB" sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" --table "$1"' _ "$DB_NAME" 2>&1
}

sub "version / capacity / connections"
run_sql <<'SQL'
SELECT VERSION() AS version, @@max_connections AS max_connections,
       @@wait_timeout AS wait_timeout, @@interactive_timeout AS interactive_timeout,
       @@innodb_buffer_pool_size AS buffer_pool_bytes,
       @@innodb_log_file_size AS log_file_bytes,
       @@table_open_cache AS table_open_cache,
       @@max_allowed_packet AS max_allowed_packet;
SHOW GLOBAL STATUS WHERE Variable_name IN
 ('Threads_connected','Threads_running','Max_used_connections','Connections',
  'Aborted_connects','Aborted_clients','Slow_queries','Uptime',
  'Connection_errors_max_connections','Innodb_buffer_pool_wait_free');
SHOW PROCESSLIST;
SQL

sub "slow query settings"
run_sql <<'SQL'
SHOW VARIABLES WHERE Variable_name IN ('slow_query_log','slow_query_log_file','long_query_time','log_queries_not_using_indexes');
SQL

sub "table sizes (top 20)"
run_sql <<'SQL'
SELECT table_name AS tbl, table_rows AS rows_est,
       ROUND(data_length/1024/1024,1) AS data_mb,
       ROUND(index_length/1024/1024,1) AS index_mb
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY data_length + index_length DESC
LIMIT 20;
SQL

sub "key row counts"
run_sql <<'SQL'
SELECT 'students' AS item, COUNT(*) AS n FROM students WHERE deleted_at IS NULL
UNION ALL SELECT 'exams_active', COUNT(*) FROM exams WHERE is_active = 1 AND deleted_at IS NULL
UNION ALL SELECT 'exam_students', COUNT(*) FROM exam_students
UNION ALL SELECT 'objective_questions', COUNT(*) FROM objective_questions WHERE deleted_at IS NULL
UNION ALL SELECT 'objective_attempts', COUNT(*) FROM objective_attempts
UNION ALL SELECT 'objective_answers', COUNT(*) FROM objective_answers
UNION ALL SELECT 'login_history', COUNT(*) FROM login_history
UNION ALL SELECT 'approved_devices', COUNT(*) FROM approved_devices;
SQL

sub "active exams"
run_sql <<'SQL'
SELECT id, name, is_active, duration_minutes, start_time, end_time,
       proctor_browser_check, proctor_webcam, proctor_live_video,
       proctor_fullscreen, proctor_tab_lock, deleted_at
FROM exams
WHERE deleted_at IS NULL
ORDER BY is_active DESC, id DESC
LIMIT 20;
SQL

sub "approved devices (status counts)"
run_sql <<'SQL'
SELECT status, COUNT(*) AS n FROM approved_devices GROUP BY status;
SELECT ip_address, status, last_seen
FROM approved_devices
ORDER BY FIELD(status,'pending','rejected','approved'), last_seen DESC
LIMIT 40;
SQL

sub "tables referenced by helper modules"
run_sql <<'SQL'
SHOW TABLES LIKE 'seb_settings';
SHOW TABLES LIKE 'device_approval_log';
SQL

sub "seb_settings contents (if present)"
run_sql <<'SQL'
SELECT * FROM seb_settings LIMIT 5;
SQL

sub "questions table: init_questions duplicate check"
run_sql <<'SQL'
SELECT COUNT(*) AS total_questions FROM questions;
SELECT text, COUNT(*) AS copies
FROM questions
GROUP BY text
HAVING copies > 1
ORDER BY copies DESC
LIMIT 25;
SHOW INDEX FROM questions;
SELECT id, text, score_values FROM questions ORDER BY id DESC LIMIT 30;
SQL

sub "live column check (key tables)"
run_sql <<'SQL'
DESCRIBE objective_answers;
DESCRIBE objective_questions;
DESCRIBE questions;
SQL

sub "indexes on hot tables"
run_sql <<'SQL'
SELECT table_name AS tbl, index_name AS idx,
       GROUP_CONCAT(column_name ORDER BY seq_in_index) AS cols,
       IF(non_unique=0,'UNIQUE','') AS kind
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name IN ('students','exams','exam_students','objective_questions','objective_answers','objective_attempts','approved_devices','login_history')
GROUP BY table_name, index_name, non_unique
ORDER BY table_name, index_name;
SQL

sub "student login lookup plan (REPLACE/UPPER prevents index use?)"
run_sql <<'SQL'
EXPLAIN SELECT id, name, matric_no, password
FROM students
WHERE deleted_at IS NULL AND (
  REPLACE(UPPER(matric_no), '/', '') = 'VUGBNS215171' OR
  REPLACE(UPPER(username), '/', '') = 'VUGBNS215171' OR
  matric_no = 'VUG/BNS/21/5171' OR
  username = 'VUG/BNS/21/5171'
);
SQL

sub "settings that affect login/exam"
run_sql <<'SQL'
SELECT setting_key, LEFT(setting_value, 160) AS value
FROM system_settings
WHERE setting_key IN ('require_student_password','portal_title','portal_url','portal_image',
                      'portal_logo','portal_favicon','exam_mode','maintenance_mode')
   OR setting_key LIKE '%device%' OR setting_key LIKE '%approval%'
ORDER BY setting_key;
SQL

sub "recent login activity"
run_sql <<'SQL'
SELECT id, username, user_type, status, ip_address, login_time
FROM login_history
ORDER BY id DESC
LIMIT 15;
SQL

line "KERNEL / SERVICE HEALTH"
echo "Failed units:"
systemctl --failed --no-pager 2>&1 | head -20
echo
echo "OOM kills (dmesg):"
dmesg -T 2>/dev/null | grep -iE 'out of memory|oom-kill|killed process' | tail -15 || echo "  none"
echo
echo "Docker errors (journal, 24h):"
journalctl -u docker --since '24 hours ago' --no-pager 2>/dev/null | grep -iE 'error|fail|panic' | tail -20 || echo "  none"

line "DONE"
