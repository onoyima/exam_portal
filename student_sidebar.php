<?php
// Student sidebar. Uses $sd_cards when the page already built it.
require_once __DIR__ . '/includes/student_exams.php';

$sd_logo = 'assets/favicon.ico';
$sd_title = 'Assessment Portal';
if (isset($db)) {
    try {
        $sd_settings = $db->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('portal_logo', 'portal_title')")->fetchAll(PDO::FETCH_KEY_PAIR);
        if (!empty($sd_settings['portal_logo']) && file_exists($sd_settings['portal_logo'])) {
            $sd_logo = $sd_settings['portal_logo'];
        }
        if (!empty($sd_settings['portal_title'])) {
            $sd_title = $sd_settings['portal_title'];
        }
    } catch (Exception $e) {
    }
    if (!isset($sd_cards)) {
        $sd_cards = sd_student_exams($db, (int) ($_SESSION['student_id'] ?? 0));
    }
}
$sd_name = $_SESSION['student_name'] ?? ($_SESSION['user'] ?? 'Student');
$sd_matric = $_SESSION['matric_no'] ?? '';
$sd_page = basename($_SERVER['PHP_SELF']);
$sd_current_exam = (int) ($_GET['exam_id'] ?? 0);
?>
<aside class="sd-side" id="sdSide" aria-label="Main menu">
  <a class="sd-brand" href="student_dashboard">
    <img src="<?= htmlspecialchars($sd_logo) ?>" alt="">
    <span><?= htmlspecialchars($sd_title) ?></span>
  </a>

  <div class="sd-me">
    <img src="passport_image.php?type=student&id=<?= (int) ($_SESSION['student_id'] ?? 0) ?>" alt=""
         onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'sd-initial',textContent:<?= htmlspecialchars(json_encode(strtoupper(mb_substr($sd_name, 0, 1))), ENT_QUOTES) ?>}))">
    <div style="min-width:0">
      <div class="sd-me-name"><?= htmlspecialchars($sd_name) ?></div>
      <div class="sd-me-matric"><?= htmlspecialchars($sd_matric) ?></div>
    </div>
  </div>

  <nav class="sd-nav">
    <a href="student_dashboard" class="<?= $sd_page === 'student_dashboard.php' ? 'is-active' : '' ?>">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 9.5 10 3l7 6.5V17a1 1 0 0 1-1 1h-4v-5H8v5H4a1 1 0 0 1-1-1V9.5Z"/></svg>
      <span class="sd-nav-text">My exams</span>
    </a>

    <?php if (!empty($sd_cards)): ?>
      <div class="sd-nav-label">Objective exams</div>
      <?php foreach ($sd_cards as $c):
        $dot = ['progress' => 'is-progress', 'done' => 'is-done', 'locked' => 'is-locked'][$c['state']] ?? ''; ?>
        <a href="student_dashboard#exam-<?= (int) $c['exam']['id'] ?>" class="<?= $sd_current_exam === (int) $c['exam']['id'] ? 'is-active' : '' ?>" title="<?= htmlspecialchars($c['exam']['name'] . ' — ' . $c['label']) ?>">
          <i class="sd-dot <?= $dot ?>"></i>
          <span class="sd-nav-text"><?= htmlspecialchars($c['exam']['name']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="sd-nav-label">Learning</div>
    <a href="lms/public/" target="_blank">
      <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h5a2 2 0 0 1 2 2v11a2 2 0 0 0-2-2H3V4Z"/><path d="M17 4h-5a2 2 0 0 0-2 2v11a2 2 0 0 1 2-2h5V4Z"/></svg>
      <span class="sd-nav-text">Course materials (LMS)</span>
    </a>
  </nav>

  <div class="sd-side-foot">
    <div class="sd-online"><i></i> Connected to exam server</div>
    <a href="student_logout">
      <svg viewBox="0 0 20 20" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 3H4v14h4"/><path d="M13 6l4 4-4 4"/><path d="M17 10H8"/></svg>
      Sign out
    </a>
  </div>
</aside>
<div class="sd-scrim" id="sdScrim" hidden></div>
<script>
  (function () {
    var side = document.getElementById('sdSide'), scrim = document.getElementById('sdScrim');
    function set(open) { side.classList.toggle('is-open', open); scrim.hidden = !open; }
    document.addEventListener('click', function (e) {
      if (e.target.closest('[data-sd-menu]')) { set(!side.classList.contains('is-open')); }
    });
    scrim.addEventListener('click', function () { set(false); });
  })();
</script>
<script src="assets/js/student_lockdown.js"></script>
