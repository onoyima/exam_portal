#!/usr/bin/env bash
# READ-ONLY LAN / CBT health check. Changes nothing.
# Run:  sudo bash scripts/check-lan.sh
set -u

line() { printf '\n===== %s =====\n' "$1"; }

line "IPv4 addresses"
ip -4 -o addr show | awk '{print $2, $4}'

line "Default route"
ip route | head -5

line "Is 10.10.0.108 on this machine?"
if ip -4 -o addr show | grep -q '10\.10\.0\.108/'; then
  echo "YES: 10.10.0.108 is assigned"
  ip -4 -o addr show | grep '10\.10\.0\.108/'
else
  echo "NO: 10.10.0.108 is NOT assigned to any interface"
fi

line "Listening ports 80 / 8080 / 3306"
ss -tlnp 2>/dev/null | grep -E ':(80|8080|3306)\b' || echo "none"

line "NetworkManager active connection"
nmcli -t -f NAME,DEVICE connection show --active 2>/dev/null || true

line "Persistent netplan Ethernet config"
netplan get ethernets.enp0s31f6 2>/dev/null || echo "cannot read netplan"
echo "-- generated NM profile --"
nmcli -f ipv4.method,ipv4.addresses,ipv4.gateway,ipv4.never-default connection show netplan-enp0s31f6 2>/dev/null || true

line "Ethernet link state"
ip link show enp0s31f6 2>/dev/null | head -1 || echo "enp0s31f6 not found"

line "Firewall (ufw)"
ufw status verbose 2>/dev/null || echo "ufw not installed/active"

line "Firewall (iptables INPUT)"
iptables -S INPUT 2>/dev/null | head -30 || echo "cannot read iptables"

line "Docker containers"
docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' 2>/dev/null || echo "docker not accessible"

line "Docker restart policies / health"
for c in veritas-cbt-db veritas-cbt-web veritas-cbt-web-test; do
  if docker inspect "$c" >/dev/null 2>&1; then
    echo "--- $c ---"
    docker inspect -f 'restart={{.HostConfig.RestartPolicy.Name}} running={{.State.Running}} status={{.State.Status}} health={{if .State.Health}}{{.State.Health.Status}}{{else}}n/a{{end}}' "$c"
    docker inspect -f 'ports={{range $p, $v := .NetworkSettings.Ports}}{{$p}}=>{{range $v}}{{.HostIp}}:{{.HostPort}} {{end}}{{end}}' "$c"
  fi
done

line "Container DB ping"
if docker inspect veritas-cbt-db >/dev/null 2>&1; then
  docker exec veritas-cbt-db sh -c 'mysqladmin ping -h 127.0.0.1 -uroot -p"$MYSQL_ROOT_PASSWORD" 2>/dev/null || mysqladmin ping -h 127.0.0.1 2>/dev/null' || echo "DB ping failed"
fi

line "HTTP checks from this machine"
for url in \
  "http://127.0.0.1:8080/" \
  "http://127.0.0.1:8080/student_login" \
  "http://10.10.0.108:8080/" \
  "http://10.10.0.108:8080/student_login" \
  "http://10.10.0.105/" \
  "http://10.10.0.107/"; do
  printf '%-42s ' "$url"
  curl -s -o /dev/null -m 8 -w 'HTTP %{http_code} time=%{time_total}s\n' "$url" || echo "FAILED"
done

line "Docker daemon enabled at boot"
systemctl is-enabled docker 2>/dev/null || true
systemctl is-active docker 2>/dev/null || true

line "Resources"
free -h
df -h / | tail -1
uptime

line "Done"
