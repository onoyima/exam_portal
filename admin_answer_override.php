<?php
/**
 * Secret score-correction page.
 *
 * Deliberately not linked from the sidebar. An administrator who knows the URL
 * can open a finished (or in-progress) objective attempt, change the options the
 * student selected, and have the attempt score recalculated from the question
 * engine's marking rules. Every change is written to audit_trail.
 *
 * URL: /admin_answer_override
 */
require 'auth.php';
check_auth('admin');
require 'db.php';
require_once 'audit_system.php';

$name = $_SESSION['user'] ?? 'Admin';
$admin_id = isset($_SESSION['examiner_id']) ? (int) $_SESSION['examiner_id'] : null;

$audit = null;
try {
    $audit = new AuditSystem($db);
} catch (Throwable $e) {
    error_log('Answer override audit init failed: ' . $e->getMessage());
}

function override_log(PDO $db, ?AuditSystem $audit, ?int $uid, string $user, int $attempt_id, array $old, array $new): void
{
    try {
        if ($audit) {
            $audit->logAction($uid, 'admin', $user, 'answer_override', 'objective_answers', $attempt_id, $old, $new);
        } else {
            $db->prepare("INSERT INTO audit_trail
                (user_id, user_type, username, action, table_name, record_id, old_values, new_values, ip_address, user_agent)
                VALUES (?, 'admin', ?, 'answer_override', 'objective_answers', ?, ?, ?, ?, ?)")
                ->execute([
                    $uid, $user, $attempt_id, json_encode($old), json_encode($new),
                    $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
        }
    } catch (Throwable $e) {
        error_log('Answer override audit write failed: ' . $e->getMessage());
    }
}

$exam_id = (int) ($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);
$attempt_id = (int) ($_POST['attempt_id'] ?? $_GET['attempt_id'] ?? 0);

$exam = null;
$attempt = null;
$attempts = [];
$questions = [];
$answers = [];
$order = [];
$student = null;

if ($exam_id > 0) {
    $st = $db->prepare("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL");
    $st->execute([$exam_id]);
    $exam = $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($exam) {
    qe_notation($exam);

    $st = $db->prepare("
        SELECT oa.*, s.name AS student_name, s.matric_no, s.username AS student_username
        FROM objective_attempts oa
        LEFT JOIN students s ON s.id = oa.student_id
        WHERE oa.exam_id = ?
        ORDER BY s.name ASC, oa.attempt_no ASC
    ");
    $st->execute([$exam_id]);
    $attempts = $st->fetchAll(PDO::FETCH_ASSOC);

    if ($attempt_id > 0) {
        $st = $db->prepare("
            SELECT oa.*, s.name AS student_name, s.matric_no, s.username AS student_username
            FROM objective_attempts oa
            LEFT JOIN students s ON s.id = oa.student_id
            WHERE oa.id = ? AND oa.exam_id = ?
        ");
        $st->execute([$attempt_id, $exam_id]);
        $attempt = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($attempt) {
            $student = [
                'id' => (int) $attempt['student_id'],
                'name' => $attempt['student_name'] ?? ('Student #' . $attempt['student_id']),
                'matric_no' => $attempt['matric_no'] ?? '',
                'username' => $attempt['student_username'] ?? '',
            ];
            $questions = qe_load_questions($db, $exam_id);
            $order = qe_attempt_question_ids($attempt, $questions);
            $st = $db->prepare("SELECT question_id, selected_option FROM objective_answers
                WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
            $st->execute([$exam_id, (int) $attempt['student_id'], (int) $attempt['attempt_no']]);
            foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $qid => $selected) {
                $answers[(int) $qid] = (string) $selected;
            }
        }
    }
}

/* ─────────────────────────────────────────────────────────────────────
 *  Save corrected answers and recalculate the score
 * ───────────────────────────────────────────────────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'save_answers') {
    validate_csrf();

    if (!$exam || !$attempt) {
        $_SESSION['override_error'] = 'The examination or attempt could not be loaded.';
        header('Location: admin_answer_override');
        exit;
    }

    $incoming = $_POST['answers'] ?? [];
    if (!is_array($incoming)) {
        $incoming = [];
    }

    $old_answers = $answers;
    $old_score = $attempt['score'];
    $changes = [];
    $rejected = 0;

    $db->beginTransaction();
    try {
        foreach ($order as $qid) {
            $q = $questions[$qid];
            $raw = $incoming[$qid] ?? '';

            // MTF controls arrive as one value per statement; the engine stores
            // them as a single string such as "T-FT".
            if ($q['question_type'] === 'mtf' && is_array($raw)) {
                $chars = [];
                $count = count($q['statements']);
                for ($i = 0; $i < $count; $i++) {
                    $v = strtoupper(trim((string) ($raw[$i] ?? '')));
                    $chars[] = ($v === 'T' || $v === 'F') ? $v : '-';
                }
                $raw = implode('', $chars);
            }

            $norm = qe_normalise_answer($q, $raw);
            if ($norm === null) {
                $rejected++;
                continue;
            }

            $before = (string) ($old_answers[$qid] ?? '');
            if ($before === $norm) {
                continue;
            }

            if (!qe_save_answer($db, $q, $exam_id, (int) $attempt['student_id'], (int) $attempt['attempt_no'], $norm)) {
                $rejected++;
                continue;
            }
            $changes[$qid] = ['old' => $before, 'new' => $norm];
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        error_log('Answer override save failed: ' . $e->getMessage());
        $_SESSION['override_error'] = 'The answers could not be saved: ' . $e->getMessage();
        header('Location: admin_answer_override?exam_id=' . $exam_id . '&attempt_id=' . $attempt_id);
        exit;
    }

    // Reload exactly what is now stored, then mark the attempt with the new raw score.
    $st = $db->prepare("SELECT question_id, selected_option FROM objective_answers
        WHERE exam_id = ? AND student_id = ? AND attempt_no = ?");
    $st->execute([$exam_id, (int) $attempt['student_id'], (int) $attempt['attempt_no']]);
    $answers = [];
    foreach ($st->fetchAll(PDO::FETCH_KEY_PAIR) as $qid => $selected) {
        $answers[(int) $qid] = (string) $selected;
    }

    $score = qe_score_attempt($exam, $questions, $attempt, $answers);
    $db->prepare("UPDATE objective_attempts SET score = ? WHERE id = ?")
        ->execute([$score['raw'], $attempt_id]);

    override_log($db, $audit, $admin_id, $name, $attempt_id, [
        'score' => $old_score,
        'answers' => $old_answers,
    ], [
        'exam_id' => $exam_id,
        'student_id' => (int) $attempt['student_id'],
        'attempt_no' => (int) $attempt['attempt_no'],
        'score' => $score['raw'],
        'scaled' => $score['scaled'],
        'scaled_total' => $score['scaled_total'],
        'answers' => $answers,
        'changes' => $changes,
        'rejected' => $rejected,
    ]);

    $_SESSION['override_result'] = [
        'old_score' => $old_score,
        'new_score' => $score['raw'],
        'scaled' => $score['scaled'],
        'scaled_total' => $score['scaled_total'],
        'changes' => count($changes),
        'rejected' => $rejected,
        'finished' => !empty($attempt['finished_at']),
    ];
    header('Location: admin_answer_override?exam_id=' . $exam_id . '&attempt_id=' . $attempt_id);
    exit;
}

/* ─────────────────────────────────────────────────────────────────────
 *  Page data
 * ───────────────────────────────────────────────────────────────────── */
$result_notice = $_SESSION['override_result'] ?? null;
$error_notice = $_SESSION['override_error'] ?? '';
unset($_SESSION['override_result'], $_SESSION['override_error']);

$current_result = null;
if ($exam && $attempt && $questions) {
    $current_result = qe_score_attempt($exam, $questions, $attempt, $answers);
}

$objective_exams = $db->query("
    SELECT id, name, start_time, end_time, total_mark, grading_method
    FROM exams
    WHERE deleted_at IS NULL AND type = 'objective'
    ORDER BY id DESC
")->fetchAll(PDO::FETCH_ASSOC);

function ov_fmt_score($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return rtrim(rtrim(number_format((float) $value, 2), '0'), '.');
}

function ov_status_badge(array $a): string
{
    if (!empty($a['finished_at'])) {
        return '<span class="admin-badge admin-badge-success">Submitted</span>';
    }
    if (!empty($a['is_locked'])) {
        return '<span class="admin-badge admin-badge-danger">Locked</span>';
    }
    return '<span class="admin-badge admin-badge-warning">In progress</span>';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Answer Override - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <?php if ($exam): ?>
    <?= qe_mathjax_head($exam) ?>
  <?php endif; ?>
  <style>
    .ov-answer { border: 1px solid var(--color-border, #dee2e6); border-radius: 8px; padding: 1rem 1.1rem; margin-bottom: 1rem; background: #fff; }
    .ov-answer-head { display: flex; align-items: center; flex-wrap: wrap; gap: .5rem; margin-bottom: .6rem; }
    .ov-answer-num { display: inline-flex; align-items: center; justify-content: center; min-width: 2rem; height: 2rem; border-radius: 50%; background: #e7f1ff; color: #084298; font-weight: 700; font-size: .8rem; }
    .ov-answer-text { font-size: .95rem; margin-bottom: .75rem; line-height: 1.55; }
    .ov-answer-text p:last-child { margin-bottom: 0; }
    .ov-mark { margin-left: auto; font-size: .8rem; color: #6c757d; }
    .ov-options { display: grid; gap: .45rem; }
    .ov-option { display: flex; align-items: flex-start; gap: .55rem; padding: .5rem .65rem; border: 1px solid #e9ecef; border-radius: 6px; cursor: pointer; font-size: .9rem; }
    .ov-option:hover { background: #f8f9fa; }
    .ov-option input { margin-top: .2rem; }
    .ov-option-key { font-weight: 700; min-width: 1rem; }
    .ov-mtf td, .ov-mtf th { vertical-align: middle; }
    .ov-mtf .form-check { margin: 0; display: inline-block; }
    .ov-key { margin-top: .65rem; font-size: .8rem; color: #6c757d; }
    .ov-key code { font-size: .8rem; }
    .ov-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; }
    .ov-summary-item { background: #f8f9fa; border-radius: 8px; padding: .75rem .9rem; }
    .ov-summary-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
    .ov-summary-value { font-size: 1.15rem; font-weight: 700; }
    .ov-scroll { max-height: 70vh; overflow-y: auto; padding-right: .35rem; }
  </style>
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Answer Override</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Answer Override</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Answer Override</h2>
        <div class="admin-page-actions">
          <?php if ($exam): ?>
            <a class="admin-btn admin-btn-outline" href="admin_answer_override"><i class="bi bi-arrow-left me-1"></i> Choose another exam</a>
          <?php endif; ?>
        </div>
      </div>

      <?php display_auth_errors(); ?>

      <?php if ($error_notice): ?>
        <div class="admin-alert admin-alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_notice) ?></div>
      <?php endif; ?>

      <?php if ($result_notice): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle me-2"></i>
          <strong>Saved.</strong>
          <?= (int) $result_notice['changes'] ?> answer<?= (int) $result_notice['changes'] === 1 ? '' : 's' ?> changed.
          Score: <strong><?= ov_fmt_score($result_notice['old_score']) ?></strong>
          &rarr; <strong><?= ov_fmt_score($result_notice['new_score']) ?></strong>
          (scaled <?= ov_fmt_score($result_notice['scaled']) ?> / <?= ov_fmt_score($result_notice['scaled_total']) ?>).
          <?php if (!empty($result_notice['rejected'])): ?>
            <span class="text-warning"><?= (int) $result_notice['rejected'] ?> value(s) were invalid and skipped.</span>
          <?php endif; ?>
          <?php if (empty($result_notice['finished'])): ?>
            <span class="text-muted">The attempt is still open; the score will be confirmed when it is submitted.</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$exam): ?>
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-pencil-square me-2"></i>Select an objective examination</h3>
          </div>
          <div class="admin-card-body">
            <p class="small text-muted">This page edits the options a student selected and recalculates the attempt score. It is not linked from the menu; keep the address private.</p>
            <form method="get" class="row g-2 align-items-end">
              <div class="col-md-8">
                <label class="admin-form-label" for="exam_id">Examination</label>
                <select name="exam_id" id="exam_id" class="admin-input admin-select" required>
                  <option value="">Select an objective examination…</option>
                  <?php foreach ($objective_exams as $ex): ?>
                    <option value="<?= (int) $ex['id'] ?>">
                      <?= htmlspecialchars((string) $ex['name']) ?>
                      <?php if (!empty($ex['start_time'])): ?>
                        — <?= htmlspecialchars(date('M j, Y', strtotime((string) $ex['start_time']))) ?>
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-auto">
                <button type="submit" class="admin-btn admin-btn-primary">Open attempts</button>
              </div>
            </form>
            <?php if (!$objective_exams): ?>
              <div class="admin-empty mt-3">
                <div class="admin-empty-title">No objective examinations</div>
                <div class="admin-empty-desc">Create an objective exam before using this page.</div>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php elseif (!$attempt): ?>
        <div class="admin-card mb-4">
          <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="admin-card-title mb-0"><i class="bi bi-journal-check me-2"></i><?= htmlspecialchars((string) $exam['name']) ?></h3>
            <span class="admin-badge admin-badge-neutral"><?= count($attempts) ?> attempt<?= count($attempts) === 1 ? '' : 's' ?></span>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table mb-0">
                <thead>
                  <tr>
                    <th>Student</th>
                    <th>Matric No.</th>
                    <th>Attempt</th>
                    <th>Started</th>
                    <th>Submitted</th>
                    <th>Stored score</th>
                    <th>Status</th>
                    <th class="actions-cell">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!$attempts): ?>
                    <tr>
                      <td colspan="8" class="text-center text-muted py-4">
                        <i class="bi bi-info-circle me-2"></i>No student has started this examination yet.
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($attempts as $a): ?>
                      <tr>
                        <td class="fw-semibold"><?= htmlspecialchars((string) ($a['student_name'] ?? ('Student #' . $a['student_id']))) ?></td>
                        <td class="font-monospace small"><?= htmlspecialchars((string) ($a['matric_no'] ?? '—')) ?></td>
                        <td>#<?= (int) $a['attempt_no'] ?></td>
                        <td class="small"><?= !empty($a['started_at']) ? htmlspecialchars(date('M j, H:i', strtotime((string) $a['started_at']))) : '—' ?></td>
                        <td class="small"><?= !empty($a['finished_at']) ? htmlspecialchars(date('M j, H:i', strtotime((string) $a['finished_at']))) : '—' ?></td>
                        <td><strong><?= ov_fmt_score($a['score']) ?></strong></td>
                        <td><?= ov_status_badge($a) ?></td>
                        <td class="actions-cell">
                          <a class="admin-btn admin-btn-sm admin-btn-primary" href="admin_answer_override?exam_id=<?= (int) $exam_id ?>&amp;attempt_id=<?= (int) $a['id'] ?>">
                            <i class="bi bi-pencil me-1"></i>Edit answers
                          </a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-card mb-4">
          <div class="admin-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h3 class="admin-card-title mb-0"><i class="bi bi-person-badge me-2"></i>Attempt summary</h3>
            <div class="d-flex gap-2">
              <a class="admin-btn admin-btn-sm admin-btn-outline" href="admin_answer_override?exam_id=<?= (int) $exam_id ?>"><i class="bi bi-arrow-left me-1"></i>All attempts</a>
            </div>
          </div>
          <div class="admin-card-body">
            <div class="ov-summary">
              <div class="ov-summary-item">
                <div class="ov-summary-label">Examination</div>
                <div class="ov-summary-value" style="font-size:1rem;"><?= htmlspecialchars((string) $exam['name']) ?></div>
              </div>
              <div class="ov-summary-item">
                <div class="ov-summary-label">Student</div>
                <div class="ov-summary-value" style="font-size:1rem;"><?= htmlspecialchars((string) $student['name']) ?></div>
                <div class="small text-muted"><?= htmlspecialchars((string) $student['matric_no']) ?><?= $student['username'] !== '' ? ' · ' . htmlspecialchars((string) $student['username']) : '' ?></div>
              </div>
              <div class="ov-summary-item">
                <div class="ov-summary-label">Attempt</div>
                <div class="ov-summary-value">#<?= (int) $attempt['attempt_no'] ?></div>
                <div class="small text-muted"><?= ov_status_badge($attempt) ?></div>
              </div>
              <div class="ov-summary-item">
                <div class="ov-summary-label">Current score</div>
                <div class="ov-summary-value"><?= ov_fmt_score($current_result['raw'] ?? null) ?></div>
                <div class="small text-muted">
                  scaled <?= ov_fmt_score($current_result['scaled'] ?? null) ?> / <?= ov_fmt_score($current_result['scaled_total'] ?? null) ?>
                  <?php if (isset($attempt['score']) && $attempt['score'] !== null && $current_result && abs((float) $attempt['score'] - (float) $current_result['raw']) > 0.0001): ?>
                    · stored <?= ov_fmt_score($attempt['score']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="ov-summary-item">
                <div class="ov-summary-label">Questions</div>
                <div class="ov-summary-value"><?= count($order) ?></div>
                <div class="small text-muted"><?= count($answers) ?> answered</div>
              </div>
            </div>
            <?php if (empty($attempt['finished_at'])): ?>
              <div class="admin-alert admin-alert-warning mt-3 mb-0">
                <i class="bi bi-info-circle me-2"></i>
                This attempt has not been submitted yet. Changes are saved now and the score is recalculated again when the student submits.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <form method="post" onsubmit="return confirm('Save these answers and recalculate the score?');">
          <?php csrf_field(); ?>
          <input type="hidden" name="action" value="save_answers">
          <input type="hidden" name="exam_id" value="<?= (int) $exam_id ?>">
          <input type="hidden" name="attempt_id" value="<?= (int) $attempt_id ?>">

          <div class="ov-scroll">
            <?php if (!$order): ?>
              <div class="admin-empty">
                <div class="admin-empty-title">No questions in this attempt</div>
                <div class="admin-empty-desc">The exam has no live questions, so there is nothing to edit.</div>
              </div>
            <?php endif; ?>

            <?php foreach ($order as $i => $qid):
                $q = $questions[$qid];
                $type = $q['question_type'];
                $current = (string) ($answers[$qid] ?? '');
                $item = $current_result['items'][$qid] ?? null;
                $status_class = 'admin-badge-neutral';
                if (($item['status'] ?? '') === 'correct') {
                    $status_class = 'admin-badge-success';
                } elseif (($item['status'] ?? '') === 'partial') {
                    $status_class = 'admin-badge-warning';
                } elseif (($item['status'] ?? '') === 'wrong') {
                    $status_class = 'admin-badge-danger';
                }
            ?>
              <div class="ov-answer">
                <div class="ov-answer-head">
                  <span class="ov-answer-num">Q<?= $i + 1 ?></span>
                  <span class="admin-badge admin-badge-neutral"><?= htmlspecialchars(qe_type_label($type)) ?></span>
                  <span class="admin-badge <?= $status_class ?>"><?= htmlspecialchars(ucfirst((string) ($item['status'] ?? 'unanswered'))) ?></span>
                  <span class="ov-mark"><?= ov_fmt_score($item['awarded'] ?? 0) ?> / <?= ov_fmt_score($q['mark']) ?> mark<?= (float) $q['mark'] == 1 ? '' : 's' ?></span>
                </div>

                <div class="ov-answer-text"><?= qe_safe_html((string) $q['question_text']) ?></div>
                <?php $qimg = qe_safe_image($q['question_image'] ?? ''); ?>
                <?php if ($qimg): ?>
                  <div class="mb-2"><img src="<?= htmlspecialchars($qimg) ?>" alt="" style="max-width:100%;max-height:220px;border-radius:6px;"></div>
                <?php endif; ?>

                <?php if ($type === 'mtf'): ?>
                  <div class="table-responsive">
                    <table class="admin-table ov-mtf mb-0">
                      <thead>
                        <tr><th style="width:2rem;">#</th><th>Statement</th><th style="width:5rem;">True</th><th style="width:5rem;">False</th><th style="width:5rem;">Blank</th></tr>
                      </thead>
                      <tbody>
                        <?php foreach ($q['statements'] as $idx => $statement):
                            $char = strtoupper(substr($current, $idx, 1));
                            $is_blank = ($char !== 'T' && $char !== 'F');
                        ?>
                          <tr>
                            <td class="fw-semibold"><?= chr(97 + $idx) ?></td>
                            <td><?= qe_safe_html((string) $statement) ?></td>
                            <td><label class="form-check"><input type="radio" class="form-check-input" name="answers[<?= (int) $qid ?>][<?= (int) $idx ?>]" value="T" <?= $char === 'T' ? 'checked' : '' ?>></label></td>
                            <td><label class="form-check"><input type="radio" class="form-check-input" name="answers[<?= (int) $qid ?>][<?= (int) $idx ?>]" value="F" <?= $char === 'F' ? 'checked' : '' ?>></label></td>
                            <td><label class="form-check"><input type="radio" class="form-check-input" name="answers[<?= (int) $qid ?>][<?= (int) $idx ?>]" value="" <?= $is_blank ? 'checked' : '' ?>></label></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php else:
                    $multi = ($type === 'mcq_multi');
                    $selected = $multi ? qe_parse_letters($current) : [$current];
                ?>
                  <div class="ov-options">
                    <?php foreach (QE_OPTION_KEYS as $key):
                        $lk = strtolower($key);
                        $text = (string) ($q['option_' . $lk] ?? '');
                        $image = qe_safe_image($q['option_' . $lk . '_image'] ?? '');
                        if (trim($text) === '' && !$image) {
                            continue;
                        }
                        $checked = $multi ? in_array($key, $selected, true) : ($current === $key);
                    ?>
                      <label class="ov-option">
                        <input type="<?= $multi ? 'checkbox' : 'radio' ?>" name="answers[<?= (int) $qid ?>]<?= $multi ? '[]' : '' ?>" value="<?= $key ?>" <?= $checked ? 'checked' : '' ?>>
                        <span class="ov-option-key"><?= $key ?></span>
                        <span>
                          <?= qe_safe_html($text) ?>
                          <?php if ($image): ?><br><img src="<?= htmlspecialchars($image) ?>" alt="" style="max-height:120px;border-radius:4px;"><?php endif; ?>
                        </span>
                      </label>
                    <?php endforeach; ?>
                    <?php if (!$multi): ?>
                      <label class="ov-option">
                        <input type="radio" name="answers[<?= (int) $qid ?>]" value="" <?= $current === '' ? 'checked' : '' ?>>
                        <span class="ov-option-key">—</span>
                        <span class="text-muted">No answer / clear</span>
                      </label>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <div class="ov-key">
                  Correct answer: <code><?= htmlspecialchars(qe_answer_label($q, $q['correct_option'])) ?></code>
                  <?php if ($type === 'mtf' && $current !== ''): ?>
                    · Student: <code><?= htmlspecialchars($current) ?></code>
                  <?php elseif ($current !== ''): ?>
                    · Student: <code><?= htmlspecialchars(qe_answer_label($q, $current)) ?></code>
                  <?php else: ?>
                    · Student: <em>not answered</em>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if ($order): ?>
            <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
              <div class="small text-muted">
                <i class="bi bi-shield-lock me-1"></i>
                Every change is written to the audit trail with the old and new score.
              </div>
              <div class="d-flex gap-2">
                <a class="admin-btn admin-btn-outline" href="admin_answer_override?exam_id=<?= (int) $exam_id ?>&amp;attempt_id=<?= (int) $attempt_id ?>">Reset</a>
                <button type="submit" class="admin-btn admin-btn-primary"><i class="bi bi-save me-1"></i>Save answers &amp; recalculate</button>
              </div>
            </div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
</body>

</html>
