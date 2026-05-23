<?php
$exam_logo = 'assets/favicon.ico';
$exam_title = 'Examiner';
if (isset($db)) {
  try {
    $sidebar_settings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('portal_logo', 'portal_title')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (!empty($sidebar_settings['portal_logo']) && file_exists($sidebar_settings['portal_logo'])) $exam_logo = $sidebar_settings['portal_logo'];
    if (!empty($sidebar_settings['portal_title'])) $exam_title = $sidebar_settings['portal_title'];
  } catch (Exception $e) {}
}

$current_page = basename($_SERVER['PHP_SELF']);

// ── Fetch examiner's assigned active exams ──
$assigned_exams = [];
if (isset($db) && isset($_SESSION['user'])) {
  try {
    $ex_id = $db->prepare("SELECT id FROM examiners WHERE username=? AND deleted_at IS NULL");
    $ex_id->execute([$_SESSION['user']]);
    $eid = $ex_id->fetchColumn();
    if ($eid) {
      $stmt = $db->prepare("
        SELECT e.id, e.name, e.type 
        FROM exams e 
        JOIN exam_examiners ee ON e.id = ee.exam_id 
        WHERE ee.examiner_id = ? AND e.is_active = 1 AND e.deleted_at IS NULL
        ORDER BY e.type, e.name
      ");
      $stmt->execute([$eid]);
      $assigned_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch (Exception $e) {}
}

$S = '<svg class="nav-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">';
$E = '</svg>';
$ICONS = [];
$ICONS['dashboard']      = $S.'<rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/>'.$E;
$ICONS['clipboard-check'] = $S.'<rect x="5" y="2" width="10" height="18" rx="2"/><path d="M8 2H7a1 1 0 0 0-1 1v1h8V3a1 1 0 0 0-1-1H8Z"/><path d="m7.5 11 2.5 2.5 4-5"/>'.$E;
$ICONS['check-circle']   = $S.'<circle cx="10" cy="10" r="8"/><path d="m6.5 10 2.5 2.5 5-5"/>'.$E;
$ICONS['chat']           = $S.'<path d="M17 8a6 6 0 0 0-6-6H7a6 6 0 0 0-6 6v4a6 6 0 0 0 6 6h2l4 3v-3h2a6 6 0 0 0 6-6V8Z"/>'.$E;
$ICONS['logout']         = $S.'<path d="M9 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h4"/><path d="M15 7l4 3-4 3"/><path d="M8 10h11"/>'.$E;
$ICONS['chevron-down']   = $S.'<path d="m5 7 5 5 5-5"/>'.$E;
$ICONS['clipboard']      = $S.'<rect x="5" y="2" width="10" height="18" rx="2"/><path d="M8 2H7a1 1 0 0 0-1 1v1h8V3a1 1 0 0 0-1-1H8Z"/><path d="M7 9h6"/><path d="M7 12h6"/><path d="M7 15h4"/>'.$E;
$ICONS['book-open']      = $S.'<path d="M3 3h5a2 2 0 0 1 2 2v12a2 2 0 0 0-2-2H3V3Z"/><path d="M17 3h-5a2 2 0 0 0-2 2v12a2 2 0 0 1 2-2h5V3Z"/>'.$E;
$ICONS['graduation']     = $S.'<path d="M10 2 1 7l9 5 9-5-9-5Z"/><path d="M1 7v5"/><path d="M16 9.5V13a6 6 0 0 1-6 6 6 6 0 0 1-6-6V9.5"/>'.$E;
$ICONS['file-text']      = $S.'<path d="M5 2h6l4 4v12a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Z"/><path d="M11 2v4h4"/><path d="M7 10h6"/><path d="M7 14h4"/>'.$E;
$ICONS['bar-chart']      = $S.'<rect x="3" y="12" width="3" height="6" rx=".6"/><rect x="8.5" y="7" width="3" height="11" rx=".6"/><rect x="14" y="3" width="3" height="15" rx=".6"/>'.$E;
$ICONS['laptop']         = $S.'<rect x="2" y="3" width="16" height="11" rx="1.5"/><path d="M2 14h16"/><path d="M6 17h8"/><path d="M9.5 14v3"/>'.$E;
$ICONS['mic']            = $S.'<rect x="7.5" y="2" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0"/><path d="M10 15v3"/><path d="M7 18h6"/>'.$E;
?>
<div class="sidebar" id="sidebarMenu">
  <div class="sidebar-brand">
    <div class="brand-icon">
      <img src="<?= htmlspecialchars($exam_logo) ?>" alt="Logo">
    </div>
    <div class="brand-text" data-label>
      <span class="brand-name"><?= htmlspecialchars($exam_title) ?></span>
      <span class="brand-role">Examiner</span>
    </div>
  </div>

  <div class="sidebar-divider"></div>

  <nav class="sidebar-nav">
    <ul class="nav-list">

      <!-- ── Assessment Console ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navConsole">
          <span class="group-label" data-label>Assessment Console</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navConsole">
          <ul class="nav-sub">
            <li><a href="assess" class="nav-item<?= $current_page == 'assess.php' ? ' active' : '' ?>">
              <?= $ICONS['graduation'] ?><span data-label>Grade Students</span></a></li>
          </ul>
        </div>
      </li>

      <!-- ── Your Assigned Examinations ── -->
      <?php if (!empty($assigned_exams)): ?>
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navMyExams">
          <span class="group-label" data-label>Your Assigned Examinations</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navMyExams">
          <ul class="nav-sub">
            <?php foreach ($assigned_exams as $ae): ?>
              <li>
                <a href="assess?exam_id=<?= $ae['id'] ?>" class="nav-item<?= ($_GET['exam_id'] ?? '') == $ae['id'] ? ' active' : '' ?>">
                  <?= $ICONS[($ae['type'] ?? 'practical') === 'viva' ? 'mic' : (($ae['type'] ?? 'practical') === 'objective' ? 'check-circle' : 'laptop')] ?>
                  <span data-label><?= htmlspecialchars($ae['name']) ?></span>
                  <span class="badge badge-xs badge-<?= $ae['type'] === 'viva' ? 'warning' : ($ae['type'] === 'objective' ? 'info' : 'success') ?> ms-auto"><?= ucfirst($ae['type'] ?? 'prac') ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <!-- ── Communication ── -->
      <li class="nav-group">
        <div class="nav-group-header" data-bs-toggle="collapse" data-bs-target="#navComm">
          <span class="group-label" data-label>Communication</span>
          <span class="group-arrow"><?= $ICONS['chevron-down'] ?></span>
        </div>
        <div class="collapse show" id="navComm">
          <ul class="nav-sub">
            <li><a href="chat" class="nav-item<?= $current_page == 'chat.php' ? ' active' : '' ?>">
              <?= $ICONS['chat'] ?><span data-label>Staff Chat</span></a></li>
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
});
</script>
