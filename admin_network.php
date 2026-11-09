<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/network_info.php';

$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf();
  $clean = isset($_POST['clear_override']) ? '' : ni_clean_address((string) ($_POST['override'] ?? ''));
  if ($clean === null) {
    $notice = ['danger', 'That does not look like an IP address or computer name. Example: 10.10.0.109 or 10.10.0.109:8080'];
  } else {
    $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('server_address_override', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([$clean]);
    $_SESSION['net_notice'] = $clean === '' ? 'Using the detected address again.' : 'Students will now be given ' . $clean . '.';
    header('Location: admin_network');
    exit;
  }
}
if (!$notice && !empty($_SESSION['net_notice'])) {
  $notice = ['success', $_SESSION['net_notice']];
  unset($_SESSION['net_notice']);
}
$override = ni_override($db);

$addresses = ni_server_addresses();
$lan = array_values(array_filter($addresses, fn($a) => $a['kind'] === 'lan'));
$primary = $lan[0]['ip'] ?? ($addresses[0]['ip'] ?? null);
$primary_base = $override ? ni_base_url_for($override) : ($primary ? ni_base_url($primary) : null);
$hostname = gethostname() ?: '';
$port = (int) ($_SERVER['SERVER_PORT'] ?? 80);

$recent = $db->query("SELECT ip_address, device_info, status, last_seen FROM approved_devices
    WHERE last_seen > DATE_SUB(NOW(), INTERVAL 15 MINUTE) ORDER BY last_seen DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
$pending = (int) $db->query("SELECT COUNT(*) FROM approved_devices WHERE status = 'pending'")->fetchColumn();

$kind_label = ['lan' => ['Local network', 'admin-badge-success'], 'public' => ['Public', 'admin-badge-info'],
               'virtual' => ['Virtual adapter', 'admin-badge-neutral'], 'linklocal' => ['No network (169.254)', 'admin-badge-warning']];

function ni_browser_name(string $ua): string
{
    foreach (['Edg/' => 'Edge', 'OPR/' => 'Opera', 'SEB' => 'Safe Exam Browser', 'Chrome/' => 'Chrome', 'Firefox/' => 'Firefox', 'Safari/' => 'Safari'] as $k => $v) {
        if (stripos($ua, $k) !== false) {
            return $v;
        }
    }
    return 'Browser';
}
function ni_os_name(string $ua): string
{
    foreach (['Windows' => 'Windows', 'Android' => 'Android', 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $v) {
        if (stripos($ua, $k) !== false) {
            return $v;
        }
    }
    return '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Network Access - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .net-hero { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 28px; align-items: center; }
    .net-url-label { font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; color: #5f6f67; font-weight: 700; }
    .net-url { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: clamp(1.1rem, 2.4vw, 1.9rem); font-weight: 700; color: #146c43; word-break: break-all; margin: 6px 0 14px; }
    .net-qr { width: 190px; height: 190px; border: 1px solid #dbe3de; border-radius: 12px; padding: 10px; background: #fff; display: flex; align-items: center; justify-content: center; }
    .net-qr svg { width: 100%; height: 100%; }
    .net-links { display: grid; gap: 8px; margin-top: 6px; }
    .net-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid #e3e9e5; border-radius: 10px; background: #fafcfb; }
    .net-link span.who { width: 92px; flex: none; font-size: .78rem; font-weight: 700; color: #5f6f67; }
    .net-link code { flex: 1; min-width: 0; color: #1d2a24; font-size: .9rem; word-break: break-all; background: none; }
    .net-steps { counter-reset: step; list-style: none; padding: 0; margin: 0; }
    .net-steps li { position: relative; padding: 0 0 14px 40px; color: #334155; font-size: .92rem; }
    .net-steps li::before { counter-increment: step; content: counter(step); position: absolute; left: 0; top: 0; width: 26px; height: 26px; border-radius: 50%; background: #e8f3ed; color: #146c43; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: .8rem; }
    .net-cmd { display: flex; gap: 8px; align-items: center; margin-top: 6px; }
    .net-cmd code { flex: 1; background: #1d2a24; color: #d8f3e4; padding: 8px 10px; border-radius: 8px; font-size: .78rem; word-break: break-all; }
    .net-pick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
    .net-pick button { border: 1px solid #dbe3de; background: #fff; border-radius: 999px; padding: 4px 12px; font-size: .8rem; font-family: ui-monospace, Consolas, monospace; }
    .net-pick button.is-on { background: #198754; border-color: #198754; color: #fff; }
    @media (max-width: 768px) { .net-hero { grid-template-columns: 1fr; } .net-qr { margin: 0 auto; } }
  </style>
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back"><i class="bi bi-arrow-left"></i></a>
          <h1 class="admin-navbar-title">Network Access</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Network Access</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Connect other computers</h2>
        <div class="admin-page-actions">
          <a href="admin_network" class="admin-btn admin-btn-outline"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
        </div>
      </div>

      <?php if ($notice): ?>
        <div class="admin-alert admin-alert-<?= $notice[0] ?>"><i class="bi bi-info-circle-fill"></i><span><?= htmlspecialchars($notice[1]) ?></span></div>
      <?php endif; ?>

      <?php if (!$primary && !$override): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-wifi-off"></i>
          <span>This server is not connected to any network. Connect it to the exam hall's Wi-Fi or switch, then press Refresh.</span>
        </div>
      <?php else: ?>
        <?php if (!$lan && !$override): ?>
          <div class="admin-alert admin-alert-warning">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>No local-network address was found, only <?= htmlspecialchars($addresses[0]['ip']) ?>. Check that the server is connected to the same network as the exam computers.</span>
          </div>
        <?php endif; ?>

        <div class="admin-card mb-4">
          <div class="admin-card-body">
            <div class="net-hero">
              <div>
                <?php if ($override): ?>
                  <div class="admin-badge admin-badge-info mb-2">Address set manually — detected address: <?= htmlspecialchars($primary ?? 'none') ?></div>
                <?php elseif (count($lan) > 1): ?>
                  <div class="net-url-label mb-2">This server has more than one network address — choose the one on the exam network</div>
                  <div class="net-pick" id="netPick">
                    <?php foreach ($lan as $i => $a): ?>
                      <button type="button" class="<?= $i === 0 ? 'is-on' : '' ?>" data-base="<?= htmlspecialchars(ni_base_url($a['ip'])) ?>"><?= htmlspecialchars($a['ip']) ?><?= $a['adapter'] ? ' · ' . htmlspecialchars($a['adapter']) : '' ?></button>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
                <div class="net-url-label">Students type this address in the browser</div>
                <div class="net-url" id="netStudentUrl"><?= htmlspecialchars($primary_base) ?>/student_login</div>
                <div class="d-flex flex-wrap gap-2 mb-3">
                  <button type="button" class="admin-btn admin-btn-primary" data-copy="#netStudentUrl"><i class="bi bi-clipboard"></i> Copy address</button>
                  <a class="admin-btn admin-btn-outline" id="netOpen" target="_blank" href="<?= htmlspecialchars($primary_base) ?>/student_login"><i class="bi bi-box-arrow-up-right"></i> Open to test</a>
                  <button type="button" class="admin-btn admin-btn-outline" onclick="window.print()"><i class="bi bi-printer"></i> Print for the hall</button>
                </div>
                <div class="net-links">
                  <div class="net-link"><span class="who">Staff / Admin</span><code data-path="/login"><?= htmlspecialchars($primary_base) ?>/login</code>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-copy-prev>Copy</button></div>
                  <div class="net-link"><span class="who">Home page</span><code data-path="/"><?= htmlspecialchars($primary_base) ?>/</code>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-copy-prev>Copy</button></div>
                  <?php if ($hostname): ?>
                    <div class="net-link"><span class="who">By name</span><code><?= htmlspecialchars(ni_base_url($hostname)) ?>/student_login</code>
                      <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-copy-prev>Copy</button></div>
                  <?php endif; ?>
                </div>
                <?php if (ni_viewing_locally()): ?>
                  <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle"></i> You are on the server itself, so you see it as <code>localhost</code>. The other computers cannot use <code>localhost</code>; they must use the address above.</p>
                <?php endif; ?>
              </div>
              <div class="text-center">
                <div class="net-qr" id="netQr" aria-label="QR code of the student address"></div>
                <div class="small text-muted mt-2">Scan with a phone or tablet</div>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <div class="admin-card mb-4">
        <div class="admin-card-header">
          <h3 class="admin-card-title"><i class="bi bi-pencil-square"></i> Use a different address</h3>
        </div>
        <div class="admin-card-body">
          <p class="small text-muted mb-3">If the exam network uses another address for this server (for example <code>10.10.0.109</code> instead of the <code>192.168…</code> shown above), enter it here. The dashboard, this page and the QR code will then show that address.</p>
          <form method="post" class="row g-2 align-items-center">
            <?php csrf_field(); ?>
            <div class="col-sm-6 col-lg-4">
              <input type="text" name="override" class="admin-input font-monospace" placeholder="e.g. 10.10.0.109 or 10.10.0.109:8080" value="<?= htmlspecialchars($override ?? '') ?>">
            </div>
            <div class="col-auto"><button type="submit" class="admin-btn admin-btn-primary">Save address</button></div>
            <?php if ($override): ?>
              <div class="col-auto"><button type="submit" name="clear_override" value="1" class="admin-btn admin-btn-outline">Use detected address</button></div>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-6">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-ethernet"></i> Server addresses</h3>
              <span class="admin-badge admin-badge-neutral"><?= htmlspecialchars($hostname) ?><?= $port !== 80 ? ' · port ' . $port : '' ?></span>
            </div>
            <div class="admin-card-body-flush">
              <table class="admin-table mb-0">
                <thead><tr><th>Address</th><th>Adapter</th><th>Type</th></tr></thead>
                <tbody>
                  <?php if (!$addresses): ?>
                    <tr><td colspan="3" class="text-muted">No network adapters with an IPv4 address.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($addresses as $a): [$lbl, $cls] = $kind_label[$a['kind']]; ?>
                    <tr>
                      <td class="font-monospace fw-semibold"><?= htmlspecialchars($a['ip']) ?></td>
                      <td class="small"><?= htmlspecialchars($a['adapter'] ?: '—') ?></td>
                      <td><span class="admin-badge <?= $cls ?>"><?= $lbl ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="admin-card h-100">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-pc-display"></i> Computers seen in the last 15 minutes</h3>
              <?php if ($pending): ?>
                <a href="admin_devices" class="admin-badge admin-badge-warning text-decoration-none"><?= $pending ?> waiting for approval</a>
              <?php endif; ?>
            </div>
            <div class="admin-card-body-flush" style="max-height:340px;overflow:auto;">
              <table class="admin-table mb-0">
                <thead><tr><th>Address</th><th>Device</th><th>Status</th><th>Last seen</th></tr></thead>
                <tbody>
                  <?php if (!$recent): ?>
                    <tr><td colspan="4" class="text-muted">No computer has opened the portal yet. Once a student opens the address, it appears here.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($recent as $d):
                    $st = ['approved' => 'admin-badge-success', 'pending' => 'admin-badge-warning', 'rejected' => 'admin-badge-danger'][$d['status']] ?? 'admin-badge-neutral'; ?>
                    <tr>
                      <td class="font-monospace"><?= htmlspecialchars($d['ip_address']) ?></td>
                      <td class="small"><?= htmlspecialchars(trim(ni_browser_name((string) $d['device_info']) . ' · ' . ni_os_name((string) $d['device_info']), ' ·')) ?></td>
                      <td><span class="admin-badge <?= $st ?>"><?= htmlspecialchars(ucfirst($d['status'])) ?></span></td>
                      <td class="small text-muted"><?= date('H:i:s', strtotime($d['last_seen'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-tools"></i> If a computer cannot open the address</h3>
            </div>
            <div class="admin-card-body">
              <ol class="net-steps">
                <li><strong>Same network.</strong> The student computer must be on the same Wi-Fi or network switch as this server. Phones on mobile data cannot reach it.</li>
                <li><strong>Apache is running.</strong> In the XAMPP Control Panel, Apache should show green.</li>
                <li><strong>Windows Firewall.</strong> Allow Apache through the firewall. Run this once in Command Prompt (as Administrator):
                  <div class="net-cmd"><code id="fwCmd">netsh advfirewall firewall add rule name="XAMPP Apache (exam portal)" dir=in action=allow protocol=TCP localport=<?= $port ?></code>
                    <button type="button" class="admin-btn admin-btn-sm admin-btn-outline" data-copy="#fwCmd">Copy</button></div>
                </li>
                <li><strong>Approve the computer.</strong> New computers wait in <a href="admin_devices">Device Auth</a> until you approve them.</li>
                <li><strong>Keep the address fixed.</strong> The address can change when the router restarts. Ask the network admin for a fixed IP (DHCP reservation) for this server, or set a static IP in Windows network settings.</li>
              </ol>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/qrcode/qrcode.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script>
    (function () {
      var urlEl = document.getElementById('netStudentUrl');
      function drawQr(text) {
        var box = document.getElementById('netQr');
        if (!box || typeof qrcode === 'undefined') return;
        var qr = qrcode(0, 'M');
        qr.addData(text);
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
      }
      if (urlEl) drawQr(urlEl.textContent.trim());

      document.querySelectorAll('#netPick button').forEach(function (b) {
        b.addEventListener('click', function () {
          document.querySelectorAll('#netPick button').forEach(function (x) { x.classList.remove('is-on'); });
          b.classList.add('is-on');
          var base = b.getAttribute('data-base');
          urlEl.textContent = base + '/student_login';
          document.getElementById('netOpen').href = base + '/student_login';
          document.querySelectorAll('code[data-path]').forEach(function (c) { c.textContent = base + c.getAttribute('data-path'); });
          drawQr(base + '/student_login');
        });
      });

      function copy(text, btn) {
        var done = function () {
          var old = btn.innerHTML;
          btn.innerHTML = '<i class="bi bi-check2"></i> Copied';
          setTimeout(function () { btn.innerHTML = old; }, 1500);
        };
        if (navigator.clipboard && window.isSecureContext) {
          navigator.clipboard.writeText(text).then(done);
        } else {
          // plain http on a LAN is not a "secure context", so use the old way
          var t = document.createElement('textarea');
          t.value = text;
          t.style.position = 'fixed';
          t.style.opacity = '0';
          document.body.appendChild(t);
          t.select();
          try { document.execCommand('copy'); done(); } catch (e) {}
          t.remove();
        }
      }
      document.querySelectorAll('[data-copy]').forEach(function (b) {
        b.addEventListener('click', function () { copy(document.querySelector(b.getAttribute('data-copy')).textContent.trim(), b); });
      });
      document.querySelectorAll('[data-copy-prev]').forEach(function (b) {
        b.addEventListener('click', function () { copy(b.previousElementSibling.textContent.trim(), b); });
      });
    })();
  </script>
</body>

</html>
