#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Persistently add 10.10.0.108/24 to the wired Ethernet profile.
#
# What it does:
#   * Backs up /etc/netplan/01-network-manager-all.yaml
#   * Adds 10.10.0.108/24 next to the existing 10.10.0.105/24 and 10.10.0.107/24
#   * Validates the YAML, then runs `netplan apply`
#   * Verifies the address, the Docker CBT port, and that .105/.107 are intact
#
# What it does NOT do:
#   * Does not touch WiFi (no static WiFi IP, no WiFi profile changes)
#   * Does not touch Apache, Docker, firewall, MySQL, or the CBT app
#   * Does not remove or change 10.10.0.105 or 10.10.0.107
#
# Safe to re-run (idempotent).
# Run:  sudo bash scripts/setup-lan-108.sh
#       sudo bash scripts/setup-lan-108.sh --force   # skip LAN duplicate-IP check
# ---------------------------------------------------------------------------
set -euo pipefail

IFACE="enp0s31f6"
NEW_IP="10.10.0.108"
PREFIX="24"
NETPLAN_FILE="/etc/netplan/01-network-manager-all.yaml"
CBT_PORT="8080"
FORCE=0

[ "${1:-}" = "--force" ] && FORCE=1

log()  { printf '\n[%s] %s\n' "$(date +%H:%M:%S)" "$*"; }
ok()   { printf '  OK   %s\n' "$*"; }
warn() { printf '  WARN %s\n' "$*"; }
die()  { printf '\nERROR: %s\n' "$*" >&2; exit 1; }

# ----------------------------- preflight -----------------------------------
[ "$(id -u)" -eq 0 ] || die "run this with sudo: sudo bash $0"

[ -f "$NETPLAN_FILE" ] || die "netplan file not found: $NETPLAN_FILE"
ip link show "$IFACE" >/dev/null 2>&1 || die "interface $IFACE does not exist"

log "Current persistent Ethernet config"
netplan get ethernets."$IFACE" 2>/dev/null || cat "$NETPLAN_FILE"

# Is the address already on some interface?
if ip -4 -o addr show | grep -q "inet ${NEW_IP}/"; then
  owner="$(ip -4 -o addr show | awk -v ip="${NEW_IP}/" '$4==ip {print $2}')"
  if [ "$owner" = "$IFACE" ]; then
    warn "${NEW_IP} is already assigned to ${IFACE} (temporary or applied)"
  else
    die "${NEW_IP} is already assigned to ${owner}. Refusing to duplicate it."
  fi
fi

# Is another device already using .108 on the LAN? (only possible with carrier)
carrier="$(cat "/sys/class/net/${IFACE}/carrier" 2>/dev/null || echo 0)"
if [ "$carrier" = "1" ] && [ "$FORCE" -eq 0 ]; then
  if ip -4 -o addr show | grep -q "inet ${NEW_IP}/"; then
    :
  elif ping -c 2 -W 1 "$NEW_IP" >/dev/null 2>&1; then
    die "${NEW_IP} answers on the LAN (another device may own it). Re-run with --force only if you are sure."
  else
    ok "${NEW_IP} did not answer on the LAN (looks free)"
  fi
else
  warn "Ethernet cable is unplugged (no carrier). Will configure persistently; test after plugging in."
fi

# ----------------------------- backup --------------------------------------
STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="${NETPLAN_FILE}.bak-${STAMP}"
cp -a "$NETPLAN_FILE" "$BACKUP"
ok "backup created: $BACKUP"

rollback() {
  warn "rolling back netplan to $BACKUP"
  cp -a "$BACKUP" "$NETPLAN_FILE"
  netplan apply >/dev/null 2>&1 || true
}
trap 'rollback' ERR

# --------------------- add address (idempotent, keeps layout) --------------
if grep -qE "^[[:space:]]*-[[:space:]]*[\"']?${NEW_IP}/${PREFIX}[\"']?[[:space:]]*$" "$NETPLAN_FILE"; then
  ok "${NEW_IP}/${PREFIX} already present in $NETPLAN_FILE"
else
  log "Adding ${NEW_IP}/${PREFIX} under ethernets.${IFACE}.addresses"
  python3 - "$NETPLAN_FILE" "$IFACE" "${NEW_IP}/${PREFIX}" <<'PY'
import sys

path, iface, address = sys.argv[1], sys.argv[2], sys.argv[3]
with open(path, "r", encoding="utf-8") as fh:
    lines = fh.read().splitlines()

# Locate the interface block.
iface_idx = None
for i, line in enumerate(lines):
    if line.strip() == iface + ":":
        iface_idx = i
        break
if iface_idx is None:
    sys.exit("interface %s not found in %s" % (iface, path))

iface_indent = len(lines[iface_idx]) - len(lines[iface_idx].lstrip())

# Locate its addresses: key (must be a child of the interface block).
addr_idx = None
for i in range(iface_idx + 1, len(lines)):
    line = lines[i]
    if not line.strip() or line.lstrip().startswith("#"):
        continue
    indent = len(line) - len(line.lstrip())
    if indent <= iface_indent:
        break
    if line.strip().startswith("addresses:"):
        addr_idx = i
        break

if addr_idx is None:
    child = " " * (iface_indent + 2)
    lines[iface_idx + 1:iface_idx + 1] = [child + "addresses:", child + "- " + address]
else:
    addr_indent = len(lines[addr_idx]) - len(lines[addr_idx].lstrip())
    item_indent = None
    last_item = addr_idx
    for i in range(addr_idx + 1, len(lines)):
        line = lines[i]
        if not line.strip() or line.lstrip().startswith("#"):
            continue
        indent = len(line) - len(line.lstrip())
        if indent <= addr_indent:
            break
        if line.lstrip().startswith("- "):
            item_indent = indent
            last_item = i
    if item_indent is None:
        item_indent = addr_indent + 2
    lines.insert(last_item + 1, " " * item_indent + "- " + address)

with open(path, "w", encoding="utf-8") as fh:
    fh.write("\n".join(lines) + "\n")
PY
  ok "address added"
fi

# ----------------------------- validate ------------------------------------
log "Validating YAML"
python3 - "$NETPLAN_FILE" "$IFACE" "${NEW_IP}/${PREFIX}" <<'PY'
import sys
import yaml

path, iface, address = sys.argv[1], sys.argv[2], sys.argv[3]
with open(path, "r", encoding="utf-8") as fh:
    data = yaml.safe_load(fh)

addrs = data["network"]["ethernets"][iface].get("addresses", [])
addrs = [str(a) for a in addrs]
for required in ("10.10.0.105/24", "10.10.0.107/24", address):
    if required not in addrs:
        sys.exit("validation failed: %s missing from %s" % (required, addrs))
print("  OK   addresses now: " + ", ".join(addrs))
PY

log "Running netplan generate (writes backend config, does not apply yet)"
netplan generate
ok "netplan generate succeeded"

# ----------------------------- apply ---------------------------------------
log "Applying netplan"
netplan apply
ok "netplan apply succeeded"
trap - ERR

# ----------------------------- verify --------------------------------------
log "Verifying persistent configuration"
netplan get ethernets."$IFACE".addresses

log "Verifying live addresses on ${IFACE}"
for _ in $(seq 1 10); do
  ip -4 -o addr show dev "$IFACE" | grep -q "inet ${NEW_IP}/" && break
  sleep 1
done

if ip -4 -o addr show dev "$IFACE" | grep -q "inet ${NEW_IP}/"; then
  ok "${NEW_IP} is live on ${IFACE}"
  LIVE=1
else
  LIVE=0
  if [ "$carrier" = "1" ]; then
    warn "${NEW_IP} is configured but not live yet; trying to activate the profile"
    nmcli connection up netplan-"$IFACE" >/dev/null 2>&1 || true
    sleep 2
    ip -4 -o addr show dev "$IFACE" | grep -q "inet ${NEW_IP}/" && LIVE=1
  fi
  if [ "$LIVE" -eq 0 ]; then
    warn "${NEW_IP} is saved for next connection/reboot (cable is unplugged or profile inactive)"
  fi
fi

log "Confirming .105 / .107 were not removed"
for ip in 10.10.0.105 10.10.0.107; do
  if netplan get ethernets."$IFACE".addresses 2>/dev/null | grep -q "$ip"; then
    ok "$ip still configured"
  else
    rollback
    die "$ip is missing from netplan — restored backup"
  fi
done

log "Confirming the CBT app is still published on all interfaces"
if ss -tln 2>/dev/null | grep -qE '(^|[[:space:]])(\*|0\.0\.0\.0):'"$CBT_PORT"'([[:space:]]|$)'; then
  ok "port ${CBT_PORT} is listening on 0.0.0.0 (LAN + WiFi)"
else
  warn "port ${CBT_PORT} is not listening on 0.0.0.0; check: sudo docker ps"
fi

log "HTTP checks"
check() {
  printf '  %-38s ' "$1"
  curl -s -o /dev/null -m 6 -w 'HTTP %{http_code}\n' "$1" 2>/dev/null || echo "FAILED"
}
check "http://127.0.0.1:${CBT_PORT}/"
if [ "$LIVE" -eq 1 ]; then
  check "http://${NEW_IP}:${CBT_PORT}/"
fi
if ip -4 -o addr show | grep -q 'inet 10\.10\.0\.105/'; then
  check "http://10.10.0.105/"
fi
if ip -4 -o addr show | grep -q 'inet 10\.10\.0\.107/'; then
  check "http://10.10.0.107/"
fi

log "Done"
echo "  Students should open: http://${NEW_IP}:${CBT_PORT}"
echo "  WiFi keeps working on whatever IP it is given, on port ${CBT_PORT}."
echo
echo "  If you ever need to undo this change:"
echo "    sudo cp -a ${BACKUP} ${NETPLAN_FILE} && sudo netplan apply"
