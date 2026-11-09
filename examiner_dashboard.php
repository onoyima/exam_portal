<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';
require_once 'includes/access.php';

$name = $_SESSION['user'] ?? 'Examiner';
$xid = acc_examiner_id($db);
$st = $db->prepare("SELECT x.*, g.name AS group_name, g.exam_ends, g.ended_at FROM examiners x LEFT JOIN exam_groups g ON g.id = x.group_id WHERE x.id = ?");
$st->execute([$xid]);
$me = $st->fetch(PDO::FETCH_ASSOC) ?: ['name' => $name, 'group_id' => null];
$display = trim(($me['firstname'] ?? '') . ' ' . ($me['lastname'] ?? '')) ?: ($me['name'] ?: $name);

$TYPE_LABEL = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];
$ids = acc_exam_ids($db) ?? [];
$exams = [];
if ($ids) {
  $in = implode(',', array_map('intval', $ids));
  $exams = $db->query("SELECT e.*, g.name AS group_name,
        (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS enrolled,
        CASE WHEN e.type = 'objective'
          THEN (SELECT COUNT(DISTINCT a.student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL)
          ELSE (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id) END AS done,
        (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id AND a.examiner_id = $xid) AS mine,
        CASE WHEN e.type = 'objective'
          THEN (SELECT COUNT(*) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NULL AND a.last_active > DATE_SUB(NOW(), INTERVAL 2 MINUTE))
          ELSE 0 END AS writing
      FROM exams e LEFT JOIN exam_groups g ON g.id = e.group_id
      WHERE e.id IN ($in) AND e.deleted_at IS NULL
      ORDER BY e.is_active DESC, FIELD(e.type, 'practical', 'viva', 'research', 'objective'), e.name")->fetchAll(PDO::FETCH_ASSOC);
}

$recent = $db->prepare("SELECT s.name AS sname, s.matric_no, e.name AS ename, e.type, MAX(COALESCE(a.updated_at, a.created_at)) AS dt, SUM(a.score) AS total
    FROM answers a JOIN students s ON s.id = a.student_id JOIN exams e ON e.id = a.exam_id
    WHERE a.examiner_id = ? GROUP BY a.exam_id, a.student_id ORDER BY dt DESC LIMIT 8");
$recent->execute([$xid]);
$recent = $recent->fetchAll(PDO::FETCH_ASSOC);
$scored_today = $db->prepare("SELECT COUNT(DISTINCT exam_id, student_id) FROM answers WHERE examiner_id = ? AND DATE(COALESCE(updated_at, created_at)) = CURDATE()");
$scored_today->execute([$xid]);
$scored_today = (int) $scored_today->fetchColumn();

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$open = fn($e) => $e['is_active'] && (!$e['start_time'] || strtotime($e['start_time']) <= time()) && (!$e['end_time'] || strtotime($e['end_time']) >= time());
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>My exams</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/css/dashboard.css?v=1" rel="stylesheet">
</head>

<body>
  <?php include 'examiner_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">My exams</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge"><span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($display, 0, 1))) ?></span><span class="admin-user-name"><?= htmlspecialchars($display) ?></span></div>
          <a href="logout" class="admin-logout-btn" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content db-page">
      <header class="db-head">
        <div>
          <p class="db-date"><?= date('l, j F Y') ?></p>
          <h2><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $display)[0]) ?>.</h2>
          <p class="db-lead"><?= count($exams) ?> exam<?= count($exams) === 1 ? '' : 's' ?> assigned to you<?= $scored_today ? ' · you scored <b>' . $scored_today . '</b> student' . ($scored_today === 1 ? '' : 's') . ' today' : '' ?>.</p>
        </div>
      </header>

      <?php if (!empty($me['group_id'])): ?>
        <div class="xd-group-note">
          <i class="bi bi-shield-lock fs-4"></i>
          <div>This account is for <b><?= htmlspecialchars($me['group_name'] ?? 'one exam group') ?></b> only. It works on one computer at a time and closes
            <?= !empty($me['exam_ends']) ? 'on <b>' . date('j M Y, g:i A', strtotime($me['exam_ends'])) . '</b>' : 'when the centre manager ends the exam' ?>. Sign out when you leave this computer.</div>
        </div>
      <?php endif; ?>

      <?php if (!$exams): ?>
        <div class="db-panel"><p class="db-empty mb-0">No exam has been assigned to you yet. Ask the <?= !empty($me['group_id']) ? 'centre manager' : 'administrator' ?> to assign one.</p></div>
      <?php else: ?>
        <div class="xd-cards mb-4">
          <?php foreach ($exams as $e): $is_open = $open($e); $pct = $e['enrolled'] ? min(100, round($e['done'] / $e['enrolled'] * 100)) : 0; ?>
            <article class="xd-card">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <span class="db-type db-t-<?= $e['type'] ?>"><?= $TYPE_LABEL[$e['type']] ?? $e['type'] ?></span>
                  <h4 class="mt-1"><?= htmlspecialchars($e['name']) ?></h4>
                  <small><?= $e['group_name'] ? htmlspecialchars($e['group_name']) . ' · ' : '' ?><?= $e['start_time'] ? date('j M, g:i A', strtotime($e['start_time'])) : 'no fixed time' ?></small>
                </div>
                <span class="admin-badge admin-badge-<?= $is_open ? 'success' : 'secondary' ?>"><?= $is_open ? 'Open' : ($e['is_active'] ? 'Scheduled' : 'Off') ?></span>
              </div>
              <div class="xd-nums">
                <div><b><?= (int) $e['enrolled'] ?></b>students</div>
                <?php if ($e['type'] === 'objective'): ?>
                  <div><b class="<?= $e['writing'] ? 'text-success' : '' ?>"><?= (int) $e['writing'] ?></b>writing</div>
                  <div><b><?= (int) $e['done'] ?></b>submitted</div>
                <?php else: ?>
                  <div><b><?= (int) $e['done'] ?></b>scored</div>
                  <div><b><?= (int) $e['mine'] ?></b>by you</div>
                <?php endif; ?>
              </div>
              <div class="db-bar"><i style="width:<?= $pct ?>%"></i></div>
              <div class="xd-actions">
                <?php if ($e['type'] === 'objective'): ?>
                  <a class="admin-btn admin-btn-sm admin-btn-primary" href="exam_overview?exam_id=<?= (int) $e['id'] ?>">Overview</a>
                  <a class="admin-btn admin-btn-sm admin-btn-outline" href="admin_live_proctor?exam_id=<?= (int) $e['id'] ?>">Live monitor</a>
                  <a class="admin-btn admin-btn-sm admin-btn-outline" href="examiner_objective_results?exam_id=<?= (int) $e['id'] ?>">Answer sheets</a>
                <?php elseif ($e['is_active']): ?>
                  <a class="admin-btn admin-btn-sm admin-btn-primary" href="assess?exam_id=<?= (int) $e['id'] ?>">Score students</a>
                <?php else: ?>
                  <span class="small text-muted">Scoring opens when the exam is switched on.</span>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <section class="db-panel">
        <div class="db-panel-head"><h3>Your latest scores</h3></div>
        <?php if (!$recent): ?>
          <p class="db-empty">Students you score appear here.</p>
        <?php else: ?>
          <ul class="db-feed">
            <?php foreach ($recent as $r): ?>
              <li>
                <span class="db-dot db-t-<?= $r['type'] ?>"></span>
                <div><b><?= htmlspecialchars($r['sname']) ?></b> <small><?= htmlspecialchars($r['matric_no']) ?></small><span><?= htmlspecialchars($r['ename']) ?> · total <?= rtrim(rtrim(number_format((float) $r['total'], 2, '.', ''), '0'), '.') ?></span></div>
                <time><?= $r['dt'] ? date('j M, g:i A', strtotime($r['dt'])) : '' ?></time>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
</body>

</html>
