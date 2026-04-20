<div class="sidebar d-flex flex-column p-3" id="sidebarMenu">
  <div class="d-flex align-items-center justify-content-between mb-4 sidebar-header-container">
    <div class="text-center sidebar-logo-section">
      <img src="assets/favicon.ico" alt="Logo" width="40" height="40" class="mb-1">
      <h4 class="fw-bold mb-0 small text-dark">Admin</h4>
    </div>
    <div class="d-flex gap-2">
      <!-- Desktop Toggle -->
      <button class="btn btn-sm btn-light border shadow-sm sidebar-toggle-btn d-none d-lg-block"
        id="desktopSidebarToggle">
        <i class="bx bx-chevron-left"></i>
      </button>
      <!-- Mobile Close Button (Visible only on mobile when sidebar is shown) -->
      <button class="btn btn-sm btn-danger d-lg-none" id="mobileSidebarClose">
        <i class="bx bx-x"></i>
      </button>
    </div>
  </div>

  <ul class="nav nav-pills flex-column mb-auto" id="sidebarNav">
    <!-- Overview Section -->
    <li class="nav-section">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navOverview">
        <span>Overview</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navOverview">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_dashboard"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? ' active' : '' ?>">
              <i class="bx bxs-dashboard me-2"></i><span>Dashboard</span></a></li>
          <li><a href="admin_analytics"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_analytics.php' ? ' active' : '' ?>">
              <i class="bx bx-line-chart me-2"></i><span>Analytics</span></a></li>
        </ul>
      </div>
    </li>

    <!-- User Management -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navUsers">
        <span>User Management</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navUsers">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_students"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_students.php' ? ' active' : '' ?>">
              <i class="bx bx-group me-2"></i><span>Manage Students</span></a></li>
          <li><a href="admin_staff"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_staff.php' ? ' active' : '' ?>">
              <i class="bx bx-id-card me-2"></i><span>Manage Staff</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Exam Management -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navExams">
        <span>Exam Management</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navExams">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_sessions"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_sessions.php' ? ' active' : '' ?>">
              <i class="bx bx-calendar me-2"></i><span>Manage Sessions</span></a></li>
          <li><a href="admin_exams"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_exams.php' ? ' active' : '' ?>">
              <i class="bx bx-cog me-2"></i><span>Manage Practical</span></a></li>
          <li><a href="admin_objective_exams"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_objective_exams.php' ? ' active' : '' ?>">
              <i class="bx bx-list-check me-2"></i><span>Manage Objective</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Content & Uploads -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navContent">
        <span>Content & Uploads</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navContent">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_questions"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_questions.php' ? ' active' : '' ?>">
              <i class="bx bx-book-content me-2"></i><span>Practical Bank</span></a></li>
          <li><a href="admin_upload"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_upload.php' ? ' active' : '' ?>">
              <i class="bx bx-cloud-upload me-2"></i><span>Upload Data Center</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Results & Reports -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navResults">
        <span>Results & Reports</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navResults">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_student_scores"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_student_scores.php' ? ' active' : '' ?>">
              <i class="bx bx-task me-2"></i><span>Practical Results</span></a></li>
          <li><a href="examiner_objective_results"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'examiner_objective_results.php' ? ' active' : '' ?>">
              <i class="bx bx-check-circle me-2"></i><span>Objective Results</span></a></li>
          <li><a href="all_students_score"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'all_students_score.php' ? ' active' : '' ?>">
              <i class="bx bx-calculator me-2"></i><span>Score Summary (All)</span></a></li>
          <li><a href="report" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'report.php' ? ' active' : '' ?>">
              <i class="bx bx-file me-2"></i><span>Generate Reports</span></a></li>
          <li><a href="admin_session_report"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_session_report.php' ? ' active' : '' ?>">
              <i class="bx bx-bar-chart-alt-2 me-2 text-primary"></i><span>Session Reports</span>
              <span class="badge bg-primary-subtle text-primary ms-auto" style="font-size:0.6rem;">NEW</span></a></li>
        </ul>
      </div>
    </li>

    <!-- Proctoring Center -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navProctor">
        <span>Proctoring Center</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navProctor">
        <ul class="nav flex-column ps-2">
          <li><a href="admin_live_proctor"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_live_proctor.php' ? ' active' : '' ?>">
              <i class="bx bx-broadcast text-danger me-2"></i><span>Live Monitor</span></a></li>
          <li><a href="admin_devices"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_devices.php' ? ' active' : '' ?>">
              <i class="bx bx-devices text-warning me-2"></i><span>Device Auth</span>
              <span class="badge bg-warning-subtle text-warning ms-auto" style="font-size:0.6rem;">NEW</span></a></li>
          <li><a href="admin_exam_audit"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_exam_audit.php' ? ' active' : '' ?>">
              <i class="bx bx-shield-quarter me-2"></i><span>Exam Audit Trail</span></a></li>
        </ul>
      </div>
    </li>

    <!-- System & Tools -->
    <li class="nav-section mt-2">
      <div
        class="nav-section-header d-flex align-items-center justify-content-between px-3 py-2 small text-uppercase fw-bold text-muted cursor-pointer"
        data-bs-toggle="collapse" data-bs-target="#navSystem">
        <span>System & Tools</span>
        <i class="bx bx-chevron-down section-arrow"></i>
      </div>
      <div class="collapse show" id="navSystem">
        <ul class="nav flex-column ps-2">
          <li><a href="chat" class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'chat.php' ? ' active' : '' ?>">
              <i class="bx bx-message-square-dots me-2"></i><span>Staff Chat</span></a></li>
          <li><a href="admin_portal_settings"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_portal_settings.php' ? ' active' : '' ?>">
              <i class="bx bx-palette me-2 text-info"></i><span>Portal Customization</span></a></li>
          <li><a href="admin_audit_trail"
              class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'admin_audit_trail.php' ? ' active' : '' ?>">
              <i class="bx bx-shield-alt-2 me-2"></i><span>Audit Trail</span></a></li>
          <li><a href="#" id="clearAllChatsSidebar" class="nav-link text-danger">
              <i class="bx bx-trash me-2"></i><span>Clear All Chats</span></a></li>
        </ul>
      </div>
    </li>

    <li class="mt-3">
      <hr>
    </li>
    <li><a href="assessment_portal_testing_checklist.html" class="nav-link text-info fw-bold" target="_blank">
        <i class="bx bx-clipboard me-2"></i><span>Testing Checklist</span></a></li>
    <li><a href="logout" class="nav-link text-danger">
        <i class="bx bx-log-out me-2"></i><span>Logout</span></a></li>
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
    const mobileClose = document.getElementById('mobileSidebarClose');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    // We use a broader search for the toggle button since it's defined in each page's navbar
    function setupMobileToggle() {
      const mobileToggle = document.getElementById('sidebarToggle');
      if (mobileToggle) {
        // Remove any existing click handlers to prevent double-toggling
        mobileToggle.onclick = null;

        mobileToggle.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          sidebar.classList.toggle('show');
          if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
        });
      }
    }

    // Run setup immediately and also after a small delay to catch dynamically loaded elements
    setupMobileToggle();
    setTimeout(setupMobileToggle, 500);

    // Desktop Toggle
    if (desktopToggle) {
      desktopToggle.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
        const icon = desktopToggle.querySelector('i');
        icon.classList.replace(isCollapsed ? 'bi-chevron-left' : 'bi-chevron-right', isCollapsed ? 'bi-chevron-right' : 'bi-chevron-left');
      });
    }

    // Mobile Close Logic
    if (mobileClose) {
      mobileClose.addEventListener('click', function () {
        sidebar.classList.remove('show');
        if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
      });
    }

    if (sidebarBackdrop) {
      sidebarBackdrop.addEventListener('click', function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      });
    }

    // Restore state from localStorage
    const IS_COLLAPSED = localStorage.getItem('sidebarCollapsed') === 'true';
    if (IS_COLLAPSED && window.innerWidth >= 992) {
      sidebar.classList.add('collapsed');
      if (mainContent) mainContent.classList.add('sidebar-collapsed');
      const icon = desktopToggle ? desktopToggle.querySelector('i') : null;
      if (icon) icon.classList.replace('bi-chevron-left', 'bi-chevron-right');
    }

    // Chat clear logic
    var clearBtn = document.getElementById('clearAllChatsSidebar');
    if (clearBtn) {
      clearBtn.addEventListener('click', function (e) {
        e.preventDefault();
        Swal.fire({
          title: 'Clear All Chats',
          text: 'Are you sure you want to clear all chat messages? This cannot be undone.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          confirmButtonText: 'Yes, clear it'
        }).then((result) => {
          if (result.isConfirmed) {
            fetch('chat_backend?action=clear_chats', { method: 'POST' })
              .then(r => r.json())
              .then(data => {
                if (data.success) {
                  Swal.fire('Cleared!', 'All chat messages cleared!', 'success').then(() => {
                    if (window.location.pathname.endsWith('chat')) location.reload();
                  });
                } else {
                  Swal.fire('Error', 'Failed to clear chats: ' + (data.error || 'Unknown error'), 'error');
                }
              });
          }
        });
      });
    }
  });
</script>