<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_exam = null;

if ($exam_id) {
  $stmt = $db->prepare("SELECT * FROM exams WHERE id = ?");
  $stmt->execute([$exam_id]);
  $selected_exam = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle Export CSV
if ($selected_exam && isset($_GET['export'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="checklist_questions_' . str_replace(' ', '_', $selected_exam['name']) . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Question Text', 'Score Values (comma separated)']);
  $q_stmt = $db->prepare("SELECT text, score_values FROM questions WHERE exam_id=? ORDER BY id");
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
    // Delete related answers first to satisfy foreign key constraints
    $db->prepare("DELETE FROM answers WHERE question_id=? AND exam_id=?")->execute([$_POST['id'], $exam_id]);

    $stmt = $db->prepare("DELETE FROM questions WHERE id=? AND exam_id=?");
    $stmt->execute([$_POST['id'], $exam_id]);
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=? AND question_id=?")->execute([$exam_id, $_POST['id']]);
  } elseif (isset($_POST['clear_all'])) {
    // Delete all related answers and mappings first
    $db->prepare("DELETE FROM answers WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM questions WHERE exam_id=?")->execute([$exam_id]);
  }
  header("Location: admin_questions?exam_id=$exam_id&success=1");
  exit;
}

// Fetch Data
if ($exam_id) {
  $questions = $db->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY id");
  $questions->execute([$exam_id]);
  $questions = $questions->fetchAll(PDO::FETCH_ASSOC);
} else {
  // Fetch exams that are practical (not objective)
  $exams = $db->query("SELECT * FROM exams WHERE type != 'objective' OR type IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
}

$name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Questions - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><i class="bi bi-list"></i></button>
          <?php if ($exam_id): ?>
            <a href="admin_questions" class="btn btn-outline-secondary btn-sm rounded-pill"><i
                class="bi bi-arrow-left"></i> All Exams</a>
          <?php endif; ?>
          <span class="navbar-brand fw-bold ms-2">Practical Questions</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold d-none d-md-block"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger btn-sm rounded-pill ms-2">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-xl py-2">
      <?php if (isset($_GET['success'])): ?>
        <div
          class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 border-start border-success border-4"
          role="alert">
          <i class="bi bi-check-circle-fill me-2"></i>
          <?php
          if (isset($_GET['success']) && $_GET['success'] == 'cleared')
            echo "Successfully cleared all questions for the <strong>" . htmlspecialchars($selected_exam['name'] ?? 'selected') . "</strong> exam. Other exams remain unchanged.";
          else
            echo "Action successful.";
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (!$exam_id): ?>
        <!-- Exam Selection List -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0">Select Exam to Manage Questions</h4>
            <p class="text-muted small mb-0 mt-1">Checklists and practical assessments are managed per exam.</p>
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-4">Exam Name</th>
                    <th>Status</th>
                    <th>Questions Count</th>
                    <th class="text-end pe-4">Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($exams as $ex): ?>
                    <?php
                    $q_count = $db->prepare("SELECT COUNT(*) FROM questions WHERE exam_id=?");
                    $q_count->execute([$ex['id']]);
                    $count = $q_count->fetchColumn();
                    ?>
                    <tr>
                      <td class="ps-4 fw-bold"><?= htmlspecialchars($ex['name']) ?></td>
                      <td>
                        <?= $ex['is_active'] ? '<span class="badge bg-success-light text-success">Active</span>' : '<span class="badge bg-secondary-light text-secondary">Inactive</span>' ?>
                      </td>
                      <td><span class="badge bg-primary-light text-primary"><?= $count ?> Questions</span></td>
                      <td class="text-end pe-4">
                        <a href="admin_questions?exam_id=<?= $ex['id'] ?>"
                          class="btn btn-primary btn-sm rounded-pill px-4">Manage Questions</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="fw-bold mb-0"><?= htmlspecialchars($selected_exam['name']) ?></h2>
            <span class="text-muted">Managing Practical Checklist Questions</span>
          </div>
          <div class="d-flex gap-2">
            <a href="?exam_id=<?= $exam_id ?>&export=1" class="btn btn-outline-success btn-sm rounded-pill px-3 me-2">
              <i class="bi bi-file-earmark-spreadsheet me-1"></i> Export to CSV
            </a>
            <form method="post" class="d-inline"
              onsubmit="return confirm('WARNING: You are about to permanently delete ALL <?= count($questions) ?> questions from the \'<?= htmlspecialchars($selected_exam['name']) ?>\' bank. All other exams remain untouched. This cannot be undone. Proceed?')">
              <button type="submit" name="clear_all" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="bi bi-trash3-fill me-1"></i> Clear All Questions (<?= count($questions) ?>)
              </button>
            </form>
            <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addModal">
              <i class="bi bi-plus-lg"></i> Add Question
            </button>
          </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="ps-4">#</th>
                    <th>Question Step Text</th>
                    <th>Allowed Score Values</th>
                    <th class="text-end pe-4">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($questions as $i => $q): ?>
                    <tr>
                      <td class="ps-4 text-muted"><?= $i + 1 ?></td>
                      <td class="fw-medium"><?= htmlspecialchars($q['text']) ?></td>
                      <td><code><?= htmlspecialchars($q['score_values']) ?></code></td>
                      <td class="text-end pe-4">
                        <div class="btn-group">
                          <button class="btn btn-sm btn-outline-info rounded-pill px-3 me-1"
                            onclick="editQuestion(<?= $q['id'] ?>, '<?= htmlspecialchars($q['text'], ENT_QUOTES) ?>', '<?= htmlspecialchars($q['score_values'], ENT_QUOTES) ?>')">
                            Edit
                          </button>
                          <form method="post" class="d-inline" onsubmit="return confirm('Delete this question?')">
                            <input type="hidden" name="id" value="<?= $q['id'] ?>">
                            <button type="submit" name="delete"
                              class="btn btn-sm btn-outline-danger rounded-pill px-3">Delete</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  <?php if (empty($questions)): ?>
                    <tr>
                      <td colspan="4" class="text-center py-5 text-muted">No questions found for this exam. Add one or
                        upload data.</td>
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
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.onclick = function () {
        sidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarBackdrop.onclick = function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      };
    }

    function editQuestion(id, text, score) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_text').value = text;
      document.getElementById('edit_score').value = score;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }
  </script>
<!-- sweetalert_custom_injected_v1 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global alert
        window.alert = function(msg) {
            Swal.fire({
                title: 'Message',
                text: msg,
                icon: 'info',
                confirmButtonColor: '#092c19'
            });
        };

        // Intercept inline onsubmit confirms
        document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') 
                                      || el.querySelector('button[type="submit"], input[type="submit"]');
                            if(btn && btn.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = btn.name;
                                hidden.value = btn.value || '1';
                                el.appendChild(hidden);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
            }
        });

        // Intercept inline onclick confirms for anchors
        document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: msg,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#092c19',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, proceed'
                    }).then((result) => {
                        if(result.isConfirmed) {
                            if(el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else if(el.tagName === 'BUTTON') {
                                // if it's a submit button inside a form
                                if(el.type === 'submit' && el.form) {
                                    if(el.name) {
                                        let hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = el.name;
                                        hidden.value = el.value || '1';
                                        el.form.appendChild(hidden);
                                    }
                                    HTMLFormElement.prototype.submit.call(el.form);
                                }
                            }
                        }
                    });
                });
            }
        });
    });
</script>
</body>


</html>
