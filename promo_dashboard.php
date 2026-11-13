<?php
/**
 * promo_dashboard.php — Staff Promotional Examination dashboard
 *
 * Imitates student_dashboard.php: shows the signed-in promo staff member their
 * active, enrolled exam(s) with state cards. Isolated from the student dashboard.
 */

require 'auth.php';
check_auth(['promo_staff']);
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$staff_id = (int) $_SESSION['promo_staff_id'];
$name = $_SESSION['user'] ?? 'Staff';

$now = time();

// Active exams this staff member is enrolled in
$exams = $db->prepare("
    SELECT pe.*, pes.enrolled_at
    FROM promo_exams pe
    JOIN promo_exam_staff pes ON pes.exam_id = pe.id AND pes.staff_id = ?
    WHERE pe.is_active = 1
      AND (pe.start_time IS NULL OR pe.start_time <= NOW())
      AND (pe.end_time IS NULL OR pe.end_time >= NOW())
    ORDER BY pe.created_at DESC, pe.id DESC
");
$exams->execute([$staff_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);

// Attempts for these exams
$examIds = array_map(fn($e) => (int) $e['id'], $exams);
$attempts = [];
if ($examIds) {
    $in = implode(',', $examIds);
    $q = $db->prepare("SELECT * FROM promo_exam_attempts WHERE staff_id = ? AND exam_id IN ($in) ORDER BY attempt_no");
    $q->execute([$staff_id]);
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $attempts[(int) $a['exam_id']][] = $a;
    }
}

// Question counts per exam
$qcounts = [];
if ($examIds) {
    $in = implode(',', $examIds);
    $qc = $db->query("SELECT exam_id, COUNT(*) AS c FROM promo_exam_questions GROUP BY exam_id");
    foreach ($qc->fetchAll(PDO::FETCH_KEY_PAIR) as $eid => $c) {
        $qcounts[(int) $eid] = (int) $c;
    }
}

$cards = [];
foreach ($exams as $e) {
    $eid = (int) $e['id'];
    $list = $attempts[$eid] ?? [];
    $finished = array_values(array_filter($list, fn($a) => $a['finished_at']));
    $open = null;
    foreach ($list as $a) {
        if (!$a['finished_at']) { $open = $a; break; }
    }
    $submitted = $open && (int) $open['is_submitted'] === 1;
    $start = !empty($e['start_time']) ? strtotime($e['start_time']) : null;
    $end = !empty($e['end_time']) ? strtotime($e['end_time']) : null;
    $url = 'promo_exam?exam_id=' . $eid;

    $kind = $c['primary']['kind'] ?? '';
    $btnClass = ['start' => 'btn-start', 'continue' => 'btn-continue', 'reattempt' => 'btn-reattempt'][$kind] ?? 'btn-ghost';
    $btnIcon = ['start' => 'play-circle', 'continue' => 'arrow-clockwise', 'reattempt' => 'arrow-repeat'][$kind] ?? 'eye';

    if ($submitted) {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => $open, 'submitted' => true, 'state' => 'submitted', 'label' => 'Submitted',
            'primary' => ['label' => 'View submission', 'href' => $url . '&view=1', 'kind' => 'ghost'],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    } elseif ($open) {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => $open, 'submitted' => false, 'state' => 'progress', 'label' => 'In progress',
            'primary' => ['label' => 'Continue exam', 'href' => $url . '&resume=1', 'kind' => 'continue'],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    } elseif ($start && $now < $start) {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => null, 'submitted' => false, 'state' => 'wait',
            'label' => 'Opens ' . date('j M, g:i A', $start),
            'primary' => ['label' => 'Opens at ' . date('g:i A', $start), 'kind' => 'ghost', 'disabled' => true],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    } elseif ($end && $now > $end) {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => null, 'submitted' => false, 'state' => 'closed', 'label' => 'Closed',
            'primary' => ['label' => 'Exam closed', 'kind' => 'ghost', 'disabled' => true],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    } elseif (count($finished) > 0 && !$open) {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => null, 'submitted' => false, 'state' => 'done', 'label' => 'Completed',
            'primary' => ['label' => 'Re-attempt', 'href' => $url . '&start_new=1', 'kind' => 'reattempt', 'confirm' => true],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    } else {
        $card = [
            'exam' => $e, 'questions' => $qcounts[$eid] ?? 0, 'attempts_used' => count($finished),
            'open_attempt' => null, 'submitted' => false, 'state' => 'open', 'label' => 'Start exam',
            'primary' => ['label' => 'Start exam', 'href' => $url . '&start=1', 'kind' => 'start', 'confirm' => true],
            'btnClass' => $btnClass, 'btnIcon' => $btnIcon,
        ];
    }
    $cards[] = $card;
}

$first = explode(' ', trim($name))[0] ?: $name;
$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>My Promotional Exams - Assessment Portal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .promo-wrap { max-width: 980px; margin: 0 auto; padding: 32px 16px 80px; }
        .promo-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .promo-title { font-family: 'Outfit', sans-serif; font-weight: 800; font-size: 1.5rem; color: #092c19; margin: 0; display: flex; align-items: center; gap: 10px; }
        .promo-greeting { color: #475569; font-size: 0.95rem; margin-bottom: 24px; }
        .promo-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .promo-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 20px; display: flex; flex-direction: column; transition: box-shadow .2s; }
        .promo-card:hover { box-shadow: 0 10px 30px rgba(9,44,25,0.08); }
        .promo-card-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 10px; }
        .promo-card-name { font-weight: 700; font-size: 1.05rem; color: #0f172a; margin: 0 0 4px 0; }
        .promo-card-type { display: inline-flex; align-items: center; gap: 6px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 3px 8px; border-radius: 999px; background: #e7f2ec; color: #15803d; margin-bottom: 8px; }
        .promo-card-questions { font-size: 0.82rem; color: #64748b; margin-bottom: 14px; }
        .promo-card-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 4px 10px; border-radius: 999px; margin-bottom: 10px; }
        .is-open .promo-card-badge { background: #dcfce7; color: #166534; }
        .is-wait .promo-card-badge { background: #dbeafe; color: #1e40af; }
        .is-progress .promo-card-badge { background: #fef3c7; color: #92400e; }
        .is-submitted .promo-card-badge { background: #e2e8f0; color: #475569; }
        .is-closed .promo-card-badge { background: #fef2f2; color: #991b1b; }
        .is-done .promo-card-badge { background: #f1f5f9; color: #64748b; }
        .promo-actions { display: flex; gap: 8px; margin-top: auto; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 16px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-start { background: #092c19; color: #fff; }
        .btn-start:hover { background: #061d10; }
        .btn-continue { background: #22c55e; color: #060d16; }
        .btn-continue:hover { background: #4ade80; }
        .btn-ghost { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .btn-ghost:hover { background: #e2e8f0; }
        .btn-ghost:disabled { opacity: .55; cursor: not-allowed; }
        .btn-reattempt { background: #f59e0b; color: #fff; }
        .btn-reattempt:hover { background: #d97706; }
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 24px; }
        .btn-outline { background: transparent; border: 1px solid #cbd5e1; color: #475569; }
        .btn-outline:hover { border-color: #092c19; color: #092c19; }
        @media (max-width: 640px) { .promo-grid { grid-template-columns: 1fr; } .promo-header { flex-direction: column; align-items: flex-start; gap: 8px; } }
    </style>
</head>
<body>
<nav class="admin-navbar" style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
    <div class="admin-navbar-inner" style="padding:0 16px;">
        <div class="admin-navbar-start" style="display:flex;align-items:center;gap:12px;">
            <a href="promo_logout" class="btn btn-outline" style="padding:8px 14px;font-size:.8rem;">Sign out</a>
            <a href="login" class="btn btn-outline" style="padding:8px 14px;font-size:.8rem;">Admin panel</a>
        </div>
        <div class="admin-navbar-end">
            <span class="admin-user-badge" style="background:rgba(9,44,25,0.08);color:#092c19;padding:6px 12px;border-radius:8px;font-weight:700;font-size:.85rem;">
                <?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?>
            </span>
        </div>
    </div>
</nav>

<div class="promo-wrap">
    <div class="promo-header">
        <h1 class="promo-title"><i class="bi bi-mortarboard-fill" style="color:#092c19;"></i> My Promotional Exams</h1>
    </div>
    <p class="promo-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($name) ?> — here are your active promotional exams.</p>

    <?php if (isset($_SESSION['promo_notice'])): ?>
        <div class="alert alert-success" style="background:#dcfce7;color:#166534;border:1px solid #bbf7d0;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:.9rem;">
            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['promo_notice']); unset($_SESSION['promo_notice']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['promo_error'])): ?>
        <div class="alert alert-danger" style="background:#fef2f2;color:#991b1b;border:1px solid #fecaca;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:.9rem;">
            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['promo_error']); unset($_SESSION['promo_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (!$cards): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <p style="font-weight:600;color:#475569;margin-bottom:6px;">No active promotional exams</p>
            <p class="small">You are not currently enrolled in any running promotional exam. Contact your administrator.</p>
        </div>
    <?php else: ?>
        <div class="promo-grid">
            <?php foreach ($cards as $c): ?>
                <div class="promo-card is-<?= $c['state'] ?>">
                    <div class="promo-card-header">
                        <div>
                            <span class="promo-card-type"><?= htmlspecialchars(ucfirst((string) $c['exam']['type'])) ?></span>
                            <h2 class="promo-card-name"><?= htmlspecialchars((string) $c['exam']['name']) ?></h2>
                            <div class="promo-card-questions"><?= $c['questions'] ?> question<?= $c['questions'] !== 1 ? 's' : '' ?> • enrolled <?= date('j M Y', strtotime((string) $c['exam']['created_at'])) ?></div>
                        </div>
                    </div>
                    <span class="promo-card-badge is-<?= $c['state'] ?>"><?= htmlspecialchars($c['label']) ?></span>
                    <?php if (!empty($c['exam']['instructions'])): ?>
                        <div style="font-size:0.8rem;color:#64748b;border-top:1px solid #f1f5f9;padding-top:10px;margin-bottom:12px;"><?= htmlspecialchars((string) $c['exam']['instructions']) ?></div>
                    <?php endif; ?>
                    <div class="promo-actions">
                        <?php if ($c['primary']): ?>
                            <a href="<?= $c['primary']['disabled'] ? '#' : (($c['primary']['href'] ?? '#')) ?>"
                               class="btn <?= $c['btnClass'] ?><?= $c['primary']['disabled'] ? ' disabled' : '' ?>"
                               <?= $c['primary']['disabled'] ? ' disabled' : '' ?>
                               <?= $c['primary']['confirm'] ? 'data-bs-toggle="modal" data-bs-target="#startExamModal" onclick="setStartExamUrl(\'' . htmlspecialchars($c['primary']['href'] ?? '#') . '\', \'' . htmlspecialchars((string) $c['primary']['label']) . '\');"' : '' ?>>
                                <i class="bi bi-<?= $c['btnIcon'] ?>"></i>
                                <?= htmlspecialchars((string) $c['primary']['label']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Start Exam Confirmation Modal -->
<div class="modal fade" id="startExamModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="startExamModalTitle">Start Exam</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="startExamModalMessage">Are you sure you want to start this exam?</p>
        <p class="small text-muted" id="startExamModalDetail"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">Cancel</button>
        <a id="startExamConfirmBtn" href="#" class="btn btn-primary">Yes, Start Exam</a>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
  let startExamUrl = '#';
  function setStartExamUrl(url, label) {
    startExamUrl = url;
    document.getElementById('startExamModalTitle').textContent = 'Start Exam — ' + label;
    document.getElementById('startExamModalMessage').textContent = 'Are you sure you want to start ' + label + '?';
    document.getElementById('startExamModalDetail').textContent = 'Your answers will be recorded and timed once you begin.';
    document.getElementById('startExamConfirmBtn').href = startExamUrl;
  }
</script>
</body>
</html>
