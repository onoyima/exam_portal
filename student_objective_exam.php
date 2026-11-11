<?php
require 'auth.php';
check_auth('student');
check_device_auth();
require 'db.php';

$student_id = (int) $_SESSION['student_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id) {
  die('No exam selected.');
}

$stmt = $db->prepare("SELECT e.* FROM exams e JOIN exam_students es ON e.id = es.exam_id
    WHERE e.id = ? AND es.student_id = ? AND e.type = 'objective' AND e.is_active = 1 AND e.deleted_at IS NULL
    -- a grouped exam handed to exam centres is written at the centre, not here
    AND (e.group_id IS NULL OR NOT EXISTS (SELECT 1 FROM center_tokens ct WHERE ct.group_id = e.group_id AND ct.revoked_at IS NULL))");
$stmt->execute([$exam_id, $student_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
  $at_centre = $db->prepare("SELECT g.name FROM exams e JOIN exam_groups g ON g.id = e.group_id
      JOIN center_tokens ct ON ct.group_id = g.id AND ct.revoked_at IS NULL WHERE e.id = ? LIMIT 1");
  $at_centre->execute([$exam_id]);
  if ($gname = $at_centre->fetchColumn()) {
    die('This paper is part of the ' . htmlspecialchars($gname) . ' and is written at your exam centre, not on this server.');
  }
  die('You are not assigned to this exam or it is not active.');
}
$self = 'student_objective_exam?exam_id=' . $exam_id;
qe_notation($exam);

/* ---------- final submission ---------- */

if (isset($_POST['confirm_final_submit'])) {
  validate_csrf();
  $attempt_no = intval($_POST['current_attempt_no'] ?? 1);
  $st = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
  $st->execute([$exam_id, $student_id, $attempt_no]);
  $att = $st->fetch(PDO::FETCH_ASSOC);

  if ($att && !$att['finished_at'] && !$att['is_locked']) {
    // The page posts every answer it holds, so anything the autosave had
    // not delivered yet (flaky network) still counts.
    $posted = json_decode((string) ($_POST['answers_json'] ?? ''), true);
    if (is_array($posted) && $posted) {
      $questions = qe_load_questions($db, $exam_id);
      $allowed = array_flip(qe_attempt_question_ids($att, $questions));
      foreach ($posted as $qid => $raw) {
        $qid = (int) $qid;
        if (isset($allowed[$qid]) && is_scalar($raw)) {
          qe_save_answer($db, $questions[$qid], $exam_id, $student_id, $attempt_no, $raw);
        }
      }
    }
    qe_finalize_attempt($db, (int) $att['id']);
  }

  // When the exam shows scores, keep the student signed in and take them to
  // their result. Otherwise end the session as before.
  if ((int) $exam['student_can_view_score'] === 1) {
    header("Location: $self&view_result=1&just_submitted=1");
    exit;
  }
  $db->prepare("UPDATE students SET current_session_id = NULL WHERE id = ?")->execute([$student_id]);
  session_destroy();
  header("Location: student_login?submitted=1");
  exit;
}

/* ---------- scheduling ---------- */

$now_ts = time();
$start_ts = !empty($exam['start_time']) ? strtotime($exam['start_time']) : null;
$end_ts = !empty($exam['end_time']) ? strtotime($exam['end_time']) : null;
$is_view_mode = isset($_GET['view_result']) || isset($_GET['review_attempt']);
if (!$is_view_mode) {
  if ($start_ts && $now_ts < $start_ts) {
    header("Location: student_dashboard?error=" . urlencode('Exam has not started yet. Scheduled for ' . date('M j, Y H:i', $start_ts)));
    exit;
  }
  if ($end_ts && $now_ts > $end_ts) {
    auto_finalize_expired_attempts($db, $exam_id, $student_id);
    header("Location: student_dashboard?error=" . urlencode('Exam access has closed.'));
    exit;
  }
}

/* ---------- which attempt ---------- */

$stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id = ? AND student_id = ? ORDER BY attempt_no DESC LIMIT 1");
$stmt->execute([$exam_id, $student_id]);
$last_attempt = $stmt->fetch(PDO::FETCH_ASSOC);

$attempt = null;
$is_review_mode = isset($_GET['review_attempt']);
$view_result = !$is_review_mode && isset($_GET['view_result']);

function start_attempt(PDO $db, int $exam_id, int $student_id, int $no): void
{
  try {
    $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, login_id, attempt_no, started_at, time_used, last_tick_at) VALUES (?, ?, ?, ?, NOW(), 0, NOW())")
      ->execute([$exam_id, $student_id, $_SESSION['login_id'] ?? null, $no]);
  } catch (PDOException $e) {
    // A double click on "Start" races two inserts; the unique key keeps one.
    if ($e->getCode() !== '23000') {
      throw $e;
    }
  }
}

if ($is_review_mode) {
  $stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
  $stmt->execute([$exam_id, $student_id, intval($_GET['review_attempt'])]);
  $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$attempt || !$attempt['finished_at']) {
    header("Location: $self&view_result=1");
    exit;
  }
} elseif (!$view_result) {
  $lock = $db->prepare("SELECT 1 FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND is_locked = 1 AND finished_at IS NULL LIMIT 1");
  $lock->execute([$exam_id, $student_id]);
  if ($lock->fetchColumn()) {
    header("Location: student_dashboard?error=" . urlencode('This exam has been locked due to security violations. Only an administrator can unlock it.'));
    exit;
  }

  $late_deadline = qe_late_entry_deadline($exam);
  $starting_new = !$last_attempt || ($last_attempt['finished_at'] && isset($_GET['start_new']));
  if ($starting_new && $late_deadline && $now_ts > $late_deadline) {
    header("Location: student_dashboard?error=" . urlencode('Late entry for ' . $exam['name'] . ' closed at ' . date('g:i A', $late_deadline) . '. Please see the invigilator.'));
    exit;
  }

  if (!$last_attempt) {
    if (!isset($_GET['start'])) {
      header("Location: student_dashboard?error=" . urlencode('Exam not started.'));
      exit;
    }
    start_attempt($db, $exam_id, $student_id, 1);
  } elseif ($last_attempt['finished_at']) {
    if (isset($_GET['start_new']) && $last_attempt['attempt_no'] < $exam['max_attempts']) {
      start_attempt($db, $exam_id, $student_id, $last_attempt['attempt_no'] + 1);
    } else {
      header("Location: $self&view_result=1");
      exit;
    }
  } elseif (!isset($_GET['resume']) && !isset($_GET['start']) && !isset($_GET['start_new'])) {
    header("Location: $self&view_result=1");
    exit;
  }

  $stmt->execute([$exam_id, $student_id]);
  $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$attempt || $attempt['finished_at']) {
    header("Location: $self&view_result=1");
    exit;
  }
}

/* ---------- results page ---------- */

if ($view_result) {
  require_once __DIR__ . '/includes/student_exams.php';
  auto_finalize_expired_attempts($db, $exam_id, $student_id);
  $atmpt_stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id = ? AND student_id = ? ORDER BY attempt_no ASC");
  $atmpt_stmt->execute([$exam_id, $student_id]);
  $all_attempts = $atmpt_stmt->fetchAll(PDO::FETCH_ASSOC);
  $has_active = (bool) array_filter($all_attempts, fn($a) => !$a['finished_at']);
  $finished_count = count(array_filter($all_attempts, fn($a) => $a['finished_at']));
  $can_view = (int) $exam['student_can_view_score'] === 1;

  $final = qe_exam_results($db, $exam, null, $student_id)[$student_id] ?? null;
  $attempt_results = $final['all'] ?? [];
  $last_no = $attempt_results ? end($attempt_results)['attempt_no'] : null;

  // Without visible scores there is nothing to show here: go straight to review.
  if (!$can_view && !$has_active && $last_no && !isset($_GET['just_submitted'])) {
    header("Location: $self&review_attempt=$last_no");
    exit;
  }

  $counted_no = $final['attempt_no'] ?? $last_no;
  $counted = null;
  foreach ($attempt_results as $r) {
    if ($r['attempt_no'] === $counted_no) {
      $counted = $r;
    }
  }
  $tally = ['correct' => 0, 'partial' => 0, 'wrong' => 0, 'unanswered' => 0];
  foreach (($counted['items'] ?? []) as $it) {
    $tally[$it['status']]++;
  }
  $score = $final['score'] ?? 0;
  $total = $final['total'] ?? 0;
  $pct = $total > 0 ? max(0, min(100, $score / $total * 100)) : 0;
  $method_label = ['highest' => 'Your best attempt counts', 'average' => 'The average of your attempts counts', 'first' => 'Your first attempt counts', 'last' => 'Your latest attempt counts'][$exam['grading_method'] ?? 'highest'] ?? 'Your best attempt counts';
  $late = qe_late_entry_deadline($exam);
  $can_retry = !$has_active && $finished_count < $exam['max_attempts'] && (!$end_ts || $now_ts < $end_ts) && (!$late || $now_ts <= $late);
  $fmt_time = function ($secs) {
    $secs = (int) $secs;
    return ($secs >= 3600 ? floor($secs / 3600) . 'h ' : '') . floor(($secs % 3600) / 60) . 'm ' . ($secs % 60) . 's';
  };
  $r_len = 2 * M_PI * 70;
  ?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <meta charset="UTF-8">
    <title>Result - <?= htmlspecialchars($exam['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="assets/css/student.css?v=2" rel="stylesheet">
  </head>

  <body class="sd">
    <div class="sd-layout">
      <?php $this_exam = $exam; include 'student_sidebar.php'; $exam = $this_exam; ?>
      <div class="sd-main">
        <header class="sd-top">
          <button type="button" class="sd-menu-btn" data-sd-menu aria-label="Menu">
            <svg viewBox="0 0 20 20" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 5h14M3 10h14M3 15h14"/></svg>
          </button>
          <h1>Result</h1>
          <a class="sd-btn sd-btn-ghost sd-btn-sm" href="student_dashboard">&larr; My exams</a>
        </header>

        <main class="sd-content">
          <?php if (isset($_GET['just_submitted'])): ?>
            <div class="sd-flash is-ok"><span><strong>Submitted.</strong> Your answers for <?= htmlspecialchars($exam['name']) ?> have been recorded.</span><button onclick="this.parentElement.remove()" aria-label="Close">&times;</button></div>
          <?php endif; ?>

          <div class="sd-result-head">
            <h2><?= htmlspecialchars($exam['name']) ?></h2>
            <span class="sd-state <?= $has_active ? 'is-progress' : 'is-done' ?>"><?= $has_active ? 'Attempt in progress' : $finished_count . ' of ' . (int) $exam['max_attempts'] . ' attempt' . ($exam['max_attempts'] == 1 ? '' : 's') . ' used' ?></span>
          </div>

          <?php if ($has_active): ?>
            <div class="sd-card" style="margin-bottom:18px">
              <p style="margin:0 0 12px">You have an attempt in progress. The time you had left has been kept for you.</p>
              <a class="sd-btn sd-btn-continue" href="<?= $self ?>&resume=1">Continue exam</a>
            </div>
          <?php endif; ?>

          <?php if ($attempt_results): ?>
            <div class="sd-result">
              <?php if ($can_view): ?>
                <section class="sd-card">
                  <h3>Score</h3>
                  <div class="sd-ring" aria-label="<?= round($pct) ?> percent">
                    <svg viewBox="0 0 160 160">
                      <circle cx="80" cy="80" r="70" fill="none" stroke="#e6ece8" stroke-width="14"/>
                      <circle cx="80" cy="80" r="70" fill="none" stroke="<?= $pct >= 70 ? '#198754' : ($pct >= 45 ? '#d4a017' : '#c0392b') ?>" stroke-width="14" stroke-linecap="round"
                        stroke-dasharray="<?= round($r_len * $pct / 100, 2) ?> <?= round($r_len, 2) ?>"/>
                    </svg>
                    <div class="sd-ring-text"><b><?= round($pct) ?>%</b><span>of total</span></div>
                  </div>
                  <div class="sd-score-line"><b><?= round($score, 2) ?></b> / <?= round($total, 2) ?></div>
                  <div class="sd-score-note"><?= $method_label ?></div>
                </section>
              <?php endif; ?>

              <section class="sd-card" <?= $can_view ? '' : 'style="grid-column:1 / -1"' ?>>
                <?php if ($can_view && $counted): ?>
                  <h3>Attempt <?= $counted['attempt_no'] ?> at a glance</h3>
                  <div class="sd-breakdown">
                    <div class="is-correct"><b><?= $tally['correct'] ?></b><span>Correct</span></div>
                    <div class="is-partial"><b><?= $tally['partial'] ?></b><span>Partly right</span></div>
                    <div class="is-wrong"><b><?= $tally['wrong'] ?></b><span>Wrong</span></div>
                    <div><b><?= $tally['unanswered'] ?></b><span>Not answered</span></div>
                  </div>
                <?php endif; ?>

                <h3>Your attempts</h3>
                <table class="sd-table">
                  <thead>
                    <tr><th>Attempt</th><th class="sd-hide-sm">Submitted</th><th>Time used</th><?php if ($can_view): ?><th>Score</th><?php endif; ?><th></th></tr>
                  </thead>
                  <tbody>
                    <?php foreach ($attempt_results as $res): $is_counted = $can_view && $res['attempt_no'] === $final['attempt_no']; ?>
                      <tr class="<?= $is_counted ? 'is-counted' : '' ?>">
                        <td><strong>#<?= $res['attempt_no'] ?></strong><?= $is_counted ? '<span class="sd-tag">counts</span>' : '' ?></td>
                        <td class="sd-hide-sm"><?= date('j M Y, g:i A', strtotime($res['attempt']['finished_at'])) ?></td>
                        <td><?= $fmt_time($res['attempt']['time_taken'] ?? 0) ?></td>
                        <?php if ($can_view): ?><td><strong><?= round($res['scaled'], 2) ?></strong> / <?= round($res['scaled_total'], 2) ?></td><?php endif; ?>
                        <td style="text-align:right"><a class="sd-btn sd-btn-ghost sd-btn-sm" href="<?= $self ?>&review_attempt=<?= $res['attempt_no'] ?>">Review</a></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>

                <div class="sd-result-actions">
                  <?php if ($counted_no): ?>
                    <a class="sd-btn sd-btn-start" href="<?= $self ?>&review_attempt=<?= $counted_no ?>">Review answers</a>
                  <?php endif; ?>
                  <?php if ($can_retry): ?>
                    <a class="sd-btn sd-btn-reattempt" href="<?= $self ?>&start_new=1" onclick="return confirm(<?= htmlspecialchars(json_encode('Start attempt ' . ($finished_count + 1) . ' of ' . $exam['max_attempts'] . '? The timer starts immediately.'), ENT_QUOTES) ?>)">Re-attempt (<?= $exam['max_attempts'] - $finished_count ?> left)</a>
                  <?php endif; ?>
                  <a class="sd-btn sd-btn-ghost" href="student_dashboard">Back to my exams</a>
                </div>
              </section>
            </div>
          <?php elseif (!$has_active): ?>
            <div class="sd-empty"><strong>No submitted attempt yet</strong>Once you submit this exam, your result appears here.</div>
          <?php endif; ?>
        </main>
      </div>
    </div>
  </body>

  </html>
  <?php
  exit;
}

/* ---------- exam / review player ---------- */

$attempt_id = (int) $attempt['id'];
$attempt_no = (int) $attempt['attempt_no'];

if (!$is_review_mode) {
  // Count the time since the last heartbeat. If the page is being opened
  // after a power cut or crash, the gap is not charged to the student.
  qe_tick_attempt($db, $attempt_id, 0);
  $st = $db->prepare("SELECT * FROM objective_attempts WHERE id = ?");
  $st->execute([$attempt_id]);
  $attempt = $st->fetch(PDO::FETCH_ASSOC);
}

$bank = qe_load_questions($db, $exam_id);
$order = qe_attempt_question_ids($attempt, $bank);
if (empty($attempt['question_order'])) {
  $order = array_keys($bank);
  if ($exam['shuffle_objective'] || $exam['questions_to_show'] > 0) {
    shuffle($order);
  }
  if ($exam['questions_to_show'] > 0) {
    $order = array_slice($order, 0, (int) $exam['questions_to_show']);
  }
  if ($order && !$is_review_mode) {
    $db->prepare("UPDATE objective_attempts SET question_order = ? WHERE id = ? AND (question_order IS NULL OR question_order = '')")
      ->execute([implode(',', $order), $attempt_id]);
    $st = $db->prepare("SELECT question_order FROM objective_attempts WHERE id = ?");
    $st->execute([$attempt_id]);
    $order = qe_attempt_question_ids(['question_order' => $st->fetchColumn()], $bank);
  }
}
if (!$order) {
  die('No questions have been set for this exam yet. Please inform the invigilator.');
}

$remaining = $is_review_mode ? null : qe_remaining_seconds($exam, $attempt);
if (!$is_review_mode && $remaining === 0) {
  qe_finalize_attempt($db, $attempt_id);
  header("Location: $self&view_result=1");
  exit;
}

$shuffle_opts = (int) ($exam['shuffle_options'] ?? 0) === 1;
$public = [];
foreach ($order as $qid) {
  $public[] = qe_public_question($bank[$qid], $attempt_id, $shuffle_opts);
}

$ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
$ans_stmt->execute([$exam_id, $student_id, $attempt_no]);
$answers = [];
foreach ($ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $qid => $sel) {
  $answers[(int) $qid] = $sel;
}

$review = null;
if ($is_review_mode && (int) $exam['student_can_view_score'] === 1) {
  $review = [];
  foreach ($order as $qid) {
    $q = $bank[$qid];
    $r = qe_score($q, $answers[$qid] ?? '', $exam);
    $review[$qid] = [
      'key' => $q['correct_option'],
      'status' => $r['status'],
      'awarded' => $r['awarded'],
      'statements' => $r['statements'] ?? null,
    ];
  }
}

$strict = (int) $exam['is_strict'] === 1;
$total_students_stmt = $db->prepare("SELECT COUNT(*) FROM exam_students WHERE exam_id = ?");
$total_students_stmt->execute([$exam_id]);
$total_students = (int) $total_students_stmt->fetchColumn();
$config = [
  'examId' => $exam_id,
  'examName' => $exam['name'],
  'attemptId' => $attempt_id,
  'attemptNo' => $attempt_no,
  'review' => $is_review_mode,
  'remaining' => $remaining,
  'duration' => (int) $exam['duration_minutes'] * 60,
  'afterSubmitUrl' => (int) $exam['student_can_view_score'] === 1 ? $self . '&view_result=1&just_submitted=1' : 'student_logout',
  'studentKey' => 'xpz:' . $student_id,
  'questions' => $public,
  'answers' => (object) $answers,
  'result' => $review,
  'violations' => (int) $attempt['violation_count'],
  'maxViolations' => 5,
  'csrf' => generate_csrf_token(),
  'policy' => [
    'fullscreen' => $strict || (int) $exam['proctor_fullscreen'] === 1,
    'autoSubmitStrikes' => ((int) $exam['proctor_auto_submit'] === 1) ? 3 : 0,
    'webcam' => $strict || (int) $exam['proctor_webcam'] === 1,
    'live' => $strict || (int) $exam['proctor_live_video'] === 1,
    'webcamMaxStudents' => defined('WEBCAM_MAX_STUDENTS') ? WEBCAM_MAX_STUDENTS : 500,
  ],
  'resultUrl' => (int) $exam['student_can_view_score'] === 1 ? $self . '&view_result=1' : 'student_dashboard',
  'heartbeatInterval' => defined('HEARTBEAT_INTERVAL') ? HEARTBEAT_INTERVAL : 30000,
  'answerFlushDelay' => defined('ANSWER_FLUSH_DELAY') ? ANSWER_FLUSH_DELAY : 2000,
  'totalStudents' => $total_students,
];
$student_name = $_SESSION['user'] ?? ($_SESSION['student_name'] ?? 'Student');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($exam['name']) ?> - Exam</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/exam_player.css?v=6" rel="stylesheet">
  <?= qe_mathjax_head($exam, 'xp-mathjax') ?>
</head>

<body class="xp<?= $is_review_mode ? ' xp-is-review' : '' ?>">
  <div class="xp-shell" id="xpShell">
    <header class="xp-top">
      <div class="xp-brand">
        <img src="assets/favicon.ico" alt="" width="28" height="28">
        <div class="xp-brand-text">
          <div class="xp-exam-name" title="<?= htmlspecialchars($exam['name']) ?>"><?= $is_review_mode ? 'Review · ' : '' ?><?= htmlspecialchars($exam['name']) ?></div>
          <div class="xp-sub">Attempt <?= $attempt_no ?> · <span id="xpCounter">Question 1 of <?= count($public) ?></span></div>
        </div>
      </div>
      <div class="xp-top-right">
        <span class="xp-sync" id="xpSync" data-state="ok" title="Answer sync status"><i></i><span>Saved</span></span>
        <div class="xp-zoom xp-zoom-top" role="group" aria-label="Text size">
          <button type="button" class="xp-icon-btn" data-zoom="-1" title="Smaller text" aria-label="Smaller text">A&minus;</button>
          <button type="button" class="xp-icon-btn" data-zoom="1" title="Larger text" aria-label="Larger text">A+</button>
        </div>
        <div class="xp-timer" id="xpTimer" aria-live="polite"><span class="xp-timer-label">Time left</span><span id="xpTimerText">--:--</span></div>
        <div class="xp-student">
          <img src="passport_image.php?type=student&id=<?= $student_id ?>" alt="" onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'xp-avatar',textContent:<?= htmlspecialchars(json_encode(strtoupper(mb_substr($student_name, 0, 1))), ENT_QUOTES) ?>}))">
          <span><?= htmlspecialchars($student_name) ?></span>
        </div>
        <?php if (!$is_review_mode): ?>
          <button type="button" class="xp-btn xp-btn-finish xp-top-submit" data-finish>Submit</button>
        <?php endif; ?>
        <button type="button" class="xp-icon-btn xp-palette-toggle" id="xpPaletteToggle" aria-label="Question list">
          <svg viewBox="0 0 24 24" width="20" height="20"><path fill="currentColor" d="M4 4h4v4H4zm6 0h4v4h-4zm6 0h4v4h-4zM4 10h4v4H4zm6 0h4v4h-4zm6 0h4v4h-4zM4 16h4v4H4zm6 0h4v4h-4zm6 0h4v4h-4z"/></svg>
        </button>
      </div>
    </header>

    <div class="xp-progress"><div id="xpProgress"></div></div>

    <main class="xp-main">
      <section class="xp-stage" aria-live="polite">
        <div class="xp-qhead">
          <span class="xp-qno" id="xpQno">Question 1</span>
          <span class="xp-qtype" id="xpQtype"></span>
          <span class="xp-qmark" id="xpQmark"></span>
        </div>
        <div class="xp-qbody" id="xpBody">
          <div class="xp-content" id="xpContent"></div>
        </div>
      </section>

      <aside class="xp-palette" id="xpPalette">
        <div class="xp-palette-head">
          <strong>Questions</strong>
          <span id="xpAnsweredCount">0 / <?= count($public) ?> answered</span>
          <button type="button" class="xp-icon-btn xp-palette-close" id="xpPaletteClose" aria-label="Close">&times;</button>
        </div>
        <div class="xp-zoom xp-zoom-drawer" role="group" aria-label="Text size">
          <span>Text size</span>
          <button type="button" class="xp-icon-btn" data-zoom="-1" aria-label="Smaller text">A&minus;</button>
          <button type="button" class="xp-icon-btn" data-zoom="1" aria-label="Larger text">A+</button>
        </div>
        <div class="xp-grid" id="xpGrid"></div>
        <div class="xp-legend">
          <?php if ($is_review_mode && $review !== null): ?>
            <span><i class="xp-dot is-correct"></i>Correct</span><span><i class="xp-dot is-partial"></i>Partly right</span><span><i class="xp-dot is-wrong"></i>Wrong / blank</span>
          <?php else: ?>
            <span><i class="xp-dot is-answered"></i>Answered</span><span><i class="xp-dot is-partial"></i>Incomplete</span><span><i class="xp-dot is-flagged"></i>Flagged</span><span><i class="xp-dot"></i>Not answered</span>
          <?php endif; ?>
        </div>
      </aside>
    </main>

    <footer class="xp-bottom">
      <button type="button" class="xp-btn xp-btn-ghost" id="xpPrev"><span aria-hidden="true">&larr;</span> Previous</button>
      <div class="xp-bottom-mid">
        <?php if (!$is_review_mode): ?>
          <button type="button" class="xp-btn xp-btn-flag" id="xpFlag" aria-pressed="false">Flag</button>
          <button type="button" class="xp-btn xp-btn-ghost xp-clear" id="xpClear">Clear</button>
        <?php else: ?>
          <a class="xp-btn xp-btn-ghost" href="<?= htmlspecialchars($config['resultUrl']) ?>">Exit review</a>
        <?php endif; ?>
      </div>
      <button type="button" class="xp-btn xp-btn-primary" id="xpNext">Next <span aria-hidden="true">&rarr;</span></button>
    </footer>
  </div>

  <div class="xp-scrim" id="xpScrim" hidden></div>

  <!-- start / fullscreen gate -->
  <div class="xp-overlay" id="xpGate" hidden>
    <div class="xp-panel">
      <h2><?= htmlspecialchars($exam['name']) ?></h2>
      <p id="xpGateText">This exam runs in full-screen mode. Leaving the exam window, switching tabs or copying content is recorded as a violation.</p>
      <ul class="xp-rules">
        <li><?= count($public) ?> question<?= count($public) === 1 ? '' : 's' ?></li>
        <li><?= $remaining === null ? 'No time limit' : 'Time left: ' . gmdate($remaining >= 3600 ? 'G\h i\m' : 'i:s', (int) $remaining) ?></li>
        <li>Answers are saved automatically as you go</li>
      </ul>
      <button type="button" class="xp-btn xp-btn-primary xp-btn-lg" id="xpGateBtn">Enter exam</button>
    </div>
  </div>

  <!-- violation -->
  <div class="xp-overlay xp-overlay-danger" id="xpViolation" hidden>
    <div class="xp-panel">
      <h2>You left the exam screen</h2>
      <p>This has been recorded and the invigilator has been notified. Your timer has kept running.</p>
      <div class="xp-strikes"><span id="xpStrikeCount">1</span> of <span id="xpStrikeMax">5</span> warnings used</div>
      <p class="xp-small" id="xpStrikeNote"></p>
      <button type="button" class="xp-btn xp-btn-primary xp-btn-lg" id="xpReturn">Return to exam</button>
    </div>
  </div>

  <!-- locked -->
  <div class="xp-overlay xp-overlay-dark" id="xpLocked" hidden>
    <div class="xp-panel">
      <h2>Exam locked</h2>
      <p>The maximum number of security warnings has been reached. Your answers so far are saved.<br><strong>Please call the invigilator.</strong></p>
      <div class="xp-strikes"><span id="xpLockCount">5</span> warnings recorded</div>
    </div>
  </div>

  <!-- messages / confirm -->
  <div class="xp-overlay" id="xpDialog" hidden>
    <div class="xp-panel xp-panel-sm" role="dialog" aria-modal="true" aria-labelledby="xpDialogTitle">
      <h2 id="xpDialogTitle"></h2>
      <div id="xpDialogBody"></div>
      <div class="xp-dialog-actions">
        <button type="button" class="xp-btn xp-btn-ghost" id="xpDialogCancel">Cancel</button>
        <button type="button" class="xp-btn xp-btn-primary" id="xpDialogOk">OK</button>
      </div>
    </div>
  </div>

  <div class="xp-toast" id="xpToast" role="status" hidden></div>

  <form id="xpSubmitForm" method="post" action="<?= htmlspecialchars($self) ?>" hidden>
    <input type="hidden" name="confirm_final_submit" value="1">
    <input type="hidden" name="current_attempt_no" value="<?= $attempt_no ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
    <input type="hidden" name="answers_json" value="">
  </form>

  <script>window.EXAM_CONFIG = <?= json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
  <script src="assets/js/exam_player.js?v=6"></script>
  <?php if (!$is_review_mode && $config['policy']['webcam']): ?>
    <script src="assets/vendor/tfjs/tf.min.js" defer></script>
    <script src="assets/js/face_detector.js" defer></script>
    <script src="assets/js/ai_proctor.js" defer></script>
  <?php endif; ?>
</body>

</html>
