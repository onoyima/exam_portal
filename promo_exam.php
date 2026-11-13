<?php
/**
 * promo_exam.php — Staff Promotional Examination player
 *
 * Displays one promo exam (objective and/or subjective questions), saves
 * answers via AJAX, and submits for scoring. Isolated from the core exam
 * system; only the signed-in promo staff member's own attempts are visible.
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

// Get or create the current open attempt
$attempt = $db->prepare("SELECT * FROM promo_exam_attempts WHERE staff_id = ? AND exam_id = ? AND finished_at IS NULL ORDER BY attempt_no DESC LIMIT 1");
$attempt->execute([$staff_id, $exam_id]);
$attempt = $attempt->fetch(PDO::FETCH_ASSOC);

$attemptId = null;
$attemptNo = 1;
$startedAt = null;

if ($action === 'start_new' && !$attempt) {
    // Start a brand new attempt
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

// View mode: load the most recent submitted attempt for review
$viewing = false;
$result = null;
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
        $answers = [];
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
} else {
    // Normal play mode: find or create an open attempt
    if (!$attempt) {
        // No open attempt: start attempt 1
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
    $answers = [];
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
    // Validate question belongs to this exam
    $qok = $db->prepare("SELECT 1 FROM promo_exam_questions WHERE id = ? AND exam_id = ? LIMIT 1");
    $qok->execute([$qid, $exam_id]);
    if (!$qok->fetchColumn()) {
        echo json_encode(['success' => false, 'error' => 'Question not found.']);
        exit;
    }
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
    // Mark attempt submitted
    $db->prepare("UPDATE promo_exam_attempts SET is_submitted = 1, finished_at = NOW() WHERE id = ? AND staff_id = ? AND finished_at IS NULL")
        ->execute([$attemptId, $staff_id]);

    // Score objective questions
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
    // Update attempt score
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
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .exam-wrap { max-width: 820px; margin: 0 auto; padding: 24px 16px 80px; }
        .exam-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
        .exam-title { font-weight: 800; font-size: 1.25rem; color: #092c19; margin: 0; }
        .timer-box { font-weight: 800; font-size: 1.1rem; color: #dc2626; background: #fef2f2; border: 1px solid #fecaca; padding: 8px 14px; border-radius: 10px; white-space: nowrap; }
        .timer-box.ok { color: #15803d; background: #f0fdf4; border-color: #bbf7d0; }
        .type-pill { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; padding: 3px 10px; border-radius: 999px; background: #e7f2ec; color: #15803d; }
        .exam-instructions { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; font-size: 0.92rem; color: #475569; }
        .question-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; overflow: hidden; display: flex; flex-direction: column; }
        .question-card-header { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; }
        .q-number { font-weight: 800; color: #092c19; font-size: 0.9rem; }
        .q-type { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; padding: 2px 8px; border-radius: 6px; background: #e2e8f0; color: #475569; margin-left: 10px; }
        .q-text { padding: 16px; font-size: 1rem; color: #1e293b; line-height: 1.6; border-bottom: 1px solid #f1f5f9; }
        .q-body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
        .options { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
        .option-btn { display: flex; align-items: center; gap: 8px; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 10px; background: #fff; cursor: pointer; font-size: 0.95rem; color: #334155; transition: all .2s; text-align: left; }
        .option-btn:hover { border-color: #092c19; background: #f0fdf4; }
        .option-btn.selected { border-color: #092c19; background: #ecfdf5; color: #065f46; font-weight: 700; }
        .option-btn input { margin: 0; }
        .essay-area { width: 100%; min-height: 160px; font-family: inherit; font-size: 0.95rem; padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; resize: vertical; font-size: 0.95rem; color: #1e293b; }
        .essay-area:focus { outline: none; border-color: #092c19; }
        .marks { font-size: 0.78rem; font-weight: 700; color: #64748b; }
        .exam-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 24px; flex-wrap: wrap; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-save { background: #092c19; color: #fff; }
        .btn-save:hover { background: #061d10; }
        .btn-submit { background: #dc2626; color: #fff; }
        .btn-submit:hover { background: #b91c1c; }
        .btn-submit:disabled { opacity: .6; cursor: not-allowed; }
        .btn-back { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
        .btn-back:hover { background: #e2e8f0; }
        .saved-badge { font-size: 0.72rem; color: #15803d; margin-left: 6px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:.6} 50%{opacity:1} }
        .result-wrap { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; margin-bottom: 24px; text-align: center; }
        .result-score { font-size: 3rem; font-weight: 800; color: #092c19; }
        .result-total { font-size: 1.1rem; color: #64748b; }
        .result-meta { display: flex; justify-content: center; gap: 24px; margin-top: 16px; font-size: 0.9rem; color: #475569; }
        .review-question { border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin-bottom: 14px; background: #fff; }
        .review-answer { margin-top: 8px; padding: 10px; border-radius: 8px; font-size: 0.9rem; }
        .review-answer.correct { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .review-answer.wrong { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .review-answer.user { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
        .review-correct-ans { font-weight: 700; color: #15803d; margin-top: 4px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #64748b; }
        .empty-state i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        @media (max-width: 640px) { .exam-wrap { padding: 12px 12px 60px; } .exam-actions { flex-direction: column; } .btn { width: 100%; justify-content: center; } }
    </style>
</head>
<body>
<nav class="admin-navbar" style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
    <div class="admin-navbar-inner" style="padding:0 16px;">
        <div class="admin-navbar-start" style="display:flex;align-items:center;gap:10px;">
            <a href="promo_dashboard" class="btn btn-back" style="padding:8px 14px;font-size:.85rem;"><i class="bi bi-arrow-left me-1"></i>Back to My Exams</a>
            <a href="promo_logout" class="btn btn-back" style="padding:8px 14px;font-size:.85rem;">Sign out</a>
        </div>
        <div class="admin-navbar-end">
            <div id="timerDisplay" class="timer-box ok">Time remaining: --:--</div>
        </div>
    </div>
</nav>

<div class="exam-wrap">
    <?php if ($viewing && $result): ?>
        <div class="result-wrap">
            <h2 style="font-weight:800;color:#092c19;margin:0 0 6px 0;">Exam submitted</h2>
            <div class="result-score"><?= number_format((float) $result['score'], 2) ?></div>
            <div class="result-total">/ <?= number_format(array_sum(array_map(fn($q) => (float) ($q['marks'] ?? 1.00), $questions)), 2) ?></div>
            <div class="result-meta">
                <span><strong>Exam:</strong> <?= htmlspecialchars((string) $exam['name']) ?></span>
                <span><strong>Attempt:</strong> #<?= (int) $result['attempt_no'] ?></span>
                <span><strong>Submitted:</strong> <?= htmlspecialchars((string) $result['finished_at']) ?></span>
            </div>
            <div style="margin-top:18px;text-align:center;">
                <a href="promo_exam?exam_id=<?= $exam_id ?>" class="btn btn-back"><i class="bi bi-arrow-left me-1"></i> Back to exam list</a>
            </div>
        </div>

        <h3 style="font-weight:700;color:#0f172a;margin-bottom:12px;">Answer review</h3>
        <?php foreach ($questions as $idx => $q): ?>
            <div class="review-question">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                    <strong style="color:#092c19;">Q<?= ($idx + 1) ?></strong>
                    <span class="q-type"><?= htmlspecialchars(ucfirst((string) $q['question_type'])) ?> • <?= number_format((float) ($q['marks'] ?? 1.00), 2) ?> mark<?= (float) ($q['marks'] ?? 1.00) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="q-text" style="border:none;padding:0 0 8px 0;"><?= nl2br(htmlspecialchars((string) $q['question_text'])) ?></div>
                <?php if ((string) $q['question_type'] === 'objective' && !empty($q['options_json'])): ?>
                    <div style="font-size:0.88rem;color:#475569;">Options: <?= htmlspecialchars((string) $q['options_json']) ?></div>
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
        <div class="exam-header">
            <div>
                <h1 class="exam-title"><?= htmlspecialchars((string) $exam['name']) ?></h1>
                <span class="type-pill"><?= htmlspecialchars(ucfirst((string) $exam['type'])) ?> exam</span>
            </div>
            <div id="timerDisplay" class="timer-box ok">Time remaining: --:--</div>
        </div>

        <?php if (!empty($exam['instructions'])): ?>
            <div class="exam-instructions"><?= nl2br(htmlspecialchars((string) $exam['instructions'])) ?></div>
        <?php endif; ?>

        <div class="exam-instructions" style="border-left:4px solid #092c19;">
            <strong style="color:#092c19;">Attempt #<?= $attemptNo ?></strong>
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
    <?php endif; ?>
</div>

<script>
// Question id map (inline from PHP)
const examQuestions = <?= json_encode(array_map(fn($q) => ['id' => (int) $q['id'], 'type' => (string) $q['question_type']], $questions)) ?>;
const attemptId = <?= $attemptId ?>;
const examId = <?= $exam_id ?>;
let saving = false;

function getAnswer(el) {
    const qid = el.closest('.question-card').querySelector('[name^="q_"]')?.name?.match(/\d+$/)?.[0] ?? 0;
    if (el.type === 'radio') return el.value;
    if (el.tagName === 'TEXTAREA') return el.value;
    return '';
}

function saveAnswer(qid, value) {
    if (saving) return;
    saving = true;
    const btn = document.querySelector('.saved-badge');
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
                if (badge) badge.style.opacity = '1';
                setTimeout(() => { if (badge) badge.style.opacity = '.6'; }, 1500);
            }
        })
        .catch(() => {})
        .finally(() => { saving = false; });
}

// Radio / textarea change handlers
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
    else { timerEl.textContent = 'No time limit'; timerEl.className = 'timer-box ok'; return; }
    let rem = Math.max(0, Math.ceil((target - now) / 1000));
    const m = Math.floor(rem / 60); const s = rem % 60;
    timerEl.textContent = 'Time remaining: ' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
    timerEl.className = rem <= 300 ? 'timer-box' : 'timer-box ok';
    if (rem <= 0) { timerEl.textContent = 'Time expired — submit now'; timerEl.className = 'timer-box'; }
}
setInterval(updateTimer, 1000);
updateTimer();

async function submitExam() {
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
