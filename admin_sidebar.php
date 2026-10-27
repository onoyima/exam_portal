<?php
$admin_logo = 'assets/favicon.ico';
$admin_title = 'Admin';
if (isset($db)) {
    try {
        $sidebar_settings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('portal_logo', 'portal_title')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($sidebar_settings['portal_logo']) && file_exists($sidebar_settings['portal_logo'])) {
            $admin_logo = $sidebar_settings['portal_logo'];
        }
        if (!empty($sidebar_settings['portal_title'])) {
            $admin_title = $sidebar_settings['portal_title'];
        }
    } catch (Exception $e) {}
}
$current_page = basename($_SERVER['PHP_SELF']);

// icon definitions (full SVG elements)
$S = '<svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
$E = '</svg>';
$ICONS = [];
$ICONS['dashboard']      = $S.'<rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/>'.$E;
$ICONS['analytics']      = $S.'<rect x="3" y="13" width="3" height="4" rx=".6"/><rect x="8.5" y="9" width="3" height="8" rx=".6"/><rect x="14" y="5" width="3" height="12" rx=".6"/>'.$E;
$ICONS['people']         = $S.'<circle cx="7" cy="6" r="2.5"/><path d="M2 17c0-3.3 2.2-6 5-6s5 2.7 5 6"/><circle cx="14" cy="6" r="2.5"/><path d="M9 17c0-3.3 2.2-6 5-6s5 2.7 5 6"/>'.$E;
$ICONS['id']             = $S.'<rect x="3" y="3" width="14" height="14" rx="2"/><circle cx="8" cy="8.5" r="1.8"/><path d="M3 14c0-2 1-3.5 3-3.5h4c2 0 3 1.5 3 3.5"/><path d="M14 7h2M14 9.5h1.5"/>'.$E;
$ICONS['calendar']       = $S.'<rect x="2" y="3" width="16" height="15" rx="2"/><path d="M2 7h16"/><path d="M6.5 1v3"/><path d="M13.5 1v3"/><circle cx="10" cy="12" r="1.5"/>'.$E;
$ICONS['gear']           = $S.'<circle cx="10" cy="10" r="2.5"/><path d="M10 2v2M10 16v2M3.3 5.5l1.7 1M15 13.5l1.7 1M2 10h2M16 10h2M3.3 14.5l1.7-1M15 6.5l1.7-1" stroke-width="1.3"/>'.$E;
$ICONS['list-check']     = $S.'<rect x="3" y="3" width="14" height="14" rx="2"/><path d="m6.5 10 2.5 2.5 4-5"/>'.$E;
$ICONS['mic']            = $S.'<rect x="7.5" y="2" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0"/><path d="M10 15v3"/><path d="M7 18h6"/>'.$E;
$ICONS['book']           = $S.'<path d="M3 3h5a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H3V3Z"/><path d="M17 3h-5a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h5V3Z"/>'.$E;
$ICONS['upload']         = $S.'<path d="M6 13V8H3l7-7 7 7h-3v5H6Z"/><path d="M3 18h14"/>'.$E;
$ICONS['clipboard-check'] = $S.'<rect x="5" y="2" width="10" height="18" rx="2"/><path d="M8 2H7a1 1 0 0 0-1 1v1h8V3a1 1 0 0 0-1-1H8Z"/><path d="m7.5 11 2.5 2.5 4-5"/>'.$E;
$ICONS['check-circle']   = $S.'<circle cx="10" cy="10" r="8"/><path d="m6.5 10 2.5 2.5 5-5"/>'.$E;
$ICONS['sigma']          = $S.'<rect x="3" y="2" width="14" height="18" rx="2"/><path d="M5 6h10M5 10h10M5 14h10"/><path d="M5 18h10"/>'.$E;
$ICONS['file']           = $S.'<path d="M5 2h6l4 4v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M11 2v4h4"/><path d="M7 10h6"/><path d="M7 14h4"/>'.$E;
$ICONS['chart']          = $S.'<rect x="3" y="12" width="3" height="6" rx=".6"/><rect x="8.5" y="7" width="3" height="11" rx=".6"/><rect x="14" y="3" width="3" height="15" rx=".6"/>'.$E;
$ICONS['broadcast']      = $S.'<path d="M17 4a14 14 0 0 0-14 0"/><path d="M14.5 7a9 9 0 0 0-9 0"/><path d="M12 10a5 5 0 0 0-4 0"/><circle cx="10" cy="14" r="1.5"/>'.$E;
$ICONS['devices']        = $S.'<rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M8 14v3"/><path d="M4 17h8"/><rect x="15" y="11" width="5" height="8" rx="1" transform="rotate(15 17 15)"/>'.$E;
$ICONS['shield']         = $S.'<path d="M10 2 3 5.5v5.5c0 5 3.5 8.5 7 10 3.5-1.5 7-5 7-10V5.5L10 2Z"/><path d="m7.5 10.5 2 2 4-4"/>'.$E;
$ICONS['chat']           = $S.'<path d="M17 8a6 6 0 0 0-6-6H7a6 6 0 0 0-6 6v4a6 6 0 0 0 6 6h2l4 3v-3h2a6 6 0 0 0 6-6V8Z"/>'.$E;
$ICONS['palette']        = $S.'<circle cx="10" cy="10" r="7.5"/><circle cx="6.5" cy="8" r="1.3" opacity=".8"/><circle cx="12" cy="6.5" r="1.3" opacity=".8"/><circle cx="15" cy="11" r="1.3" opacity=".8"/><circle cx="11.5" cy="14.5" r="1.3" opacity=".8"/><path d="M8.5 10.5a2.5 2.5 0 0 0 5 0"/>'.$E;
$ICONS['eye']            = $S.'<path d="M10 4C4.5 4 1 10 1 10s3.5 6 9 6 9-6 9-6-3.5-6-9-6Z"/><circle cx="10" cy="10" r="3"/>'.$E;
$ICONS['trash']          = $S.'<path d="M3 5.5h14"/><path d="M5.5 5.5v10a2 2 0 0 0 2 2h5a2 2 0 0 0 2-2v-10"/><path d="M8 5.5v-2a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v2"/><path d="M9.5 9v5"/><path d="M12.5 9v1.5"/>'.$E;
$ICONS['chevron-left']   = $S.'<path d="m12 5-5 5 5 5"/>'.$E;
$ICONS['chevron-right']  = $S.'<path d="m8 5 5 5-5 5"/>'.$E;
$ICONS['chevron-down']   = $S.'<path d="m5 7 5 5 5-5"/>'.$E;
$ICONS['logout']         = $S.'<path d="M9 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4"/><path d="M15 7l4 3-4 3"/><path d="M8 10h11"/>'.$E;
$ICONS['clipboard']      = $S.'<rect x="5" y="2" width="10" height="18" rx="2"/><path d="M8 2H7a1 1 0 0 0-1 1v1h8V3a1 1 0 0 0-1-1H8Z"/><path d="M7 9h6"/><path d="M7 12h6"/><path d="M7 15h4"/>'.$E;
?>
<!-- Sidebar -->
<div class="sidebar" id="sidebarMenu">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="<?= htmlspecialchars($admin_logo) ?>" alt="Logo">
    </div>
    <div class="brand-text" data-label>
      <span class="brand-name"><?= htmlspecialchars($admin_title) ?></span>
      <span class="brand-role">Administrator</span>
    </div>
  </div>

  <div class="sidebar-divider"></div>

  <nav class="sidebar-nav">
    <ul class="nav-list">

      <!-- ── Overview ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navOverview">
          <span class="group-label" data-label>Overview</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navOverview">
          <ul class="nav-sub">
            <li><a href="admin_dashboard" class="nav-item<?= $current_page == 'admin_dashboard.php' ? ' active' : '' ?>">
              <?= $ICONS['dashboard'] ?><span data-label>Dashboard</span></a></li>
            <li><a href="admin_analytics" class="nav-item<?= $current_page == 'admin_analytics.php' ? ' active' : '' ?>">
              <?= $ICONS['analytics'] ?><span data-label>Analytics</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── User Management ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navUsers">
          <span class="group-label" data-label>User Management</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navUsers">
          <ul class="nav-sub">
            <li><a href="admin_students" class="nav-item<?= $current_page == 'admin_students.php' ? ' active' : '' ?>">
              <?= $ICONS['people'] ?><span data-label>Manage Students</span></a></li>
            <li><a href="admin_staff" class="nav-item<?= $current_page == 'admin_staff.php' ? ' active' : '' ?>">
              <?= $ICONS['id'] ?><span data-label>Manage Staff</span></a></li>
            <li><a href="admin_roles" class="nav-item<?= $current_page == 'admin_roles.php' ? ' active' : '' ?>">
              <?= $ICONS['shield'] ?><span data-label>Role Assignment</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Exam Management ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navExams">
          <span class="group-label" data-label>Exam Management</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navExams">
          <ul class="nav-sub">
            <li><a href="admin_sessions" class="nav-item<?= $current_page == 'admin_sessions.php' ? ' active' : '' ?>">
              <?= $ICONS['calendar'] ?><span data-label>Manage Sessions</span></a></li>
            <li><a href="admin_exams" class="nav-item<?= $current_page == 'admin_exams.php' ? ' active' : '' ?>">
              <?= $ICONS['gear'] ?><span data-label>Manage Practical</span></a></li>
            <li><a href="admin_objective_exams" class="nav-item<?= $current_page == 'admin_objective_exams.php' ? ' active' : '' ?>">
              <?= $ICONS['list-check'] ?><span data-label>Manage Objective</span></a></li>
            <li><a href="admin_viva_exams" class="nav-item<?= $current_page == 'admin_viva_exams.php' ? ' active' : '' ?>">
              <?= $ICONS['mic'] ?><span data-label>Manage Viva</span><span class="badge badge-new" data-label>NEW</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Content & Uploads ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navContent">
          <span class="group-label" data-label>Content & Uploads</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navContent">
          <ul class="nav-sub">
            <li><a href="admin_questions" class="nav-item<?= $current_page == 'admin_questions.php' ? ' active' : '' ?>">
              <?= $ICONS['book'] ?><span data-label>Practical Bank</span></a></li>
            <li><a href="admin_upload" class="nav-item<?= $current_page == 'admin_upload.php' ? ' active' : '' ?>">
              <?= $ICONS['upload'] ?><span data-label>Upload Data Center</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Results & Reports ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navResults">
          <span class="group-label" data-label>Results & Reports</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navResults">
          <ul class="nav-sub">
            <li><a href="admin_student_scores" class="nav-item<?= $current_page == 'admin_student_scores.php' ? ' active' : '' ?>">
              <?= $ICONS['clipboard-check'] ?><span data-label>Practical Results</span></a></li>
            <li><a href="examiner_objective_results" class="nav-item<?= $current_page == 'examiner_objective_results.php' ? ' active' : '' ?>">
              <?= $ICONS['check-circle'] ?><span data-label>Objective Results</span></a></li>
            <li><a href="admin_student_scores?type=viva" class="nav-item<?= ($current_page == 'admin_student_scores.php' && ($_GET['type'] ?? '') == 'viva') ? ' active' : '' ?>">
              <?= $ICONS['mic'] ?><span data-label>Viva Results</span></a></li>
            <li><a href="all_students_score" class="nav-item<?= $current_page == 'all_students_score.php' ? ' active' : '' ?>">
              <?= $ICONS['sigma'] ?><span data-label>Score Summary (All)</span></a></li>
            <li><a href="report" class="nav-item<?= $current_page == 'report.php' ? ' active' : '' ?>">
              <?= $ICONS['file'] ?><span data-label>Generate Reports</span></a></li>
            <li><a href="admin_session_report" class="nav-item<?= $current_page == 'admin_session_report.php' ? ' active' : '' ?>">
              <span class="text-primary"><?= $ICONS['chart'] ?></span><span data-label>Session Reports</span><span class="badge badge-new" data-label>NEW</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Proctoring Center ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navProctor">
          <span class="group-label" data-label>Proctoring Center</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navProctor">
          <ul class="nav-sub">
            <li><a href="admin_live_proctor" class="nav-item<?= $current_page == 'admin_live_proctor.php' ? ' active' : '' ?>">
              <span class="text-danger"><?= $ICONS['broadcast'] ?></span><span data-label>Live Monitor</span></a></li>
            <li><a href="admin_devices" class="nav-item<?= $current_page == 'admin_devices.php' ? ' active' : '' ?>">
              <span class="text-warning"><?= $ICONS['devices'] ?></span><span data-label>Device Auth</span><span class="badge badge-new" data-label>NEW</span></a></li>
            <li><a href="admin_exam_audit" class="nav-item<?= $current_page == 'admin_exam_audit.php' ? ' active' : '' ?>">
              <?= $ICONS['shield'] ?><span data-label>Exam Audit Trail</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── System & Tools ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navSystem">
          <span class="group-label" data-label>System & Tools</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navSystem">
          <ul class="nav-sub">
            <li><a href="chat" class="nav-item<?= $current_page == 'chat.php' ? ' active' : '' ?>">
              <?= $ICONS['chat'] ?><span data-label>Staff Chat</span></a></li>
            <li><a href="admin_portal_settings" class="nav-item<?= $current_page == 'admin_portal_settings.php' ? ' active' : '' ?>">
              <span class="text-info"><?= $ICONS['palette'] ?></span><span data-label>Portal Customization</span></a></li>
            <li><a href="admin_audit_trail" class="nav-item<?= $current_page == 'admin_audit_trail.php' ? ' active' : '' ?>">
              <?= $ICONS['eye'] ?><span data-label>Audit Trail</span></a></li>
            <li><a href="#" id="clearAllChatsSidebar" class="nav-item text-danger-item">
              <?= $ICONS['trash'] ?><span data-label>Clear All Chats</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Learning Management System ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navLms">
          <span class="group-label" data-label>Learning Management System</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navLms">
          <ul class="nav-sub">
            <li><a href="lms/public/" target="_blank" class="nav-item">
              <?= $ICONS['book'] ?><span data-label>Open LMS</span></a></li>
          </ul>
        </div>
      </li>

    </ul>
  </nav>

  <div class="sidebar-divider"></div>

  <div class="sidebar-footer">
    <a href="assessment_portal_testing_checklist.html" target="_blank" class="nav-item footer-link">
      <?= $ICONS['clipboard'] ?><span data-label>Documentation</span>
    </a>
    <a href="logout" class="nav-item footer-link text-danger-item">
      <?= $ICONS['logout'] ?><span data-label>Logout</span>
    </a>
    <div class="footer-status">
      <span class="status-indicator online"></span>
      <span class="status-text" data-label>System Online</span>
    </div>
  </div>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.getElementById('sidebarMenu');
  var backdrop = document.getElementById('sidebarBackdrop');
  if (backdrop) {
    backdrop.addEventListener('click', function () {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
      document.body.classList.remove('sidebar-open');
    });
  }
  document.querySelectorAll('.nav-group-header').forEach(function (header) {
    header.addEventListener('click', function () {
      var arrow = this.querySelector('.group-arrow svg');
      if (arrow) {
        var isExpanded = this.getAttribute('aria-expanded') !== 'false';
        arrow.style.transform = isExpanded ? 'rotate(0deg)' : 'rotate(180deg)';
      }
    });
  });
  var clearBtn = document.getElementById('clearAllChatsSidebar');
  if (clearBtn) {
    clearBtn.addEventListener('click', function (e) {
      e.preventDefault();
      Swal.fire({
        title: 'Clear All Chats',
        text: 'Are you sure? This cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Yes, clear it'
      }).then(function (result) {
        if (result.isConfirmed) {
          fetch('chat_backend?action=clear_chats', { method: 'POST' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
              if (data.success) {
                Swal.fire('Cleared!', 'All chat messages cleared!', 'success').then(function () {
                  if (window.location.pathname.endsWith('chat')) location.reload();
                });
              } else {
                Swal.fire('Error', data.error || 'Unknown error', 'error');
              }
            });
        }
      });
    });
  }
});
</script>
