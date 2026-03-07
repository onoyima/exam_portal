<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

// Initialize audit tables if they don't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS audit_trail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        user_type ENUM('admin', 'examiner', 'student') NOT NULL,
        username VARCHAR(100),
        action VARCHAR(255) NOT NULL,
        table_name VARCHAR(100),
        record_id INT,
        old_values TEXT,
        new_values TEXT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_action (user_id, action),
        INDEX idx_created_at (created_at)
    )
");

$db->exec("
    CREATE TABLE IF NOT EXISTS login_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        username VARCHAR(100) NOT NULL,
        user_type ENUM('admin', 'examiner', 'student') NOT NULL,
        login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        logout_time DATETIME NULL,
        ip_address VARCHAR(45),
        user_agent TEXT,
        status ENUM('success', 'failed') DEFAULT 'success',
        INDEX idx_user_login (user_id, login_time)
    )
");

// Get audit trail data
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

$audit_trail = $db->query("
    SELECT * FROM audit_trail 
    ORDER BY created_at DESC 
    LIMIT {$limit} OFFSET {$offset}
")->fetchAll(PDO::FETCH_ASSOC);

$total_records = $db->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Get login history
$login_history = $db->query("
    SELECT * FROM login_history 
    ORDER BY login_time DESC 
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

$stats = [
  'total_actions' => $total_records,
  'today_actions' => $db->query("SELECT COUNT(*) FROM audit_trail WHERE DATE(created_at) = CURDATE()")->fetchColumn(),
  'total_logins' => $db->query("SELECT COUNT(*) FROM login_history")->fetchColumn(),
  'today_logins' => $db->query("SELECT COUNT(*) FROM login_history WHERE DATE(login_time) = CURDATE()")->fetchColumn()
];

// Check for emergency log files (database failover)
$failover_logs = [];
$log_dir = __DIR__ . '/logs';
if (is_dir($log_dir)) {
  $files = glob($log_dir . '/*.log');
  foreach ($files as $file) {
    $failover_logs[] = [
      'name' => basename($file),
      'size' => round(filesize($file) / 1024, 2) . ' KB',
      'date' => date('F j, Y H:i:s', filemtime($file))
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Audit Trail - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .profile-icon {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--primary-color);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: 1.2rem;
    }

    .audit-entry {
      border-left: 4px solid var(--primary-color);
      padding-left: 1rem;
      margin-bottom: 1rem;
    }

    .audit-entry.insert {
      border-left-color: var(--success-color);
    }

    .audit-entry.update {
      border-left-color: var(--warning-color);
    }

    .audit-entry.delete {
      border-left-color: var(--danger-color);
    }

    .audit-entry.login {
      border-left-color: var(--info-color);
    }
  </style>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
              class="navbar-toggler-icon"></span></button>
          <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
          <span class="navbar-brand fw-bold">Audit Trail</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <!-- Statistics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Actions</h5>
            <div class="display-6 fw-bold text-primary"><?= number_format($stats['total_actions']) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Today's Actions</h5>
            <div class="display-6 fw-bold text-success"><?= number_format($stats['today_actions']) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Logins</h5>
            <div class="display-6 fw-bold text-info"><?= number_format($stats['total_logins']) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Today's Logins</h5>
            <div class="display-6 fw-bold text-warning"><?= number_format($stats['today_logins']) ?></div>
          </div>
        </div>
      </div>

      <!-- Failover Logs Warning -->
      <?php if (!empty($failover_logs)): ?>
          <div class="alert alert-warning shadow-sm border-0 rounded-4 mb-4 p-4">
            <div class="d-flex gap-3">
              <i class="bi bi-exclamation-triangle-fill fs-1 text-warning"></i>
              <div>
                <h5 class="fw-bold mb-1">Emergency Logs Detected</h5>
                <p class="mb-3 text-muted">The system detected audit data written to files due to temporary database connection issues. These logs represent actions that occurred during the "Database Failover" period.</p>
                <div class="table-responsive bg-white rounded-3 p-2">
                  <table class="table table-sm align-middle mb-0">
                    <thead class="small text-uppercase fw-bold text-muted">
                      <tr><th>Log File</th><th>Size</th><th>Last Updated</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($failover_logs as $flog): ?>
                        <tr>
                          <td class="fw-bold fs-7"><?= htmlspecialchars($flog['name']) ?></td>
                          <td class="text-muted small"><?= $flog['size'] ?></td>
                          <td class="text-muted small"><?= $flog['date'] ?></td>
                          <td>
                            <a href="logs/<?= htmlspecialchars($flog['name']) ?>" target="_blank" class="btn btn-sm btn-link text-primary fw-bold text-decoration-none">View RAW</a>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
      <?php endif; ?>

      <!-- Audit Trail -->
      <div class="card p-4 shadow-sm mb-4">
        <h4 class="mb-3">Recent Audit Trail</h4>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Type</th>
                <th>Action</th>
                <th>Details</th>
                <th>IP Address</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($audit_trail)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No audit trail entries found. System activity will be logged here.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($audit_trail as $entry): ?>
                  <tr>
                    <td><?= date('M j, Y H:i', strtotime($entry['created_at'])) ?></td>
                    <td>
                      <span class="fw-semibold"><?= htmlspecialchars($entry['username'] ?? 'System') ?></span>
                      <br><small class="text-muted"><?= ucfirst($entry['user_type']) ?></small>
                    </td>
                    <td>
                      <span
                        class="badge bg-<?= $entry['user_type'] == 'admin' ? 'danger' : ($entry['user_type'] == 'examiner' ? 'primary' : 'success') ?>">
                        <?= ucfirst($entry['user_type']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="fw-semibold"><?= htmlspecialchars($entry['action']) ?></span>
                      <?php if ($entry['table_name']): ?>
                        <br><small class="text-muted"><?= htmlspecialchars($entry['table_name']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($entry['new_values']): ?>
                        <button class="btn btn-sm btn-outline-info"
                          onclick="showDetails('<?= htmlspecialchars($entry['new_values']) ?>')">
                          View Details
                        </button>
                      <?php endif; ?>
                    </td>
                    <td>
                      <small class="text-muted"><?= htmlspecialchars($entry['ip_address'] ?? 'N/A') ?></small>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <nav aria-label="Audit trail pagination">
            <ul class="pagination justify-content-center">
              <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      </div>

      <!-- Login History -->
      <div class="card p-4 shadow-sm">
        <h4 class="mb-3">Recent Login History</h4>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Time</th>
                <th>User</th>
                <th>Type</th>
                <th>Status</th>
                <th>IP Address</th>
                <th>Duration</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($login_history)): ?>
                <tr>
                  <td colspan="6" class="text-center text-muted py-4">
                    <i class="fas fa-info-circle me-2"></i>
                    No login history found. User logins will be tracked here.
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($login_history as $login): ?>
                  <tr>
                    <td><?= date('M j, Y H:i', strtotime($login['login_time'])) ?></td>
                    <td>
                      <span class="fw-semibold"><?= htmlspecialchars($login['username']) ?></span>
                    </td>
                    <td>
                      <span
                        class="badge bg-<?= $login['user_type'] == 'admin' ? 'danger' : ($login['user_type'] == 'examiner' ? 'primary' : 'success') ?>">
                        <?= ucfirst($login['user_type']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-<?= $login['status'] == 'success' ? 'success' : 'danger' ?>">
                        <?= ucfirst($login['status']) ?>
                      </span>
                    </td>
                    <td>
                      <small class="text-muted"><?= htmlspecialchars($login['ip_address'] ?? 'N/A') ?></small>
                    </td>
                    <td>
                      <?php if ($login['logout_time']): ?>
                        <?php
                        $duration = strtotime($login['logout_time']) - strtotime($login['login_time']);
                        $hours = floor($duration / 3600);
                        $minutes = floor(($duration % 3600) / 60);
                        echo "{$hours}h {$minutes}m";
                        ?>
                      <?php else: ?>
                        <span class="badge bg-warning">Active</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- WhatsApp Chat Icon -->
  <a href="https://wa.me/+2348168438930?text=Hi%20BMXcode%20Initiator,%20I%20need%20help%20with%20the%20Assessment%20Portal"
    class="whatsapp-chat" target="_blank" title="Contact Developer"></a>

  <!-- Footer -->
  <footer class="footer">
    <div class="footer-content">
      <h5>Assessment Portal</h5>
      <p>Comprehensive student assessment and examination management system</p>
      <div class="developer-info">
        <p class="mb-0"><strong>Developed by:</strong> BMXcode Initiator</p>
        <p class="mb-0"><strong>Version:</strong> 2.0 Enhanced</p>
        <p class="mb-0"><strong>Features:</strong> Responsive Design, Audit Trail, Real-time Chat</p>
      </div>
    </div>
  </footer>

  <!-- Details Modal -->
  <div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Audit Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <pre id="detailsContent"></pre>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.onclick = function () {
        sidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarBackdrop.onclick = function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      };
    }

    // Show details modal
    function showDetails(data) {
      try {
        const parsed = JSON.parse(data);
        document.getElementById('detailsContent').textContent = JSON.stringify(parsed, null, 2);
      } catch (e) {
        document.getElementById('detailsContent').textContent = data;
      }
      new bootstrap.Modal(document.getElementById('detailsModal')).show();
    }
  </script>
<!-- sweetalert_custom_injected_v1 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global alert
        window.alert = function(msg) {
            Swal.fire({
                title: 'Message',
                text: msg,
                icon: 'info',
                confirmButtonColor: '#092c19'
            });
        };

        // Intercept inline onsubmit confirms
        document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') 
                                      || el.querySelector('button[type="submit"], input[type="submit"]');
                            if(btn && btn.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = btn.name;
                                hidden.value = btn.value || '1';
                                el.appendChild(hidden);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
            }
        });

        // Intercept inline onclick confirms for anchors
        document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            if(el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else if(el.tagName === 'BUTTON') {
                                // if it's a submit button inside a form
                                if(el.type === 'submit' && el.form) {
                                    if(el.name) {
                                        let hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = el.name;
                                        hidden.value = el.value || '1';
                                        el.form.appendChild(hidden);
                                    }
                                    HTMLFormElement.prototype.submit.call(el.form);
                                }
                            }
                        }
                    });
                });
            }
        });
    });
</script>
</body>


</html>
