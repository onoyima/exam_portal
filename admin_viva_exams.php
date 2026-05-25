<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create'])) {
            $name = trim($_POST['name']);
            $session_id = intval($_POST['session_id']);
            $total_mark = floatval($_POST['total_mark']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $db->beginTransaction();

            // Insert exam
            $stmt = $db->prepare("INSERT INTO exams (name, type, session_id, total_mark, is_active) VALUES (?, 'viva', ?, ?, ?)");
            $stmt->execute([$name, $session_id, $total_mark, $is_active]);
            $exam_id = $db->lastInsertId();

            // Auto-create single question for VIVA
            $stmt = $db->prepare("INSERT INTO questions (text, score_values) VALUES (?, ?)");
            $stmt->execute(["General Viva Performance", "0"]); // score_values will be handled dynamically in assess.php
            $question_id = $db->lastInsertId();

            // Link question to exam
            $stmt = $db->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (?, ?)");
            $stmt->execute([$exam_id, $question_id]);

            $db->commit();
            header('Location: admin_viva_exams.php?success=Exam created successfully');
            exit;
        }

        if (isset($_POST['edit'])) {
            $id = intval($_POST['id']);
            $name = trim($_POST['name']);
            $session_id = intval($_POST['session_id']);
            $total_mark = floatval($_POST['total_mark']);
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $db->prepare("UPDATE exams SET name=?, session_id=?, total_mark=?, is_active=? WHERE id=? AND type='viva'");
            $stmt->execute([$name, $session_id, $total_mark, $is_active, $id]);
            
            header('Location: admin_viva_exams.php?success=Exam updated successfully');
            exit;
        }

        if (isset($_POST['delete'])) {
            $id = intval($_POST['id']);
            // Soft delete exam (mark as deleted, keep child data for audit)
            $db->prepare("UPDATE exams SET deleted_at=NOW() WHERE id=? AND type='viva'")->execute([$id]);
            header('Location: admin_viva_exams.php?success=Exam deleted successfully');
            exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

$sessions = $db->query("SELECT * FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$exams = $db->query("SELECT e.*, s.session_name FROM exams e LEFT JOIN academic_sessions s ON e.session_id = s.id WHERE e.type='viva' AND e.deleted_at IS NULL ORDER BY e.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['success'])) $success = $_GET['success'];
$back_url = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage VIVA Exams - Assessment Portal</title>
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
          <?php if (isset($back_url)): ?>
            <a href="<?= $back_url ?>" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a>
          <?php endif; ?>
          <h1 class="admin-navbar-title">VIVA Exam Management</h1>
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
          <li class="current" aria-current="page">Viva Exams</li>
        </ol>
      </nav>

      <?php if (isset($success) && $success): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($success) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($error) && $error): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= htmlspecialchars($error) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title"><i class="bi bi-mic-fill"></i> VIVA Exams</h2>
        <div class="admin-page-actions">
          <button class="admin-btn admin-btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-plus-circle"></i> Create VIVA Exam
          </button>
        </div>
      </div>

      <div class="admin-card">
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Exam Name</th>
                  <th>Academic Session</th>
                  <th>Max Score</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $exam): ?>
                  <tr>
                    <td class="fw-bold"><?= htmlspecialchars($exam['name']) ?></td>
                    <td><?= htmlspecialchars($exam['session_name'] ?? 'N/A') ?></td>
                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($exam['total_mark']) ?> Points</span></td>
                    <td>
                      <?= $exam['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                    </td>
                    <td class="small text-muted"><?= date('M d, Y', strtotime($exam['created_at'])) ?></td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $exam['id'] ?>" title="Edit">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <a href="admin_assign_students?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-info" title="Assign Students">
                          <i class="bi bi-people"></i>
                        </a>
                        <a href="admin_exams?exam_id=<?= $exam['id'] ?>" class="btn btn-outline-secondary" title="Assign Examiners">
                          <i class="bi bi-person-badge"></i>
                        </a>
                        <form method="post" class="d-inline" data-swal="Delete this VIVA exam? This action cannot be undone.">
                          <input type="hidden" name="id" value="<?= $exam['id'] ?>">
                          <button type="submit" name="delete" class="btn btn-outline-danger" title="Delete">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>

                  <!-- Edit Modal -->
                  <div class="modal fade" id="editModal<?= $exam['id'] ?>" tabindex="-1">
                    <div class="modal-dialog">
                      <div class="modal-content border-0 shadow-lg">
                        <form method="post">
                          <input type="hidden" name="id" value="<?= $exam['id'] ?>">
                          <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title">Edit VIVA Exam</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                          </div>
                          <div class="modal-body">
                            <div class="mb-3">
                              <label class="form-label fw-bold">Exam Name</label>
                              <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($exam['name']) ?>" required>
                            </div>
                            <div class="mb-3">
                              <label class="form-label fw-bold">Academic Session</label>
                              <select name="session_id" class="form-select" required>
                                <?php foreach ($sessions as $s): ?>
                                  <option value="<?= $s['id'] ?>" <?= $exam['session_id'] == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['session_name']) ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>
                            <div class="mb-3">
                              <label class="form-label fw-bold">Maximum Score</label>
                              <input type="number" name="total_mark" class="form-control" step="0.01" value="<?= htmlspecialchars($exam['total_mark']) ?>" required>
                              <small class="text-muted">The examiner will be able to select scores within this range.</small>
                            </div>
                            <div class="form-check form-switch mt-3">
                              <input class="form-check-input" type="checkbox" name="is_active" id="activeEdit<?= $exam['id'] ?>" <?= $exam['is_active'] ? 'checked' : '' ?>>
                              <label class="form-check-label" for="activeEdit<?= $exam['id'] ?>">Active Session</label>
                            </div>
                          </div>
                          <div class="modal-footer bg-light border-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="edit" class="btn btn-primary px-4">Update Exam</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php if (empty($exams)): ?>
                  <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No VIVA exams found. Create one to get started.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Create Modal -->
  <div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-0 shadow-lg">
        <form method="post">
          <div class="modal-header bg-success text-white">
            <h5 class="modal-title">Create New VIVA Exam</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label fw-bold">Exam Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Clinical Nursing VIVA" required>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Academic Session</label>
              <select name="session_id" class="form-select" required>
                <option value="">-- Select Session --</option>
                <?php foreach ($sessions as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['session_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Maximum Score</label>
              <input type="number" name="total_mark" class="form-control" step="0.01" value="10" required>
              <small class="text-muted">Common values: 10, 20. The system will generate score options automatically.</small>
            </div>
            <div class="form-check form-switch mt-3">
              <input class="form-check-input" type="checkbox" name="is_active" id="activeCreate" checked>
              <label class="form-check-label" for="activeCreate">Active Session</label>
            </div>
          </div>
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create" class="btn btn-success px-4">Create Exam</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
