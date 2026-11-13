<?php
/**
 * promo_exam.php — Staff Promotional Examination player
 *
 * Imitates the normal student exam screen: timer, question cards, auto-save,
 * submit, and result review. Isolated from the core exam system.
 */

require 'auth.php';
check_auth(['promo_staff']);
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$staff_id = (int) $_SESSION['promo_staff_id'];
$name = $_SESSION['user'] ?? 'Staff';
$error = '';
$notice = '';

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
$action = $_GET['action'] ?? '';
$view = isset($_GET['view']) ? 1 : 0;

// Load the exam
$exam = $db->prepare("SELECT * FROM promo_exams WHERE id = ? AND is_active = 1");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    header("Location: promo_dashboard");
    exit;
}

// Confirm staff is enrolled
$enrolled = $db->prepare("SELECT 1 FROM promo_exam_staff WHERE exam_id = ? AND staff_id = ? LIMIT 1");
$enrolled->execute([$exam_id, $staff_id]);
if (!$enrolled->fetchColumn()) {
    $_SESSION['promo_error'] = 'You are not enrolled in this exam.';
    header("Location: promo_dashboard");
    exit;
}

$now = time();
$start = !empty($exam['start_time']) ? strtotime($exam['start_time']) : null;
$end = !empty($exam['end_time']) ? strtotime($exam['end_time']) : null;
$duration = (int) ($exam['duration_minutes'] ?? 0) * 60;

// Load questions
$questions = $db->prepare("SELECT * FROM promo_exam_questions WHERE exam_id = ? ORDER BY sort_order ASC, id ASC");
$questions->execute([$exam_id]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);

// ── VIEW MODE: review submitted result ──
$viewing = false;
$result = null;
$answers = [];

if ($view) {
    $stmt = $db->prepare("SELECT * FROM promo_exam_attempts WHERE staff_id = ? AND exam_id = ? AND is_submitted = 1 AND finished_at IS NOT NULL ORDER BY attempt_no DESC LIMIT 1");
    $stmt->execute([$staff_id, $exam_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $viewing = true;
        $attemptId = (int) $result['id'];
        $attemptNo = (int) $result['attempt_no'];
        $startedAt = $result['started_at'];
        // Load answers for display
        $ansQ = $db->prepare("SELECT question_id, selected_option, answer_text, score FROM promo_exam_answers WHERE attempt_id = ?");
        $ansQ->execute([$attemptId]);
        foreach ($ansQ->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $answers[(int) $a['question_id']] = $a;
        }
    } else {
        $_SESSION['promo_error'] = 'No submitted attempt to review.';
        header("Location: promo_dashboard");
        exit;
    }
}

// ── PLAY MODE: find or create an open attempt ──
$attemptId = null;
$attemptNo = 1;
$startedAt = null;

if (!$viewing) {
    $attempt = $db->prepare("SELECT * FROM promo_exam_attempts WHERE staff_id = ? AND exam_id = ? AND finished_at IS NULL ORDER BY attempt_no DESC LIMIT 1");
    $attempt->execute([$staff_id, $exam_id]);
    $attempt = $attempt->fetch(PDO::FETCH_ASSOC);

    if ($action === 'start_new' && !$attempt) {
        $maxAttemptsRow = $db->prepare("SELECT MAX(attempt_no) AS m FROM promo_exam_attempts WHERE staff_id = ? AND exam_id = ?");
        $maxAttemptsRow->execute([$staff_id, $exam_id]);
        $maxAttempts = (int) ($maxAttemptsRow->fetchColumn() ?? 0);
        $attemptNo = $maxAttempts + 1;
        $startedAt = date('Y-m-d H:i:s');
        $db->prepare("INSERT INTO promo_exam_attempts (exam_id, staff_id, attempt_no, started_at) VALUES (?, ?, ?, ?)")
            ->execute([$exam_id, $staff_id, $attemptNo, $startedAt]);
        $attemptId = (int) $db->lastInsertId();
        $_SESSION['promo_notice'] = 'New attempt started.';
        header("Location: promo_exam?exam_id=$exam_id");
        exit;
    }

    if (!$attempt) {
        $attemptNo = 1;
        $startedAt = date('Y-m-d H:i:s');
        $db->prepare("INSERT INTO promo_exam_attempts (exam_id, staff_id, attempt_no, started_at) VALUES (?, ?, ?, ?)")
            ->execute([$exam_id, $staff_id, $attemptNo, $startedAt]);
        $attemptId = (int) $db->lastInsertId();
    } else {
        $attemptId = (int) $attempt['id'];
        $attemptNo = (int) $attempt['attempt_no'];
        $startedAt = $attempt['started_at'];
    }

    // Load existing answers for this attempt
    $ansQ = $db->prepare("SELECT question_id, selected_option, answer_text, score FROM promo_exam_answers WHERE attempt_id = ?");
    $ansQ->execute([$attemptId]);
    foreach ($ansQ->fetchAll(PDO::FETCH_ASSOC) as $a) {
        $answers[(int) $a['question_id']] = $a;
    }
}

// AJAX: save an answer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_answer') {
    header('Content-Type: application/json');
    $qid = (int) ($_POST['question_id'] ?? 0);
    $selected = $_POST['selected_option'] ?? null;
    $text = $_POST['answer_text'] ?? null;
    if ($qid <= 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid question.']);
        exit;
    }
    $qok = $db->prepare("SELECT 1 FROM promo_exam_questions WHERE id = ? AND exam_id = ? LIMIT 1");
    $qok->execute([$qid, $exam_id]);
    if (!$qok->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Question not found.']);
        exit;
    }
    // Only save on open (unsubmitted) attempts
    $db->prepare("INSERT INTO promo_exam_answers (attempt_id, question_id, selected_option, answer_text, score)
        VALUES (?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE selected_option = VALUES(selected_option), answer_text = VALUES(answer_text), submitted_at = NOW()")
        ->execute([$attemptId, $qid, $selected, $text]);
    echo json_encode(['success' => true]);
    exit;
}

// AJAX: submit exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_exam') {
    header('Content-Type: application/json');
    $db->prepare("UPDATE promo_exam_attempts SET is_submitted = 1, finished_at = NOW() WHERE id = ? AND staff_id = ? AND finished_at IS NULL")
        ->execute([$attemptId, $staff_id]);

    $totalScore = 0.0;
    $totalPossible = 0.0;
    foreach ($questions as $q) {
        $qid = (int) $q['id'];
        $marks = (float) ($q['marks'] ?? 1.00);
        $totalPossible += $marks;
        if ((string) $q['question_type'] !== 'objective') continue;
        $ans = $answers[$qid] ?? null;
        $selected = $ans['selected_option'] ?? null;
        $correct = (string) ($q['correct_answer'] ?? '');
        $earned = 0.0;
        if ($selected !== null && $selected !== '' && $correct !== '' && strtoupper($selected) === strtoupper($correct)) {
            $earned = $marks;
        }
        if ($ans) {
            $db->prepare("UPDATE promo_exam_answers SET score = ? WHERE attempt_id = ? AND question_id = ?")
                ->execute([$earned, $attemptId, $qid]);
        }
        $totalScore += $earned;
    }
    $db->prepare("UPDATE promo_exam_attempts SET score = ? WHERE id = ?")
        ->execute([$totalScore, $attemptId]);

    $_SESSION['promo_notice'] = 'Exam submitted. Score: ' . number_format($totalScore, 2) . ' / ' . number_format($totalPossible, 2);
    echo json_encode(['success' => true, 'score' => $totalScore, 'total' => $totalPossible, 'redirect' => 'promo_exam?exam_id=' . $exam_id . '&view=1']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?= htmlspecialchars((string) $exam['name']) ?> - Promo Exam</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/vendor/fonts/fonts.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        :root { --primary: #092c19; --accent: #22c55e; --dark: #060d16; }
        body { background: #060d16; font-family: 'Outfit', sans-serif; color: #e8f0e9; overflow-x: hidden; }
        .exam-wrap { max-width: 900px; margin: 0 auto; padding: 0 16px 80px; }
        /* Secure header */
        .secure-header { position: sticky; top: 0; z-index: 100; height: 60px; background: rgba(6,13,22,0.85); border-bottom: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(20px); display: flex; align-items: center; padding: 0 24px; gap: 12px; }
        .secure-header .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1rem; color: #e8f0e9; }
        .secure-header .shield-icon { width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #fff; }
        .timer-box { margin-left: auto; font-weight: 800; font-size: 1.1rem; color: #22c55e; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); padding: 8px 14px; border-radius: 10px; white-space: nowrap; }
        .timer-box.warn { color: #f59e0b; border-color: rgba(245,158,11,0.3); background: rgba(245,158,11,0.1); }
        .timer-box.danger { color: #ef4444; border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.1); }
        .secure-badge { margin-left: 12px; background: rgba(0,230,118,0.1); border: 1px solid rgba(0,230,118,0.3); color: #00e676; padding: 4px 12px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; letter-spacing: 0.1em; }
        /* Exam content */
        .exam-content { background: #fff; color: #1e293b; border-radius: 16px; overflow: hidden; margin-top: 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); }
        .exam-header { background: var(--primary); color: #fff; padding: 24px; display: flex; flex-direction: column; gap: 10px; }
        .exam-title { font-weight: 800; font-size: 1.35rem; margin: 0; text-transform: uppercase; letter-spacing: -0.5px; }
        .exam-meta { font-size: 0.85rem; color: rgba(255,255,255,0.8); display: flex; gap: 16px; flex-wrap: wrap; }
        .exam-meta span { display: flex; align-items: center; gap: 6px; }
        .exam-instructions { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 24px; font-size: 0.92rem; color: #475569; }
        /* Question card */
        .question-card { border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; overflow: hidden; background: #fff; }
        .question-card-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; }
        .q-number { font-weight: 800; color: var(--primary); font-size: 0.9rem; }
        .q-type { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 2px 8px; border-radius: 6px; background: #e2e8f0; color: #475569; margin-left: 10px; }
        .q-text { padding: 16px; font-size: 1rem; color: #1e293b; line-height: 1.6; border-bottom: 1px solid #f1f5f9; }
        .q-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .options { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
        .option-btn { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; font-size: 0.95rem; color: #334155; transition: all .2s; text-align: left; }
        .option-btn:hover { border-color: var(--primary); background: #f0fdf4; }
        .option-btn.selected { border-color: var(--primary); background: #ecfdf5; color: #065f46; font-weight: 700; }
        .option-btn input { margin: 0; }
        .essay-area { width: 100%; min-height: 180px; font-family: inherit; font-size: 0.95rem; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; resize: vertical; color: #1e293b; }
        .essay-area:focus { outline: none; border-color: var(--primary); }
        .marks { font-size: 0.78rem; font-weight: 700; color: #64748b; }
        .saved-badge { font-size: 0.72rem; color: #15803d; margin-left: 6px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:.6} 50%{opacity:1} }
        /* Actions */
        .exam-actions { display: flex; gap: 10px; justify-content: flex-end; padding: 24px; background: #f8fafc; border-top: 1px solid #e2e8f0; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 12px 22px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-save { background: var(--primary); color: #fff; }
        .btn-save:hover { background: #061d10; }
        .btn-submit { background: #dc2626; color: #fff; }
        .btn-submit:hover { background: #b91c1c; }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; }
        .btn-back { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .btn-back:hover { background: #e2e8f0; }
        /* Result review */
        .result-wrap { background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 14px; padding: 32px; margin-bottom: 24px; text-align: center; }
        .result-score { font-size: 3.5rem; font-weight: 800; color: var(--primary); }
        .result-total { font-size: 1.25rem; color: #64748b; }
        .result-meta { display: flex; justify-content: center; gap: 24px; margin-top: 20px; font-size: 0.9rem; color: #475569; flex-wrap: wrap; }
        .review-question { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 14px; background: #fff; }
        .review-answer { margin-top: 8px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
        .review-answer.correct { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .review-answer.wrong { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .review-answer.user { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .review-correct-ans { font-weight: 700; color: #15803d; margin-top: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        @media (max-width: 640px) { .exam-wrap { padding: 0 12px 60px; } .exam-actions { flex-direction: column; } .btn { width: 100%; justify-content: center; } .secure-header { padding: 0 12px; } }
    </style>
</head>
<body>
<!-- Secure header (imitates student exam screen) -->
<header class="secure-header">
    <div class="brand">
        <div class="shield-icon"><i class="bi bi-shield-lock-fill"></i></div>
        <span><?= htmlspecialchars((string) $exam['name']) ?></span>
    </div>
    <div id="timerDisplay" class="timer-box">Time remaining: --:--</div>
    <span class="secure-badge"><i class="bi bi-shield-lock-fill"></i> SECURE ENVIRONMENT</span>
</header>

<div class="exam-wrap">
    <?php if ($viewing && $result): ?>
        <!-- Result review screen -->
        <div class="result-wrap">
            <h2 style="font-weight:800;color:var(--primary);margin:0 0 8px 0;">Exam submitted</h2>
            <div class="result-score"><?= number_format((float) $result['score'], 2) ?></div>
            <div class="result-total">/ <?= number_format(array_sum(array_map(fn($q) => (float) ($q['marks'] ?? 1.00), $questions)), 2) ?></div>
            <div class="result-meta">
                <span><strong>Exam:</strong> <?= htmlspecialchars((string) $exam['name']) ?></span>
                <span><strong>Attempt:</strong> #<?= (int) $result['attempt_no'] ?></span>
                <span><strong>Submitted:</strong> <?= htmlspecialchars((string) $result['finished_at']) ?></span>
            </div>
            <div style="margin-top:24px;text-align:center;">
                <a href="promo_dashboard" class="btn btn-back"><i class="bi bi-arrow-left me-1"></i> Back to My Exams</a>
            </div>
        </div>

        <h3 style="font-weight:700;color:#0f172a;margin-bottom:12px;">Answer review</h3>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="review-question">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <strong style="color:var(--primary);">Q<?= ($idx + 1) ?></strong>
                    <span class="q-type"><?= htmlspecialchars(ucfirst((string) $q['question_type'])) ?> • <?= number_format((float) ($q['marks'] ?? 1.00), 2) ?> mark<?= (float) ($q['marks'] ?? 1.00) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="q-text" style="border:none;padding:0 0 8px 0;"><?= nl2br(htmlspecialchars((string) $q['question_text'])) ?></div>
                <?php if ((string) $q['question_type'] === 'objective' && !empty($q['options_json'])): ?>
                    <div style="font-size:0.8rem;color:#64748b;">Options: <?= htmlspecialchars((string) $q['options_json']) ?></div>
                <?php endif; ?>
                <div class="review-answer user">
                    <strong>Your answer:</strong><br>
                    <?= nl2br(htmlspecialchars((string) (($answers[$q['id']]['selected_option'] ?? $answers[$q['id']]['answer_text'] ?? '') ?: '(no answer)'))) ?>
                </div>
                <?php if ((string) $q['question_type'] === 'objective' && $q['correct_answer'] !== ''): ?>
                    <div class="review-correct-ans">Correct answer: <?= htmlspecialchars((string) $q['correct_answer']) ?></div>
                <?php endif; ?>
                <div class="review-answer <?= ((float) (($answers[$q['id']]['score'] ?? 0)) > 0 ? 'correct' : 'wrong') ?>">
                    <strong>Score:</strong> <?= number_format((float) ($answers[$q['id']]['score'] ?? 0), 2) ?> / <?= number_format((float) ($q['marks'] ?? 1.00), 2) ?>
                </div>
            </div>
        <?php endforeach; ?>

    <?php elseif (empty($questions)): ?>
        <div class="empty-state">
            <i class="bi bi-file-earmark-text"></i>
            <p style="font-weight:600;color:#475569;margin-bottom:6px;">No questions yet</p>
            <p class="small">This promotional exam has no questions. Contact your administrator.</p>
            <div style="margin-top:16px;">
                <a href="promo_dashboard" class="btn btn-back"><i class="bi bi-arrow-left me-1"></i> Back to exam list</a>
            </div>
        </div>

    <?php else: ?>
        <!-- Exam player -->
        <div class="exam-content">
            <div class="exam-header">
                <h1 class="exam-title"><?= htmlspecialchars((string) $exam['name']) ?></h1>
                <div class="exam-meta">
                    <span><i class="bi bi-mortarboard-fill"></i> Promotional Exam</span>
                    <span><i class="bi bi-clock"></i> <?= (int) $exam['duration_minutes'] ?> min</span>
                    <span><i class="bi bi-arrow-repeat"></i> Attempt #<?= $attemptNo ?></span>
                </div>
            </div>

            <?php if (!empty($exam['instructions'])): ?>
                <div class="exam-instructions"><?= nl2br(htmlspecialchars((string) $exam['instructions'])) ?></div>
            <?php endif; ?>

            <div class="exam-instructions" style="border-left:4px solid var(--primary);">
                <strong style="color:var(--primary);">Attempt #<?= $attemptNo ?></strong>
                • Answer each question, then click <strong>Submit exam</strong> when finished.<br>
                • Your answers are saved automatically as you select them.
            </div>

            <form id="examForm">
                <?php foreach ($questions as $idx => $q): ?>
                    <div class="question-card">
                        <div class="question-card-header">
                            <span class="q-number">Q<?= ($idx + 1) ?></span>
                            <span class="q-type"><?= htmlspecialchars(ucfirst((string) $q['question_type'])) ?></span>
                            <span class="marks"><?= number_format((float) ($q['marks'] ?? 1.00), 2) ?> mark<?= (float) ($q['marks'] ?? 1.00) !== 1 ? 's' : '' ?></span>
                        </div>
                        <div class="q-text"><?= nl2br(htmlspecialchars((string) $q['question_text'])) ?></div>
                        <div class="q-body">
                            <?php if ((string) $q['question_type'] === 'objective'): ?>
                                <div class="options">
                                    <?php
                                    $opts = [];
                                    $raw = (string) ($q['options_json'] ?? '');
                                    if ($raw !== '') {
                                        $decoded = json_decode($raw, true);
                                        if (is_array($decoded)) $opts = $decoded;
                                    }
                                    if ($opts === []) {
                                        $opts = ['A' => 'Option A', 'B' => 'Option B', 'C' => 'Option C', 'D' => 'Option D'];
                                    }
                                    $currentSel = $answers[$q['id']]['selected_option'] ?? '';
                                    foreach ($opts as $k => $v):
                                    ?>
                                        <label class="option-btn <?= $currentSel === $k ? 'selected' : '' ?>">
                                            <input type="radio" name="q_<?= (int) $q['id'] ?>" value="<?= htmlspecialchars((string) $k) ?>" <?= $currentSel === $k ? 'checked' : '' ?> style="display:none;">
                                            <span><?= htmlspecialchars((string) $k) ?></span>
                                            <span><?= htmlspecialchars((string) $v) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <textarea name="q_<?= (int) $q['id'] ?>" class="essay-area" placeholder="Type your answer here..."><?= htmlspecialchars((string) ($answers[$q['id']]['answer_text'] ?? '')) ?></textarea>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </form>

            <div class="exam-actions">
                <a href="promo_dashboard" class="btn btn-back"><i class="bi bi-house me-1"></i> Back to exam list</a>
                <button type="button" class="btn btn-submit" id="submitBtn" onclick="submitExam()"><i class="bi bi-check-lg me-1"></i> Submit exam</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
// Question id map
const examQuestions = <?= json_encode(array_map(fn($q) => ['id' => (int) $q['id'], 'type' => (string) $q['question_type']], $questions)) ?>;
const attemptId = <?= $viewing ? 0 : $attemptId ?>;
const examId = <?= $exam_id ?>;
const viewing = <?= $viewing ? 'true' : 'false' ?>;
let saving = false;

function getSavedBadge(qid) { return document.getElementById('saved-' + qid); }

function saveAnswer(qid, value) {
    if (viewing || saving) return;
    saving = true;
    const fd = new FormData();
    fd.append('action', 'save_answer');
    fd.append('question_id', qid);
    if (value === null || value === '') {
        fd.append('selected_option', '');
        fd.append('answer_text', '');
    } else {
        const q = examQuestions.find(x => x.id == qid);
        if (q && q.type === 'objective') { fd.append('selected_option', value); fd.append('answer_text', ''); }
        else { fd.append('selected_option', ''); fd.append('answer_text', value); }
    }
    fetch('promo_exam', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const badge = document.getElementById('saved-' + qid);
                if (badge) { badge.style.opacity = '1'; setTimeout(() => { badge.style.opacity = '.6'; }, 1500); }
            }
        })
        .catch(() => {})
        .finally(() => { saving = false; });
}

// Handlers
document.querySelectorAll('.option-btn input').forEach(r => {
    r.addEventListener('change', function() {
        const qid = this.name.match(/\d+$/)[0];
        saveAnswer(qid, this.value);
        document.querySelectorAll('.option-btn').forEach(l => l.classList.remove('selected'));
        this.closest('.option-btn').classList.add('selected');
    });
});
document.querySelectorAll('.essay-area').forEach(t => {
    let timer;
    t.addEventListener('input', function() {
        const qid = this.name.match(/\d+$/)[0];
        clearTimeout(timer);
        timer = setTimeout(() => saveAnswer(qid, this.value), 800);
    });
});

// Timer
const endTime = <?= $end ? $end * 1000 : 0 ?>;
const startTime = <?= $start ? $start * 1000 : 0 ?>;
const durationSec = <?= $duration ?>;
const timerEl = document.getElementById('timerDisplay');

function updateTimer() {
    const now = Date.now();
    let target;
    if (endTime) target = endTime;
    else if (startTime && durationSec) target = startTime + durationSec * 1000;
    else { timerEl.textContent = 'No time limit'; timerEl.className = 'timer-box'; return; }
    let rem = Math.max(0, Math.ceil((target - now) / 1000));
    const m = Math.floor(rem / 60); const s = rem % 60;
    timerEl.textContent = 'Time remaining: ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    timerEl.className = rem <= 60 ? 'timer-box danger' : (rem <= 300 ? 'timer-box warn' : 'timer-box');
    if (rem <= 0) { timerEl.textContent = 'Time expired — submit now'; timerEl.className = 'timer-box danger'; }
}
setInterval(updateTimer, 1000);
updateTimer();

async function submitExam() {
    if (viewing) return;
    if (!confirm('Submit this exam? You will not be able to change answers afterward.')) return;
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-loader me-1"></i> Submitting...';
    const fd = new FormData();
    fd.append('action', 'submit_exam');
    const res = await fetch('promo_exam', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } });
    const data = await res.json();
    if (data.success) {
        window.location.href = data.redirect;
    } else {
        alert(data.error || 'Submit failed. Try again.');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Submit exam';
    }
}
</script>
</body>
</html>
