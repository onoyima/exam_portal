<?php
require 'db.php';
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

      <!-- Search Filter -->
      <div class="admin-filters">
        <div class="admin-filter-group">
          <i class="bi bi-search admin-filter-icon"></i>
          <input type="text" id="tableSearch" class="admin-filter-input" placeholder="Search by name, matric, or email...">
        </div>
      </div>

      <!-- Bulk Form -->
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

    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    // Master checkbox toggle
    document.getElementById('masterCheckbox').addEventListener('change', function () {
      const checkboxes = document.querySelectorAll('.student-checkbox');
      checkboxes.forEach(cb => cb.checked = this.checked);
      updateSelectedCount();
    });

    // Individual checkbox change
    document.addEventListener('change', function (e) {
      if (e.target.classList.contains('student-checkbox')) {
        updateSelectedCount();
      }
    });

    function updateSelectedCount() {
      const selected = document.querySelectorAll('.student-checkbox:checked').length;
      const el = document.getElementById('selectedCount');
      const txt = document.getElementById('selectedCountText');
      if (selected > 0) {
        el.style.display = 'inline-flex';
        txt.textContent = selected;
      } else {
        el.style.display = 'none';
      }
    }

    function submitBulk(action) {
      const selected = document.querySelectorAll('.student-checkbox:checked').length;
      if (selected === 0) {
        Swal.fire({
          title: 'No Selection',
          text: 'Please select at least one student first.',
          icon: 'warning',
          confirmButtonColor: '#198754'
        });
        return;
      }

      const msg = action === 'assign'
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
          const btn = action === 'assign' ? document.getElementById('assignBtn') : null;
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

    // Table search filter
    document.getElementById('tableSearch').addEventListener('keyup', function () {
      const query = this.value.toLowerCase();
      const rows = document.querySelectorAll('#studentTable tbody tr');
      let visible = 0;
      rows.forEach(function (row) {
        if (row.classList.contains('admin-empty-row')) return;
        const text = row.textContent.toLowerCase();
        const match = text.includes(query);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });
      const tbody = document.querySelector('#studentTable tbody');
      let emptyRow = tbody.querySelector('.admin-empty-row');
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
  </script>
</body>

</html>