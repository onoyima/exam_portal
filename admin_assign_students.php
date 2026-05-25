<?php
require 'db.php';
require 'db_exeat2.php';
require 'auth.php';
check_auth('admin');
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id)
  die('No exam selected.');
$exam = $db->prepare("SELECT * FROM exams WHERE id=? AND deleted_at IS NULL");
$exam->execute([$exam_id]);
$exam = $exam->fetch(PDO::FETCH_ASSOC);
if (!$exam)
  die('Invalid exam.');

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['bulk_action']) && isset($_POST['student_ids'])) {
    $sids = $_POST['student_ids'];
    if ($_POST['bulk_action'] === 'assign') {
      foreach ($sids as $sid) {
        $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")->execute([$exam_id, intval($sid)]);
      }
      $msg = "assigned";
    } else {
      foreach ($sids as $sid) {
        $db->prepare("DELETE FROM exam_students WHERE exam_id=? AND student_id=?")->execute([$exam_id, intval($sid)]);
      }
      $msg = "removed";
    }
    header("Location: admin_assign_students?exam_id=$exam_id&success=$msg");
    exit;
  }
  if (isset($_POST['remove']) && isset($_POST['student_id'])) {
    $sid = intval($_POST['student_id']);
    $db->prepare("DELETE FROM exam_students WHERE exam_id=? AND student_id=?")->execute([$exam_id, $sid]);
    header("Location: admin_assign_students?exam_id=$exam_id&success=removed");
    exit;
  }
  if (isset($_POST['clear_all'])) {
    $db->prepare("DELETE FROM exam_students WHERE exam_id=?")->execute([$exam_id]);
    header("Location: admin_assign_students?exam_id=$exam_id&success=cleared");
    exit;
  }
  if (isset($_POST['assign_all_system'])) {
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE deleted_at IS NULL")->execute([$exam_id]);
    header("Location: admin_assign_students?exam_id=$exam_id&success=all_assigned");
    exit;
  }
  if (isset($_POST['assign_by_email_domain']) && !empty($_POST['email_domain'])) {
    $domain = '%' . trim($_POST['email_domain']);
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE email LIKE ? AND deleted_at IS NULL")->execute([$exam_id, $domain]);
    header("Location: admin_assign_students?exam_id=$exam_id&success=domain_assigned");
    exit;
  }
}

// Sorting logic
$sort = $_GET['sort'] ?? 'name';
$dir = $_GET['dir'] ?? 'ASC';
$allowed_sort = ['name', 'matric_no', 'email'];
if (!in_array($sort, $allowed_sort))
  $sort = 'name';
$dir = (strtoupper($dir) === 'DESC') ? 'DESC' : 'ASC';
$next_dir = ($dir === 'ASC') ? 'DESC' : 'ASC';

// Fetch all students with sorting
$students = $db->query("SELECT * FROM students WHERE deleted_at IS NULL ORDER BY $sort $dir")->fetchAll(PDO::FETCH_ASSOC);
// Fetch assigned students
$assigned = $db->prepare("SELECT student_id FROM exam_students WHERE exam_id=?");
$assigned->execute([$exam_id]);
$assigned_ids = array_column($assigned->fetchAll(PDO::FETCH_ASSOC), 'student_id');
$name = $_SESSION['user'] ?? 'Admin';

// Fetch exeat2 filter options
$exeat2_departments = [];
$exeat2_levels = [];
$exeat2_course_studies = [];
if ($exeat2_db) {
  try {
    $exeat2_departments = $exeat2_db->query("SELECT id, name FROM departments WHERE name IS NOT NULL AND name != '' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $exeat2_levels = $exeat2_db->query("SELECT id, level FROM levels ORDER BY CAST(level AS UNSIGNED)")->fetchAll(PDO::FETCH_ASSOC);
    $exeat2_course_studies = $exeat2_db->query("SELECT id, name, department_id FROM course_studies ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
  } catch (Exception $e) {
    $exeat2_error = $e->getMessage();
    error_log("exeat2 query failed: " . $e->getMessage());
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Assign Students to Exam - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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
          <a href="admin_exams" class="admin-back-btn" aria-label="Back to Exams">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Assign Students</h1>
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

      <!-- Breadcrumb -->
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house-door"></i> Dashboard</a></li>
          <li class="sep">/</li>
          <li><a href="admin_exams">Exams</a></li>
          <li class="sep">/</li>
          <li class="current">Assign Students</li>
        </ol>
      </nav>

      <!-- Page Header -->
      <div class="admin-page-header">
        <h1 class="admin-page-title">
          <i class="bi bi-people"></i> <?= htmlspecialchars($exam['name']) ?>
          <span class="admin-badge admin-badge-info ms-2"><?= count($students) ?> students</span>
        </h1>
        <div class="admin-page-actions">
          <form method="post" data-swal="Note: This will enroll EVERY student in your database to this exam. Proceed?" class="d-inline">
            <button type="submit" name="assign_all_system" class="admin-btn admin-btn-primary">
              <i class="bi bi-person-plus-fill"></i> Enroll All
            </button>
          </form>
          <form method="post" class="d-inline-flex align-items-center gap-2" data-swal="Enroll all students with this email domain?">
            <input type="text" name="email_domain" class="admin-input" placeholder="@domain.com" required>
            <button type="submit" name="assign_by_email_domain" class="admin-btn admin-btn-primary">
              <i class="bi bi-globe"></i> Enroll by Domain
            </button>
          </form>
        </div>
      </div>

      <!-- Success Alert -->
      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle"></i>
          <span>
            <?php
            $msg = $_GET['success'];
            if ($msg == 'domain_assigned')
              echo "Successfully enrolled all matching domain students!";
            elseif ($msg == 'all_assigned')
              echo "Successfully enrolled EVERY student in the system!";
            elseif ($msg == 'cleared')
              echo "All assignments cleared for this exam.";
            else
              echo "Action successful.";
            ?>
          </span>
          <button class="admin-alert-close" data-bs-dismiss="alert">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Source Selector -->
      <div class="admin-filters mb-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <span class="fw-semibold small text-secondary">STUDENT SOURCE:</span>
          <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="source_selector" id="src_local" value="local" checked>
            <label class="btn btn-outline-primary" for="src_local">Exam Portal DB</label>
            <input type="radio" class="btn-check" name="source_selector" id="src_exeat2" value="exeat2" <?= $exeat2_db ? '' : 'disabled' ?>>
            <label class="btn btn-outline-primary" for="src_exeat2">School Portal (exeat2)</label>
            <a href="admin_upload" class="btn btn-outline-primary btn-sm">Upload CSV</a>
          </div>
          <?php if (!$exeat2_db): ?>
            <span class="small text-danger"><i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($exeat2_error ?: 'School Portal DB not reachable') ?></span>
          <?php endif; ?>
        </div>
      </div>

      <!-- exeat2 Filters (hidden by default) -->
      <div id="exeat2Filters" class="admin-filters mb-3 d-none">
        <div class="d-flex align-items-center gap-3 flex-wrap">
          <span class="fw-semibold small text-secondary">FILTERS:</span>
          <select id="filterDepartment" class="admin-filter-input" style="width:auto;min-width:200px">
            <option value="">All Departments</option>
            <?php foreach ($exeat2_departments as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="filterLevel" class="admin-filter-input" style="width:auto;min-width:120px">
            <option value="">All Levels</option>
            <?php foreach ($exeat2_levels as $l): ?>
              <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['level']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="filterCourseStudy" class="admin-filter-input" style="width:auto;min-width:200px">
            <option value="">All Course Studies</option>
          </select>
          <button type="button" id="refreshExeat2" class="admin-btn admin-btn-primary admin-btn-sm">
            <i class="bi bi-arrow-repeat"></i> Refresh
          </button>
          <span id="exeat2LoadMsg" class="small text-secondary d-none"><i class="bi bi-hourglass-split"></i> Loading...</span>
        </div>
      </div>

      <!-- Local Portal Search Filter -->
      <div id="localSearchFilter" class="admin-filters">
        <div class="admin-filter-group">
          <i class="bi bi-search admin-filter-icon"></i>
          <input type="text" id="tableSearch" class="admin-filter-input" placeholder="Search by name, matric, or email...">
        </div>
      </div>

      <!-- Bulk Form (local DB) -->
      <form method="post" id="bulkForm">
        <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
        <div class="admin-card">
          <div class="admin-card-header">
            <div class="d-flex align-items-center gap-3">
              <h2 class="admin-card-title mb-0"><i class="bi bi-table"></i> Students</h2>
              <span class="admin-badge admin-badge-neutral"><?= count($students) ?> total</span>
            </div>
            <span id="selectedCount" class="admin-badge admin-badge-info d-none">
              <i class="bi bi-check-circle"></i> <span id="selectedCountText">0</span> selected
            </span>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table" id="studentTable">
                <thead>
                  <tr>
                    <th width="40"><input type="checkbox" id="masterCheckbox" class="form-check-input"></th>
                    <th><a href="?exam_id=<?= $exam_id ?>&sort=name&dir=<?= $next_dir ?>" class="text-decoration-none text-dark d-flex align-items-center gap-1">Name <?= $sort === 'name' ? ($dir === 'ASC' ? '<i class="bi bi-sort-up"></i>' : '<i class="bi bi-sort-down"></i>') : '' ?></a></th>
                    <th><a href="?exam_id=<?= $exam_id ?>&sort=matric_no&dir=<?= $next_dir ?>" class="text-decoration-none text-dark d-flex align-items-center gap-1">Matric No <?= $sort === 'matric_no' ? ($dir === 'ASC' ? '<i class="bi bi-sort-up"></i>' : '<i class="bi bi-sort-down"></i>') : '' ?></a></th>
                    <th><a href="?exam_id=<?= $exam_id ?>&sort=email&dir=<?= $next_dir ?>" class="text-decoration-none text-dark d-flex align-items-center gap-1">Email <?= $sort === 'email' ? ($dir === 'ASC' ? '<i class="bi bi-sort-up"></i>' : '<i class="bi bi-sort-down"></i>') : '' ?></a></th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($students)): ?>
                    <tr>
                      <td colspan="5">
                        <div class="admin-empty">
                          <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                          <div class="admin-empty-title">No Students Found</div>
                          <div class="admin-empty-desc">There are no students in the system to display.</div>
                        </div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                      <tr>
                        <td><input type="checkbox" name="student_ids[]" value="<?= $stu['id'] ?>" class="student-checkbox form-check-input"></td>
                        <td><?= htmlspecialchars($stu['name']) ?></td>
                        <td><?= htmlspecialchars($stu['matric_no']) ?></td>
                        <td><?= htmlspecialchars($stu['email'] ?? '') ?></td>
                        <td>
                          <?php if (in_array($stu['id'], $assigned_ids)): ?>
                            <span class="admin-badge admin-badge-success"><i class="bi bi-check-circle"></i> Assigned</span>
                          <?php else: ?>
                            <span class="admin-badge admin-badge-neutral"><i class="bi bi-x-circle"></i> Not Assigned</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 align-items-center p-3 border-top">
            <span class="fw-semibold small text-secondary me-2">BULK ACTIONS:</span>
            <button type="button" onclick="submitBulk('assign')" class="admin-btn admin-btn-primary admin-btn-sm" id="assignBtn">
              <i class="bi bi-person-check"></i> Assign Selected
            </button>
            <button type="button" onclick="submitBulk('remove')" class="admin-btn admin-btn-danger admin-btn-sm">
              <i class="bi bi-person-x"></i> Unassign Selected
            </button>
            <div class="ms-auto">
              <button type="submit" name="clear_all" class="admin-btn admin-btn-danger admin-btn-sm" data-swal="This will remove ALL student assignments from this exam. Proceed?">
                <i class="bi bi-trash"></i> Clear All
              </button>
            </div>
          </div>
        </div>
      </form>

      <!-- exeat2 Student Table (hidden by default) -->
      <div id="exeat2Section" class="d-none">
        <div class="admin-card mt-3">
          <div class="admin-card-header">
            <div class="d-flex align-items-center gap-3">
              <h2 class="admin-card-title mb-0"><i class="bi bi-database"></i> School Portal Students</h2>
              <span id="exeat2Count" class="admin-badge admin-badge-neutral d-none">0</span>
            </div>
          </div>
          <div class="admin-card-body-flush">
            <div id="exeat2Empty" class="admin-empty d-none">
              <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
              <div class="admin-empty-title">No Students Loaded</div>
              <div class="admin-empty-desc">Select filters above and click Refresh to fetch students from the school portal.</div>
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
                    <th width="40"><input type="checkbox" id="exeat2MasterCheckbox" class="form-check-input"></th>
                    <th>Matric No</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Dept</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody id="exeat2TableBody"></tbody>
              </table>
            </div>
          </div>
          <div class="d-flex flex-wrap gap-2 align-items-center p-3 border-top">
            <span class="fw-semibold small text-secondary me-2">EXEAT2 ACTIONS:</span>
            <button type="button" id="importSelectedBtn" class="admin-btn admin-btn-primary admin-btn-sm" disabled>
              <i class="bi bi-person-check"></i> Import Selected
            </button>
            <button type="button" id="importAllBtn" class="admin-btn admin-btn-success admin-btn-sm" disabled>
              <i class="bi bi-person-plus-fill"></i> Import All Matching
            </button>
          </div>
        </div>
      </div>

      <!-- Import Result Modal -->
      <div class="modal fade" id="importResultModal" tabindex="-1">
        <div class="modal-dialog">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="bi bi-check-circle text-success"></i> Import Complete</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="importResultBody"></div>
            <div class="modal-footer">
              <button type="button" class="admin-btn admin-btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    // ─── Source selector toggle ───
    document.querySelectorAll('input[name="source_selector"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        var showExeat2 = (this.value === 'exeat2');
        document.getElementById('localSearchFilter').classList.toggle('d-none', showExeat2);
        document.querySelector('#bulkForm').closest('.admin-card').classList.toggle('d-none', showExeat2);
        document.getElementById('exeat2Filters').classList.toggle('d-none', !showExeat2);
        document.getElementById('exeat2Section').classList.toggle('d-none', !showExeat2);
      });
    });

    // ─── Master checkbox toggle (local) ───
    document.getElementById('masterCheckbox').addEventListener('change', function () {
      var checkboxes = document.querySelectorAll('.student-checkbox');
      checkboxes.forEach(function (cb) { cb.checked = this.checked; }.bind(this));
      updateSelectedCount();
    });

    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('student-checkbox')) {
        updateSelectedCount();
      }
    });

    function updateSelectedCount() {
      var selected = document.querySelectorAll('.student-checkbox:checked').length;
      var el = document.getElementById('selectedCount');
      var txt = document.getElementById('selectedCountText');
      if (selected > 0) {
        el.style.display = 'inline-flex';
        txt.textContent = selected;
      } else {
        el.style.display = 'none';
      }
    }

    function submitBulk(action) {
      var selected = document.querySelectorAll('.student-checkbox:checked').length;
      if (selected === 0) {
        Swal.fire({
          title: 'No Selection',
          text: 'Please select at least one student first.',
          icon: 'warning',
          confirmButtonColor: '#198754'
        });
        return;
      }

      var msg = action === 'assign'
        ? 'Assign ' + selected + ' selected student(s) to this exam?'
        : 'Remove ' + selected + ' selected student(s) from this exam?';

      Swal.fire({
        title: 'Are you sure?',
        text: msg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, proceed'
      }).then(function (result) {
        if (result.isConfirmed) {
          var btn = action === 'assign' ? document.getElementById('assignBtn') : null;
          if (btn) {
            btn.disabled = true;
            btn.classList.add('btn-loading');
            btn.innerHTML = '<span class="btn-spinner"></span> Processing...';
          }
          document.getElementById('bulkActionInput').value = action;
          HTMLFormElement.prototype.submit.call(document.getElementById('bulkForm'));
        }
      });
    }

    // ─── Local table search filter ───
    document.getElementById('tableSearch').addEventListener('keyup', function () {
      var query = this.value.toLowerCase();
      var rows = document.querySelectorAll('#studentTable tbody tr');
      var visible = 0;
      rows.forEach(function (row) {
        if (row.classList.contains('admin-empty-row')) return;
        var text = row.textContent.toLowerCase();
        var match = text.includes(query);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      var tbody = document.querySelector('#studentTable tbody');
      var emptyRow = tbody.querySelector('.admin-empty-row');
      if (visible === 0) {
        if (!emptyRow) {
          emptyRow = document.createElement('tr');
          emptyRow.className = 'admin-empty-row';
          emptyRow.innerHTML = '<td colspan="5"><div class="admin-empty"><div class="admin-empty-icon"><i class="bi bi-search"></i></div><div class="admin-empty-title">No Matching Students</div><div class="admin-empty-desc">Try adjusting your search term.</div></div></td>';
          tbody.appendChild(emptyRow);
        }
        emptyRow.style.display = '';
      } else {
        if (emptyRow) emptyRow.style.display = 'none';
      }
    });

    // ─── exeat2: course study cascading dropdown ───
    document.getElementById('filterDepartment').addEventListener('change', function () {
      var deptId = this.value;
      var csSelect = document.getElementById('filterCourseStudy');
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

    // ─── exeat2: fetch and render students ───
    var exeat2Students = [];

    document.getElementById('refreshExeat2').addEventListener('click', function () {
      var dept = document.getElementById('filterDepartment').value;
      var level = document.getElementById('filterLevel').value;
      var courseStudyId = document.getElementById('filterCourseStudy').value;

      if (!dept && !level) {
        Swal.fire({ title: 'Filter Required', text: 'Please select at least a Department or Level to narrow results.', icon: 'warning', confirmButtonColor: '#198754' });
        return;
      }

      var url = 'includes/fetch_exeat2_students.php?exam_id=<?= $exam_id ?>';
      if (dept) url += '&department_id=' + dept;
      if (level) url += '&level_id=' + level;
      if (courseStudyId) url += '&course_study_id=' + courseStudyId;

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
      document.getElementById('exeat2Count').classList.add('d-none');
      document.getElementById('importSelectedBtn').disabled = true;
      document.getElementById('importAllBtn').disabled = true;
    }

    function renderExeat2Table(data) {
      document.getElementById('exeat2Error').classList.add('d-none');
      var tbody = document.getElementById('exeat2TableBody');
      tbody.innerHTML = '';

      if (!data || data.length === 0) {
        document.getElementById('exeat2Empty').classList.remove('d-none');
        document.getElementById('exeat2TableWrapper').classList.add('d-none');
        document.getElementById('exeat2Count').classList.add('d-none');
        document.getElementById('importSelectedBtn').disabled = true;
        document.getElementById('importAllBtn').disabled = true;
        return;
      }

      document.getElementById('exeat2Empty').classList.add('d-none');
      document.getElementById('exeat2TableWrapper').classList.remove('d-none');
      document.getElementById('exeat2Count').classList.remove('d-none');
      document.getElementById('exeat2Count').textContent = data.length + ' students';
      document.getElementById('importSelectedBtn').disabled = false;
      document.getElementById('importAllBtn').disabled = false;

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
          '<td>' + htmlEsc(stu.matric_no) + '</td>' +
          '<td>' + htmlEsc(stu.name) + '</td>' +
          '<td>' + htmlEsc(stu.email || '') + '</td>' +
          '<td></td>' +
          '<td>' + label + '</td>';
        tbody.appendChild(tr);
      });
    }

    function htmlEsc(s) {
      if (!s) return '';
      var d = document.createElement('div');
      d.appendChild(document.createTextNode(s));
      return d.innerHTML;
    }

    // ─── exeat2 master checkbox ───
    document.getElementById('exeat2MasterCheckbox').addEventListener('change', function () {
      document.querySelectorAll('.exeat2-checkbox:not(:disabled)').forEach(function (cb) {
        cb.checked = this.checked;
      }.bind(this));
    });

    // ─── exeat2: Import Selected ───
    document.getElementById('importSelectedBtn').addEventListener('click', function () {
      var checked = document.querySelectorAll('.exeat2-checkbox:checked');
      if (checked.length === 0) {
        Swal.fire({ title: 'No Selection', text: 'Select at least one student.', icon: 'warning', confirmButtonColor: '#198754' });
        return;
      }
      var ids = [];
      checked.forEach(function (cb) { ids.push(parseInt(cb.value)); });
      doImport(ids);
    });

    // ─── exeat2: Import All Matching ───
    document.getElementById('importAllBtn').addEventListener('click', function () {
      var ids = exeat2Students.map(function (s) { return s.exeat2_id; });
      Swal.fire({
        title: 'Import All?',
        text: 'This will import and assign ' + ids.length + ' student(s) from the school portal.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, import all'
      }).then(function (result) {
        if (result.isConfirmed) doImport(ids);
      });
    });

    function doImport(ids) {
      var btn = document.getElementById('importSelectedBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="btn-spinner"></span> Importing...';
      document.getElementById('importAllBtn').disabled = true;

      fetch('includes/import_exeat2_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'exam_id=<?= $exam_id ?>&student_ids=' + encodeURIComponent(JSON.stringify(ids))
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
        document.getElementById('importAllBtn').disabled = false;

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
            var icon = d.status === 'imported' ? '✅' : d.status === 'updated' ? '🔄' : '⏭️';
            html += '<li>' + icon + ' ' + htmlEsc(d.name) + ' (' + htmlEsc(d.matric_no) + ') — ' + d.status + '</li>';
          });
          html += '</ul>';
        }

        document.getElementById('importResultBody').innerHTML = html;
        var modal = new bootstrap.Modal(document.getElementById('importResultModal'));
        modal.show();

        // Refresh exeat2 listing to update statuses
        document.getElementById('refreshExeat2').click();
      })
      .catch(function (err) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
        document.getElementById('importAllBtn').disabled = false;
        Swal.fire({ title: 'Import Error', text: err.message, icon: 'error', confirmButtonColor: '#198754' });
      });
    }
  </script>
</body>

</html>