<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

// Handle create/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $grading_method = $_POST['grading_method'];
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

    $stmt = $db->prepare("INSERT INTO exams (name, description, is_active, type, student_can_view_score, total_mark, duration_minutes, shuffle_objective, questions_to_show, max_attempts, grading_method, is_strict, shuffle_options, proctor_fullscreen, proctor_tab_lock, proctor_kb_lock, proctor_auto_submit, proctor_browser_check, proctor_webcam, proctor_live_video, start_time, end_time) VALUES (?, ?, ?, 'objective', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$name, $desc, $is_active, $student_can_view_score, $total_mark, $duration, $shuffle, $q_to_show, $max_attempts, $grading_method, $is_strict, $shuffle_opts, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time]);
    header('Location: admin_objective_exams.php?success=created');
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
    $grading_method = $_POST['grading_method'];
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

    $stmt = $db->prepare("UPDATE exams SET name=?, description=?, is_active=?, student_can_view_score=?, total_mark=?, duration_minutes=?, shuffle_objective=?, questions_to_show=?, max_attempts=?, grading_method=?, is_strict=?, shuffle_options=?, proctor_fullscreen=?, proctor_tab_lock=?, proctor_kb_lock=?, proctor_auto_submit=?, proctor_browser_check=?, proctor_webcam=?, proctor_live_video=?, start_time=?, end_time=? WHERE id=? AND type='objective'");
    $stmt->execute([$name, $desc, $is_active, $student_can_view_score, $total_mark, $duration, $shuffle, $q_to_show, $max_attempts, $grading_method, $is_strict, $shuffle_opts, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time, $id]);
    header('Location: admin_objective_exams.php?success=edited');
    exit;
  }
  if (isset($_POST['delete']) && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    // Delete related rows first
    $db->prepare("DELETE FROM exam_students WHERE exam_id=?")->execute([$id]);
    $db->prepare("DELETE FROM objective_answers WHERE exam_id=?")->execute([$id]);
    $db->prepare("DELETE FROM objective_questions WHERE exam_id=?")->execute([$id]);
    $db->prepare("DELETE FROM exams WHERE id=? AND type='objective'")->execute([$id]);
    header('Location: admin_objective_exams.php?success=deleted');
    exit;
  }
}

// Fetch all objective exams
$exams = $db->query("SELECT * FROM exams WHERE type='objective' ORDER BY created_at DESC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Objective Exams - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <div class="container-xl py-4">
      <h2 class="mb-4">Objective Exams</h2>
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">Action successful.</div>
      <?php endif; ?>
      <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#createModal">+ Create Objective
        Exam</button>
      <div class="table-responsive">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Duration (Min)</th>
              <th>Total Mark</th>
              <th>Attempts Allowed</th>
              <th>Grading</th>
              <th>Status</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($exams as $exam): ?>
              <tr>
                <td><?= htmlspecialchars($exam['name']) ?></td>
                <td><?= htmlspecialchars($exam['description']) ?></td>
                <td><span class="badge bg-light text-dark"><?= intval($exam['duration_minutes']) ?> mins</span></td>
                <td><?= htmlspecialchars($exam['total_mark']) ?></td>
                <td><span class="badge bg-info"><?= intval($exam['max_attempts'] ?? 1) ?> attempts</span></td>
                <td><?= ($exam['grading_method'] ?? 'highest') === 'highest' ? 'Highest Score' : 'Average Score' ?></td>
                <td>
                  <?= $exam['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                </td>
                <td><?= htmlspecialchars($exam['created_at'] ?? '') ?></td>
                <td>
                  <button class="btn btn-sm btn-info" data-bs-toggle="modal"
                    data-bs-target="#editModal<?= $exam['id'] ?>">Edit</button>
                  <form method="post" style="display:inline-block" onsubmit="return confirm('Delete this exam?')">
                    <input type="hidden" name="id" value="<?= $exam['id'] ?>">
                    <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
                  </form>
                  <a href="admin_objective_questions.php?exam_id=<?= $exam['id'] ?>"
                    class="btn btn-sm btn-secondary">Questions</a>
                  <a href="admin_assign_students.php?exam_id=<?= $exam['id'] ?>"
                    class="btn btn-sm btn-outline-primary">Assign Students</a>
                </td>
              </tr>
              <!-- Edit Modal -->
              <div class="modal fade" id="editModal<?= $exam['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="post">
                      <input type="hidden" name="id" value="<?= $exam['id'] ?>">
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Exam</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label">Name</label>
                          <input type="text" name="name" class="form-control"
                            value="<?= htmlspecialchars($exam['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Description</label>
                          <textarea name="description" class="form-control"
                            required><?= htmlspecialchars($exam['description']) ?></textarea>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Total Mark</label>
                          <input type="number" name="total_mark" class="form-control" min="0" step="0.01"
                            value="<?= isset($exam['total_mark']) ? htmlspecialchars($exam['total_mark']) : '' ?>">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Duration (Minutes)</label>
                          <input type="number" name="duration_minutes" class="form-control" min="0"
                            value="<?= isset($exam['duration_minutes']) ? intval($exam['duration_minutes']) : '0' ?>"
                            required>
                          <small class="text-muted">Set to 0 for untimed exam.</small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Number of questions per student</label>
                          <input type="number" name="questions_to_show" class="form-control" min="0"
                            value="<?= isset($exam['questions_to_show']) ? intval($exam['questions_to_show']) : '0' ?>"
                            required>
                          <small class="text-muted">Enter 0 to show all questions in the bank.</small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Maximum Attempts Allowed</label>
                          <input type="number" name="max_attempts" class="form-control" min="1"
                            value="<?= isset($exam['max_attempts']) ? intval($exam['max_attempts']) : '1' ?>" required>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Grading Method</label>
                          <select name="grading_method" class="form-select">
                            <option value="highest" <?= ($exam['grading_method'] ?? 'highest') === 'highest' ? 'selected' : '' ?>>Highest Mark overrides</option>
                            <option value="average" <?= ($exam['grading_method'] ?? '') === 'average' ? 'selected' : '' ?>>
                              Calculate Average of all attempts</option>
                          </select>
                        </div>
                        <div class="row">
                          <div class="col-6 mb-3">
                            <label class="form-label">Start Time</label>
                            <input type="datetime-local" name="start_time" class="form-control"
                              value="<?= $exam['start_time'] ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : '' ?>">
                          </div>
                          <div class="col-6 mb-3">
                            <label class="form-label">End Time</label>
                            <input type="datetime-local" name="end_time" class="form-control"
                              value="<?= $exam['end_time'] ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : '' ?>">
                          </div>
                        </div>
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" name="shuffle_objective"
                            id="shuffle<?= $exam['id'] ?>" <?= $exam['shuffle_objective'] ? 'checked' : '' ?>>
                          <label class="form-check-label" for="shuffle<?= $exam['id'] ?>">Shuffle question order for each
                            student</label>
                        </div>
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" name="shuffle_options"
                            id="shuffleOpts<?= $exam['id'] ?>" <?= ($exam['shuffle_options'] ?? 0) ? 'checked' : '' ?>>
                          <label class="form-check-label" for="shuffleOpts<?= $exam['id'] ?>">Shuffle choices (A,B,C,D)
                            order for each question</label>
                        </div>
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" name="is_active" id="active<?= $exam['id'] ?>"
                            <?= $exam['is_active'] ? 'checked' : '' ?>>
                          <label class="form-check-label" for="active<?= $exam['id'] ?>">Active</label>
                        </div>
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" name="student_can_view_score"
                            id="viewScore<?= $exam['id'] ?>" <?= $exam['student_can_view_score'] ? 'checked' : '' ?>>
                          <label class="form-check-label" for="viewScore<?= $exam['id'] ?>">Allow students to view their
                            score after submission</label>
                        </div>
                        <div class="row">
                          <div class="col-6">
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_fullscreen"
                                id="pfs<?= $exam['id'] ?>" <?= ($exam['proctor_fullscreen'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="pfs<?= $exam['id'] ?>">Force Fullscreen</label>
                            </div>
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_tab_lock"
                                id="ptab<?= $exam['id'] ?>" <?= ($exam['proctor_tab_lock'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="ptab<?= $exam['id'] ?>">Tab/Window Switch
                                Lock</label>
                            </div>
                          </div>
                          <div class="col-6">
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_kb_lock"
                                id="pkb<?= $exam['id'] ?>" <?= ($exam['proctor_kb_lock'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="pkb<?= $exam['id'] ?>">Keyboard/Copy Lock</label>
                            </div>
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_auto_submit"
                                id="pauto<?= $exam['id'] ?>" <?= ($exam['proctor_auto_submit'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="pauto<?= $exam['id'] ?>">3-Strike
                                Auto-Submit</label>
                            </div>
                          </div>
                          <div class="col-12">
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_browser_check"
                                id="pbc<?= $exam['id'] ?>" <?= ($exam['proctor_browser_check'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="pbc<?= $exam['id'] ?>">Enforce Proctoring Browser
                                Check</label>
                            </div>
                            <div class="form-check mb-2">
                              <input class="form-check-input" type="checkbox" name="proctor_webcam"
                                id="pwc<?= $exam['id'] ?>" <?= ($exam['proctor_webcam'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small" for="pwc<?= $exam['id'] ?>">Force Webcam
                                Access</label>
                            </div>
                            <div class="form-check mb-3">
                              <input class="form-check-input" type="checkbox" name="proctor_live_video"
                                id="plive<?= $exam['id'] ?>" <?= ($exam['proctor_live_video'] ?? 0) ? 'checked' : '' ?>>
                              <label class="form-check-label small text-danger fw-bold"
                                for="plive<?= $exam['id'] ?>">Enable Live Video Stream</label>
                            </div>
                          </div>
                        </div>
                        <div class="form-check mb-3">
                          <input class="form-check-input" type="checkbox" name="is_strict" id="strict<?= $exam['id'] ?>"
                            <?= $exam['is_strict'] ? 'checked' : '' ?>>
                          <label class="form-check-label text-danger fw-bold" for="strict<?= $exam['id'] ?>">
                            <i class="bi bi-shield-lock"></i> Strict Mode (Enable All Above)
                          </label>
                          <br><small class="text-muted">Master toggle for advanced proctoring.</small>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit" class="btn btn-success">Save Changes</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <!-- Create Modal -->
  <div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <h5 class="modal-title">Create Objective Exam</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" required></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Total Mark</label>
              <input type="number" name="total_mark" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="mb-3">
              <label class="form-label">Duration (Minutes)</label>
              <input type="number" name="duration_minutes" class="form-control" min="0" value="30" required>
              <small class="text-muted">Set to 0 for untimed exam.</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Number of questions per student</label>
              <input type="number" name="questions_to_show" class="form-control" min="0" value="0" required>
              <small class="text-muted">Enter 0 to show all questions in the bank.</small>
            </div>
            <div class="mb-3">
              <label class="form-label">Maximum Attempts Allowed</label>
              <input type="number" name="max_attempts" class="form-control" min="1" value="1" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Grading Method</label>
              <select name="grading_method" class="form-select">
                <option value="highest">Highest Mark overrides</option>
                <option value="average">Calculate Average of all attempts</option>
              </select>
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label class="form-label">Start Time</label>
                <input type="datetime-local" name="start_time" class="form-control">
              </div>
              <div class="col-6 mb-3">
                <label class="form-label">End Time</label>
                <input type="datetime-local" name="end_time" class="form-control">
              </div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_active" id="activeCreate">
              <label class="form-check-label" for="activeCreate">Active</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="shuffle_objective" id="shuffleCreate" checked>
              <label class="form-check-label" for="shuffleCreate">Shuffle question order for each student</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="shuffle_options" id="shuffleOptsNew" checked>
              <label class="form-check-label" for="shuffleOptsNew">Shuffle choices (A,B,C,D) order for each
                question</label>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="student_can_view_score" id="viewScoreCreate">
              <label class="form-check-label" for="viewScoreCreate">Allow students to view their score after
                submission</label>
            </div>
            <div class="row">
              <div class="col-6">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_fullscreen" id="pfsCreate" checked>
                  <label class="form-check-label small" for="pfsCreate">Force Fullscreen</label>
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_tab_lock" id="ptabCreate" checked>
                  <label class="form-check-label small" for="ptabCreate">Tab/Window Switch Lock</label>
                </div>
              </div>
              <div class="col-6">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_kb_lock" id="pkbCreate" checked>
                  <label class="form-check-label small" for="pkbCreate">Keyboard/Copy Lock</label>
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_auto_submit" id="pautoCreate" checked>
                  <label class="form-check-label small" for="pautoCreate">3-Strike Auto-Submit</label>
                </div>
              </div>
              <div class="col-12">
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_browser_check" id="pbcCreate" checked>
                  <label class="form-check-label small" for="pbcCreate">Enforce Proctoring Browser Check</label>
                </div>
                <div class="form-check mb-2">
                  <input class="form-check-input" type="checkbox" name="proctor_webcam" id="pwcCreate" checked>
                  <label class="form-check-label small" for="pwcCreate">Force Webcam Access</label>
                </div>
                <div class="form-check mb-3">
                  <input class="form-check-input" type="checkbox" name="proctor_live_video" id="pliveCreate" checked>
                  <label class="form-check-label small text-danger fw-bold" for="pliveCreate">Enable Live Video
                    Stream</label>
                </div>
              </div>
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="is_strict" id="strictCreate" checked>
              <label class="form-check-label text-danger fw-bold" for="strictCreate">
                <i class="bi bi-shield-lock"></i> Strict Mode (Enable All Above)
              </label>
              <br><small class="text-muted">Advanced proctoring features for this exam.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create" class="btn btn-primary">Create Exam</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>