<?php
/**
 * admin_promo_questions.php — Manage questions for a promotional exam (admin only)
 *
 * Add/edit objective (MCQ) and subjective (essay) questions for one promo exam.
 * Bulk import from CSV. Uses the standard admin sidebar layout.
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

$error = '';

// CSV template download
if (isset($_GET['download_template']) && $_GET['download_template'] === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="promo_questions_template.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['question', 'question_type', 'marks', 'sort_order', 'correct_answer', 'option_a', 'option_b', 'option_c', 'option_d']);
    fputcsv($out, ['What is the capital of Nigeria?', 'objective', '1', '0', 'A', 'Abuja', 'Lagos', 'Kano', 'Ibadan']);
    fputcsv($out, ['Describe your experience with promotional exams.', 'subjective', '2', '1', '', '', '', '', '']);
    fclose($out);
    exit;
}

// Bulk import questions from CSV
if (isset($_POST['import_questions'])) {
    if (empty($_FILES['questions_file']['tmp_name'])) {
        $error = 'Please choose a CSV file.';
    } else {
        $row = 0; $imported = 0; $skipped = 0;
        if (($handle = fopen($_FILES['questions_file']['tmp_name'], 'r')) !== false) {
            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $row++;
                if ($row === 1 && (strcasecmp(trim($data[0] ?? ''), 'question') === 0 || strcasecmp(trim($data[0] ?? ''), 'question_text') === 0)) {
                    continue;
                }
                $qtext = trim($data[0] ?? '');
                $qtype = trim($data[1] ?? 'objective');
                $marks = floatval($data[2] ?? 1);
                $sort = intval($data[3] ?? 0);
                $correct = trim($data[4] ?? '');
                $optA = trim($data[5] ?? '');
                $optB = trim($data[6] ?? '');
                $optC = trim($data[7] ?? '');
                $optD = trim($data[8] ?? '');

                if ($qtext === '') { $skipped++; continue; }
                if (!in_array($qtype, ['objective', 'subjective'], true)) { $qtype = 'objective'; }

                $optionsJson = null;
                if ($qtype === 'objective') {
                    $optionsJson = json_encode([$optA, $optB, $optC, $optD]);
                }

                $db->prepare("INSERT INTO promo_exam_questions (exam_id, question_text, question_type, options_json, correct_answer, marks, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$exam_id, $qtext, $qtype, $optionsJson, $correct, $marks, $sort]);
                $imported++;
            }
            fclose($handle);
        }
        header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions&imported=' . $imported . '&skipped=' . $skipped);
        exit;
    }
}

// Create question
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
        header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
        exit;
    }
}

// Edit question
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
        header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
        exit;
    }
}

// Delete question
if (isset($_POST['delete_q']) && isset($_POST['qid'])) {
    $qid = intval($_POST['qid']);
    $db->prepare("DELETE FROM promo_exam_questions WHERE id = ? AND exam_id = ?")->execute([$qid, $exam_id]);
    header('Location: admin_promo_questions?exam_id=' . $exam_id . '#questions');
    exit;
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
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="admin_promo_exams" class="admin-back-btn" aria-label="Back to exams">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Questions</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($admin_name) ?></span>
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
          <li><a href="admin_promo_exams">Promo Exams</a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Questions</li>
        </ol>
      </nav>

      <?php display_auth_errors(); ?>
      <?php if (isset($_GET['imported'])): ?>
        <div class="admin-alert admin-alert-success"><i class="bi bi-check-circle me-2"></i>
          Question import: <?= (int) $_GET['imported'] ?> added, <?= (int) ($_GET['skipped'] ?? 0) ?> skipped.
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Questions</h2>
        <div class="admin-page-actions">
          <a href="admin_promo_questions?exam_id=<?= $exam_id ?>&download_template=1" class="admin-btn admin-btn-outline">
            <i class="bi bi-download me-1"></i> CSV Template
          </a>
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#importModal">
            <i class="bi bi-cloud-arrow-up me-1"></i> Import CSV
          </button>
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
            <i class="bi bi-plus me-1"></i> Add question
          </button>
        </div>
      </div>

      <div class="admin-page-header mb-3">
        <h2 class="admin-page-title"><?= htmlspecialchars((string) $exam['name']) ?></h2>
        <p class="text-muted small">
          Type: <strong><?= htmlspecialchars(ucfirst((string) $exam['type'])) ?></strong> •
          Duration: <?= (int) $exam['duration_minutes'] ?> min •
          Total questions: <?= count($questions) ?>
        </p>
      </div>

      <h3 class="admin-section-title" id="questions">Question list</h3>
      <?php if (!$questions): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <p class="text-muted text-center py-4"><i class="bi bi-file-earmark-text me-2"></i>No questions yet. Add objective (MCQ) or subjective (essay) questions to this exam.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Question</th>
                <th>Type</th>
                <th>Marks</th>
                <th>Order</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($questions as $idx => $q): ?>
              <tr>
                <td><?= ($idx + 1) ?></td>
                <td style="font-size:0.9rem;color:#334155;max-width:360px;"><?= nl2br(htmlspecialchars((string) $q['question_text'])) ?></td>
                <td>
                  <span class="admin-badge admin-badge-<?= $q['question_type'] === 'objective' ? 'info' : 'success' ?>">
                    <?= htmlspecialchars(ucfirst((string) $q['question_type'])) ?>
                  </span>
                </td>
                <td><?= number_format((float) $q['marks'], 2) ?></td>
                <td><?= (int) $q['sort_order'] ?></td>
                <td class="text-end">
                  <button class="admin-btn admin-btn-outline admin-btn-sm" onclick="editQuestion(<?= htmlspecialchars(json_encode($q)) ?>)">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <form method="post" style="display:inline;" onsubmit="return confirm('Delete this question?')">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="qid" value="<?= (int) $q['id'] ?>">
                    <button type="submit" name="delete_q" class="admin-btn admin-btn-danger admin-btn-sm"><i class="bi bi-trash me-1"></i></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Add / Edit question modal -->
  <div class="modal fade" id="addQuestionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="qModalTitle">Add question</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" id="qForm">
          <?php csrf_field(); ?>
          <input type="hidden" name="qid" id="qModalQid">
          <input type="hidden" name="edit_q" value="0">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Question text</label>
              <textarea name="question_text" id="qModalText" class="form-control" required placeholder="Enter the question..."></textarea>
            </div>
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label">Type</label>
                <select name="question_type" id="qModalType" class="form-select" onchange="toggleQuestionType()">
                  <option value="objective">Objective (MCQ)</option>
                  <option value="subjective">Subjective (Essay)</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Marks</label>
                <input type="number" name="marks" id="qModalMarks" class="form-control" value="1" step="0.01" min="0">
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Sort order</label>
              <input type="number" name="sort_order" id="qModalSort" class="form-control" value="0" min="0">
            </div>

            <!-- Objective options -->
            <div id="qObjectiveOptions" class="card mb-3" style="display:none;">
              <div class="card-body">
                <label class="form-label">Options</label>
                <div id="optionsContainer"></div>
                <button type="button" class="admin-btn admin-btn-outline admin-btn-sm mt-2" onclick="addOptionRow()"><i class="bi bi-plus me-1"></i> Add option</button>
                <div class="mt-3">
                  <label class="form-label">Correct answer</label>
                  <div id="correctAnswerContainer"></div>
                </div>
              </div>
            </div>

            <!-- Subjective hint -->
            <div id="qSubjectiveHint" class="alert alert-info" style="display:none;">
              <i class="bi bi-info-circle me-1"></i> Subjective questions are essay-style. Staff type their answer in a text box during the exam.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create_q" class="admin-btn admin-btn-primary" id="qCreateBtn">Add question</button>
            <button type="submit" name="edit_q" class="admin-btn admin-btn-primary d-none" id="qEditBtn">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Import questions modal -->
  <div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Import Questions from CSV</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" enctype="multipart/form-data">
          <?php csrf_field(); ?>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">CSV file</label>
              <input type="file" name="questions_file" class="form-control" accept=".csv" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Columns</label>
              <div class="form-control" style="background:#f8f9ca;">question, question_type, marks, sort_order, correct_answer, option_a, option_b, option_c, option_d</div>
            </div>
            <div class="mb-2">
              <label class="form-label">Example row (objective)</label>
              <div class="form-control" style="background:#f8f9ca;">What is the capital of Nigeria?,objective,1,0,A,Abuja,Lagos,Kano,Ibadan</div>
            </div>
            <div class="mb-2">
              <label class="form-label">Example row (subjective)</label>
              <div class="form-control" style="background:#f8f9ca;">Describe your experience,subjective,2,1,,,,,</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="import_questions" class="admin-btn admin-btn-primary">Import Questions</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script>
    const addQuestionModal = document.getElementById('addQuestionModal');
    const importModal = document.getElementById('importModal');
    const qModalTitle = document.getElementById('qModalTitle');
    const qCreateBtn = document.getElementById('qCreateBtn');
    const qEditBtn = document.getElementById('qEditBtn');
    let optionCounter = 0;

    function openModal(id) { new bootstrap.Modal(document.getElementById(id)).show(); }

    function toggleQuestionType() {
      const type = document.getElementById('qModalType').value;
      document.getElementById('qObjectiveOptions').style.display = type === 'objective' ? 'block' : 'none';
      document.getElementById('qSubjectiveHint').style.display = type === 'subjective' ? 'block' : 'none';
      renderCorrectAnswer();
    }

    function addOptionRow(text = '', isCorrect = false) {
      const id = 'opt_' + optionCounter++;
      const container = document.getElementById('optionsContainer');
      const row = document.createElement('div');
      row.className = 'input-group mb-2';
      row.id = id;
      row.innerHTML = `
        <input type="text" class="form-control option-input" placeholder="Option text" value="${escapeHtml(text)}" required>
        <span class="input-group-text bg-white border">
          <input type="radio" name="correct_option" value="${id}" class="form-check-input" ${isCorrect ? 'checked' : ''}>
        </span>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeOptionRow('${id}')">&times;</button>
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
      document.querySelectorAll('#optionsContainer .input-group').forEach((row, idx) => {
        const input = row.querySelector('.option-input');
        const radio = row.querySelector('input[type="radio"]');
        const label = document.createElement('label');
        label.className = 'form-check-label ms-2';
        label.textContent = input ? input.value : 'Option ' + (idx + 1);
        container.appendChild(radio);
        container.appendChild(label);
        container.appendChild(document.createElement('br'));
      });
    }

    function escapeHtml(t) { return String(t).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c])); }

    function editQuestion(q) {
      qModalTitle.textContent = 'Edit question';
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

      qCreateBtn.classList.add('d-none');
      qEditBtn.classList.remove('d-none');
      openModal('addQuestionModal');
    }

    addQuestionModal.addEventListener('hidden.bs.modal', function () {
      qModalTitle.textContent = 'Add question';
      document.getElementById('qModalQid').value = '';
      document.getElementById('qModalText').value = '';
      document.getElementById('qModalType').value = 'objective';
      document.getElementById('qModalMarks').value = '1';
      document.getElementById('qModalSort').value = '0';
      document.getElementById('optionsContainer').innerHTML = '';
      optionCounter = 0;
      toggleQuestionType();
      qCreateBtn.classList.remove('d-none');
      qEditBtn.classList.add('d-none');
    });

    importModal.addEventListener('hidden.bs.modal', function () {
      document.getElementById('importModal').querySelector('form').reset();
    });

    toggleQuestionType();
  </script>
</body>
</html>
