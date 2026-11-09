<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

// Handle create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  validate_csrf();
  if (!empty($_POST['id'])) {
    cs_guard_exam($db, (int) $_POST['id'], 'admin_objective_exams');
  }
  $partial = isset($_POST['partial_scoring']) ? 1 : 0;
  $math = isset($_POST['math_notation']) ? 1 : 0;
  $late = trim((string) ($_POST['late_start_minutes'] ?? ''));
  $late = ($late === '' || (int) $late <= 0) ? null : (int) $late;
  $grading_ok = ['highest', 'average', 'first', 'last'];
  if (isset($_POST['create'])) {
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $shuffle = isset($_POST['shuffle_objective']) ? 1 : 0;
    $q_to_show = intval($_POST['questions_to_show']);
    $student_can_view_score = isset($_POST['student_can_view_score']) ? 1 : 0;
    $total_mark = floatval($_POST['total_mark']);
    $duration = intval($_POST['duration_minutes']);
    $max_attempts = intval($_POST['max_attempts']);
    $grading_method = in_array($_POST['grading_method'] ?? '', $grading_ok, true) ? $_POST['grading_method'] : 'highest';
    $is_strict = isset($_POST['is_strict']) ? 1 : 0;
    $shuffle_opts = isset($_POST['shuffle_options']) ? 1 : 0;
    $p_fs = isset($_POST['proctor_fullscreen']) ? 1 : 0;
    $p_tab = isset($_POST['proctor_tab_lock']) ? 1 : 0;
    $p_kb = isset($_POST['proctor_kb_lock']) ? 1 : 0;
    $p_auto = isset($_POST['proctor_auto_submit']) ? 1 : 0;
    $p_browser = isset($_POST['proctor_browser_check']) ? 1 : 0;
    $p_webcam = isset($_POST['proctor_webcam']) ? 1 : 0;
    $p_live = isset($_POST['proctor_live_video']) ? 1 : 0;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

    $session_id = $_POST['session_id'];
    $stmt = $db->prepare("INSERT INTO exams (name, description, is_active, type, session_id, student_can_view_score, total_mark, duration_minutes, shuffle_objective, questions_to_show, max_attempts, grading_method, is_strict, shuffle_options, partial_scoring, proctor_fullscreen, proctor_tab_lock, proctor_kb_lock, proctor_auto_submit, proctor_browser_check, proctor_webcam, proctor_live_video, start_time, end_time, late_start_minutes, math_notation) VALUES (?, ?, ?, 'objective', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $desc, $is_active, $session_id, $student_can_view_score, $total_mark, $duration, $shuffle, $q_to_show, $max_attempts, $grading_method, $is_strict, $shuffle_opts, $partial, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time, $late, $math]);
    header('Location: admin_objective_exams?success=created');
    exit;
  }
  if (isset($_POST['edit']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $desc = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $shuffle = isset($_POST['shuffle_objective']) ? 1 : 0;
    $q_to_show = intval($_POST['questions_to_show']);
    $student_can_view_score = isset($_POST['student_can_view_score']) ? 1 : 0;
    $total_mark = floatval($_POST['total_mark']);
    $duration = intval($_POST['duration_minutes']);
    $max_attempts = intval($_POST['max_attempts']);
    $grading_method = in_array($_POST['grading_method'] ?? '', $grading_ok, true) ? $_POST['grading_method'] : 'highest';
    $is_strict = isset($_POST['is_strict']) ? 1 : 0;
    $shuffle_opts = isset($_POST['shuffle_options']) ? 1 : 0;
    $p_fs = isset($_POST['proctor_fullscreen']) ? 1 : 0;
    $p_tab = isset($_POST['proctor_tab_lock']) ? 1 : 0;
    $p_kb = isset($_POST['proctor_kb_lock']) ? 1 : 0;
    $p_auto = isset($_POST['proctor_auto_submit']) ? 1 : 0;
    $p_browser = isset($_POST['proctor_browser_check']) ? 1 : 0;
    $p_webcam = isset($_POST['proctor_webcam']) ? 1 : 0;
    $p_live = isset($_POST['proctor_live_video']) ? 1 : 0;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

    $session_id = $_POST['session_id'];
    $stmt = $db->prepare("UPDATE exams SET name=?, description=?, is_active=?, session_id=?, student_can_view_score=?, total_mark=?, duration_minutes=?, shuffle_objective=?, questions_to_show=?, max_attempts=?, grading_method=?, is_strict=?, shuffle_options=?, partial_scoring=?, proctor_fullscreen=?, proctor_tab_lock=?, proctor_kb_lock=?, proctor_auto_submit=?, proctor_browser_check=?, proctor_webcam=?, proctor_live_video=?, start_time=?, end_time=?, late_start_minutes=?, math_notation=? WHERE id=? AND type='objective'");
    $stmt->execute([$name, $desc, $is_active, $session_id, $student_can_view_score, $total_mark, $duration, $shuffle, $q_to_show, $max_attempts, $grading_method, $is_strict, $shuffle_opts, $partial, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time, $late, $math, $id]);
    header('Location: admin_objective_exams?success=edited');
    exit;
  }
  if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    // Soft delete exam (keep child data for audit)
    $db->prepare("UPDATE exams SET deleted_at=NOW() WHERE id=? AND type='objective'")->execute([$id]);
    header('Location: admin_objective_exams?success=deleted');
    exit;
  }
}

// Fetch all objective exams
$sessions = $db->query("SELECT * FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$exams = $db->query("SELECT * FROM exams WHERE type='objective' AND deleted_at IS NULL ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Objective Exams - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/vendor/boxicons/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">

    <!-- Unified Admin Navbar -->
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <a href="admin_dashboard" class="admin-back-btn" aria-label="Back to Dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Objective Exams</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($admin_name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">

      <!-- Breadcrumb -->
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house-door"></i> Dashboard</a></li>
          <li class="sep">/</li>
          <li class="current">Objective Exams</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="admin-page-header">
        <h1 class="admin-page-title">
          <i class="bi bi-list-check"></i> Objective Exams
        </h1>
        <div class="admin-page-actions">
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle"></i> Create Objective Exam
          </button>
        </div>
      </div>

      <!-- Success Alert -->
      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle"></i>
          <span>Action successful.</span>
          <button class="admin-alert-close" data-bs-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Exams Card -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h2 class="admin-card-title">
            <i class="bi bi-table"></i> All Objective Exams
            <span class="admin-badge admin-badge-neutral ms-2"><?= count($exams) ?></span>
          </h2>
        </div>
        <div class="admin-card-body-flush">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Description</th>
                  <th>Duration</th>
                  <th>Total Mark</th>
                  <th>Attempts</th>
                  <th>Grading</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="actions-cell">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($exams)): ?>
                  <tr>
                    <td colspan="9">
                      <div class="admin-empty">
                        <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                        <div class="admin-empty-title">No Objective Exams</div>
                        <div class="admin-empty-desc">Get started by creating your first objective exam.</div>
                      </div>
                    </td>
                  </tr>
                <?php endif; ?>
                <?php foreach ($exams as $exam): ?>
                  <tr>
                    <td class="fw-semibold"><?= htmlspecialchars($exam['name']) ?></td>
                    <td class="text-secondary"><?= htmlspecialchars($exam['description']) ?></td>
                    <td><span class="admin-badge admin-badge-neutral"><?= intval($exam['duration_minutes']) ?> mins</span></td>
                    <td><?= htmlspecialchars($exam['total_mark']) ?></td>
                    <td><span class="admin-badge admin-badge-info"><?= intval($exam['max_attempts'] ?? 1) ?> attempts</span></td>
                    <td><?= ($exam['grading_method'] ?? 'highest') === 'highest' ? 'Highest Score' : 'Average Score' ?></td>
                    <td>
                      <?= $exam['is_active'] ? '<span class="admin-badge admin-badge-success">Active</span>' : '<span class="admin-badge admin-badge-neutral">Inactive</span>' ?>
                    </td>
                    <td class="text-secondary"><?= htmlspecialchars($exam['created_at'] ?? '') ?></td>
                    <td class="actions-cell">
                      <button class="admin-btn admin-btn-sm admin-btn-outline" data-bs-toggle="modal"
                        data-bs-target="#editModal<?= $exam['id'] ?>" title="Edit">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <form method="post" class="d-inline" data-swal="Delete this exam?">
<?php csrf_field(); ?>
                        <input type="hidden" name="id" value="<?= $exam['id'] ?>">
                        <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger" title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
                      </form>
                      <a href="admin_objective_questions?exam_id=<?= $exam['id'] ?>"
                        class="admin-btn admin-btn-sm admin-btn-outline" title="Questions">
                        <i class="bi bi-question-circle"></i>
                      </a>
                      <a href="admin_assign_students?exam_id=<?= $exam['id'] ?>"
                        class="admin-btn admin-btn-sm admin-btn-primary" title="Assign Students">
                        <i class="bi bi-people"></i>
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- Edit Modals -->
  <?php foreach ($exams as $exam): ?>
    <div class="modal fade" id="editModal<?= $exam['id'] ?>" tabindex="-1">
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <form method="post">
<?php csrf_field(); ?>
            <input type="hidden" name="id" value="<?= $exam['id'] ?>">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Exam</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="admin-form-group">
                <label class="admin-form-label">Academic Session</label>
                <select name="session_id" class="admin-select admin-input" required>
                  <?php foreach ($sessions as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= ($exam['session_id'] ?? 0) == $s['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($s['session_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="admin-form-group">
                <label class="admin-form-label">Name</label>
                <input type="text" name="name" class="admin-input"
                  value="<?= htmlspecialchars($exam['name']) ?>" required>
              </div>
              <div class="admin-form-group">
                <label class="admin-form-label">Description</label>
                <textarea name="description" class="admin-input" required><?= htmlspecialchars($exam['description'] ?? '') ?></textarea>
              </div>
              <div class="row">
                <div class="col-md-4">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Total Mark</label>
                    <input type="number" name="total_mark" class="admin-input" min="0" step="0.01"
                      value="<?= isset($exam['total_mark']) ? htmlspecialchars($exam['total_mark']) : '' ?>">
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" class="admin-input" min="0"
                      value="<?= isset($exam['duration_minutes']) ? intval($exam['duration_minutes']) : '0' ?>" required>
                    <small class="text-muted">Set to 0 for untimed exam.</small>
                  </div>
                </div>
                <div class="col-md-4">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Questions per Student</label>
                    <input type="number" name="questions_to_show" class="admin-input" min="0"
                      value="<?= isset($exam['questions_to_show']) ? intval($exam['questions_to_show']) : '0' ?>" required>
                    <small class="text-muted">0 = show all.</small>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Maximum Attempts</label>
                    <input type="number" name="max_attempts" class="admin-input" min="1"
                      value="<?= isset($exam['max_attempts']) ? intval($exam['max_attempts']) : '1' ?>" required>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Grading Method</label>
                    <select name="grading_method" class="admin-select admin-input">
                      <option value="highest" <?= ($exam['grading_method'] ?? 'highest') === 'highest' ? 'selected' : '' ?>>Highest Mark overrides</option>
                      <option value="average" <?= ($exam['grading_method'] ?? '') === 'average' ? 'selected' : '' ?>>Calculate Average of all attempts</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row">
                <div class="col-md-6">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Start Time</label>
                    <input type="datetime-local" name="start_time" class="admin-input"
                      value="<?= $exam['start_time'] ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : '' ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="admin-form-group">
                    <label class="admin-form-label">End Time</label>
                    <input type="datetime-local" name="end_time" class="admin-input"
                      value="<?= $exam['end_time'] ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : '' ?>">
                  </div>
                </div>
                <div class="col-12">
                  <div class="admin-form-group">
                    <label class="admin-form-label">Late entry</label>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                      <span class="small">Students may start up to</span>
                      <input type="number" name="late_start_minutes" class="admin-input" style="width:110px" min="1" step="1"
                        placeholder="any time" value="<?= $exam['late_start_minutes'] !== null ? (int) $exam['late_start_minutes'] : '' ?>">
                      <span class="small">minutes after the Start Time. Leave blank to allow starting at any time while the exam is open.</span>
                    </div>
                  </div>
                </div>
              </div>

              <ul class="nav nav-tabs mb-3" id="editTab<?= $exam['id'] ?>" role="tablist">
                <li class="nav-item" role="presentation">
                  <button class="nav-link active" id="edit-general-tab<?= $exam['id'] ?>" data-bs-toggle="tab"
                    data-bs-target="#edit-general<?= $exam['id'] ?>" type="button" role="tab">
                    <i class="bi bi-gear"></i> General Settings
                  </button>
                </li>
                <li class="nav-item" role="presentation">
                  <button class="nav-link" id="edit-proctor-tab<?= $exam['id'] ?>" data-bs-toggle="tab"
                    data-bs-target="#edit-proctor<?= $exam['id'] ?>" type="button" role="tab">
                    <i class="bi bi-shield-check"></i> Proctoring
                  </button>
                </li>
              </ul>

              <div class="tab-content">
                <div class="tab-pane fade show active" id="edit-general<?= $exam['id'] ?>" role="tabpanel">
                  <div class="admin-checkbox-group">
                    <label class="admin-checkbox">
                      <input type="checkbox" name="shuffle_objective" <?= $exam['shuffle_objective'] ? 'checked' : '' ?>>
                      <i class="bi bi-shuffle"></i> Shuffle question order
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="shuffle_options" <?= ($exam['shuffle_options'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-arrow-left-right"></i> Shuffle choices and MTF statements
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="partial_scoring" <?= ($exam['partial_scoring'] ?? 1) ? 'checked' : '' ?>>
                      <i class="bi bi-pie-chart"></i> Partial credit for Multiple True/False and multiple-answer questions
                    </label>
                    <label class="admin-checkbox" title="For maths, physics and chemistry papers">
                      <input type="checkbox" name="math_notation" <?= ($exam['math_notation'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-calculator"></i> Maths &amp; science notation: $x^2$ formulas, x^2 and H_2O shorthand
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="is_active" <?= $exam['is_active'] ? 'checked' : '' ?>>
                      <i class="bi bi-check-circle"></i> Active
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="student_can_view_score" <?= $exam['student_can_view_score'] ? 'checked' : '' ?>>
                      <i class="bi bi-eye"></i> Allow students to view score
                    </label>
                  </div>
                </div>

                <div class="tab-pane fade" id="edit-proctor<?= $exam['id'] ?>" role="tabpanel">
                  <div class="admin-checkbox-group">
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_fullscreen" <?= ($exam['proctor_fullscreen'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-arrows-fullscreen"></i> Force Fullscreen
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_tab_lock" <?= ($exam['proctor_tab_lock'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-lock"></i> Tab/Window Switch Lock
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_kb_lock" <?= ($exam['proctor_kb_lock'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-keyboard"></i> Keyboard/Copy Lock
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_auto_submit" <?= ($exam['proctor_auto_submit'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-send"></i> 3-Strike Auto-Submit
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_browser_check" <?= ($exam['proctor_browser_check'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-browser-chrome"></i> Proctoring Browser Check
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_webcam" <?= ($exam['proctor_webcam'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-camera-video"></i> Force Webcam Access
                    </label>
                    <label class="admin-checkbox">
                      <input type="checkbox" name="proctor_live_video" <?= ($exam['proctor_live_video'] ?? 0) ? 'checked' : '' ?>>
                      <i class="bi bi-broadcast"></i> Enable Live Video Stream
                    </label>
                  </div>
                  <div class="mt-3">
                    <label class="admin-checkbox fw-bold text-danger">
                      <input type="checkbox" name="is_strict" <?= $exam['is_strict'] ? 'checked' : '' ?>>
                      <i class="bi bi-shield-lock"></i> Strict Mode (Enable All Above)
                    </label>
                    <br><small class="text-muted">Master toggle for advanced proctoring.</small>
                  </div>
                </div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">
                <i class="bi bi-x"></i> Cancel
              </button>
              <button type="submit" name="edit" class="admin-btn admin-btn-primary">
                <i class="bi bi-check-lg"></i> Save Changes
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- Create Modal -->
  <div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form method="post">
<?php csrf_field(); ?>
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Create Objective Exam</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="admin-form-group">
              <label class="admin-form-label">Academic Session</label>
              <select name="session_id" class="admin-select admin-input" required>
                <option value="">-- Select Session --</option>
                <?php foreach ($sessions as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['session_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-form-group">
              <label class="admin-form-label">Name</label>
              <input type="text" name="name" class="admin-input" required>
            </div>
            <div class="admin-form-group">
              <label class="admin-form-label">Description</label>
              <textarea name="description" class="admin-input" required></textarea>
            </div>
            <div class="row">
              <div class="col-md-4">
                <div class="admin-form-group">
                  <label class="admin-form-label">Total Mark</label>
                  <input type="number" name="total_mark" class="admin-input" min="0" step="0.01" value="0">
                </div>
              </div>
              <div class="col-md-4">
                <div class="admin-form-group">
                  <label class="admin-form-label">Duration (Minutes)</label>
                  <input type="number" name="duration_minutes" class="admin-input" min="0" value="30" required>
                  <small class="text-muted">Set to 0 for untimed exam.</small>
                </div>
              </div>
              <div class="col-md-4">
                <div class="admin-form-group">
                  <label class="admin-form-label">Questions per Student</label>
                  <input type="number" name="questions_to_show" class="admin-input" min="0" value="0" required>
                  <small class="text-muted">0 = show all.</small>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label class="admin-form-label">Maximum Attempts</label>
                  <input type="number" name="max_attempts" class="admin-input" min="1" value="1" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label class="admin-form-label">Grading Method</label>
                  <select name="grading_method" class="admin-select admin-input">
                    <option value="highest">Highest Mark overrides</option>
                    <option value="average">Calculate Average of all attempts</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label class="admin-form-label">Start Time</label>
                  <input type="datetime-local" name="start_time" class="admin-input">
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label class="admin-form-label">End Time</label>
                  <input type="datetime-local" name="end_time" class="admin-input">
                </div>
              </div>
              <div class="col-12">
                <div class="admin-form-group">
                  <label class="admin-form-label">Late entry</label>
                  <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="small">Students may start up to</span>
                    <input type="number" name="late_start_minutes" class="admin-input" style="width:110px" min="1" step="1" placeholder="any time">
                    <span class="small">minutes after the Start Time. Leave blank to allow starting at any time while the exam is open.</span>
                  </div>
                </div>
              </div>
            </div>

            <ul class="nav nav-tabs mb-3" id="createTab" role="tablist">
              <li class="nav-item" role="presentation">
                <button class="nav-link active" id="create-general-tab" data-bs-toggle="tab"
                  data-bs-target="#create-general" type="button" role="tab">
                  <i class="bi bi-gear"></i> General Settings
                </button>
              </li>
              <li class="nav-item" role="presentation">
                <button class="nav-link" id="create-proctor-tab" data-bs-toggle="tab"
                  data-bs-target="#create-proctor" type="button" role="tab">
                  <i class="bi bi-shield-check"></i> Proctoring
                </button>
              </li>
            </ul>

            <div class="tab-content">
              <div class="tab-pane fade show active" id="create-general" role="tabpanel">
                <div class="admin-checkbox-group">
                  <label class="admin-checkbox">
                    <input type="checkbox" name="is_active" id="activeCreate">
                    <i class="bi bi-check-circle"></i> Active
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="shuffle_objective" id="shuffleCreate" checked>
                    <i class="bi bi-shuffle"></i> Shuffle question order
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="shuffle_options" id="shuffleOptsNew" checked>
                    <i class="bi bi-arrow-left-right"></i> Shuffle choices and MTF statements
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="partial_scoring" id="partialNew" checked>
                    <i class="bi bi-pie-chart"></i> Partial credit for Multiple True/False and multiple-answer questions
                  </label>
                  <label class="admin-checkbox" title="For maths, physics and chemistry papers">
                    <input type="checkbox" name="math_notation" id="mathNew">
                    <i class="bi bi-calculator"></i> Maths &amp; science notation: $x^2$ formulas, x^2 and H_2O shorthand
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="student_can_view_score" id="viewScoreCreate">
                    <i class="bi bi-eye"></i> Allow students to view score
                  </label>
                </div>
              </div>

              <div class="tab-pane fade" id="create-proctor" role="tabpanel">
                <div class="admin-checkbox-group">
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_fullscreen" id="pfsCreate" checked>
                    <i class="bi bi-arrows-fullscreen"></i> Force Fullscreen
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_tab_lock" id="ptabCreate" checked>
                    <i class="bi bi-lock"></i> Tab/Window Switch Lock
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_kb_lock" id="pkbCreate" checked>
                    <i class="bi bi-keyboard"></i> Keyboard/Copy Lock
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_auto_submit" id="pautoCreate" checked>
                    <i class="bi bi-send"></i> 3-Strike Auto-Submit
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_browser_check" id="pbcCreate" checked>
                    <i class="bi bi-browser-chrome"></i> Proctoring Browser Check
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_webcam" id="pwcCreate" checked>
                    <i class="bi bi-camera-video"></i> Force Webcam Access
                  </label>
                  <label class="admin-checkbox">
                    <input type="checkbox" name="proctor_live_video" id="pliveCreate" checked>
                    <i class="bi bi-broadcast"></i> Enable Live Video Stream
                  </label>
                </div>
                <div class="mt-3">
                  <label class="admin-checkbox fw-bold text-danger">
                    <input type="checkbox" name="is_strict" id="strictCreate" checked>
                    <i class="bi bi-shield-lock"></i> Strict Mode (Enable All Above)
                  </label>
                  <br><small class="text-muted">Advanced proctoring features for this exam.</small>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">
              <i class="bi bi-x"></i> Cancel
            </button>
            <button type="submit" name="create" class="admin-btn admin-btn-primary">
              <i class="bi bi-check-lg"></i> Create Exam
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>

</html>
