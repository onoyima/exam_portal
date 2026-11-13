<?php
/**
 * admin_promo_exams.php — Manage Staff Promotional Exams (admin only)
 *
 * Imitates admin_objective_exams.php: full CRUD for promotional exams.
 * Uses the standard admin sidebar layout. Isolated from the core exam system.
 */

require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    if (isset($_POST['create'])) {
        $name = trim($_POST['name']);
        $type = in_array($_POST['type'] ?? '', ['objective', 'subjective'], true) ? $_POST['type'] : 'objective';
        $instructions = trim($_POST['instructions']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $duration = intval($_POST['duration_minutes']);
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        if ($name === '') {
            $error = 'Exam name is required.';
        } else {
            $db->prepare("INSERT INTO promo_exams (name, type, instructions, is_active, duration_minutes, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?)")
                ->execute([$name, $type, $instructions, $is_active, $duration, $start_time, $end_time]);
            header('Location: admin_promo_exams?success=created');
            exit;
        }
    }
    if (isset($_POST['edit']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);
        $type = in_array($_POST['type'] ?? '', ['objective', 'subjective'], true) ? $_POST['type'] : 'objective';
        $instructions = trim($_POST['instructions']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $duration = intval($_POST['duration_minutes']);
        $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
        $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
        if ($name === '') {
            $error = 'Exam name is required.';
        } else {
            $db->prepare("UPDATE promo_exams SET name=?, type=?, instructions=?, is_active=?, duration_minutes=?, start_time=?, end_time=? WHERE id=?")
                ->execute([$name, $type, $instructions, $is_active, $duration, $start_time, $end_time, $id]);
            header('Location: admin_promo_exams?success=edited');
            exit;
        }
    }
    if (isset($_POST['delete']) && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $db->prepare("UPDATE promo_exams SET is_active = 0 WHERE id = ?")->execute([$id]);
        header('Location: admin_promo_exams?success=deleted');
        exit;
    }
}

$exams = $db->query("SELECT * FROM promo_exams ORDER BY is_active DESC, created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($exams as &$e) {
    $e['staff_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_staff WHERE exam_id = " . (int) $e['id'])->fetchColumn();
    $e['question_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_questions WHERE exam_id = " . (int) $e['id'])->fetchColumn();
    $e['attempt_count'] = (int) $db->query("SELECT COUNT(*) FROM promo_exam_attempts WHERE exam_id = " . (int) $e['id'])->fetchColumn();
}
unset($e);

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Promotional Exams - Admin</title>
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
          <a href="admin_dashboard" class="admin-back-btn" aria-label="Back to Dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Promotional Exams</h1>
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
          <li class="current" aria-current="page">Promotional Exams</li>
        </nav>
      </div>

      <?php display_auth_errors(); ?>
      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success"><i class="bi bi-check-circle me-2"></i>
          <?= 'created' === $_GET['success'] ? 'Promotional exam created.' : ('edited' === $_GET['success'] ? 'Promotional exam updated.' : 'Promotional exam deactivated.') ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Promotional Exams</h2>
        <div class="admin-page-actions">
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
            <i class="bi bi-plus me-1"></i> Create exam
          </button>
          <a href="admin_promo_staff" class="admin-btn admin-btn-outline">
            <i class="bi bi-people me-1"></i> Manage promo staff
          </a>
          <a href="admin_promo_results" class="admin-btn admin-btn-outline">
            <i class="bi bi-bar-chart me-1"></i> Promo results
          </a>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= count($exams) ?></div>
            <div class="admin-stat-label">Total Exams</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= array_sum(array_map(fn($e) => (int) $e['staff_count'], $exams)) ?></div>
            <div class="admin-stat-label">Staff Enrolments</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= array_sum(array_map(fn($e) => (int) $e['question_count'], $exams)) ?></div>
            <div class="admin-stat-label">Questions</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= array_sum(array_map(fn($e) => (int) $e['attempt_count'], $exams)) ?></div>
            <div class="admin-stat-label">Attempts</div>
          </div>
        </div>
      </div>

      <?php if (!$exams): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <p class="text-muted text-center py-4"><i class="bi bi-mortarboard me-2"></i>No promotional exams yet. Create one to begin.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Exam name</th>
                <th>Type</th>
                <th>Status</th>
                <th>Duration</th>
                <th>Staff</th>
                <th>Questions</th>
                <th>Attempts</th>
                <th>Window</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($exams as $e): ?>
              <tr>
                <td><?= (int) $e['id'] ?></td>
                <td><?= htmlspecialchars((string) $e['name']) ?></td>
                <td>
                  <span class="admin-badge admin-badge-<?= $e['type'] === 'objective' ? 'info' : 'success' ?>">
                    <?= htmlspecialchars(ucfirst((string) $e['type'])) ?>
                  </span>
                </td>
                <td>
                  <span class="admin-badge admin-badge-<?= (int) $e['is_active'] ? 'success' : 'neutral' ?>">
                    <?= (int) $e['is_active'] ? 'Active' : 'Inactive' ?>
                  </span>
                </td>
                <td><?= (int) $e['duration_minutes'] ?> min</td>
                <td><?= (int) $e['staff_count'] ?></td>
                <td><?= (int) $e['question_count'] ?></td>
                <td><?= (int) $e['attempt_count'] ?></td>
                <td class="small text-muted">
                  <?= !empty($e['start_time']) ? date('M j, g:i A', strtotime((string) $e['start_time'])) : '—' ?>
                  <span class="text-muted"> to </span><?= !empty($e['end_time']) ? date('M j, g:i A', strtotime((string) $e['end_time'])) : '—' ?>
                </td>
                <td class="text-end">
                  <a href="admin_promo_questions?exam_id=<?= (int) $e['id'] ?>" class="admin-btn admin-btn-outline admin-btn-sm">
                    <i class="bi bi-pencil-square me-1"></i>Questions
                  </a>
                  <button class="admin-btn admin-btn-outline admin-btn-sm" onclick="editExam(<?= htmlspecialchars(json_encode($e)) ?>)">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </button>
                  <button class="admin-btn admin-btn-danger admin-btn-sm" onclick="deleteExam(<?= (int) $e['id'] ?>, <?= json_encode(htmlspecialchars((string) $e['name'])) ?>)">
                    <i class="bi bi-trash me-1"></i>Deactivate
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <!-- Create / Edit modal -->
  <div class="modal fade" id="createExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="createExamModalLabel">Create promotional exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post">
          <?php csrf_field(); ?>
          <input type="hidden" name="id" id="modalExamId">
          <input type="hidden" name="edit" value="0">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Exam name</label>
              <input type="text" name="name" id="modalName" class="form-control" required>
            </div>
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label">Type</label>
                <select name="type" id="modalType" class="form-select">
                  <option value="objective">Objective (MCQ)</option>
                  <option value="subjective">Subjective (Essay)</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label">Duration (minutes)</label>
                <input type="number" name="duration_minutes" id="modalDuration" class="form-control" value="60" min="0">
              </div>
            </div>
            <div class="row g-3 mt-3">
              <div class="col-6">
                <label class="form-label">Start time</label>
                <input type="datetime-local" name="start_time" id="modalStart" class="form-control">
              </div>
              <div class="col-6">
                <label class="form-label">End time</label>
                <input type="datetime-local" name="end_time" id="modalEnd" class="form-control">
              </div>
            </div>
            <div class="mb-3 mt-3">
              <label class="form-label">Instructions to staff</label>
              <textarea name="instructions" id="modalInstructions" class="form-control" rows="3" placeholder="e.g. Read each question carefully. This attempt will be recorded."></textarea>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" name="is_active" id="modalActive" class="form-check-input" checked>
              <label class="form-check-label" for="modalActive">Active (staff can see and take this exam)</label>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create" class="admin-btn admin-btn-primary" id="modalSubmitBtn">Create exam</button>
            <button type="submit" name="edit" class="admin-btn admin-btn-primary d-none" id="modalEditBtn">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script>
    const createExamModal = document.getElementById('createExamModal');
    const modalTitle = document.getElementById('createExamModalLabel');
    const modalSubmitBtn = document.getElementById('modalSubmitBtn');
    const modalEditBtn = document.getElementById('modalEditBtn');

    function editExam(e) {
      modalTitle.textContent = 'Edit promotional exam';
      document.getElementById('modalExamId').value = e.id;
      document.getElementById('modalName').value = e.name;
      document.getElementById('modalType').value = e.type;
      document.getElementById('modalDuration').value = e.duration_minutes;
      document.getElementById('modalInstructions').value = e.instructions ?? '';
      document.getElementById('modalActive').checked = e.is_active == 1;
      const start = e.start_time ? new Date(e.start_time).toISOString().slice(0, 16) : '';
      const end = e.end_time ? new Date(e.end_time).toISOString().slice(0, 16) : '';
      document.getElementById('modalStart').value = start;
      document.getElementById('modalEnd').value = end;
      document.querySelector('input[name="edit"]').value = '1';
      modalSubmitBtn.classList.add('d-none');
      modalEditBtn.classList.remove('d-none');
      new bootstrap.Modal(createExamModal).show();
    }

    function deleteExam(id, name) {
      if (!confirm('Deactivate the promotional exam "' + name + '"? Staff will no longer see or take it.')) return;
      const fd = new FormData();
      fd.append('csrf_token', '<?= bin2hex(random_bytes(16)) ?>');
      fd.append('id', id);
      fd.append('delete', '1');
      fetch('admin_promo_exams', { method: 'POST', body: fd, headers: { 'X-Requested-With': 'fetch' } })
        .then(() => location.reload());
    }

    createExamModal.addEventListener('hidden.bs.modal', function () {
      modalTitle.textContent = 'Create promotional exam';
      document.getElementById('modalExamId').value = '';
      document.getElementById('modalName').value = '';
      document.getElementById('modalType').value = 'objective';
      document.getElementById('modalDuration').value = '60';
      document.getElementById('modalInstructions').value = '';
      document.getElementById('modalActive').checked = true;
      document.getElementById('modalStart').value = '';
      document.getElementById('modalEnd').value = '';
      document.querySelector('input[name="edit"]').value = '0';
      modalSubmitBtn.classList.remove('d-none');
      modalEditBtn.classList.add('d-none');
    });
  </script>
</body>
</html>
