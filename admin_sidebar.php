<div class="sidebar d-flex flex-column p-3" id="sidebarMenu">
  <div class="d-flex align-items-center justify-content-between mb-4 sidebar-header-container">
    <div class="text-center sidebar-logo-section">
      <img src="assets/favicon.ico" alt="Logo" width="40" height="40" class="mb-1">
      <h4 class="fw-bold mb-0 small">Admin</h4>
    </div>
    <button class="btn btn-sm btn-light border shadow-sm sidebar-toggle-btn d-none d-lg-block"
      id="desktopSidebarToggle">
      <i class="bi bi-chevron-left"></i>
    </button>
  </div>

  <ul class="nav nav-pills flex-column mb-auto" id="sidebarNav">
    <!-- Overview Section -->
    <li class="nav-section">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navOverview">
        <span>Overview</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navOverview">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_dashboard.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? ' active' : '' ?>">
              <i class="bi bi-speedometer2 me-2"></i><span>Dashboard</span></a></li>
          <li><a href="admin_analytics.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_analytics.php' ? ' active' : '' ?>">
              <i class="bi bi-graph-up me-2"></i><span>Analytics</span></a></li>
        </ul>
      </div>
    </li>

    <!-- User Management -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navUsers">
        <span>User Management</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navUsers">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_students.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_students.php' ? ' active' : '' ?>">
              <i class="bi bi-people me-2"></i><span>Manage Students</span></a></li>
          <li><a href="admin_staff.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_staff.php' ? ' active' : '' ?>">
              <i class="bi bi-person-badge me-2"></i><span>Manage Staff</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Exam Management -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navExams">
        <span>Exam Management</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navExams">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_exams.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_exams.php' ? ' active' : '' ?>">
              <i class="bi bi-gear me-2"></i><span>Manage Practical</span></a></li>
          <li><a href="admin_objective_exams.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_objective_exams.php' ? ' active' : '' ?>">
              <i class="bi bi-card-checklist me-2"></i><span>Manage Objective</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Content & Uploads -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navContent">
        <span>Content & Uploads</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navContent">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_questions.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_questions.php' ? ' active' : '' ?>">
              <i class="bi bi-journal-text me-2"></i><span>Practical Bank</span></a></li>
          <li><a href="admin_upload.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_upload.php' ? ' active' : '' ?>">
              <i class="bi bi-cloud-upload me-2"></i><span>Upload Data Center</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Results & Reports -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navResults">
        <span>Results & Reports</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navResults">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_student_scores.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_student_scores.php' ? ' active' : '' ?>">
              <i class="bi bi-list-check me-2"></i><span>Practical Results</span></a></li>
          <li><a href="examiner_objective_results.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'examiner_objective_results.php' ? ' active' : '' ?>">
              <i class="bi bi-check2-circle me-2"></i><span>Objective Results</span></a></li>
          <li><a href="all_students_score.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'all_students_score.php' ? ' active' : '' ?>">
              <i class="bi bi-calculator me-2"></i><span>Score Summary (All)</span></a></li>
          <li><a href="report.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'report.php' ? ' active' : '' ?>">
              <i class="bi bi-file-earmark-bar-graph me-2"></i><span>Generate Reports</span></a></li>
          <li><a href="admin_exam_audit.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_exam_audit.php' ? ' active' : '' ?>">
              <i class="bi bi-person-workspace me-2"></i><span>Exam Audit Trail</span></a></li>
        </ul>
      </div>
    </li>

    <!-- System & Tools -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navSystem">
        <span>System & Tools</span>
        <i class="bi bi-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navSystem">
        <ul class="nav flex-column ps-2">
          <li><a href="chat.php" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? ' active' : '' ?>">
              <i class="bi bi-chat-dots me-2"></i><span>Staff Chat</span></a></li>
          <li><a href="admin_audit_trail.php"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_audit_trail.php' ? ' active' : '' ?>">
              <i class="bi bi-shield-lock me-2"></i><span>Audit Trail</span></a></li>
          <li><a href="#" id="clearAllChatsSidebar" class="nav-link text-danger">
              <i class="bi bi-trash me-2"></i><span>Clear All Chats</span></a></li>
        </ul>
      </div>
    </li>

    <li class="mt-3">
      <hr>
    </li>
    <li><a href="assessment_portal1_testing_checklist.html" class="nav-link text-info fw-bold" target="_blank">
        <i class="bi bi-clipboard-check me-2"></i><span>Testing Checklist</span></a></li>
    <li><a href="logout.php" class="nav-link text-danger">
        <i class="bi bi-box-arrow-right me-2"></i><span>Logout</span></a></li>
  </ul>

  <!-- System Status -->
  <div class="mt-auto pt-3 border-top sidebar-footer-status">
    <div class="small text-muted">
      <div class="d-flex align-items-center mb-1">
        <span class="status-dot online me-2"></span>
        <div class="status-text">System Online</div>
      </div>
      <div class="d-flex align-items-center">
        <span class="status-dot online me-2"></span>
        <div class="status-text">Database Connected</div>
      </div>
    </div>
  </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebarMenu');
    const mainContent = document.querySelector('.main-content');
    const desktopToggle = document.getElementById('desktopSidebarToggle');
    const mobileToggle = document.getElementById('sidebarToggle');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    // Restore state from localStorage
    const IS_COLLAPSED = localStorage.getItem('sidebarCollapsed') === 'true';
    if (IS_COLLAPSED && window.innerWidth >= 992) {
      sidebar.classList.add('collapsed');
      if (mainContent) mainContent.classList.add('sidebar-collapsed');
      if (desktopToggle) desktopToggle.querySelector('i').classList.replace('bi-chevron-left', 'bi-chevron-right');
    }

    // Desktop Toggle
    if (desktopToggle) {
      desktopToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        if (mainContent) mainContent.classList.toggle('sidebar-collapsed');

        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);

        const icon = desktopToggle.querySelector('i');
        if (isCollapsed) {
          icon.classList.replace('bi-chevron-left', 'bi-chevron-right');
        } else {
          icon.classList.replace('bi-chevron-right', 'bi-chevron-left');
        }
      });
    }

    // Mobile Toggle Logic (Shared across all pages)
    if (mobileToggle) {
      mobileToggle.addEventListener('click', function () {
        sidebar.classList.toggle('show');
        if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
      });
    }

    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      });
    }

    // Chat clear logic
    var clearBtn = document.getElementById('clearAllChatsSidebar');
    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        if (confirm('Are you sure you want to clear all chat messages? This cannot be undone.')) {
          fetch('chat_backend.php?action=clear_chats', { method: 'POST' })
            .then(r => r.json())
            .then(data => {
              if (data.success) {
                alert('All chat messages cleared!');
                if (window.location.pathname.endsWith('chat.php')) location.reload();
              } else {
                alert('Failed to clear chats: ' + (data.error || 'Unknown error'));
              }
            });
        }
      });
    }
  });
</script>