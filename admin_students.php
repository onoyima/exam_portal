<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require 'db_exeat2.php';
$name = $_SESSION['user'] ?? 'Admin';

// Fetch exeat2 filter options
$exeat2_departments = [];
$exeat2_levels = [];
$exeat2_course_studies = [];
if ($exeat2_db) {
  try {
    $exeat2_departments = $exeat2_db->query("SELECT id, name FROM departments WHERE name IS NOT NULL AND name != '' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $exeat2_levels = $exeat2_db->query("SELECT id, level FROM levels ORDER BY CAST(level AS UNSIGNED)")->fetchAll(PDO::FETCH_ASSOC);
    $exeat2_course_studies = $exeat2_db->query("SELECT id, name, department_id FROM exeat2.course_studies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $exeat2_error = $e->getMessage();
    error_log("exeat2 query failed: " . $e->getMessage());
  }
}

// ── AJAX: Get student details and exam history ──
if (isset($_GET['action']) && $_GET['action'] === 'get_student_exams' && isset($_GET['student_id'])) {
  header('Content-Type: application/json');
  $sid = intval($_GET['student_id']);

  $student = $db->prepare("SELECT id, name, firstname, middlename, lastname, email, matric_no, username FROM students WHERE id=? AND deleted_at IS NULL");
  $student->execute([$sid]);
  $student_data = $student->fetch(PDO::FETCH_ASSOC);
  if (!$student_data) { echo json_encode(['error' => 'Student not found']); exit; }

  // Practical exams — answers has one row per question; SUM scores per exam
  $practical = $db->prepare("
    SELECT e.id, e.name, e.type, e.created_at,
           SUM(a.score) as total_score,
           COUNT(a.id) as questions_graded,
           MAX(a.created_at) as last_activity
    FROM answers a
    JOIN exams e ON a.exam_id = e.id AND e.deleted_at IS NULL AND e.type = 'practical'
    WHERE a.student_id = ?
    GROUP BY e.id, e.name, e.type, e.created_at
    ORDER BY e.created_at DESC
  ");
  $practical->execute([$sid]);
  $practical_exams = $practical->fetchAll(PDO::FETCH_ASSOC);

  // Viva exams — also in answers table, one row per viva attempt
  $viva = $db->prepare("
    SELECT e.id, e.name, e.type, e.created_at,
           SUM(a.score) as total_score,
           COUNT(a.id) as questions_graded,
           MAX(a.created_at) as last_activity
    FROM answers a
    JOIN exams e ON a.exam_id = e.id AND e.deleted_at IS NULL AND e.type = 'viva'
    WHERE a.student_id = ?
    GROUP BY e.id, e.name, e.type, e.created_at
    ORDER BY e.created_at DESC
  ");
  $viva->execute([$sid]);
  $viva_exams = $viva->fetchAll(PDO::FETCH_ASSOC);

  // Objective exams — one row per attempt; use grading_method to pick correct score
  $objective = $db->prepare("
    SELECT e.id, e.name, e.type, e.created_at, e.grading_method, e.max_attempts,
           oa.score, oa.attempt_no, oa.finished_at
    FROM objective_attempts oa
    JOIN exams e ON oa.exam_id = e.id AND e.deleted_at IS NULL
    WHERE oa.student_id = ? AND oa.finished_at IS NOT NULL
    ORDER BY e.id, oa.finished_at DESC
  ");
  $objective->execute([$sid]);
  $objective_rows = $objective->fetchAll(PDO::FETCH_ASSOC);

  // Accumulate objective scores per exam per student per grading method
  $objective_exams = [];
  $exam_groups = [];
  foreach ($objective_rows as $row) {
    $eid = $row['id'];
    if (!isset($exam_groups[$eid])) {
      $exam_groups[$eid] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'type' => $row['type'],
        'created_at' => $row['created_at'],
        'grading_method' => $row['grading_method'],
        'attempts' => []
      ];
    }
    $exam_groups[$eid]['attempts'][] = [
      'score' => $row['score'],
      'attempt_no' => $row['attempt_no'],
      'finished_at' => $row['finished_at']
    ];
  }
  foreach ($exam_groups as $eid => $eg) {
    if ($eg['grading_method'] === 'average') {
      $scores = array_column($eg['attempts'], 'score');
      $final_score = count($scores) > 0 ? array_sum($scores) / count($scores) : 0;
    } else {
      // 'highest' (default) — take the highest score
      $final_score = max(array_column($eg['attempts'], 'score'));
    }
    $objective_exams[] = [
      'id' => $eg['id'],
      'name' => $eg['name'],
      'type' => $eg['type'],
      'created_at' => $eg['created_at'],
      'total_score' => round($final_score, 1),
      'attempts_count' => count($eg['attempts']),
      'grading_method' => $eg['grading_method']
    ];
  }
  usort($objective_exams, function($a, $b) { return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });

  // All exams the student is assigned to
  $assigned = $db->prepare("
    SELECT e.id, e.name, e.type, e.is_active, e.created_at
    FROM exam_students es
    JOIN exams e ON es.exam_id = e.id AND e.deleted_at IS NULL
    WHERE es.student_id = ?
    ORDER BY e.created_at DESC
  ");
  $assigned->execute([$sid]);
  $assigned_exams = $assigned->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode([
    'student' => $student_data,
    'practical' => $practical_exams,
    'viva' => $viva_exams,
    'objective' => $objective_exams,
    'assigned' => $assigned_exams
  ]);
  exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add'])) {
    $full_name = trim($_POST['firstname'] . ' ' . $_POST['middlename'] . ' ' . $_POST['lastname']);
    $full_name = str_replace('  ', ' ', $full_name);
    $stmt = $db->prepare("INSERT INTO students (name, firstname, middlename, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $_POST['firstname'], $_POST['middlename'], $_POST['lastname'], $_POST['email'], $_POST['matric_no'], $_POST['matric_no'], $_POST['password'] ?: '12345']);
    $success = "Student added successfully!";
  } elseif (isset($_POST['delete'])) {
    $stmt = $db->prepare("UPDATE students SET deleted_at=NOW() WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success = "Student deleted successfully!";
  } elseif (isset($_POST['edit'])) {
    $full_name = trim($_POST['firstname'] . ' ' . ($_POST['middlename'] ?? '') . ' ' . $_POST['lastname']);
    $full_name = str_replace('  ', ' ', $full_name);
    $stmt = $db->prepare("UPDATE students SET name = ?, firstname = ?, middlename = ?, lastname = ?, email = ?, matric_no = ?, username = ? WHERE id = ?");
    $stmt->execute([$full_name, $_POST['firstname'], $_POST['middlename'], $_POST['lastname'], $_POST['email'], $_POST['matric_no'], $_POST['matric_no'], $_POST['id']]);
    $success = "Student updated successfully!";
  } elseif (isset($_POST['reset_password'])) {
    $stmt = $db->prepare("UPDATE students SET password = '12345' WHERE id = ?");
    $stmt->execute([$_POST['id']]);
    $success = "Password reset to default (12345) successfully!";
  } elseif (isset($_POST['enroll']) && isset($_POST['student_id'])) {
    $sid = intval($_POST['student_id']);
    $eids = isset($_POST['exam_ids']) ? (array) $_POST['exam_ids'] : [];
    if (!empty($eids)) {
      foreach ($eids as $eid) {
        $eid = intval($eid);
        $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")->execute([$eid, $sid]);
      }
      $success = "Student enrolled in " . count($eids) . " exams successfully!";
    }
  }
}

// Paginated students list — avoid loading 3500+ rows at once
$per_page = 50;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;
$search = trim($_GET['search'] ?? '');

if ($search) {
    $search_term = "%{$search}%";
    $total_students = $db->prepare("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND (name LIKE ? OR matric_no LIKE ? OR email LIKE ?)");
    $total_students->execute([$search_term, $search_term, $search_term]);
    $total_students = $total_students->fetchColumn();
    $stmt = $db->prepare("SELECT id, name, firstname, middlename, lastname, email, matric_no, source, external_id FROM students WHERE deleted_at IS NULL AND (name LIKE ? OR matric_no LIKE ? OR email LIKE ?) ORDER BY name ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $search_term, PDO::PARAM_STR);
    $stmt->bindValue(2, $search_term, PDO::PARAM_STR);
    $stmt->bindValue(3, $search_term, PDO::PARAM_STR);
    $stmt->bindValue(4, (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(5, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $total_students = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
    $stmt = $db->prepare("SELECT id, name, firstname, middlename, lastname, email, matric_no, source, external_id FROM students WHERE deleted_at IS NULL ORDER BY name ASC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, (int)$per_page, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$total_pages = ceil($total_students / $per_page);
// Get all exams for enrollment modal
$all_exams = $db->query("SELECT id, name, type FROM exams WHERE deleted_at IS NULL ORDER BY type DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Students - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
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
          <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <a href="admin_dashboard" class="admin-back-btn" aria-label="Go back">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Manage Students</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Students</li>
        </ol>
      </nav>

      <?php if (isset($success)): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($success) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Add Student Form -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title"><i class="bi bi-person-plus"></i> Add New Student</h3>
        </div>
        <div class="admin-card-body">
          <form method="post" class="row g-3">
            <div class="col-md-3">
              <label for="firstname" class="admin-form-label">First Name</label>
              <input type="text" class="admin-input" id="firstname" name="firstname" required>
            </div>
            <div class="col-md-3">
              <label for="middlename" class="admin-form-label">Middle Name</label>
              <input type="text" class="admin-input" id="middlename" name="middlename">
            </div>
            <div class="col-md-3">
              <label for="lastname" class="admin-form-label">Last Name</label>
              <input type="text" class="admin-input" id="lastname" name="lastname" required>
            </div>
            <div class="col-md-3">
              <label for="email" class="admin-form-label">Email</label>
              <input type="email" class="admin-input" id="email" name="email">
            </div>
            <div class="col-md-3">
              <label for="matric_no" class="admin-form-label">Matric No</label>
              <input type="text" class="admin-input" id="matric_no" name="matric_no" required>
            </div>
            <div class="col-md-3 d-flex align-items-end">
              <button type="submit" name="add" class="admin-btn admin-btn-primary w-100">
                <i class="bi bi-plus-lg"></i> Add Student
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Import from School Portal (exeat2) -->
      <div class="admin-card mb-3">
        <div class="admin-section-toggle p-4 pb-0" data-bs-toggle="collapse" data-bs-target="#exeat2ImportSection" aria-expanded="false">
          <h3 class="admin-card-title mb-0 outfit text-success"><i class="bi bi-database"></i> Import from School Portal (exeat2)</h3>
          <i class="bi bi-chevron-down ms-auto admin-section-chevron"></i>
        </div>
        <div class="collapse" id="exeat2ImportSection">
          <div class="p-4 pt-3">
            <?php if (!$exeat2_db): ?>
              <div class="admin-alert admin-alert-warning">
                <i class="bi bi-exclamation-triangle"></i>
                <span><?= htmlspecialchars($exeat2_error ?: 'School Portal database not reachable.') ?></span>
              </div>
            <?php else: ?>
              <!-- Filters -->
              <div class="d-flex align-items-center gap-3 flex-wrap mb-3">
                <span class="fw-semibold small text-secondary">FILTERS:</span>
                <select id="exeat2Dept" class="admin-filter-input" style="width:auto;min-width:200px">
                  <option value="">All Departments</option>
                  <?php foreach ($exeat2_departments as $d): ?>
                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                  <?php endforeach; ?>
                </select>
                <select id="exeat2Level" class="admin-filter-input" style="width:auto;min-width:120px">
                  <option value="">All Levels</option>
                  <?php foreach ($exeat2_levels as $l): ?>
                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['level']) ?></option>
                  <?php endforeach; ?>
                </select>
                <select id="exeat2CourseStudy" class="admin-filter-input" style="width:auto;min-width:200px">
                  <option value="">All Course Studies</option>
                </select>
                <button type="button" id="exeat2RefreshBtn" class="admin-btn admin-btn-primary admin-btn-sm">
                  <i class="bi bi-arrow-repeat"></i> Load Students
                </button>
                <span id="exeat2LoadMsg" class="small text-secondary d-none"><i class="bi bi-hourglass-split"></i> Loading...</span>
              </div>

              <!-- Results -->
              <div id="exeat2Empty" class="admin-empty d-none">
                <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                <div class="admin-empty-title">No Students Loaded</div>
                <div class="admin-empty-desc">Select filters above and click Load Students to fetch students from the school portal.</div>
              </div>
              <div id="exeat2Error" class="admin-empty d-none">
                <div class="admin-empty-icon"><i class="bi bi-exclamation-triangle text-danger"></i></div>
                <div class="admin-empty-title">Error Loading Students</div>
                <div id="exeat2ErrorMsg" class="admin-empty-desc"></div>
              </div>
              <div id="exeat2TableWrapper" class="table-responsive d-none">
                <table class="admin-table" id="exeat2Table">
                  <thead>
                    <tr>
                      <th width="40"><input type="checkbox" id="exeat2MasterCb" class="form-check-input"></th>
                      <th>Matric No</th>
                      <th>Name</th>
                      <th>Email</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody id="exeat2TableBody"></tbody>
                </table>
              </div>
              <div class="d-flex flex-wrap gap-2 align-items-center mt-3 <?= $exeat2_db ? '' : 'd-none' ?>" id="exeat2Actions">
                <button type="button" id="exeat2ImportSelectedBtn" class="admin-btn admin-btn-primary admin-btn-sm" disabled>
                  <i class="bi bi-person-check"></i> Import Selected
                </button>
                <button type="button" id="exeat2ImportAllBtn" class="admin-btn admin-btn-success admin-btn-sm" disabled>
                  <i class="bi bi-person-plus-fill"></i> Import All
                </button>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Students List -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title"><i class="bi bi-people"></i> Students List</h3>
          <div class="d-flex align-items-center gap-2">
            <span class="admin-badge admin-badge-primary"><?= $search ? count($students).' matched' : $total_students.' total' ?></span>
            <input type="text" id="studentSearch" class="admin-filter-input admin-filter-input-wide" placeholder="Search students..." oninput="filterStudents()" value="<?= htmlspecialchars($search) ?>">
          </div>
        </div>
        <div class="admin-card-body-flush">
          <?php if (empty($students)): ?>
            <div class="admin-empty">
              <div class="admin-empty-icon"><i class="bi bi-people admin-empty-icon-lg"></i></div>
              <div class="admin-empty-title">No students yet</div>
              <div class="admin-empty-desc">Add your first student using the form above.</div>
            </div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="admin-table" id="studentsTable">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Matric No</th>
                    <th>Email</th>
                    <th class="actions-cell">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($students as $student): ?>
                    <tr>
                      <td class="text-center" style="width:42px">
                        <img src="passport_image.php?type=student&id=<?= $student['id'] ?>"
                             class="rounded-circle" style="width:32px;height:32px;object-fit:cover"
                             alt="" onerror="this.style.display='none'">
                      </td>
                      <td><a href="#" class="text-decoration-none fw-medium student-name-link" data-student-id="<?= $student['id'] ?>"><?= htmlspecialchars($student['name']) ?></a></td>
                      <td><?= htmlspecialchars($student['matric_no']) ?></td>
                      <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                      <td class="actions-cell">
                        <button class="admin-btn admin-btn-sm admin-btn-outline"
                          onclick="editStudent(<?= $student['id'] ?>, '<?= htmlspecialchars($student['firstname'] ?? '') ?>', '<?= htmlspecialchars($student['middlename'] ?? '') ?>', '<?= htmlspecialchars($student['lastname'] ?? '') ?>', '<?= htmlspecialchars($student['email'] ?? '') ?>', '<?= htmlspecialchars($student['matric_no']) ?>')">
                          <i class="bi bi-pencil"></i> Edit
                        </button>
                        <button class="admin-btn admin-btn-sm admin-btn-primary"
                          onclick="openEnrollModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['name']) ?>')">
                          <i class="bi bi-bookmark-plus"></i> Enroll
                        </button>
                        <form method="post" class="d-inline" data-swal="Reset this student password to default (12345)?">
                          <input type="hidden" name="id" value="<?= $student['id'] ?>">
                          <button type="submit" name="reset_password" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset PW</button>
                        </form>
                        <form method="post" class="d-inline" data-swal="Are you sure you want to delete this student?">
                          <input type="hidden" name="id" value="<?= $student['id'] ?>">
                          <button type="submit" name="delete" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php if ($total_pages > 1): ?>
            <nav class="d-flex align-items-center justify-content-center gap-3 my-3 px-3">
              <span class="small text-muted">Page <?= $page ?> of <?= $total_pages ?></span>
              <div class="d-flex gap-1">
                <?php if ($page > 1): ?>
                <a href="?page=<?= $page-1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="admin-btn admin-btn-sm admin-btn-outline">‹ Prev</a>
                <?php endif; ?>
                <?php
                  $range_start = max(1, $page - 2);
                  $range_end = min($total_pages, $page + 2);
                  for ($i = $range_start; $i <= $range_end; $i++):
                ?>
                  <?php if ($i === $page): ?>
                    <span class="admin-btn admin-btn-sm admin-btn-primary" style="cursor:default"><?= $i ?></span>
                  <?php else: ?>
                    <a href="?page=<?= $i ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="admin-btn admin-btn-sm admin-btn-outline"><?= $i ?></a>
                  <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page+1 ?><?= $search ? '&search='.urlencode($search) : '' ?>" class="admin-btn admin-btn-sm admin-btn-outline">Next ›</a>
                <?php endif; ?>
              </div>
            </nav>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label for="edit_firstname" class="admin-form-label">First Name</label>
                  <input type="text" class="admin-input" id="edit_firstname" name="firstname" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label for="edit_middlename" class="admin-form-label">Middle Name</label>
                  <input type="text" class="admin-input" id="edit_middlename" name="middlename">
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label for="edit_lastname" class="admin-form-label">Last Name</label>
                  <input type="text" class="admin-input" id="edit_lastname" name="lastname" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label for="edit_email" class="admin-form-label">Email</label>
                  <input type="email" class="admin-input" id="edit_email" name="email">
                </div>
              </div>
              <div class="col-md-6">
                <div class="admin-form-group">
                  <label for="edit_matric_no" class="admin-form-label">Matric Number</label>
                  <input type="text" class="admin-input" id="edit_matric_no" name="matric_no" required>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i> Update Student</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Enrollment Modal -->
  <div class="modal fade" id="enrollModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Enroll <span id="enroll_student_name" class="text-primary"></span> in Exams</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="student_id" id="enroll_student_id">
            <label class="admin-form-label">Select Exams (Multi-select)</label>
            <select name="exam_ids[]" class="admin-input admin-select" multiple size="10" required>
              <?php foreach ($all_exams as $ex): ?>
                <option value="<?= $ex['id'] ?>">
                  <?= strtoupper($ex['type'] ?? 'PRACTICAL') ?>: <?= htmlspecialchars($ex['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="admin-dropzone-hint mt-2">Hold Ctrl (Cmd) to select multiple exams.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="enroll" class="admin-btn admin-btn-primary"><i class="bi bi-bookmark-check"></i> Enroll Now</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Import Result Modal -->
  <div class="modal fade" id="exeat2ImportResultModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-check-circle text-success"></i> Import Complete</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="exeat2ImportResultBody"></div>
        <div class="modal-footer">
          <button type="button" class="admin-btn admin-btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Student Detail Modal -->
  <div class="modal fade" id="studentDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="detailModalTitle">Student Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="detailModalBody">
          <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    function filterStudents() {
      var input = document.getElementById('studentSearch');
      var filter = input.value.toLowerCase();
      var table = document.getElementById('studentsTable');
      if (!table) return;
      var tr = table.getElementsByTagName('tr');
      for (var i = 1; i < tr.length; i++) {
        var tdName = tr[i].getElementsByTagName('td')[1];
        var tdMatric = tr[i].getElementsByTagName('td')[2];
        if (tdName || tdMatric) {
          var txt = (tdName ? tdName.textContent : '') + ' ' + (tdMatric ? tdMatric.textContent : '');
          tr[i].style.display = txt.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
        }
      }
    }

    document.getElementById('studentSearch').addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var q = this.value.trim();
        var url = 'admin_students?page=1' + (q ? '&search=' + encodeURIComponent(q) : '');
        window.location.href = url;
      }
    });

    // If there's a search term, put cursor in search input on load
    <?php if ($search): ?>
    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('studentSearch').focus();
      document.getElementById('studentSearch').setSelectionRange(document.getElementById('studentSearch').value.length, document.getElementById('studentSearch').value.length);
    });
    <?php endif; ?>

    function editStudent(id, fname, mname, lname, email, matric) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_firstname').value = fname;
      document.getElementById('edit_middlename').value = mname;
      document.getElementById('edit_lastname').value = lname;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_matric_no').value = matric;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    function openEnrollModal(id, name) {
      document.getElementById('enroll_student_id').value = id;
      document.getElementById('enroll_student_name').textContent = name;
      new bootstrap.Modal(document.getElementById('enrollModal')).show();
    }

    // Click student name to show details
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.student-name-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          var sid = this.getAttribute('data-student-id');
          var modal = new bootstrap.Modal(document.getElementById('studentDetailModal'));
          document.getElementById('detailModalTitle').textContent = 'Loading...';
          document.getElementById('detailModalBody').innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
          modal.show();
          fetch('admin_students?action=get_student_exams&student_id=' + sid)
            .then(function(r) { return r.json(); })
            .then(function(data) {
              if (data.error) {
                document.getElementById('detailModalBody').innerHTML = '<div class="admin-alert admin-alert-danger">' + data.error + '</div>';
                return;
              }
              var s = data.student;
              document.getElementById('detailModalTitle').textContent = s.name;
              var html = '<div class="text-center mb-3">' +
                  '<img src="passport_image.php?type=student&id=' + s.id + '" class="rounded-circle border" style="width:80px;height:80px;object-fit:cover" alt="Photo" onerror="this.style.display=\'none\'">' +
                '</div>' +
                '<div class="row mb-4">' +
                '<div class="col-md-6">' +
                  '<div class="admin-card border"><div class="admin-card-body">' +
                    '<h6 class="fw-bold mb-3"><i class="bi bi-person-vcard"></i> Student Information</h6>' +
                    '<table class="table table-sm mb-0">' +
                    '<tr><td class="fw-semibold text-muted" style="width:120px">Name</td><td>' + escHtml(s.name) + '</td></tr>' +
                    '<tr><td class="fw-semibold text-muted">Matric</td><td>' + escHtml(s.matric_no) + '</td></tr>' +
                    '<tr><td class="fw-semibold text-muted">Email</td><td>' + escHtml(s.email || '-') + '</td></tr>' +
                    '<tr><td class="fw-semibold text-muted">Username</td><td>' + escHtml(s.username) + '</td></tr>' +
                    '</table>' +
                  '</div></div>' +
                '</div>' +
                '<div class="col-md-6">' +
                  '<div class="admin-card border"><div class="admin-card-body">' +
                    '<h6 class="fw-bold mb-3"><i class="bi bi-bookmark-check"></i> Assigned Exams (' + data.assigned.length + ')</h6>' +
                    '<div style="max-height:200px;overflow-y:auto">' +
                    (data.assigned.length ? '<table class="table table-sm mb-0"><thead><tr><th>Exam</th><th>Type</th><th>Status</th></tr></thead><tbody>' +
                    data.assigned.map(function(e) {
                      return '<tr><td>' + escHtml(e.name) + '</td><td><span class="admin-badge admin-badge-' + (e.type==='objective'?'info':e.type==='viva'?'warning':'primary') + '">' + e.type.toUpperCase() + '</span></td><td>' + (e.is_active ? '<span class="admin-badge admin-badge-success">Active</span>' : '<span class="admin-badge admin-badge-neutral">Inactive</span>') + '</td></tr>';
                    }).join('') : '<p class="text-muted mb-0">No exams assigned</p>') +
                    '</tbody></table></div>' +
                  '</div></div>' +
                '</div>' +
              '</div>';

              var exams = [].concat(data.practical || []).concat(data.viva || []);
              if (data.objective) exams = exams.concat(data.objective);

              if (exams.length) {
                html += '<div class="admin-card border"><div class="admin-card-header"><h6 class="fw-bold mb-0"><i class="bi bi-trophy"></i> Exam History (' + exams.length + ')</h6></div><div class="admin-card-body-flush"><div class="table-responsive"><table class="admin-table"><thead><tr><th>Exam</th><th>Type</th><th>Score</th><th>Info</th></tr></thead><tbody>';
                exams.forEach(function(ex) {
                  var score = ex.total_score !== null && ex.total_score !== undefined ? parseFloat(ex.total_score).toFixed(1) : '-';
                  var info = '';
                  if (ex.attempts_count) {
                    info = ex.attempts_count + ' attempt(s)';
                  } else if (ex.questions_graded) {
                    info = ex.questions_graded + ' question(s)';
                  }
                  html += '<tr><td>' + escHtml(ex.name) + '</td><td><span class="admin-badge admin-badge-' + (ex.type==='objective'?'info':ex.type==='viva'?'warning':'primary') + '">' + ex.type.toUpperCase() + '</span></td><td class="fw-bold text-success">' + score + '</td><td class="small text-muted">' + info + '</td></tr>';
                });
                html += '</tbody></table></div></div></div>';
              } else {
                html += '<div class="admin-alert admin-alert-warning"><i class="bi bi-info-circle"></i> <span>No exam participation history found.</span></div>';
              }

              document.getElementById('detailModalBody').innerHTML = html;
            })
            .catch(function() {
              document.getElementById('detailModalBody').innerHTML = '<div class="admin-alert admin-alert-danger">Failed to load student details.</div>';
            });
        });
      });
    });

    // ─── exeat2: course study cascading dropdown ───
    var exeat2Dept = document.getElementById('exeat2Dept');
    if (exeat2Dept) {
      exeat2Dept.addEventListener('change', function () {
        var deptId = this.value;
        var csSelect = document.getElementById('exeat2CourseStudy');
        csSelect.innerHTML = '<option value="">All Course Studies</option>';
        if (!deptId) return;
        fetch('includes/fetch_exeat2_course_studies.php?department_id=' + deptId)
          .then(function (r) { return r.json(); })
          .then(function (data) {
            data.forEach(function (cs) {
              var opt = document.createElement('option');
              opt.value = cs.id;
              opt.textContent = cs.name;
              csSelect.appendChild(opt);
            });
          })
          .catch(function () {});
      });
    }

    // ─── exeat2: fetch and render students ───
    var exeat2Students = [];

    document.getElementById('exeat2RefreshBtn').addEventListener('click', function () {
      var dept = document.getElementById('exeat2Dept').value;
      var level = document.getElementById('exeat2Level').value;
      var courseStudyId = document.getElementById('exeat2CourseStudy').value;

      if (!dept && !level) {
        Swal.fire({ title: 'Filter Required', text: 'Please select at least a Department or Level to narrow results.', icon: 'warning', confirmButtonColor: '#198754' });
        return;
      }

      var url = 'includes/fetch_exeat2_students.php';
      if (dept) url += '?department_id=' + dept;
      if (level) url += (dept ? '&' : '?') + 'level_id=' + level;
      if (courseStudyId) url += (dept || level ? '&' : '?') + 'course_study_id=' + courseStudyId;

      var msgEl = document.getElementById('exeat2LoadMsg');
      msgEl.classList.remove('d-none');

      fetch(url)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          msgEl.classList.add('d-none');
          if (data.error) {
            showExeat2Error(data.error);
            return;
          }
          exeat2Students = data;
          renderExeat2Table(data);
        })
        .catch(function (err) {
          msgEl.classList.add('d-none');
          showExeat2Error('Failed to fetch students: ' + err.message);
        });
    });

    function showExeat2Error(msg) {
      document.getElementById('exeat2Empty').classList.add('d-none');
      document.getElementById('exeat2TableWrapper').classList.add('d-none');
      document.getElementById('exeat2ErrorMsg').textContent = msg;
      document.getElementById('exeat2Error').classList.remove('d-none');
      document.getElementById('exeat2ImportSelectedBtn').disabled = true;
      document.getElementById('exeat2ImportAllBtn').disabled = true;
    }

    function renderExeat2Table(data) {
      document.getElementById('exeat2Error').classList.add('d-none');
      var tbody = document.getElementById('exeat2TableBody');
      tbody.innerHTML = '';

      if (!data || data.length === 0) {
        document.getElementById('exeat2Empty').classList.remove('d-none');
        document.getElementById('exeat2TableWrapper').classList.add('d-none');
        document.getElementById('exeat2ImportSelectedBtn').disabled = true;
        document.getElementById('exeat2ImportAllBtn').disabled = true;
        return;
      }

      document.getElementById('exeat2Empty').classList.add('d-none');
      document.getElementById('exeat2TableWrapper').classList.remove('d-none');
      document.getElementById('exeat2ImportSelectedBtn').disabled = false;
      document.getElementById('exeat2ImportAllBtn').disabled = false;

      var statusLabels = {
        'new': '<span class="admin-badge admin-badge-primary"><i class="bi bi-plus-circle"></i> New</span>',
        'exists_exeat2': '<span class="admin-badge admin-badge-info"><i class="bi bi-arrow-repeat"></i> Exists (exeat2)</span>',
        'exists_upload': '<span class="admin-badge admin-badge-warning"><i class="bi bi-exclamation-circle"></i> Exists (upload)</span>',
        'exists_manual': '<span class="admin-badge admin-badge-warning"><i class="bi bi-exclamation-circle"></i> Exists (manual)</span>',
        'assigned': '<span class="admin-badge admin-badge-success"><i class="bi bi-check-circle"></i> Already Assigned</span>'
      };

      data.forEach(function (stu) {
        var tr = document.createElement('tr');
        var label = statusLabels[stu.status] || '<span class="admin-badge admin-badge-neutral">Unknown</span>';
        var canSelect = (stu.status !== 'assigned');
        tr.innerHTML =
          '<td><input type="checkbox" class="exeat2-checkbox form-check-input" value="' + stu.exeat2_id + '"' + (canSelect ? '' : ' disabled') + '></td>' +
          '<td>' + escHtml(stu.matric_no) + '</td>' +
          '<td>' + escHtml(stu.name) + '</td>' +
          '<td>' + escHtml(stu.email || '') + '</td>' +
          '<td>' + label + '</td>';
        tbody.appendChild(tr);
      });
    }

    // ─── exeat2 master checkbox ───
    var exeat2MasterCb = document.getElementById('exeat2MasterCb');
    if (exeat2MasterCb) {
      exeat2MasterCb.addEventListener('change', function () {
        document.querySelectorAll('.exeat2-checkbox:not(:disabled)').forEach(function (cb) {
          cb.checked = this.checked;
        }.bind(this));
      });
    }

    // ─── exeat2: Import Selected ───
    document.getElementById('exeat2ImportSelectedBtn').addEventListener('click', function () {
      var checked = document.querySelectorAll('.exeat2-checkbox:checked');
      if (checked.length === 0) {
        Swal.fire({ title: 'No Selection', text: 'Select at least one student.', icon: 'warning', confirmButtonColor: '#198754' });
        return;
      }
      var ids = [];
      checked.forEach(function (cb) { ids.push(parseInt(cb.value)); });
      doExeat2Import(ids);
    });

    // ─── exeat2: Import All ───
    document.getElementById('exeat2ImportAllBtn').addEventListener('click', function () {
      var ids = exeat2Students.map(function (s) { return s.exeat2_id; });
      Swal.fire({
        title: 'Import All?',
        text: 'This will import ' + ids.length + ' student(s) from the school portal.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, import all'
      }).then(function (result) {
        if (result.isConfirmed) doExeat2Import(ids);
      });
    });

    function doExeat2Import(ids) {
      var btn = document.getElementById('exeat2ImportSelectedBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="btn-spinner"></span> Importing...';
      document.getElementById('exeat2ImportAllBtn').disabled = true;

      fetch('includes/import_exeat2_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'student_ids=' + encodeURIComponent(JSON.stringify(ids))
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
        document.getElementById('exeat2ImportAllBtn').disabled = false;

        var html = '';
        html += '<div class="mb-3">';
        html += '<div class="d-flex justify-content-around text-center">';
        html += '<div><span class="fs-4 text-success">' + res.imported + '</span><br><small>Newly Imported</small></div>';
        html += '<div><span class="fs-4 text-info">' + res.updated + '</span><br><small>Updated</small></div>';
        html += '<div><span class="fs-4 text-warning">' + res.skipped_upload + '</span><br><small>Skipped (upload)</small></div>';
        html += '<div><span class="fs-4 text-warning">' + res.skipped_manual + '</span><br><small>Skipped (manual)</small></div>';
        html += '</div></div>';
        html += '<p class="text-center fw-bold">Total: ' + res.total + ' students processed</p>';

        if (res.details && res.details.length > 0) {
          html += '<hr><ul class="small" style="max-height:200px;overflow-y:auto">';
          res.details.forEach(function (d) {
            var icon = d.status === 'imported' ? '&#10004;' : d.status === 'updated' ? '&#128260;' : '&#9201;';
            html += '<li>' + icon + ' ' + escHtml(d.name) + ' (' + escHtml(d.matric_no) + ') — ' + d.status + '</li>';
          });
          html += '</ul>';
        }

        document.getElementById('exeat2ImportResultBody').innerHTML = html;
        var modal = new bootstrap.Modal(document.getElementById('exeat2ImportResultModal'));
        modal.show();

        // Reload the student list to reflect new imports
        window.location.href = 'admin_students';
      })
      .catch(function (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
        document.getElementById('exeat2ImportAllBtn').disabled = false;
        Swal.fire({ title: 'Import Error', text: err.message, icon: 'error', confirmButtonColor: '#198754' });
      });
    }

    function escHtml(str) {
      if (!str) return '';
      var d = document.createElement('div');
      d.textContent = str;
      return d.innerHTML;
    }
  </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
