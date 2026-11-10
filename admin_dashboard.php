<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/network_info.php';

$name = $_SESSION['user'] ?? 'Admin';

$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  validate_csrf();
  $instruction = trim($_POST['student_login_content']);
  $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('student_login_content', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
  $stmt->execute([$instruction, $instruction]);
  $success_msg = 'Login instructions saved.';
}
$student_login_content = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'student_login_content'")->fetchColumn()
  ?: "1. LOGIN WITH YOUR MATRIC NUMBER\n2. Only your Matric Number is needed.\n3. Make sure you submit before you leave.";

$is_center = cs_is_center($db);
$now = date('Y-m-d H:i:s');
$TYPE_LABEL = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];

/* ---------- exams running now ---------- */
$live = $db->query("SELECT e.id, e.name, e.type, e.start_time, e.end_time, e.duration_minutes, e.group_id, g.name AS group_name,
      (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS enrolled,
      CASE WHEN e.type = 'objective'
        THEN (SELECT COUNT(DISTINCT a.student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL)
        ELSE (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id) END AS done,
      CASE WHEN e.type = 'objective'
        THEN (SELECT COUNT(*) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NULL AND a.last_active > DATE_SUB(NOW(), INTERVAL 2 MINUTE))
        ELSE 0 END AS writing,
      CASE WHEN e.type = 'objective'
        THEN (SELECT COUNT(*) FROM objective_questions q WHERE q.exam_id = e.id AND q.deleted_at IS NULL)
        ELSE (SELECT COUNT(*) FROM exam_questions eq WHERE eq.exam_id = e.id) END AS questions
    FROM exams e LEFT JOIN exam_groups g ON g.id = e.group_id
    WHERE e.is_active = 1 AND e.deleted_at IS NULL
      AND (e.start_time IS NULL OR e.start_time <= NOW()) AND (e.end_time IS NULL OR e.end_time >= NOW())
    ORDER BY writing DESC, (e.start_time IS NULL), e.start_time DESC, e.name LIMIT 12")->fetchAll(PDO::FETCH_ASSOC);
$writing_total = array_sum(array_column($live, 'writing'));

/* ---------- next 7 days ---------- */
$upcoming = $db->query("SELECT id, name, type, start_time, is_active FROM exams
    WHERE deleted_at IS NULL AND start_time > NOW() AND start_time <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    ORDER BY start_time LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);

/* ---------- things to look at ---------- */
$attention = [];
$empty_q = $db->query("SELECT e.id, e.name, e.type FROM exams e WHERE e.is_active = 1 AND e.deleted_at IS NULL AND (
      (e.type = 'objective' AND NOT EXISTS (SELECT 1 FROM objective_questions q WHERE q.exam_id = e.id AND q.deleted_at IS NULL))
   OR (e.type <> 'objective' AND NOT EXISTS (SELECT 1 FROM exam_questions eq WHERE eq.exam_id = e.id))) LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($empty_q as $e) {
  $attention[] = ['warn', 'No questions in an active exam', $e['name'], $e['type'] === 'objective' ? 'admin_objective_questions?exam_id=' . $e['id'] : ($e['type'] === 'practical' ? 'admin_questions?exam_id=' . $e['id'] : ($e['type'] === 'viva' ? 'admin_viva_exams' : 'admin_research_exams'))];
}
$empty_s = $db->query("SELECT e.id, e.name FROM exams e WHERE e.is_active = 1 AND e.deleted_at IS NULL
    AND NOT EXISTS (SELECT 1 FROM exam_students es WHERE es.exam_id = e.id) LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($empty_s as $e) {
  $attention[] = ['warn', 'Active exam with no students', $e['name'], 'admin_assign_students?exam_id=' . $e['id']];
}
$locked = (int) $db->query("SELECT COUNT(*) FROM objective_attempts WHERE is_locked = 1 AND finished_at IS NULL")->fetchColumn();
if ($locked) {
  $attention[] = ['bad', 'Students locked out of their exam', $locked . ' waiting to be unlocked', 'admin_live_proctor'];
}
$pending_dev = (int) $db->query("SELECT COUNT(*) FROM approved_devices WHERE status = 'pending'")->fetchColumn();
if ($pending_dev) {
  $attention[] = ['warn', 'Computers waiting for approval', $pending_dev . ' device' . ($pending_dev === 1 ? '' : 's'), 'admin_devices'];
}
if (!$is_center) {
  $silent = $db->query("SELECT c.name AS centre, g.name AS grp, g.id FROM center_tokens t JOIN exam_centers c ON c.id = t.center_id
      JOIN exam_groups g ON g.id = t.group_id AND g.deleted_at IS NULL
      WHERE t.revoked_at IS NULL AND g.status = 'published' AND t.activated_at IS NULL LIMIT 4")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($silent as $r) {
    $attention[] = ['info', 'Centre has not activated its token', $r['centre'] . ' — ' . $r['grp'], 'admin_exam_groups?id=' . $r['id'] . '#centres'];
  }
} else {
  $unsent = $db->query("SELECT l.id, g.name FROM center_links l JOIN exam_groups g ON g.id = l.group_id AND g.deleted_at IS NULL
      WHERE l.last_sync_at IS NULL OR l.last_sync_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($unsent as $r) {
    $attention[] = ['info', 'Results not sent to the main server recently', $r['name'], 'center_console#group-' . $r['id']];
  }
}

/* ---------- totals ---------- */
$counts = $db->query("SELECT
    (SELECT COUNT(*) FROM students WHERE deleted_at IS NULL) AS students,
    (SELECT COUNT(*) FROM examiners WHERE role = 'examiner' AND deleted_at IS NULL AND (source IS NULL OR source <> 'center')) AS examiners,
    (SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL) AS exams,
    (SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL AND is_active = 1) AS active,
    (SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL) + (SELECT COUNT(*) FROM objective_questions WHERE deleted_at IS NULL) AS questions,
    (SELECT COUNT(*) FROM exam_groups WHERE deleted_at IS NULL) AS `groups`")->fetch(PDO::FETCH_ASSOC);
$by_type = $db->query("SELECT type, COUNT(*) FROM exams WHERE deleted_at IS NULL GROUP BY type")->fetchAll(PDO::FETCH_KEY_PAIR);

/* ---------- last 14 days ---------- */
$days = [];
for ($i = 13; $i >= 0; $i--) {
  $days[date('Y-m-d', strtotime("-$i day"))] = ['obj' => 0, 'mark' => 0];
}
foreach ($db->query("SELECT DATE(finished_at) d, COUNT(*) n FROM objective_attempts WHERE finished_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d") as $r) {
  if (isset($days[$r['d']])) {
    $days[$r['d']]['obj'] = (int) $r['n'];
  }
}
foreach ($db->query("SELECT DATE(created_at) d, COUNT(DISTINCT exam_id, student_id) n FROM answers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY d") as $r) {
  if (isset($days[$r['d']])) {
    $days[$r['d']]['mark'] = (int) $r['n'];
  }
}
$activity_total = array_sum(array_map(fn($d) => $d['obj'] + $d['mark'], $days));

/* ---------- latest results ---------- */
$recent = $db->query("
    (SELECT e.type, MAX(oa.finished_at) AS dt, s.name AS sname, s.matric_no, e.name AS ename, e.id AS eid
       FROM objective_attempts oa JOIN students s ON s.id = oa.student_id JOIN exams e ON e.id = oa.exam_id AND e.deleted_at IS NULL
      WHERE oa.finished_at IS NOT NULL GROUP BY oa.student_id, oa.exam_id ORDER BY dt DESC LIMIT 6)
    UNION ALL
    (SELECT e.type, MAX(a.created_at) AS dt, s.name, s.matric_no, e.name, e.id
       FROM answers a JOIN students s ON s.id = a.student_id JOIN exams e ON e.id = a.exam_id AND e.deleted_at IS NULL
      GROUP BY a.student_id, a.exam_id ORDER BY dt DESC LIMIT 6)
    ORDER BY dt DESC LIMIT 7")->fetchAll(PDO::FETCH_ASSOC);

$net_all = ni_server_addresses();
$net_lan = array_values(array_filter($net_all, fn($x) => $x['kind'] === 'lan')) ?: array_values(array_filter($net_all, fn($x) => $x['kind'] === 'public'));
$net_override = ni_override($db);
$student_url = $net_override ? ni_base_url_for($net_override) . '/student_login' : ($net_lan ? ni_base_url($net_lan[0]['ip']) . '/student_login' : null);

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$monitor_url = fn($e) => $e['type'] === 'objective' ? 'admin_live_proctor?exam_id=' . $e['id'] : 'report?exam_id=' . $e['id'];
$result_url = fn($e) => $e['type'] === 'objective' ? 'exam_overview?exam_id=' . $e['id'] : 'report?exam_id=' . $e['id'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/css/dashboard.css?v=1" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Dashboard</h1>
        </div>
        <div class="admin-navbar-end">
          <a href="admin_analytics" class="navbar-icon-btn" title="Analytics"><i class="bi bi-graph-up"></i></a>
          <div class="admin-user-badge"><span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span><span class="admin-user-name"><?= htmlspecialchars($name) ?></span></div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content db-page">
      <?php display_auth_errors(); ?>

      <header class="db-head">
        <div>
          <p class="db-date"><?= date('l, j F Y') ?> · <span data-clock><?= date('g:i A') ?></span></p>
          <h2><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $name)[0]) ?>.</h2>
          <p class="db-lead">
            <?php if ($writing_total): ?><b><?= $writing_total ?></b> student<?= $writing_total === 1 ? ' is' : 's are' ?> writing right now across <?= count(array_filter($live, fn($e) => $e['writing'] > 0)) ?> exam<?= count(array_filter($live, fn($e) => $e['writing'] > 0)) === 1 ? '' : 's' ?>.
            <?php elseif ($live): ?><?= count($live) ?> exam<?= count($live) === 1 ? ' is' : 's are' ?> open; nobody is writing at the moment.
            <?php else: ?>No exam is open right now.<?php endif; ?>
            <?php if ($is_center): ?><span class="db-role"><i class="bi bi-building"></i> Exam centre server</span><?php endif; ?>
          </p>
        </div>
        <a class="db-address" href="admin_network" title="Network access and QR code">
          <span>Students open</span>
          <?php if ($student_url): ?><b><?= htmlspecialchars($student_url) ?></b><?php else: ?><b class="is-bad">no network found</b><?php endif; ?>
          <i class="bi bi-qr-code"></i>
        </a>
      </header>

      <section class="db-panel">
        <div class="db-panel-head">
          <h3>Open now</h3>
          <a href="admin_live_proctor" class="db-link">Live monitor <i class="bi bi-arrow-right"></i></a>
        </div>
        <?php if (!$live): ?>
          <p class="db-empty">Exams appear here while they are switched on and inside their time window.</p>
        <?php else: ?>
          <div class="db-live">
            <?php foreach ($live as $e): $pct = $e['enrolled'] ? min(100, round($e['done'] / $e['enrolled'] * 100)) : 0; ?>
              <div class="db-live-row">
                <div class="db-live-name">
                  <span class="db-type db-t-<?= $e['type'] ?>"><?= $TYPE_LABEL[$e['type']] ?? $e['type'] ?></span>
                  <a href="<?= $result_url($e) ?>"><?= htmlspecialchars($e['name']) ?></a>
                  <small><?= $e['group_name'] ? htmlspecialchars($e['group_name']) . ' · ' : '' ?><?= $e['end_time'] ? 'until ' . date('g:i A', strtotime($e['end_time'])) : 'no closing time' ?></small>
                </div>
                <div class="db-live-now <?= $e['writing'] ? 'is-on' : '' ?>">
                  <?php if ($e['type'] === 'objective'): ?><b><?= (int) $e['writing'] ?></b><span>writing</span><?php else: ?><b>—</b><span>marking</span><?php endif; ?>
                </div>
                <div class="db-live-bar">
                  <div class="db-bar"><i style="width:<?= $pct ?>%"></i></div>
                  <span><?= (int) $e['done'] ?> of <?= (int) $e['enrolled'] ?> <?= $e['type'] === 'objective' ? 'submitted' : 'scored' ?></span>
                </div>
                <div class="db-live-act">
                  <a class="admin-btn admin-btn-sm admin-btn-outline" href="<?= $monitor_url($e) ?>"><?= $e['type'] === 'objective' ? 'Monitor' : 'Scores' ?></a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <div class="db-cols">
        <section class="db-panel">
          <div class="db-panel-head"><h3>Needs attention</h3><span class="db-count"><?= count($attention) ?></span></div>
          <?php if (!$attention): ?>
            <p class="db-empty db-ok"><i class="bi bi-check2-circle"></i> Nothing waiting for you.</p>
          <?php else: ?>
            <ul class="db-todo">
              <?php foreach ($attention as [$lvl, $what, $detail, $href]): ?>
                <li class="is-<?= $lvl ?>"><a href="<?= htmlspecialchars($href) ?>"><b><?= htmlspecialchars($what) ?></b><span><?= htmlspecialchars($detail) ?></span><i class="bi bi-chevron-right"></i></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>

        <section class="db-panel">
          <div class="db-panel-head"><h3>Next 7 days</h3></div>
          <?php if (!$upcoming): ?>
            <p class="db-empty">No exam is scheduled to start in the coming week.</p>
          <?php else: ?>
            <ol class="db-agenda">
              <?php foreach ($upcoming as $u): ?>
                <li>
                  <time><b><?= date('D j', strtotime($u['start_time'])) ?></b><?= date('g:i A', strtotime($u['start_time'])) ?></time>
                  <div><span class="db-type db-t-<?= $u['type'] ?>"><?= $TYPE_LABEL[$u['type']] ?? $u['type'] ?></span> <?= htmlspecialchars($u['name']) ?>
                    <?php if (!$u['is_active']): ?><em>switched off</em><?php endif; ?></div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>
      </div>

      <section class="db-ledger">
        <a href="admin_students"><b><?= number_format((int) $counts['students']) ?></b><span>Students</span></a>
        <a href="admin_staff"><b><?= number_format((int) $counts['examiners']) ?></b><span>Examiners</span></a>
        <a href="admin_objective_exams"><b><?= (int) $counts['active'] ?><small>/<?= (int) $counts['exams'] ?></small></b><span>Exams switched on</span></a>
        <div class="db-ledger-types">
          <?php foreach ($TYPE_LABEL as $t => $l): ?><span><i class="db-dot db-t-<?= $t ?>"></i><?= $l ?> <b><?= (int) ($by_type[$t] ?? 0) ?></b></span><?php endforeach; ?>
        </div>
        <a href="<?= $is_center ? 'center_console' : 'admin_exam_groups' ?>"><b><?= (int) $counts['groups'] ?></b><span>Exam groups</span></a>
        <div><b><?= number_format((int) $counts['questions']) ?></b><span>Questions</span></div>
      </section>

      <div class="db-cols db-cols-wide">
        <section class="db-panel">
          <div class="db-panel-head"><h3>Last 14 days</h3><span class="text-muted small"><?= $activity_total ?> results recorded</span></div>
          <div class="db-chart"><canvas id="dbChart" aria-label="Results recorded per day"></canvas></div>
          <div class="db-legend"><span><i class="db-dot db-t-objective"></i>Objective submissions</span><span><i class="db-dot db-t-practical"></i>Students scored by examiners</span></div>
        </section>

        <section class="db-panel">
          <div class="db-panel-head"><h3>Latest results</h3><a href="report" class="db-link">Reports <i class="bi bi-arrow-right"></i></a></div>
          <?php if (!$recent): ?>
            <p class="db-empty">Results appear here as students submit and examiners score.</p>
          <?php else: ?>
            <ul class="db-feed">
              <?php foreach ($recent as $r): ?>
                <li>
                  <span class="db-dot db-t-<?= $r['type'] ?>"></span>
                  <div><b><?= htmlspecialchars($r['sname']) ?></b> <small><?= htmlspecialchars($r['matric_no']) ?></small>
                    <span><?= htmlspecialchars($r['ename']) ?></span></div>
                  <time><?= date('j M', strtotime($r['dt'])) === date('j M') ? date('g:i A', strtotime($r['dt'])) : date('j M', strtotime($r['dt'])) ?></time>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      </div>

      <section class="db-panel">
        <div class="db-panel-head"><h3>Shortcuts</h3></div>
        <div class="db-shortcuts">
          <div>
            <h4>Prepare</h4>
            <a href="admin_objective_exams"><i class="bi bi-ui-checks"></i> Objective exams</a>
            <a href="admin_exams"><i class="bi bi-clipboard2-pulse"></i> Practical exams</a>
            <a href="admin_viva_exams"><i class="bi bi-mic"></i> Viva</a>
            <a href="admin_research_exams"><i class="bi bi-journal-richtext"></i> Research</a>
            <a href="<?= $is_center ? 'center_console' : 'admin_exam_groups' ?>"><i class="bi bi-collection"></i> <?= $is_center ? 'Center Console' : 'Exam groups' ?></a>
          </div>
          <div>
            <h4>People</h4>
            <a href="admin_students"><i class="bi bi-people"></i> Students</a>
            <a href="admin_staff"><i class="bi bi-person-badge"></i> Staff</a>
            <a href="admin_roles"><i class="bi bi-diagram-3"></i> Role assignment</a>
            <a href="admin_upload"><i class="bi bi-cloud-arrow-up"></i> Upload data</a>
          </div>
          <div>
            <h4>Exam day</h4>
            <a href="admin_live_proctor"><i class="bi bi-camera-video"></i> Live monitor</a>
            <a href="admin_devices"><i class="bi bi-pc-display"></i> Approve computers</a>
            <a href="admin_network"><i class="bi bi-wifi"></i> Network &amp; QR code</a>
            <a href="exam_overview"><i class="bi bi-bar-chart"></i> Exam overview</a>
          </div>
          <div>
            <h4>After</h4>
            <a href="report"><i class="bi bi-file-earmark-spreadsheet"></i> Reports</a>
            <a href="admin_student_scores"><i class="bi bi-list-ol"></i> Student scores</a>
            <a href="admin_session_report"><i class="bi bi-calendar-check"></i> Session reports</a>
            <a href="admin_analytics"><i class="bi bi-graph-up"></i> Analytics</a>
          </div>
        </div>
      </section>

      <details class="db-panel db-settings" <?= $success_msg ? 'open' : '' ?>>
        <summary><h3>Student login instructions</h3><span class="text-muted small">shown on the student login page</span></summary>
        <?php if ($success_msg): ?><div class="admin-alert admin-alert-success mt-3"><i class="bi bi-check-circle-fill"></i><span><?= $success_msg ?></span></div><?php endif; ?>
        <form method="post" class="mt-3">
          <?php csrf_field(); ?>
          <textarea name="student_login_content" class="admin-input" rows="4"><?= htmlspecialchars($student_login_content) ?></textarea>
          <button type="submit" name="save_settings" class="admin-btn admin-btn-primary mt-2"><i class="bi bi-save"></i> Save</button>
        </form>
      </details>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/vendor/chartjs/chart.umd.min.js"></script>
  <script>
    (function () {
      var days = <?= json_encode(array_map(fn($d, $v) => ['d' => $d, 'obj' => $v['obj'], 'mark' => $v['mark']], array_keys($days), $days)) ?>;
      var c = document.getElementById('dbChart');
      if (c && window.Chart) {
        var dark = document.body.classList.contains('dark-mode');
        var tick = dark ? '#9fb0a8' : '#6b7a73', grid = dark ? 'rgba(255,255,255,.06)' : '#eef2ef';
        new Chart(c, {
          type: 'bar',
          data: {
            labels: days.map(function (x) { var d = new Date(x.d + 'T00:00:00'); return d.getDate() + ' ' + d.toLocaleString('en', { month: 'short' }); }),
            datasets: [
              { label: 'Objective submissions', data: days.map(function (x) { return x.obj; }), backgroundColor: '#2f5bd3', borderRadius: 3, stack: 's' },
              { label: 'Students scored', data: days.map(function (x) { return x.mark; }), backgroundColor: '#2e9a68', borderRadius: 3, stack: 's' }
            ]
          },
          options: {
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
              x: { stacked: true, grid: { display: false }, ticks: { color: tick, maxRotation: 0, autoSkip: true, maxTicksLimit: 7 } },
              y: { stacked: true, beginAtZero: true, grid: { color: grid }, ticks: { color: tick, precision: 0 } }
            }
          }
        });
      }
      var clock = document.querySelector('[data-clock]');
      if (clock) setInterval(function () { clock.textContent = new Date().toLocaleTimeString('en', { hour: 'numeric', minute: '2-digit' }); }, 30000);
    })();
  </script>
</body>

</html>



