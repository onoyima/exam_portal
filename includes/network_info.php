<?php
/**
 * Works out which addresses other computers on the network can use to reach
 * this server, so the admin can read them off the screen instead of running
 * ipconfig in a terminal.
 */

/** IPv4 addresses of this machine: [['ip' => ..., 'adapter' => ..., 'kind' => lan|virtual|public|linklocal]] */
function ni_server_addresses(): array
{
    $found = [];
    $add = function (string $ip, string $adapter = '') use (&$found) {
        $ip = trim($ip);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || strpos($ip, '127.') === 0 || $ip === '0.0.0.0') {
            return;
        }
        if (!isset($found[$ip]) || ($found[$ip] === '' && $adapter !== '')) {
            $found[$ip] = $adapter;
        }
    };

    // 1. Adapter list from the operating system (gives names such as "Wi-Fi")
    if (function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
        if (stripos(PHP_OS_FAMILY, 'Windows') === 0) {
            $out = (string) @shell_exec('ipconfig 2>NUL');
            $adapter = '';
            foreach (preg_split('/\r?\n/', $out) as $line) {
                if ($line !== '' && $line[0] !== ' ' && substr(rtrim($line), -1) === ':') {
                    $adapter = trim(preg_replace('/^.*?adapter\s+/i', '', rtrim(trim($line), ':')));
                } elseif (preg_match('/IPv4[^:]*:\s*([0-9.]+)/i', $line, $m)) {
                    $add($m[1], $adapter);
                }
            }
        } else {
            $out = (string) @shell_exec('ip -4 -o addr show 2>/dev/null');
            foreach (preg_split('/\n/', $out) as $line) {
                if (preg_match('/^\d+:\s+(\S+)\s+inet\s+([0-9.]+)/', $line, $m)) {
                    $add($m[2], $m[1]);
                }
            }
        }
    }

    // 2. Host name lookup
    $host = gethostname();
    if ($host) {
        foreach ((array) @gethostbynamel($host) as $ip) {
            $add($ip);
        }
    }

    // 3. The address this request arrived on (correct when the admin is on another PC)
    if (!empty($_SERVER['SERVER_ADDR'])) {
        $add($_SERVER['SERVER_ADDR']);
    }

    // 4. The interface the OS would use for outside traffic (sends nothing)
    $sock = @stream_socket_client('udp://8.8.8.8:53', $errno, $errstr, 1);
    if ($sock) {
        $name = stream_socket_get_name($sock, false);
        fclose($sock);
        if ($name) {
            $add(preg_replace('/:\d+$/', '', $name));
        }
    }

    $list = [];
    foreach ($found as $ip => $adapter) {
        $list[] = ['ip' => $ip, 'adapter' => $adapter, 'kind' => ni_classify($ip, $adapter)];
    }
    $rank = ['lan' => 0, 'public' => 1, 'virtual' => 2, 'linklocal' => 3];
    usort($list, function ($a, $b) use ($rank) {
        return [$rank[$a['kind']], $a['ip']] <=> [$rank[$b['kind']], $b['ip']];
    });
    return $list;
}

function ni_classify(string $ip, string $adapter): string
{
    if (strpos($ip, '169.254.') === 0) {
        return 'linklocal';
    }
    if (preg_match('/virtual|vmware|vbox|hyper-v|vethernet|docker|wsl|loopback|bluetooth|tap|tun|vpn|npcap/i', $adapter)
        || strpos($ip, '192.168.56.') === 0) {
        return 'virtual';
    }
    $private = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    return $private ? 'lan' : 'public';
}

/** Base URL of the portal for a given host, e.g. http://192.168.1.5/CBT */
function ni_base_url(string $host): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $port = (int) ($_SERVER['SERVER_PORT'] ?? 80);
    $port_part = ($https && $port === 443) || (!$https && $port === 80) ? '' : ':' . $port;
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $dir = rtrim($dir === '.' ? '' : $dir, '/');
    return ($https ? 'https' : 'http') . '://' . $host . $port_part . $dir;
}

/** True when the page itself is being viewed through localhost on the server. */
function ni_viewing_locally(): bool
{
    $h = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    return in_array($h, ['localhost', '127.0.0.1', '[::1]', '::1'], true);
}

/** Address the admin chose to hand out instead of the detected one (e.g. 10.10.0.109), or null. */
function ni_override(PDO $db): ?string
{
    try {
        $st = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'server_address_override'");
        $st->execute();
        $v = trim((string) $st->fetchColumn());
        return $v !== '' ? $v : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Accepts an IPv4 address or host name, optionally with :port. Returns the cleaned value or null. */
function ni_clean_address(string $v): ?string
{
    $v = trim(preg_replace('#^https?://#i', '', trim($v)), '/ ');
    if ($v === '') {
        return '';
    }
    if (!preg_match('/^([A-Za-z0-9](?:[A-Za-z0-9\-\.]{0,251}[A-Za-z0-9])?)(?::(\d{1,5}))?$/', $v, $m)) {
        return null;
    }
    if (isset($m[2]) && ((int) $m[2] < 1 || (int) $m[2] > 65535)) {
        return null;
    }
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $m[1]) && !filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return null;
    }
    return $v;
}

/** Base URL for a host that may carry its own :port. */
function ni_base_url_for(string $host_or_hostport): string
{
    if (preg_match('/^(.*):(\d+)$/', $host_or_hostport, $m)) {
        $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $dir = rtrim($dir === '.' ? '' : $dir, '/');
        return 'http://' . $m[1] . ($m[2] === '80' ? '' : ':' . $m[2]) . $dir;
    }
    return ni_base_url($host_or_hostport);
}
