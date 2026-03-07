<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  $instruction = trim($_POST['login_instruction']);
  $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('login_instruction', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
  $stmt->execute([$instruction, $instruction]);
  $success_msg = "Settings updated successfully!";
}

$login_instruction = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'login_instruction'")->fetchColumn() ?: "Welcome to the Assessment Portal. Use your Matric Number to login.";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Admin Dashboard - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
          <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index.php">
            <img src="assets/favicon.ico" alt="Logo" width="30" height="30">
            <span>Admin Console</span>
          </a>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <button class="btn btn-link position-relative p-0 ms-2" id="navbarChatBell" title="Chat Notifications"
            style="font-size:1.6rem;">
            <span class="bi bi-bell">🔔</span>
            <span id="navbarChatBadge"
              style="display:none;position:absolute;top:0;right:-8px;background:#dc3545;color:#fff;border-radius:50%;padding:2px 7px;font-size:13px;z-index:1100;min-width:22px;text-align:center;"></span>
          </button>
          <a href="logout.php" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>
    <div class="container-xl">
      <div class="text-center mb-4">
        <h2 class="fw-bold">Welcome, Admin</h2>
        <p class="text-muted">Use the sidebar to manage students, questions, exams, and view analytics.</p>
        <?php display_auth_errors(); ?>
      </div>

      <!-- Quick Stats -->
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-2">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Students</h5>
            <div class="display-6 fw-bold text-primary">
              <?= $db->query("SELECT COUNT(*) FROM students")->fetchColumn() ?>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Exams</h5>
            <div class="display-6 fw-bold text-primary"><?= $db->query("SELECT COUNT(*) FROM exams")->fetchColumn() ?>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Active Exams</h5>
            <div class="display-6 fw-bold text-success">
              <?= $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1")->fetchColumn() ?>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Questions</h5>
            <div class="display-6 fw-bold text-primary">
              <?= $db->query("SELECT COUNT(*) FROM questions")->fetchColumn() ?>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-2">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Staff</h5>
            <div class="display-6 fw-bold text-info">
              <?= $db->query("SELECT COUNT(*) FROM examiners WHERE role='examiner'")->fetchColumn() ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions & System Settings -->
      <div class="row g-4 mb-5">
        <div class="col-12 col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-lightning-charge me-2 text-warning"></i>Quick Actions</h4>
            <div class="d-grid gap-2">
              <a href="admin_students.php" class="btn btn-primary rounded-pill py-2">Manage Students</a>
              <a href="admin_staff.php" class="btn btn-dark rounded-pill py-2">Manage Staff / Examiners</a>
              <a href="admin_questions.php" class="btn btn-success rounded-pill py-2">Manage Practical Questions</a>
              <a href="admin_objective_exams.php" class="btn btn-outline-primary fw-bold rounded-pill py-2">Manage
                Objective Questions</a>
              <a href="admin_exams.php" class="btn btn-info rounded-pill py-2">General Exam Settings</a>
              <a href="admin_analytics.php" class="btn btn-warning rounded-pill py-2">View Analytics</a>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-gear me-2 text-secondary"></i>System Settings</h4>
            <?php if ($success_msg): ?>
              <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 py-2 shadow-sm mb-3">
                <i class="bi bi-check-circle-fill me-2"></i><?= $success_msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
              </div>
            <?php endif; ?>
            <form method="post">
              <div class="mb-3">
                <label class="form-label fw-bold small">Student Login Instructions</label>
                <textarea name="login_instruction" class="form-control border-light-subtle bg-light rounded-3" rows="4"
                  required><?= htmlspecialchars($login_instruction) ?></textarea>
                <div class="form-text mt-1 small">This text appears next to the student login form. Use it for help or
                  reminders.</div>
              </div>
              <button type="submit" name="save_settings" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-save me-2"></i> Save Settings
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script>
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
  </script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>