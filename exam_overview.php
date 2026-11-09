<?php
/**
 * Exam overview (in the spirit of Moodle's quiz report): every objective exam
 * at a glance, then per exam the attempt statistics, the score distribution,
 * a live scoreboard and re-scaling of the marks to any total.
 * Admins see every exam; examiners see the exams assigned to them.
 */
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
require_once __DIR__ . '/includes/access.php';
require_once __DIR__ . '/includes/student_import_helper.php';

$name = $_SESSION['user'] ?? 'Staff';
$exam_id = intval($_GET['exam_id'] ?? 0);

/* ---------------------------------------------------------------- */

function ov_status(array $e): array
{
    $now = time();
    if ((int) $e['is_active'] !== 1) {
        return ['Inactive', 'neutral'];
    }
    if (!empty($e['start_time']) && $now < strtotime($e['start_time'])) {
        return ['Scheduled', 'info'];
    }
    if (!empty($e['end_time']) && $now > strtotime($e['end_time'])) {
        return ['Closed', 'neutral'];
    }
    return ['Open', 'success'];
}

/** One row per enrolled student, with their counted result if they finished. */
function ov_scoreboard(PDO $db, array $exam): array
{
    $eid = (int) $exam['id'];
    $results = qe_exam_results($db, $exam);
    $st = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON es.student_id = s.id WHERE es.exam_id = ? AND s.deleted_at IS NULL ORDER BY s.name");
    $st->execute([$eid]);
    $students = $st->fetchAll(PDO::FETCH_ASSOC);
    $at = $db->prepare("SELECT student_id, COUNT(*) AS n, SUM(finished_at IS NOT NULL) AS done, MIN(started_at) AS first_start,
            MAX(finished_at) AS last_finish, MAX(finished_at IS NULL) AS open, MAX(is_locked AND finished_at IS NULL) AS locked,
            MAX(CASE WHEN finished_at IS NULL THEN TIMESTAMPDIFF(SECOND, last_active, NOW()) END) AS idle
        FROM objective_attempts WHERE exam_id = ? GROUP BY student_id");
    $at->execute([$eid]);
    $att = [];
    foreach ($at->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $att[(int) $a['student_id']] = $a;
    }
    $rows = [];
    foreach ($students as $s) {
        $sid = (int) $s['id'];
        $a = $att[$sid] ?? null;
        $r = $results[$sid] ?? null;
        $status = 'Not started';
        if ($a && (int) $a['locked']) {
            $status = 'Locked';
        } elseif ($a && (int) $a['open']) {
            $status = ($a['idle'] !== null && (int) $a['idle'] <= 120) ? 'Writing now' : 'In progress';
        } elseif ($a && (int) $a['done'] > 0) {
            $status = 'Submitted';
        }
        $counted = null;
        if ($r) {
            foreach ($r['all'] as $x) {
                if ($r['attempt_no'] === null || $x['attempt_no'] === $r['attempt_no']) {
                    $counted = $x;
                }
            }
        }
        [$f, $m, $l] = sih_display_name_parts($s);
        $rows[] = [
            'id' => $sid,
            'name' => trim($s['name']) !== '' ? $s['name'] : trim("$f $m $l"),
            'matric' => $s['matric_no'],
            'status' => $status,
            'attempts' => (int) ($a['done'] ?? 0),
            'started' => $a['first_start'] ?? null,
            'finished' => $a['last_finish'] ?? null,
            'time' => $counted['attempt']['time_taken'] ?? null,
            'score' => $r ? round($r['score'], 2) : null,
            'total' => $r ? round($r['total'], 2) : null,
            'pct' => ($r && $r['total'] > 0) ? round($r['score'] / $r['total'] * 100, 2) : null,
        ];
    }
    return $rows;
}

/* ---------------- detail page data / actions ---------------- */

$exam = null;
if ($exam_id) {
    acc_require_exam($db, $exam_id);
    $st = $db->prepare("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL");
    $st->execute([$exam_id]);
    $exam = $st->fetch(PDO::FETCH_ASSOC);
    if (!$exam || $exam['type'] !== 'objective') {
        http_response_code(404);
        die('Exam not found.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_total'])) {
        validate_csrf();
        $t = round((float) $_POST['out_of'], 2);
        if ($t > 0 && $t <= 10000) {
            $db->prepare("UPDATE exams SET total_mark = ? WHERE id = ?")->execute([$t, $exam_id]);
            $_SESSION['ov_flash'] = "Scores for this exam are now reported out of $t everywhere (student results, reports and downloads).";
        }
        header("Location: exam_overview?exam_id=$exam_id");
        exit;
    }

    $bank = qe_load_questions($db, $exam_id);
    $raw_total = array_sum(array_map(fn($q) => $q['mark'], $bank));
    if ((int) $exam['questions_to_show'] > 0 && count($bank)) {
        $raw_total = round($raw_total / count($bank) * min(count($bank), (int) $exam['questions_to_show']), 2);
    }
    $rows = ov_scoreboard($db, $exam);

    if (isset($_GET['download'])) {
        $out_of = (float) ($_GET['out_of'] ?? 0);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="scoreboard_' . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $exam['name']) . ($out_of > 0 ? '_out_of_' . $out_of : '') . '.csv"');
        $o = fopen('php://output', 'w');
        $head = ['Name', 'Matric No', 'Status', 'Attempts', 'Started', 'Submitted', 'Time taken (min)', 'Score', 'Out of', 'Percentage'];
        if ($out_of > 0) {
            $head[] = 'Score out of ' . rtrim(rtrim(number_format($out_of, 2, '.', ''), '0'), '.');
        }
        fputcsv($o, $head);
        foreach ($rows as $r) {
            $line = [$r['name'], $r['matric'], $r['status'], $r['attempts'], $r['started'], $r['finished'],
                $r['time'] !== null ? round($r['time'] / 60, 1) : '', $r['score'] ?? '', $r['total'] ?? '', $r['pct'] !== null ? $r['pct'] : ''];
            if ($out_of > 0) {
                $line[] = $r['pct'] !== null ? round($r['pct'] / 100 * $out_of, 2) : '';
            }
            fputcsv($o, $line);
        }
        fclose($o);
        exit;
    }

    $scored = array_values(array_filter($rows, fn($r) => $r['pct'] !== null));
    $pcts = array_column($scored, 'pct');
    sort($pcts);
    $bands = array_fill(0, 10, 0);
    foreach ($pcts as $p) {
        $bands[min(9, (int) floor($p / 10))]++;
    }
    $count = fn($s) => count(array_filter($rows, fn($r) => $r['status'] === $s));
    $stats = [
        'enrolled' => count($rows),
        'writing' => $count('Writing now'),
        'progress' => $count('In progress') + $count('Locked'),
        'submitted' => $count('Submitted'),
        'not_started' => $count('Not started'),
        'avg' => $pcts ? round(array_sum($pcts) / count($pcts), 1) : null,
        'median' => $pcts ? round(count($pcts) % 2 ? $pcts[intdiv(count($pcts), 2)] : ($pcts[count($pcts) / 2 - 1] + $pcts[count($pcts) / 2]) / 2, 1) : null,
        'high' => $pcts ? max($pcts) : null,
        'low' => $pcts ? min($pcts) : null,
        'pass' => count(array_filter($pcts, fn($p) => $p >= 50)),
    ];
    $flash = $_SESSION['ov_flash'] ?? null;
    unset($_SESSION['ov_flash']);
} else {
    $exams = $db->query("SELECT e.*,
            (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS enrolled,
            (SELECT COUNT(*) FROM objective_questions q WHERE q.exam_id = e.id AND q.deleted_at IS NULL) AS questions,
            (SELECT COUNT(DISTINCT student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NULL AND a.last_active > DATE_SUB(NOW(), INTERVAL 2 MINUTE)) AS writing,
            (SELECT COUNT(DISTINCT student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL) AS submitted
        FROM exams e WHERE e.type = 'objective' AND e.deleted_at IS NULL AND " . acc_exam_sql($db) . "
        ORDER BY (e.is_active = 1) DESC, COALESCE(e.start_time, e.created_at) DESC")->fetchAll(PDO::FETCH_ASSOC);
}
$fmt = fn($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= $exam ? htmlspecialchars($exam['name']) . ' — ' : '' ?>Exam Overview</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .ov-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
    .ov-tile { background: #fff; border: 1px solid #e3e9e5; border-radius: 12px; padding: 14px 16px; }
    .ov-tile b { display: block; font-size: 1.6rem; line-height: 1.1; color: #1d2a24; }
    .ov-tile span { font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; color: #5f6f67; }
    .ov-tile.is-live b { color: #198754; }
    .ov-tile.is-live { border-color: #9fd3b6; background: #f3faf6; }
    .ov-row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 18px; margin-bottom: 20px; }
    .ov-chart-wrap { height: 280px; }
    .ov-scale { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .ov-scale button.ov-preset { border: 1px solid #d7dfdb; background: #fff; border-radius: 8px; padding: 6px 12px; font-weight: 600; }
    .ov-scale button.ov-preset.is-on { background: #198754; border-color: #198754; color: #fff; }
    .ov-status { font-size: .72rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
    .ov-s-writing { background: #e8f3ed; color: #146c43; }
    .ov-s-progress { background: #fdf3e1; color: #8a5a00; }
    .ov-s-locked { background: #fbeaea; color: #a1261b; }
    .ov-s-submitted { background: #e9efff; color: #2f5bd3; }
    .ov-s-not { background: #eef1ef; color: #5f6f67; }
    .ov-table th { cursor: pointer; user-select: none; white-space: nowrap; }
    .ov-table th.sorted-asc::after { content: " ▲"; font-size: .6rem; }
    .ov-table th.sorted-desc::after { content: " ▼"; font-size: .6rem; }
    .ov-table td.num { text-align: right; font-variant-numeric: tabular-nums; }
    .ov-scaled { font-weight: 700; color: #146c43; }
    .ov-filters { display: flex; flex-wrap: wrap; gap: 8px; }
    .ov-filters .admin-input { max-width: 240px; }
    .ov-list td a.ov-name { font-weight: 700; color: #1d2a24; text-decoration: none; }
    .ov-list td a.ov-name:hover { color: #198754; }
    .ov-mini { display: flex; gap: 14px; font-size: .82rem; color: #5f6f67; }
    .ov-mini b { color: #1d2a24; }
    @media (max-width: 980px) { .ov-row { grid-template-columns: 1fr; } }
  </style>
</head>

<body>
  <?php include acc_sidebar(); ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="<?= $exam ? 'exam_overview' : acc_home() ?>" class="admin-back-btn" aria-label="Back"><i class="bi bi-arrow-left"></i></a>
          <h1 class="admin-navbar-title"><?= $exam ? 'Exam Report' : 'Exams' ?></h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge"><span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span><span class="admin-user-name"><?= htmlspecialchars($name) ?></span></div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <?php if (!$exam): ?>
        <div class="admin-page-header">
          <h2 class="admin-page-title"><?= acc_is_admin() ? 'All objective exams' : 'Exams assigned to you' ?></h2>
        </div>
        <div class="admin-card">
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table ov-list mb-0">
                <thead><tr><th>Exam</th><th>Status</th><th>Window</th><th class="text-end">Questions</th><th class="text-end">Students</th><th class="text-end">Writing now</th><th class="text-end">Submitted</th><th></th></tr></thead>
                <tbody>
                  <?php if (!$exams): ?>
                    <tr><td colspan="8" class="text-muted py-4 text-center"><?= acc_is_admin() ? 'No objective exams yet.' : 'No exam has been assigned to you yet. An administrator can assign one under Manage Practical → Examiners.' ?></td></tr>
                  <?php endif; ?>
                  <?php foreach ($exams as $e): [$lbl, $cls] = ov_status($e); ?>
                    <tr>
                      <td><a class="ov-name" href="exam_overview?exam_id=<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></a>
                        <div class="small text-muted"><?= (int) $e['duration_minutes'] ?> min · <?= (int) $e['max_attempts'] ?> attempt<?= $e['max_attempts'] == 1 ? '' : 's' ?></div></td>
                      <td><span class="admin-badge admin-badge-<?= $cls ?>"><?= $lbl ?></span></td>
                      <td class="small"><?= $e['start_time'] ? date('j M, g:i A', strtotime($e['start_time'])) : 'Any time' ?><?= $e['end_time'] ? ' – ' . date('j M, g:i A', strtotime($e['end_time'])) : '' ?></td>
                      <td class="text-end"><?= (int) $e['questions'] ?></td>
                      <td class="text-end"><?= (int) $e['enrolled'] ?></td>
                      <td class="text-end <?= $e['writing'] ? 'text-success fw-bold' : '' ?>"><?= (int) $e['writing'] ?></td>
                      <td class="text-end"><?= (int) $e['submitted'] ?></td>
                      <td class="text-end"><a class="admin-btn admin-btn-sm admin-btn-outline" href="exam_overview?exam_id=<?= (int) $e['id'] ?>">Open</a></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <?php else: [$lbl, $cls] = ov_status($exam); ?>
        <nav class="admin-breadcrumb"><ol><li><a href="exam_overview">Exams</a></li><li class="sep">/</li><li class="current"><?= htmlspecialchars($exam['name']) ?></li></ol></nav>
        <?php if ($flash): ?><div class="admin-alert admin-alert-success"><i class="bi bi-check-circle-fill"></i><span><?= htmlspecialchars($flash) ?></span></div><?php endif; ?>

        <div class="admin-page-header">
          <div>
            <h2 class="admin-page-title mb-1"><?= htmlspecialchars($exam['name']) ?> <span class="admin-badge admin-badge-<?= $cls ?> align-middle"><?= $lbl ?></span></h2>
            <div class="ov-mini">
              <span><b><?= count($bank) ?></b> questions in bank</span>
              <span>Marks per attempt <b><?= $fmt($raw_total) ?></b></span>
              <span>Reported out of <b><?= (float) $exam['total_mark'] > 0 ? $fmt($exam['total_mark']) : $fmt($raw_total) ?></b></span>
              <span><?= (int) $exam['duration_minutes'] ?> min</span>
            </div>
          </div>
          <div class="admin-page-actions">
            <a class="admin-btn admin-btn-outline" href="admin_objective_questions?exam_id=<?= $exam_id ?>"><i class="bi bi-list-ol"></i> Questions</a>
            <a class="admin-btn admin-btn-outline" href="admin_assign_students?exam_id=<?= $exam_id ?>"><i class="bi bi-people"></i> Students</a>
            <a class="admin-btn admin-btn-outline" href="examiner_objective_results?exam_id=<?= $exam_id ?>"><i class="bi bi-table"></i> Answer sheets</a>
            <a class="admin-btn admin-btn-outline" href="admin_live_proctor?exam_id=<?= $exam_id ?>"><i class="bi bi-broadcast"></i> Live monitor</a>
          </div>
        </div>

        <div class="ov-tiles">
          <div class="ov-tile"><b><?= $stats['enrolled'] ?></b><span>Students</span></div>
          <div class="ov-tile is-live"><b id="ovWriting"><?= $stats['writing'] ?></b><span>Writing now</span></div>
          <div class="ov-tile"><b><?= $stats['progress'] ?></b><span>Started, not active</span></div>
          <div class="ov-tile"><b><?= $stats['submitted'] ?></b><span>Submitted</span></div>
          <div class="ov-tile"><b><?= $stats['not_started'] ?></b><span>Not started</span></div>
          <div class="ov-tile"><b><?= $stats['avg'] !== null ? $stats['avg'] . '%' : '—' ?></b><span>Average</span></div>
          <div class="ov-tile"><b><?= $stats['median'] !== null ? $stats['median'] . '%' : '—' ?></b><span>Median</span></div>
          <div class="ov-tile"><b><?= $stats['high'] !== null ? $fmt($stats['high']) . '%' : '—' ?></b><span>Highest</span></div>
          <div class="ov-tile"><b><?= $stats['low'] !== null ? $fmt($stats['low']) . '%' : '—' ?></b><span>Lowest</span></div>
          <div class="ov-tile"><b><?= $pcts ? round($stats['pass'] / count($pcts) * 100) . '%' : '—' ?></b><span>Scored 50%+</span></div>
        </div>

        <div class="ov-row">
          <div class="admin-card mb-0">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-bar-chart"></i> Score distribution</h3><span class="small text-muted"><?= count($pcts) ?> submitted</span></div>
            <div class="admin-card-body">
              <?php if (!$pcts): ?>
                <div class="text-muted">The chart appears once students submit.</div>
              <?php else: ?>
                <div class="ov-chart-wrap"><canvas id="ovChart" aria-label="Number of students in each score band"></canvas></div>
              <?php endif; ?>
            </div>
          </div>
          <div class="admin-card mb-0">
            <div class="admin-card-header"><h3 class="admin-card-title"><i class="bi bi-calculator"></i> Recalculate scores</h3></div>
            <div class="admin-card-body">
              <p class="small text-muted">Show every student's score out of a different total. For example, an exam of <?= $fmt($raw_total) ?> marks out of 100, 70 or 30. The table and the download update straight away; nothing is saved unless you press <strong>Save</strong>.</p>
              <div class="ov-scale mb-3">
                <?php foreach ([100, 70, 60, 40, 30, 20, 10] as $p): ?>
                  <button type="button" class="ov-preset" data-out-of="<?= $p ?>"><?= $p ?></button>
                <?php endforeach; ?>
                <input type="number" id="ovOutOf" class="admin-input" style="width:110px" min="1" step="0.5" placeholder="Other">
              </div>
              <div class="d-flex flex-wrap gap-2">
                <a class="admin-btn admin-btn-primary" id="ovDownload" href="exam_overview?exam_id=<?= $exam_id ?>&download=1"><i class="bi bi-download"></i> Download scoreboard</a>
                <form method="post" class="d-inline" id="ovSaveForm" data-swal="Report this exam out of the chosen total everywhere (student results, reports and downloads)?">
                  <?php csrf_field(); ?>
                  <input type="hidden" name="out_of" id="ovSaveVal">
                  <button type="submit" name="set_total" value="1" class="admin-btn admin-btn-outline" id="ovSave" disabled>Save as official total</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="admin-card">
          <div class="admin-card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h3 class="admin-card-title mb-0"><i class="bi bi-trophy"></i> Scoreboard</h3>
            <div class="ov-filters">
              <select class="admin-input admin-select" id="ovStatus">
                <option value="">All statuses</option>
                <option>Writing now</option><option>In progress</option><option>Locked</option><option>Submitted</option><option>Not started</option>
              </select>
              <input type="search" class="admin-input" id="ovSearch" placeholder="Name or matric">
            </div>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table ov-table mb-0" id="ovTable">
                <thead>
                  <tr>
                    <th data-k="i">#</th><th data-k="name">Student</th><th data-k="status">Status</th><th data-k="started">Started</th>
                    <th data-k="finished">Submitted</th><th data-k="time" class="text-end">Time</th><th data-k="attempts" class="text-end">Attempts</th>
                    <th data-k="score" class="text-end">Score</th><th data-k="pct" class="text-end">%</th><th data-k="pct" class="text-end ov-scaled-head" hidden>Out of <span id="ovOutOfHead"></span></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <?php if ($exam): ?>
    <script src="assets/vendor/chartjs/chart.umd.min.js"></script>
    <script>
      (function () {
        var rows = <?= json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
        var bands = <?= json_encode($bands) ?>;
        var examId = <?= $exam_id ?>;
        var outOf = null, sortKey = 'name', sortDir = 1;
        var tbody = document.querySelector('#ovTable tbody');
        var cls = { 'Writing now': 'writing', 'In progress': 'progress', 'Locked': 'locked', 'Submitted': 'submitted', 'Not started': 'not' };
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
        function t(s) { if (!s) return '—'; var d = new Date(s.replace(' ', 'T')); return d.toLocaleString(undefined, { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }); }
        function num(n) { return Math.round(n * 100) / 100; }

        function draw() {
          var q = document.getElementById('ovSearch').value.trim().toLowerCase();
          var st = document.getElementById('ovStatus').value;
          var list = rows.filter(function (r) {
            return (!st || r.status === st) && (!q || (r.name + ' ' + r.matric).toLowerCase().indexOf(q) !== -1);
          });
          list.sort(function (a, b) {
            var x = a[sortKey], y = b[sortKey];
            if (x == null) return 1; if (y == null) return -1;
            return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y))) * sortDir;
          });
          tbody.innerHTML = list.map(function (r, i) {
            return '<tr><td>' + (i + 1) + '</td><td><div class="fw-semibold">' + esc(r.name) + '</div><div class="small text-muted font-monospace">' + esc(r.matric) + '</div></td>' +
              '<td><span class="ov-status ov-s-' + cls[r.status] + '">' + r.status + '</span></td>' +
              '<td class="small">' + t(r.started) + '</td><td class="small">' + t(r.finished) + '</td>' +
              '<td class="num small">' + (r.time != null ? Math.round(r.time / 60) + ' min' : '—') + '</td>' +
              '<td class="num">' + r.attempts + '</td>' +
              '<td class="num">' + (r.score != null ? num(r.score) + ' / ' + num(r.total) : '') + '</td>' +
              '<td class="num">' + (r.pct != null ? num(r.pct) + '%' : '') + '</td>' +
              (outOf ? '<td class="num ov-scaled">' + (r.pct != null ? num(r.pct / 100 * outOf) : '') + '</td>' : '') + '</tr>';
          }).join('') || '<tr><td colspan="10" class="text-muted text-center py-4">No students match.</td></tr>';
        }

        document.querySelectorAll('#ovTable th[data-k]').forEach(function (th) {
          th.addEventListener('click', function () {
            var k = th.getAttribute('data-k');
            sortDir = (k === sortKey) ? -sortDir : (k === 'pct' || k === 'score' ? -1 : 1);
            sortKey = k === 'i' ? 'name' : k;
            document.querySelectorAll('#ovTable th').forEach(function (x) { x.classList.remove('sorted-asc', 'sorted-desc'); });
            th.classList.add(sortDir === 1 ? 'sorted-asc' : 'sorted-desc');
            draw();
          });
        });
        document.getElementById('ovSearch').addEventListener('input', draw);
        document.getElementById('ovStatus').addEventListener('change', draw);

        function setOutOf(v) {
          outOf = v > 0 ? v : null;
          document.querySelectorAll('.ov-preset').forEach(function (b) { b.classList.toggle('is-on', +b.getAttribute('data-out-of') === outOf); });
          document.querySelector('.ov-scaled-head').hidden = !outOf;
          document.getElementById('ovOutOfHead').textContent = outOf || '';
          document.getElementById('ovDownload').href = 'exam_overview?exam_id=' + examId + '&download=1' + (outOf ? '&out_of=' + outOf : '');
          document.getElementById('ovDownload').innerHTML = '<i class="bi bi-download"></i> Download scoreboard' + (outOf ? ' (out of ' + outOf + ')' : '');
          document.getElementById('ovSave').disabled = !outOf;
          document.getElementById('ovSaveVal').value = outOf || '';
          draw();
        }
        document.querySelectorAll('.ov-preset').forEach(function (b) {
          b.addEventListener('click', function () {
            var v = +b.getAttribute('data-out-of');
            document.getElementById('ovOutOf').value = '';
            setOutOf(outOf === v ? null : v);
          });
        });
        document.getElementById('ovOutOf').addEventListener('input', function () { setOutOf(parseFloat(this.value)); });
        draw();

        var c = document.getElementById('ovChart');
        if (c && window.Chart) {
          var labels = ['0–10', '10–20', '20–30', '30–40', '40–50', '50–60', '60–70', '70–80', '80–90', '90–100'];
          new Chart(c, {
            type: 'bar',
            data: { labels: labels.map(function (l) { return l + '%'; }), datasets: [{ label: 'Students', data: bands, borderRadius: 4,
              backgroundColor: bands.map(function (_, i) { return i < 4 ? '#e0897f' : i < 5 ? '#e8b45c' : i < 7 ? '#8cc9a6' : '#2e9a68'; }) }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false },
              tooltip: { callbacks: { label: function (x) { return x.raw + ' student' + (x.raw === 1 ? '' : 's'); } } } },
              scales: { y: { beginAtZero: true, ticks: { precision: 0 }, title: { display: true, text: 'Students' } }, x: { title: { display: true, text: 'Score band' } } } }
          });
        }
      })();
    </script>
  <?php endif; ?>
</body>

</html>
