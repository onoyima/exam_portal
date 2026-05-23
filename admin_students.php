<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

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

// Get all students
$students = $db->query("SELECT * FROM students WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
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

      <!-- Students List -->
      <div class="admin-card">
        <div class="admin-card-header">
          <h3 class="admin-card-title"><i class="bi bi-people"></i> Students List</h3>
          <div class="d-flex align-items-center gap-2">
            <span class="admin-badge admin-badge-primary"><?= count($students) ?> total</span>
            <input type="text" id="studentSearch" class="admin-filter-input admin-filter-input-wide" placeholder="Search students..." oninput="filterStudents()">
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
        var tdName = tr[i].getElementsByTagName('td')[0];
        var tdMatric = tr[i].getElementsByTagName('td')[1];
        if (tdName || tdMatric) {
          var txt = (tdName ? tdName.textContent : '') + ' ' + (tdMatric ? tdMatric.textContent : '');
          tr[i].style.display = txt.toLowerCase().indexOf(filter) > -1 ? '' : 'none';
        }
      }
    }

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
              var html = '<div class="row mb-4">' +
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
