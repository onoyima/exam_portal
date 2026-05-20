<?php
// This sidebar expects $active_exams and $practical_exams to be populated from the main page
$student_logo = 'assets/favicon.ico';
$student_title = 'Student';
if (isset($db)) {
    try {
        $sidebar_settings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('portal_logo', 'portal_title')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($sidebar_settings['portal_logo']) && file_exists($sidebar_settings['portal_logo'])) {
            $student_logo = $sidebar_settings['portal_logo'];
        }
        if (!empty($sidebar_settings['portal_title'])) {
            $student_title = $sidebar_settings['portal_title'];
        }
    } catch (Exception $e) {}
}
?>
<div class="sidebar d-flex flex-column p-3" id="studentSidebar">
    <div class="d-flex align-items-center justify-content-between mb-4 sidebar-header-container">
        <div class="text-center sidebar-logo-section w-100">
            <a href="student_dashboard" class="text-decoration-none">
                <img src="<?= htmlspecialchars($student_logo) ?>" alt="Logo" width="40" style="max-height: 40px; width: auto; object-fit: contain;" class="mb-1 rounded-1">
                <h4 class="fw-bold mb-0 small text-dark mt-1 text-truncate" style="max-width: 150px;" title="<?= htmlspecialchars($student_title) ?>"><?= htmlspecialchars($student_title) ?></h4>
            </a>
        </div>
        <div class="d-flex gap-2">
            <!-- Desktop Toggle -->
            <button class="btn btn-sm btn-light border shadow-sm sidebar-toggle-btn d-none d-lg-block"
                id="desktopStudentSidebarToggle">
                <i class="bx bx-chevron-left"></i>
            </button>
            <!-- Mobile Close Button (Visible only on mobile when sidebar is shown) -->
            <button class="btn btn-sm btn-danger d-lg-none" id="mobileSidebarClose">
                <i class="bx bx-x"></i>
            </button>
        </div>
    </div>

    <div class="profile-summary p-3 bg-light rounded-4 mb-4 text-center sidebar-profile-section">
        <div class="profile-icon mx-auto mb-2" style="width: 50px; height: 50px; font-size: 1.5rem;">
            <?= strtoupper(substr($name ?? 'S', 0, 1)) ?>
        </div>
        <div class="fw-bold small profile-name-text">
            <?= htmlspecialchars($name ?? 'Student') ?>
        </div>
        <div class="text-muted profile-matric-text" style="font-size: 0.7rem;">
            <?= htmlspecialchars($matric ?? '') ?>
        </div>
    </div>

    <ul class="nav nav-pills flex-column mb-auto">
        <li class="nav-section-label small text-uppercase fw-bold text-muted mb-2 px-3">Main</li>
        <li>
            <a href="student_dashboard"
                class="nav-link<?= basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? ' active' : '' ?>">
                <i class="bx bx-home-alt me-2"></i> <span>Dashboard</span>
            </a>
        </li>

        <li class="nav-section-label small text-uppercase fw-bold text-muted mb-2 mt-4 px-3">Your Objective Exams</li>
        <?php if (empty($active_exams)): ?>
            <li class="px-3"><span class="text-muted small">No active exams</span></li>
        <?php else: ?>
            <?php foreach ($active_exams as $exam): ?>
                <li>
                    <a href="student_dashboard#exam-<?= $exam['id'] ?>"
                        class="nav-link d-flex justify-content-between align-items-center <?= (isset($_GET['exam_id']) && $_GET['exam_id'] == $exam['id']) ? 'active' : '' ?>">
                        <span class="text-truncate mr-2">
                            <?= htmlspecialchars($exam['name']) ?>
                        </span>
                        <?php if ($exam['finished_attempts'] > 0): ?>
                            <span class="badge bg-success rounded-pill sidebar-badge" style="font-size: 0.6rem;">Done</span>
                        <?php elseif ($exam['total_attempts'] > 0): ?>
                            <span class="badge bg-warning text-dark rounded-pill sidebar-badge"
                                style="font-size: 0.6rem;">Started</span>
                        <?php else: ?>
                            <i class="bx bx-circle small opacity-50 sidebar-badge-dot"></i>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Practical Exams section removed from student view -->

        <li class="mt-4">
            <hr>
        </li>
        <li>
            <a href="student_logout" class="nav-link text-danger">
                <i class="bx bx-log-out me-2"></i> <span>Logout</span>
            </a>
        </li>
    </ul>

    <!-- System Status -->
    <div class="mt-auto pt-3 border-top sidebar-footer-status">
        <div class="small text-muted" style="font-size: 0.75rem;">
            <div class="d-flex align-items-center mb-1">
                <span class="status-dot online me-2"></span>
                <div class="status-text">Portal Online</div>
            </div>
            <div class="d-flex align-items-center">
                <span class="status-dot online me-2"></span>
                <div class="status-text">Secure Session</div>
            </div>
        </div>
    </div>
</div>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('studentSidebar');
        const mainContent = document.querySelector('.main-content');
        const desktopToggle = document.getElementById('desktopStudentSidebarToggle');
        const mobileClose = document.getElementById('mobileSidebarClose');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        // Restore state from localStorage
        const IS_COLLAPSED = localStorage.getItem('studentSidebarCollapsed') === 'true';
        if (IS_COLLAPSED && window.innerWidth >= 992) {
            if (sidebar) sidebar.classList.add('collapsed');
            if (mainContent) mainContent.classList.add('sidebar-collapsed');
            const icon = desktopToggle ? desktopToggle.querySelector('i') : null;
            if (icon) icon.classList.replace('bi-chevron-left', 'bi-chevron-right');
        }

        // Broad search for mobile toggle defined in student_dashboard.php
        function setupMobileToggle() {
            const mobileToggle = document.getElementById('sidebarToggle');
            if (mobileToggle) {
                mobileToggle.onclick = null;
                mobileToggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    sidebar.classList.toggle('show');
                    if (sidebarBackdrop) sidebarBackdrop.classList.toggle('show');
                });
            }
        }
        setupMobileToggle();
        setTimeout(setupMobileToggle, 500);

        // Desktop Toggle
        if (desktopToggle) {
            desktopToggle.addEventListener('click', function () {
                sidebar.classList.toggle('collapsed');
                if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
                const isCollapsed = sidebar.classList.contains('collapsed');
                localStorage.setItem('studentSidebarCollapsed', isCollapsed);
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
    });
</script>
<!-- Dependencies for Secure Mode -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- Phase 5: Global Student Lockdown -->
<script src="assets/js/student_lockdown.js"></script>