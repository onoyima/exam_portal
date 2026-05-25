<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_exam = null;

if ($exam_id) {
  $stmt = $db->prepare("SELECT * FROM exams WHERE id = ? AND deleted_at IS NULL");
  $stmt->execute([$exam_id]);
  $selected_exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$selected_exam) { header("Location: admin_questions"); exit; }
}

// Handle Export CSV
if ($selected_exam && isset($_GET['export'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="checklist_questions_' . str_replace(' ', '_', $selected_exam['name']) . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Question Text', 'Score Values (comma separated)']);
  $q_stmt = $db->prepare("SELECT text, score_values FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
  $q_stmt->execute([$exam_id]);
  while ($row = $q_stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}

// Handle add, edit, delete (only if exam selected)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $exam_id) {
  if (isset($_POST['add'])) {
    $stmt = $db->prepare("INSERT INTO questions(exam_id, text, score_values) VALUES (?, ?, ?)");
    $stmt->execute([$exam_id, $_POST['text'], $_POST['score_values']]);

    // Also keep legacy mapping for backward compatibility if needed
    $qid = $db->lastInsertId();
    $db->prepare("INSERT INTO exam_questions(exam_id, question_id) VALUES (?, ?)")->execute([$exam_id, $qid]);

  } elseif (isset($_POST['edit'])) {
    $stmt = $db->prepare("UPDATE questions SET text=?, score_values=? WHERE id=? AND exam_id=?");
    $stmt->execute([$_POST['text'], $_POST['score_values'], $_POST['id'], $exam_id]);
  } elseif (isset($_POST['delete'])) {
    // Soft delete question (keep answers for audit)
    $stmt = $db->prepare("UPDATE questions SET deleted_at=NOW() WHERE id=? AND exam_id=?");
    $stmt->execute([$_POST['id'], $exam_id]);
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=? AND question_id=?")->execute([$exam_id, $_POST['id']]);
  } elseif (isset($_POST['clear_all'])) {
    // Soft delete all questions for this exam (keep answers for audit)
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("UPDATE questions SET deleted_at=NOW() WHERE exam_id=?")->execute([$exam_id]);
  }
  header("Location: admin_questions?exam_id=$exam_id&success=1");
  exit;
}

// Fetch Data
if ($exam_id) {
  $questions = $db->prepare("SELECT * FROM questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
  $questions->execute([$exam_id]);
  $questions = $questions->fetchAll(PDO::FETCH_ASSOC);
} else {
  // Fetch exams that are practical (not objective) and not deleted
  $exams = $db->query("SELECT * FROM exams WHERE (type != 'objective' OR type IS NULL) AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Manage Questions - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Practical Questions</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Question Bank</li>
        </ol>
      </nav>

      <?php if (isset($_GET['success'])): ?>
        <?php
          $success_msg = '';
          if (isset($_GET['success']) && $_GET['success'] == 'cleared') {
            $success_msg = "Successfully cleared all questions for the <strong>" . htmlspecialchars($selected_exam['name'] ?? 'selected') . "</strong> exam. Other exams remain unchanged.";
          } else {
            $success_msg = "Action successful.";
          }
        ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= $success_msg ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <?php if (!$exam_id): ?>
        <!-- Exam Selection List -->
        <div class="admin-page-header">
          <h2 class="admin-page-title">Select Exam to Manage Questions</h2>
          <div class="admin-page-actions">
            <span class="admin-badge admin-badge-primary">Checklists &amp; Practical Assessments</span>
          </div>
        </div>
        <div class="admin-card">
          <div class="admin-card-header"><h3 class="admin-card-title">All Exams</h3></div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Exam Name</th>
                    <th>Status</th>
                    <th>Questions Count</th>
                    <th class="text-end">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($exams as $ex): ?>
                    <?php
                    $q_count = $db->prepare("SELECT COUNT(*) FROM questions WHERE exam_id=? AND deleted_at IS NULL");
                    $q_count->execute([$ex['id']]);
                    $count = $q_count->fetchColumn();
                    ?>
                    <tr>
                      <td class="fw-bold"><?= htmlspecialchars($ex['name']) ?></td>
                      <td>
                        <?= $ex['is_active'] ? '<span class="badge bg-success-light text-success">Active</span>' : '<span class="badge bg-secondary-light text-secondary">Inactive</span>' ?>
                      </td>
                      <td><span class="admin-badge admin-badge-primary"><?= $count ?> Questions</span></td>
                      <td class="text-end">
                        <a href="admin_questions?exam_id=<?= $ex['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">Manage Questions</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Questions for Specific Exam -->
        <div class="admin-page-header">
          <h2 class="admin-page-title"><?= htmlspecialchars($selected_exam['name']) ?></h2>
          <div class="admin-page-actions">
            <span class="admin-badge admin-badge-info">Managing Practical Checklist Questions</span>
            <a href="?exam_id=<?= $exam_id ?>&export=1" class="admin-btn admin-btn-sm admin-btn-outline">
              <i class="bi bi-file-earmark-spreadsheet"></i> Export to CSV
            </a>
            <form method="post" class="d-inline" data-swal="WARNING: You are about to permanently delete ALL <?= count($questions) ?> questions from the '<?= htmlspecialchars($selected_exam['name']) ?>' bank. All other exams remain untouched. This cannot be undone. Proceed?">
              <button type="submit" name="clear_all" class="admin-btn admin-btn-sm admin-btn-danger">
                <i class="bi bi-trash3-fill"></i> Clear All (<?= count($questions) ?>)
              </button>
            </form>
            <button class="admin-btn admin-btn-primary admin-btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="bi bi-plus-lg"></i> Add Question
            </button>
          </div>
        </div>

        <div class="admin-card">
          <div class="admin-card-header"><h3 class="admin-card-title">Question Steps</h3></div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Question Step Text</th>
                    <th>Allowed Score Values</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($questions as $i => $q): ?>
                    <tr>
                      <td class="text-muted"><?= $i + 1 ?></td>
                      <td class="fw-medium"><?= htmlspecialchars($q['text']) ?></td>
                      <td><code><?= htmlspecialchars($q['score_values']) ?></code></td>
                      <td class="text-end">
                        <div class="admin-btn-group">
                          <button class="admin-btn admin-btn-sm admin-btn-info"
                            onclick="editQuestion(<?= $q['id'] ?>, '<?= htmlspecialchars($q['text'], ENT_QUOTES) ?>', '<?= htmlspecialchars($q['score_values'], ENT_QUOTES) ?>')">
                            Edit
                          </button>
                          <form method="post" class="d-inline" data-swal="Delete this question?">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($questions)): ?>
                    <tr>
                      <td colspan="4" class="text-center py-5 text-muted">No questions found for this exam. Add one or upload data.</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Add Modal -->
  <div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <form method="post">
          <div class="modal-header border-0 pb-0 ps-4 pt-4">
            <h5 class="modal-title fw-bold">Add Question Entry</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label fw-semibold">Step/Question Text</label>
              <textarea name="text" class="form-control rounded-3" rows="3" required
                placeholder="e.g. Wash hands before procedure"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Score Values</label>
              <input type="text" name="score_values" class="form-control rounded-3" required placeholder="e.g. 0,1,2">
              <small class="text-muted">Separate possible scores with commas.</small>
            </div>
          </div>
          <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add" class="btn btn-primary rounded-pill px-4">Add Step</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-4">
        <form method="post">
          <div class="modal-header border-0 pb-0 ps-4 pt-4">
            <h5 class="modal-title fw-bold">Edit Question Step</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body p-4">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-3">
              <label class="form-label fw-semibold">Step/Question Text</label>
              <textarea name="text" id="edit_text" class="form-control rounded-3" rows="3" required></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Score Values</label>
              <input type="text" name="score_values" id="edit_score" class="form-control rounded-3" required>
            </div>
          </div>
          <div class="modal-footer border-0 p-4 pt-0">
            <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit" class="btn btn-primary rounded-pill px-4">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    function editQuestion(id, text, score) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_text').value = text;
      document.getElementById('edit_score').value = score;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }
  </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>



</html>
