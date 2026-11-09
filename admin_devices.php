<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

// ── CSV downloads: the current list, or one archived reset ──
if (isset($_GET['export'])) {
    header('Content-Type: text/csv; charset=utf-8');
    if ($_GET['export'] === 'log' && !empty($_GET['at'])) {
        $at = date('Y-m-d H:i:s', strtotime($_GET['at']));
        header('Content-Disposition: attachment; filename="approved_computers_' . date('Y-m-d_His', strtotime($at)) . '.csv"');
        $rows = $db->prepare("SELECT ip_address, status, device_info, first_seen, last_seen, reset_at, reset_by FROM device_approval_log WHERE reset_at = ? ORDER BY status, ip_address");
        $rows->execute([$at]);
        $rows = $rows->fetchAll(PDO::FETCH_ASSOC);
    } else {
        header('Content-Disposition: attachment; filename="computers_' . date('Y-m-d_His') . '.csv"');
        $rows = $db->query("SELECT ip_address, status, device_info, created_at AS first_seen, last_seen FROM approved_devices ORDER BY status, ip_address")->fetchAll(PDO::FETCH_ASSOC);
    }
    $out = fopen('php://output', 'w');
    fputcsv($out, $rows ? array_keys($rows[0]) : ['ip_address', 'status', 'device_info', 'first_seen', 'last_seen']);
    foreach ($rows as $r) {
        fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ── AJAX Actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $ip  = $_POST['ip_address'] ?? '';
    $action = $_GET['action'];

    if ($action === 'approve') {
        $db->prepare("UPDATE approved_devices SET status='approved' WHERE ip_address=?")->execute([$ip]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'reject') {
        $db->prepare("UPDATE approved_devices SET status='rejected' WHERE ip_address=?")->execute([$ip]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'approve_all') {
        $db->exec("UPDATE approved_devices SET status='approved' WHERE status='pending'");
        echo json_encode(['success' => true]);
    } elseif ($action === 'reset') {
        // Archive every approval, then clear the list so the next exam starts
        // from zero. The server's own browser is kept so the admin is not locked out.
        $db->beginTransaction();
        $at = date('Y-m-d H:i:s');
        $db->prepare("INSERT INTO device_approval_log (reset_at, reset_by, ip_address, device_info, status, first_seen, last_seen)
            SELECT ?, ?, ip_address, device_info, status, created_at, last_seen FROM approved_devices WHERE ip_address NOT IN ('127.0.0.1', '::1')")
            ->execute([$at, $name]);
        $n = $db->exec("DELETE FROM approved_devices WHERE ip_address NOT IN ('127.0.0.1', '::1')");
        $db->commit();
        echo json_encode(['success' => true, 'cleared' => $n, 'at' => $at]);
    } elseif ($action === 'delete') {
        $db->prepare("DELETE FROM approved_devices WHERE ip_address=?")->execute([$ip]);
        echo json_encode(['success' => true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Device Authorization - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main-content">
  <nav class="admin-navbar">
    <div class="admin-navbar-inner">
      <div class="admin-navbar-start">
        <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </button>
        <a href="<?= $back_url ?>" class="admin-back-btn">
          <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="admin-navbar-title">Device Authorization</h1>
      </div>
      <div class="admin-navbar-end">
        <div class="admin-user-badge">
          <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
          <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
        </div>
        <a href="logout" class="admin-logout-btn" title="Logout">
          <i class="bi bi-box-arrow-right"></i>
        </a>
      </div>
    </div>
  </nav>

  <div class="admin-content">
    <nav class="admin-breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
        <li class="sep">/</li>
        <li class="current" aria-current="page">Device Auth</li>
      </ol>
    </nav>

    <div class="admin-page-header">
      <h2 class="admin-page-title">Device Authorization</h2>
      <div class="admin-page-actions">
        <button class="admin-btn admin-btn-primary" onclick="approveAll()">
          <i class="bi bi-check-all me-1"></i> Approve All Pending
        </button>
        <a class="admin-btn admin-btn-outline" href="admin_devices?export=current"><i class="bi bi-download me-1"></i> Download list</a>
        <button class="admin-btn admin-btn-danger" onclick="resetApprovals()">
          <i class="bi bi-arrow-counterclockwise me-1"></i> Reset for next exam
        </button>
        <button class="admin-btn admin-btn-outline" onclick="location.reload()" title="Refresh">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
    </div>

  <div class="container-xl">
    <div class="row g-3 mb-4">
      <?php
        $stats = $db->query("SELECT status, COUNT(*) as cnt FROM approved_devices GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        $pending  = $stats['pending']  ?? 0;
        $approved = $stats['approved'] ?? 0;
        $rejected = $stats['rejected'] ?? 0;
        $total    = $pending + $approved + $rejected;
      ?>
      <div class="col-md-3">
        <div class="admin-stat-card">
          <div class="admin-stat-value"><?= $total ?></div>
          <div class="admin-stat-label">TOTAL DEVICES</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="admin-stat-card">
          <div class="admin-stat-value"><?= $pending ?></div>
          <div class="admin-stat-label">PENDING</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="admin-stat-card">
          <div class="admin-stat-value"><?= $approved ?></div>
          <div class="admin-stat-label">APPROVED</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="admin-stat-card">
          <div class="admin-stat-value"><?= $rejected ?></div>
          <div class="admin-stat-label">REJECTED</div>
        </div>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-header d-flex justify-content-between align-items-center">
        <h3 class="admin-card-title mb-0"><i class="bi bi-list-check me-2"></i>All Connected Devices</h3>
        <select id="filterStatus" class="admin-filter-input" onchange="filterDevices(this.value)">
          <option value="all">All Status</option>
          <option value="pending">Pending</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div class="admin-card-body-flush">
        <div class="table-responsive">
          <table class="admin-table" id="devicesTable">
            <thead>
              <tr>
                <th>Status</th>
                <th>IP Address</th>
                <th>Device Info</th>
                <th>Last Seen</th>
                <th class="actions-cell">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $devices = $db->query("SELECT * FROM approved_devices ORDER BY FIELD(status,'pending','rejected','approved'), last_seen DESC")->fetchAll(PDO::FETCH_ASSOC);
              if (empty($devices)):
              ?>
              <tr><td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-hdd-network big-icon d-block mb-3"></i>
                No devices registered yet.
              </td></tr>
              <?php else: foreach ($devices as $d): ?>
              <tr data-status="<?= $d['status'] ?>">
                <td>
                  <span class="admin-badge admin-badge-<?= $d['status'] === 'approved' ? 'success' : ($d['status'] === 'pending' ? 'warning' : 'danger') ?>">
                    <i class="bi bi-<?= $d['status'] === 'approved' ? 'check-circle' : ($d['status'] === 'pending' ? 'clock' : 'x-circle') ?>"></i>
                    <?= strtoupper($d['status']) ?>
                  </span>
                </td>
                <td>
                  <code><?= htmlspecialchars($d['ip_address']) ?></code>
                  <?php if ($d['mac_address']): ?>
                    <div class="text-muted small">MAC: <?= htmlspecialchars($d['mac_address']) ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <span class="small text-muted" title="<?= htmlspecialchars($d['device_info'] ?? '') ?>">
                    <?= htmlspecialchars(substr($d['device_info'] ?? 'Unknown', 0, 60)) ?><?= strlen($d['device_info'] ?? '') > 60 ? '...' : '' ?>
                  </span>
                </td>
                <td>
                  <span class="small text-muted"><?= date('M j, Y H:i', strtotime($d['last_seen'])) ?></span>
                </td>
                <td class="actions-cell">
                  <div class="d-flex justify-content-end gap-2">
                    <?php if ($d['status'] !== 'approved'): ?>
                    <button class="admin-btn admin-btn-sm admin-btn-success"
                      onclick="deviceAction('approve','<?= htmlspecialchars($d['ip_address']) ?>', this)">
                      <i class="bi bi-check-circle-fill me-1"></i>Approve
                    </button>
                    <?php endif; ?>
                    <?php if ($d['status'] !== 'rejected'): ?>
                    <button class="admin-btn admin-btn-sm admin-btn-outline-danger"
                      onclick="deviceAction('reject','<?= htmlspecialchars($d['ip_address']) ?>', this)">
                      <i class="bi bi-x-circle-fill me-1"></i>Reject
                    </button>
                    <?php endif; ?>
                    <button class="admin-btn admin-btn-sm admin-btn-outline"
                      onclick="deviceAction('delete','<?= htmlspecialchars($d['ip_address']) ?>', this)">
                      <i class="bi bi-trash3"></i>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-shield-exclamation me-2"></i>How Device Authorization Works</h3></div>
      <div class="admin-card-body">
        <p class="mb-0 small text-muted">
          When a student accesses the exam portal from a new device, it is automatically logged here as <strong>Pending</strong>.
          Only <strong>Approved</strong> devices can access exams. <strong>Rejected</strong> devices are blocked with an access-denied screen.
          You can approve all pending devices at once using the button above, or approve/reject individually.
        </p>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<?php
  $resets = $db->query("SELECT reset_at, reset_by, COUNT(*) AS n,
        SUM(status = 'approved') AS approved, SUM(status = 'pending') AS pending, SUM(status = 'rejected') AS rejected
      FROM device_approval_log GROUP BY reset_at, reset_by ORDER BY reset_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="admin-content pt-0">
  <div class="admin-card">
    <div class="admin-card-header">
      <h3 class="admin-card-title mb-0"><i class="bi bi-clock-history me-2"></i>Previous resets</h3>
      <span class="small text-muted">Each reset keeps a record of the computers that had been allowed, so you can trace them later.</span>
    </div>
    <div class="admin-card-body-flush">
      <table class="admin-table mb-0">
        <thead><tr><th>Reset at</th><th>By</th><th>Approved</th><th>Pending</th><th>Rejected</th><th class="actions-cell">Record</th></tr></thead>
        <tbody>
          <?php if (!$resets): ?>
            <tr><td colspan="6" class="text-muted">No resets yet. Use <strong>Reset for next exam</strong> after an exam to clear every approval.</td></tr>
          <?php endif; ?>
          <?php foreach ($resets as $r): ?>
            <tr>
              <td><?= date('j M Y, g:i A', strtotime($r['reset_at'])) ?></td>
              <td><?= htmlspecialchars($r['reset_by'] ?? '') ?></td>
              <td><?= (int) $r['approved'] ?></td>
              <td><?= (int) $r['pending'] ?></td>
              <td><?= (int) $r['rejected'] ?></td>
              <td class="actions-cell"><a class="admin-btn admin-btn-sm admin-btn-outline" href="admin_devices?export=log&amp;at=<?= urlencode($r['reset_at']) ?>"><i class="bi bi-download"></i> CSV</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-swal.js"></script>
<script src="assets/js/admin-common.js"></script>
<script>
  async function deviceAction(action, ip, btn) {
    if (action === 'delete') {
      const result = await Swal.fire({
        title: 'Delete this device?',
        text: 'This will remove the device from the approved list permanently.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Yes, Delete'
      });
      if (!result.isConfirmed) return;
    }
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const fd = new FormData();
    fd.append('ip_address', ip);
    await fetch(`admin_devices?action=${action}`, { method: 'POST', body: fd });
    location.reload();
  }

  async function approveAll() {
    const result = await Swal.fire({
      title: 'Approve All Pending?',
      text: 'This will approve all devices currently in the pending queue.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: '#15803d',
      confirmButtonText: 'Yes, Approve All'
    });
    if (result.isConfirmed) {
      const fd = new FormData();
      await fetch('admin_devices?action=approve_all', { method: 'POST', body: fd });
      location.reload();
    }
  }

  async function resetApprovals() {
    const result = await Swal.fire({
      title: 'Reset all approvals?',
      html: 'Every computer will need approval again before it can open the portal.<br>A record of the current list is kept under <b>Previous resets</b>.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#dc3545',
      confirmButtonText: 'Yes, reset'
    });
    if (!result.isConfirmed) return;
    const res = await fetch('admin_devices?action=reset', { method: 'POST', body: new FormData() });
    const data = await res.json().catch(() => ({}));
    await Swal.fire({ title: 'Approvals cleared', text: (data.cleared || 0) + ' computer(s) removed. Their record has been saved.', icon: 'success' });
    location.reload();
  }

  function filterDevices(status) {
    document.querySelectorAll('#devicesTable tbody tr[data-status]').forEach(row => {
      row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
    });
  }

  // Auto-refresh every 30 seconds to catch new devices
  setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
