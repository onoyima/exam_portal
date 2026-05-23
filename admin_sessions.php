<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_session'])) {
        $session_name = trim($_POST['session_name']);
        if (!empty($session_name)) {
            $stmt = $db->prepare("INSERT INTO academic_sessions (session_name, is_active) VALUES (?, 0)");
            $stmt->execute([$session_name]);
            $success = "Session '$session_name' created successfully.";
        } else {
            $error = "Session name cannot be empty.";
        }
    } elseif (isset($_POST['set_active'])) {
        $id = intval($_POST['session_id']);
        // Deactivate all others first
        $db->query("UPDATE academic_sessions SET is_active = 0");
        $stmt = $db->prepare("UPDATE academic_sessions SET is_active = 1 WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Active session updated.";
    } elseif (isset($_POST['delete_session'])) {
        $id = intval($_POST['session_id']);
        // Check if active exams are linked
        $check = $db->prepare("SELECT COUNT(*) FROM exams WHERE session_id = ? AND deleted_at IS NULL");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $error = "Cannot delete session with linked exams. Reassign exams first.";
        } else {
            $stmt = $db->prepare("UPDATE academic_sessions SET deleted_at=NOW() WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Session deleted.";
        }
    }
}

$sessions = $db->query("SELECT * FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$back_url = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Academic Sessions - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <?php if (isset($back_url)): ?>
            <a href="<?= $back_url ?>" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a>
          <?php endif; ?>
          <h1 class="admin-navbar-title">Academic Sessions</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Sessions</li>
        </ol>
      </nav>

      <?php if (isset($success)): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($success) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($error)): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= htmlspecialchars($error) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Create Session -->
        <div class="col-md-4">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-plus-circle"></i> Add New Session</h3>
            </div>
            <div class="admin-card-body">
              <form method="post">
                <div class="mb-3">
                  <label class="form-label small fw-bold text-muted">Session Name</label>
                  <input type="text" name="session_name" class="form-control" placeholder="e.g. 2023/2024 First Semester" required>
                </div>
                <button type="submit" name="create_session" class="admin-btn admin-btn-primary w-100">
                  <i class="bi bi-plus-lg"></i> Create Session
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Session List -->
        <div class="col-md-8">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-calendar-event"></i> All Academic Sessions</h3>
            </div>
            <div class="admin-card-body p-0">
              <div class="table-responsive">
                <table class="admin-table">
                  <thead>
                    <tr>
                      <th>Session Name</th>
                      <th>Status</th>
                      <th class="text-end">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($sessions as $s): ?>
                    <tr>
                      <td class="fw-bold"><?= htmlspecialchars($s['session_name']) ?></td>
                      <td>
                        <?php if ($s['is_active']): ?>
                          <span class="badge bg-success rounded-pill px-3">ACTIVE</span>
                        <?php else: ?>
                          <span class="badge bg-light text-muted rounded-pill px-3">INACTIVE</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <div class="d-flex justify-content-end gap-2">
                          <?php if (!$s['is_active']): ?>
                          <form method="post" class="d-inline">
                            <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="set_active" class="btn btn-sm btn-outline-success rounded-pill px-3">Make Active</button>
                          </form>
                          <?php endif; ?>
                          <form method="post" class="d-inline" data-swal="Delete this session?">
                            <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                            <button type="submit" name="delete_session" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash"></i></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
