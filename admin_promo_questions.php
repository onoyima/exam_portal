<?php
/**
 * admin_promo_questions.php — Manage questions for a promotional exam (admin only)
 *
 * Add/edit objective (MCQ) and subjective (essay) questions for one promo exam.
 * Isolated from the core question bank.
 */

require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$exam_id = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;
if ($exam_id <= 0) {
    header("Location: admin_promo_exams");
    exit;
}

$exam = $db->prepare("SELECT * FROM promo_exams WHERE id = ?");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);
if (!$exam) {
    header("Location: admin_promo_exams");
    exit;
}

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    // ── Create question ──
    if (isset($_POST['create_q'])) {
        $qtext = trim($_POST['question_text']);
        $qtype = in_array($_POST['question_type'] ?? '', ['objective', 'subjective'], true) ? $_POST['question_type'] : 'objective';
        $marks = floatval($_POST['marks'] ?? 1);
        $sort = intval($_POST['sort_order'] ?? 0);
        $correct = trim($_POST['correct_answer'] ?? '');
        $options = $_POST['options'] ?? [];

        if ($qtext === '') {
            $error = 'Question text is required.';
        } else {
            $optionsJson = $qtype === 'objective' ? json_encode($options) : null;
            $db->prepare("INSERT INTO promo_exam_questions (exam_id, question_text, question_type, options_json, correct_answer, marks, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$exam_id, $qtext, $qtype, $optionsJson, $correct, $marks, $sort]);
            $_SESSION['promo_notice'] = 'Question added.';
            header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
            exit;
        }
    }

    // ── Edit question ──
    if (isset($_POST['edit_q']) && isset($_POST['qid'])) {
        $qid = intval($_POST['qid']);
        $qtext = trim($_POST['question_text']);
        $qtype = in_array($_POST['question_type'] ?? '', ['objective', 'subjective'], true) ? $_POST['question_type'] : 'objective';
        $marks = floatval($_POST['marks'] ?? 1);
        $sort = intval($_POST['sort_order'] ?? 0);
        $correct = trim($_POST['correct_answer'] ?? '');
        $options = $_POST['options'] ?? [];

        if ($qtext === '') {
            $error = 'Question text is required.';
        } else {
            $optionsJson = $qtype === 'objective' ? json_encode($options) : null;
            $db->prepare("UPDATE promo_exam_questions SET question_text=?, question_type=?, options_json=?, correct_answer=?, marks=?, sort_order=? WHERE id=? AND exam_id=?")
                ->execute([$qtext, $qtype, $optionsJson, $correct, $marks, $sort, $qid, $exam_id]);
            $_SESSION['promo_notice'] = 'Question updated.';
            header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
            exit;
        }
    }

    // ── Delete question ──
    if (isset($_POST['delete_q']) && isset($_POST['qid'])) {
        $qid = intval($_POST['qid']);
        $db->prepare("DELETE FROM promo_exam_questions WHERE id = ? AND exam_id = ?")->execute([$qid, $exam_id]);
        $_SESSION['promo_notice'] = 'Question deleted.';
        header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
        exit;
    }
}

// Load questions
$questions = $db->prepare("SELECT * FROM promo_exam_questions WHERE exam_id = ? ORDER BY sort_order ASC, id ASC");
$questions->execute([$exam_id]);
$questions = $questions->fetchAll(PDO::FETCH_ASSOC);

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Questions - <?= htmlspecialchars((string) $exam['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 24px 16px 100px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .page-title { font-weight: 800; font-size: 1.25rem; color: #092c19; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; }
        .card-body { padding: 18px 20px; }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; display: block; margin-bottom: 6px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff; }
        .form-control:focus { border-color: #092c19; outline: none; box-shadow: 0 0 0 3px rgba(9,44,25,0.1); }
        textarea.form-control { min-height: 100px; resize: vertical; }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-primary { background: #092c19; color: #fff; border-color: #092c19; }
        .btn-primary:hover { background: #061d10; }
        .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-outline:hover { border-color: #092c19; color: #092c19; }
        .btn-success { background: #22c55e; color: #060d16; border-color: #22c55e; }
        .btn-success:hover { background: #4ade80; }
        .btn-danger { background: #dc2626; color: #fff; border-color: #dc2626; }
        .btn-danger:hover { background: #b91c1c; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th { text-align: left; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; vertical-align: middle; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .badge-objective { background: #dbeafe; color: #1e40af; }
        .badge-subjective { background: #f0fdf4; color: #15803d; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .flash-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.4); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: #fff; border-radius: 12px; padding: 24px; width: 90%; max-width: 640px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-title { font-weight: 800; color: #0f172a; margin: 0 0 16px 0; font-size: 1.1rem; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .close-modal { background: none; border: none; font-size: 1.5rem; color: #94a3b8; position: absolute; top: 10px; right: 14px; cursor: pointer; }
        .close-modal:hover { color: #0f172a; }
        .relative { position: relative; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; }
        .options-list { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; background: #fafafa; }
        .option-row { display: flex; align-items: center; gap: 8px; padding: 8px; border-bottom: 1px solid #f1f5f9; margin-bottom: 6px; background: #fff; border-radius: 6px; }
        .option-row:last-child { border-bottom: none; margin-bottom: 0; }
        .option-input { flex: 1; }
        .correct-radio { width: 18px; height: 18px; cursor: pointer; }
        .remove-option { background: none; border: 1px solid #dc2626; color: #dc2626; border-radius: 4px; padding: 2px 8px; font-size: 0.8rem; cursor: pointer; }
        .remove-option:hover { background: #dc2626; color: #fff; }
        .add-option { margin-top: 8px; }
        .type-specific { display: none; }
        .type-specific.active { display: block; }
        .d-none { display: none !important; }
        .empty { text-align: center; padding: 40px 20px; color: #64748b; }
        .empty i { font-size: 3rem; color: #cbd5e1; margin-bottom: 16px; display: block; }
        @media (max-width: 640px) { .grid-2,.grid-3 { grid-template-columns: 1fr; } .page-header { flex-direction: column; align-items: flex-start; } }
    </style>
</head>
<body>
<nav class="admin-navbar" style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
    <div class="admin-navbar-inner" style="padding:0 16px;">
        <div class="admin-navbar-start" style="display:flex;align-items:center;gap:10px;">
            <a href="admin_promo_exams" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;"><i class="bi bi-arrow-left me-1"></i>Back to exams</a>
        </div>
        <div class="admin-navbar-end">
            <span style="background:rgba(9,44,25,0.08);color:#092c19;padding:6px 12px;border-radius:8px;font-weight:700;font-size:.85rem;"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <a href="logout" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;">Sign out</a>
        </div>
    </div>
</nav>

<div class="wrap">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-question-circle" style="color:#092c19;"></i> Questions</h1>
        <a href="#addQuestionModal" class="btn btn-primary" onclick="openModal('addQuestionModal')"><i class="bi bi-plus me-1"></i> Add question</a>
        <a href="admin_promo_exams" class="btn btn-outline"><i class="bi bi-mortarboard me-1"></i> Exams</a>
    </div>

    <div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:16px 20px;margin-bottom:16px;">
        <h2 style="font-weight:800;color:#092c19;margin:0 0 4px 0;font-size:1.1rem;"><?= htmlspecialchars((string) $exam['name']) ?></h2>
        <p style="color:#64748b;margin:0;font-size:0.9rem;">
            Type: <strong><?= htmlspecialchars(ucfirst((string) $exam['type'])) ?></strong> •
            Duration: <?= (int) $exam['duration_minutes'] ?> min •
            Total questions: <?= count($questions) ?>
        </p>
    </div>

    <?php if (isset($_SESSION['promo_notice'])): ?>
        <div class="flash flash-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['promo_notice']); unset($_SESSION['promo_notice']); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="flash flash-error"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <h3 id="questions" style="font-weight:700;color:#0f172a;margin:20px 0 10px 0;font-size:1rem;">Question list</h3>
    <?php if (!$questions): ?>
        <div class="empty"><i class="bi bi-file-earmark-text"></i><p style="font-weight:600;margin-bottom:6px;">No questions yet</p><p class="small">Add objective (MCQ) or subjective (essay) questions to this exam.</p></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Question</th>
                    <th style="width:110px;">Type</th>
                    <th style="width:90px;">Marks</th>
                    <th style="width:120px;">Order</th>
                    <th style="width:150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($questions as $idx => $q): ?>
                <tr>
                    <td><?= ($idx + 1) ?></td>
                    <td style="font-size:0.9rem;color:#334155;max-width:360px;"><?= nl2br(htmlspecialchars((string) $q['question_text'])) ?></td>
                    <td><span class="badge <?= $q['question_type'] === 'objective' ? 'badge-objective' : 'badge-subjective' ?>"><?= htmlspecialchars(ucfirst((string) $q['question_type'])) ?></span></td>
                    <td><?= number_format((float) $q['marks'], 2) ?></td>
                    <td><?= (int) $q['sort_order'] ?></td>
                    <td>
                        <div style="display:flex;gap:4px;">
                            <button class="btn btn-sm btn-outline" onclick="editQuestion(<?= htmlspecialchars(json_encode($q), ENT_QUOTES) ?>)"><i class="bi bi-pencil me-1"></i></button>
                            <form method="post" style="margin:0;" onsubmit="return confirm('Delete this question?')">
                                <?php csrf_field(); ?>
                                <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
                                <button type="submit" name="delete_q" class="btn btn-sm btn-danger"><i class="bi bi-trash me-1"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Add / Edit question modal -->
<div class="modal" id="addQuestionModal">
    <div class="modal-content relative">
        <button class="close-modal" onclick="closeModal('addQuestionModal')">&times;</button>
        <h2 class="modal-title" id="qModalTitle">Add question</h2>
        <form method="post" id="qForm">
            <?php csrf_field(); ?>
            <input type="hidden" name="qid" id="qModalQid">
            <input type="hidden" name="edit_q" value="0">
            <div class="mb-3">
                <label class="form-label">Question text</label>
                <textarea name="question_text" id="qModalText" class="form-control" required placeholder="Enter the question..."></textarea>
            </div>
            <div class="grid-2">
                <div>
                    <label class="form-label">Type</label>
                    <select name="question_type" id="qModalType" class="form-control" onchange="toggleQuestionType()">
                        <option value="objective">Objective (MCQ)</option>
                        <option value="subjective">Subjective (Essay)</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Marks</label>
                    <input type="number" name="marks" id="qModalMarks" class="form-control" value="1" step="0.01" min="0">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Sort order</label>
                <input type="number" name="sort_order" id="qModalSort" class="form-control" value="0" min="0">
            </div>

            <!-- Objective options -->
            <div id="qObjectiveOptions" class="type-specific">
                <div class="mb-3">
                    <label class="form-label">Options</label>
                    <div id="optionsContainer" class="options-list"></div>
                    <button type="button" class="btn btn-outline add-option" onclick="addOptionRow()"><i class="bi bi-plus me-1"></i> Add option</button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Correct answer</label>
                    <div id="correctAnswerContainer"></div>
                </div>
            </div>

            <!-- Subjective hint -->
            <div id="qSubjectiveHint" class="type-specific" style="background:#f1f5f9;border:1px solid #e2e8f0;border-radius:8px;padding:12px;">
                <p style="margin:0;color:#64748b;font-size:0.85rem;"><i class="bi bi-info-circle me-1"></i> Subjective questions are essay-style. Staff type their answer in a text box during the exam.</p>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addQuestionModal')">Cancel</button>
                <button type="submit" name="create_q" class="btn btn-primary" id="qCreateBtn">Add question</button>
                <button type="submit" name="edit_q" class="btn btn-primary d-none" id="qEditBtn">Save changes</button>
            </div>
        </form>
    </div>
</div>

<script>
const addQuestionModal = document.getElementById('addQuestionModal');
function openModal(id) { document.getElementById(id).classList.add('show'); }
function closeModal(id) { document.getElementById(id).classList.remove('show'); }

let optionCounter = 0;

function toggleQuestionType() {
    const type = document.getElementById('qModalType').value;
    document.getElementById('qObjectiveOptions').classList.toggle('active', type === 'objective');
    document.getElementById('qSubjectiveHint').classList.toggle('active', type === 'subjective');
    renderCorrectAnswer();
}

function addOptionRow(text = '', isCorrect = false) {
    const id = 'opt_' + optionCounter++;
    const container = document.getElementById('optionsContainer');
    const row = document.createElement('div');
    row.className = 'option-row';
    row.id = id;
    row.innerHTML = `
        <input type="text" class="option-input" placeholder="Option text" value="${escapeHtml(text)}" required>
        <input type="radio" name="correct_option" value="${id}" class="correct-radio" ${isCorrect ? 'checked' : ''}>
        <button type="button" class="remove-option" onclick="removeOptionRow('${id}')">&times;</button>
    `;
    container.appendChild(row);
    renderCorrectAnswer();
}

function removeOptionRow(id) {
    document.getElementById(id).remove();
    renderCorrectAnswer();
}

function renderCorrectAnswer() {
    const container = document.getElementById('correctAnswerContainer');
    container.innerHTML = '';
    document.querySelectorAll('.option-row').forEach((row, idx) => {
        const input = row.querySelector('.option-input');
        const radio = row.querySelector('.correct-radio');
        const rowId = row.id;
        const label = input ? input.value : 'Option ' + (idx + 1);
        const labelEl = document.createElement('label');
        labelEl.style = 'margin-left:4px;cursor:pointer;font-size:0.9rem;color:#334155;';
        labelEl.textContent = label;
        container.appendChild(radio.cloneNode(true));
        container.appendChild(labelEl);
        container.appendChild(document.createElement('br'));
    });
}

function escapeHtml(t) {
    return String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]));
}

function editQuestion(q) {
    document.getElementById('qModalTitle').textContent = 'Edit question';
    document.getElementById('qModalQid').value = q.id;
    document.getElementById('qModalText').value = q.question_text;
    document.getElementById('qModalType').value = q.question_type;
    document.getElementById('qModalMarks').value = q.marks;
    document.getElementById('qModalSort').value = q.sort_order;
    toggleQuestionType();

    const optionsContainer = document.getElementById('optionsContainer');
    optionsContainer.innerHTML = '';
    optionCounter = 0;

    if (q.options_json) {
        try {
            const opts = JSON.parse(q.options_json);
            opts.forEach((opt, i) => {
                addOptionRow(opt, (q.correct_answer === opt));
            });
        } catch (e) {
            addOptionRow('', false);
        }
    } else {
        addOptionRow('', false);
    }

    document.getElementById('qCreateBtn').classList.add('d-none');
    document.getElementById('qEditBtn').classList.remove('d-none');
    openModal('addQuestionModal');
}

addQuestionModal.addEventListener('click', function(e) { if (e.target === addQuestionModal) closeModal('addQuestionModal'); });

// Initialize type toggle on load
toggleQuestionType();
</script>
</body>
</html>
