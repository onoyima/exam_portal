<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

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
  <title>Device Authorization - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .device-card { border-radius: 12px; border: 1px solid rgba(0,0,0,0.07); transition: box-shadow 0.2s; }
    .device-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
    .status-badge { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.05em; padding: 4px 10px; border-radius: 20px; }
    .badge-pending  { background: rgba(251,191,36,0.15); color: #b45309; }
    .badge-approved { background: rgba(34,197,94,0.15);  color: #15803d; }
    .badge-rejected { background: rgba(239,68,68,0.15);  color: #b91c1c; }
    .ip-code { font-family: monospace; font-size: 1rem; font-weight: 700; color: #0f172a; }
    .pulse-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 6px; }
    .dot-pending  { background: #f59e0b; animation: pulse 2s infinite; }
    .dot-approved { background: #22c55e; }
    .dot-rejected { background: #ef4444; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
  </style>
</head>
<body class="bg-light">
<?php include 'admin_sidebar.php'; ?>
<div class="main-content">
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold text-primary"><i class="bi bi-hdd-network-fill me-2"></i>Device Authorization</span>
      <div class="ms-auto d-flex gap-2">
        <button class="btn btn-success btn-sm rounded-pill fw-bold px-4" onclick="approveAll()">
          <i class="bi bi-check-all me-1"></i> Approve All Pending
        </button>
        <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" onclick="location.reload()">
          <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
    </div>
  </nav>

  <div class="container-xl">
    <!-- Stats Row -->
    <div class="row g-3 mb-4">
      <?php
        $stats = $db->query("SELECT status, COUNT(*) as cnt FROM approved_devices GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
        $pending  = $stats['pending']  ?? 0;
        $approved = $stats['approved'] ?? 0;
        $rejected = $stats['rejected'] ?? 0;
        $total    = $pending + $approved + $rejected;
      ?>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
          <div class="display-6 fw-bold text-primary"><?= $total ?></div>
          <div class="text-muted small fw-bold mt-1">TOTAL DEVICES</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
          <div class="display-6 fw-bold text-warning"><?= $pending ?></div>
          <div class="text-muted small fw-bold mt-1">PENDING</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
          <div class="display-6 fw-bold text-success"><?= $approved ?></div>
          <div class="text-muted small fw-bold mt-1">APPROVED</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
          <div class="display-6 fw-bold text-danger"><?= $rejected ?></div>
          <div class="text-muted small fw-bold mt-1">REJECTED</div>
        </div>
      </div>
    </div>

    <!-- Device List -->
    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
      <div class="card-header bg-white border-0 p-4 d-flex justify-content-between align-items-center">
        <h5 class="mb-0 fw-bold"><i class="bi bi-list-check me-2 text-primary"></i>All Connected Devices</h5>
        <div class="d-flex gap-2">
          <select id="filterStatus" class="form-select form-select-sm rounded-3 border-0 bg-light" onchange="filterDevices(this.value)" style="width:auto;">
            <option value="all">All Status</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" id="devicesTable">
            <thead class="bg-light">
              <tr class="small text-uppercase text-muted" style="font-size:0.7rem; letter-spacing:0.05em;">
                <th class="ps-4 py-3 border-0">Status</th>
                <th class="py-3 border-0">IP Address</th>
                <th class="py-3 border-0">Device Info</th>
                <th class="py-3 border-0">Last Seen</th>
                <th class="py-3 border-0 text-end pe-4">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $devices = $db->query("SELECT * FROM approved_devices ORDER BY FIELD(status,'pending','rejected','approved'), last_seen DESC")->fetchAll(PDO::FETCH_ASSOC);
              if (empty($devices)):
              ?>
              <tr><td colspan="5" class="text-center py-5 text-muted">
                <i class="bi bi-hdd-network display-4 d-block mb-3"></i>
                No devices registered yet. Devices will appear here when students attempt to access the portal.
              </td></tr>
              <?php else: foreach ($devices as $d): ?>
              <tr data-status="<?= $d['status'] ?>">
                <td class="ps-4 py-3">
                  <span class="pulse-dot dot-<?= $d['status'] ?>"></span>
                  <span class="status-badge badge-<?= $d['status'] ?>"><?= strtoupper($d['status']) ?></span>
                </td>
                <td class="py-3">
                  <div class="ip-code"><?= htmlspecialchars($d['ip_address']) ?></div>
                  <?php if ($d['mac_address']): ?>
                    <div class="text-muted" style="font-size:0.72rem;">MAC: <?= htmlspecialchars($d['mac_address']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="py-3">
                  <div class="text-muted" style="font-size:0.78rem; max-width:280px;" title="<?= htmlspecialchars($d['device_info'] ?? '') ?>">
                    <?= htmlspecialchars(substr($d['device_info'] ?? 'Unknown', 0, 60)) ?><?= strlen($d['device_info'] ?? '') > 60 ? '...' : '' ?>
                  </div>
                </td>
                <td class="py-3">
                  <div class="small text-muted"><?= date('M j, Y H:i', strtotime($d['last_seen'])) ?></div>
                </td>
                <td class="py-3 text-end pe-4">
                  <div class="d-flex justify-content-end gap-2">
                    <?php if ($d['status'] !== 'approved'): ?>
                    <button class="btn btn-sm btn-success rounded-pill fw-bold px-3"
                      onclick="deviceAction('approve','<?= htmlspecialchars($d['ip_address']) ?>', this)">
                      <i class="bi bi-check-circle-fill me-1"></i>Approve
                    </button>
                    <?php endif; ?>
                    <?php if ($d['status'] !== 'rejected'): ?>
                    <button class="btn btn-sm btn-outline-danger rounded-pill fw-bold px-3"
                      onclick="deviceAction('reject','<?= htmlspecialchars($d['ip_address']) ?>', this)">
                      <i class="bi bi-x-circle-fill me-1"></i>Reject
                    </button>
                    <?php endif; ?>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3"
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

    <div class="mt-4 p-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 rounded-4">
      <h6 class="fw-bold text-warning-emphasis mb-2"><i class="bi bi-shield-exclamation me-2"></i>How Device Authorization Works</h6>
      <p class="mb-0 small text-muted">
        When a student accesses the exam portal from a new device, it is automatically logged here as <strong>Pending</strong>.
        Only <strong>Approved</strong> devices can access exams. <strong>Rejected</strong> devices are blocked with an access-denied screen.
        You can approve all pending devices at once using the button above, or approve/reject individually.
      </p>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  async function deviceAction(action, ip, btn) {
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
