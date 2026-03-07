<?php
require 'db.php';
require 'auth.php';
check_auth('admin');
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id)
  die('No exam selected.');
$exam = $db->prepare("SELECT * FROM exams WHERE id=?");
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
    header("Location: admin_assign_students.php?exam_id=$exam_id&success=$msg");
    exit;
  }
  if (isset($_POST['remove']) && isset($_POST['student_id'])) {
    $sid = intval($_POST['student_id']);
    $db->prepare("DELETE FROM exam_students WHERE exam_id=? AND student_id=?")->execute([$exam_id, $sid]);
    header("Location: admin_assign_students.php?exam_id=$exam_id&success=removed");
    exit;
  }
  if (isset($_POST['clear_all'])) {
    $db->prepare("DELETE FROM exam_students WHERE exam_id=?")->execute([$exam_id]);
    header("Location: admin_assign_students.php?exam_id=$exam_id&success=cleared");
    exit;
  }
  if (isset($_POST['assign_all_system'])) {
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students")->execute([$exam_id]);
    header("Location: admin_assign_students.php?exam_id=$exam_id&success=all_assigned");
    exit;
  }
  if (isset($_POST['assign_by_email_domain']) && !empty($_POST['email_domain'])) {
    $domain = '%' . trim($_POST['email_domain']);
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE email LIKE ?")->execute([$exam_id, $domain]);
    header("Location: admin_assign_students.php?exam_id=$exam_id&success=domain_assigned");
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
$students = $db->query("SELECT * FROM students ORDER BY $sort $dir")->fetchAll(PDO::FETCH_ASSOC);
// Fetch assigned students
$assigned = $db->prepare("SELECT student_id FROM exam_students WHERE exam_id=?");
$assigned->execute([$exam_id]);
$assigned_ids = array_column($assigned->fetchAll(PDO::FETCH_ASSOC), 'student_id');
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Assign Students to Exam - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <div class="container-xl py-4">
      <div class="row g-3 mb-4">
        <div class="col-md-8">
          <h2 class="mb-0">Assign Students to: <span class="text-primary fw-bold"><?= htmlspecialchars($exam['name']) ?></span></h2>
        </div>
        <div class="col-md-4 text-md-end">
          <div class="d-flex flex-column gap-2">
            <!-- Enroll All Button -->
            <form method="post" onsubmit="return confirm('Note: This will enroll EVERY student in your database to this exam. Proceed?')">
              <button type="submit" name="assign_all_system" class="btn btn-success rounded-pill px-4 fw-bold w-100">
                <i class="bi bi-person-plus-fill me-1"></i> Enroll All System Students
              </button>
            </form>

            <!-- Domain Enroll Tool -->
            <form method="post" class="input-group" onsubmit="return confirm('Enroll all students with this email domain?')">
              <input type="text" name="email_domain" class="form-control rounded-start-pill" placeholder="e.g. @hum.veritas.edu.ng" required>
              <button type="submit" name="assign_by_email_domain" class="btn btn-info rounded-end-pill px-3 fw-bold">
                Enroll by Domain
              </button>
            </form>
          </div>
        </div>
      </div>
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm mb-4">
          <i class="bi bi-check-circle-fill me-1"></i>
          <?php
          $msg = $_GET['success'];
          if ($msg == 'domain_assigned') echo "Successfully enrolled all matching domain students!";
          elseif ($msg == 'all_assigned') echo "Successfully enrolled EVERY student in the system!";
          elseif ($msg == 'cleared') echo "All assignments cleared for this exam.";
          else echo "Action successful.";
          ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Search Filter -->
      <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body p-3 bg-light">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0"><i class="bi bi-search"></i></span>
            <input type="text" id="tableSearch" class="form-control border-0 bg-transparent" placeholder="Search matric, name, or email in the list below...">
          </div>
        </div>
      </div>
      <form method="post" id="bulkForm">
        <input type="hidden" name="bulk_action" id="bulkActionInput" value="">
        <div class="table-responsive bg-white rounded-3 shadow-sm p-3 mb-4 border">
          <table class="table align-middle table-hover" id="studentTable">
            <thead>
              <tr class="table-light">
                <th width="40"><input type="checkbox" id="masterCheckbox" class="form-check-input"></th>
                <th><a href="?exam_id=<?= $exam_id ?>&sort=name&dir=<?= $next_dir ?>"
                    class="text-decoration-none text-dark d-flex align-items-center gap-1">Name
                    <?= $sort === 'name' ? ($dir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th><a href="?exam_id=<?= $exam_id ?>&sort=matric_no&dir=<?= $next_dir ?>"
                    class="text-decoration-none text-dark d-flex align-items-center gap-1">Matric No
                    <?= $sort === 'matric_no' ? ($dir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th><a href="?exam_id=<?= $exam_id ?>&sort=email&dir=<?= $next_dir ?>"
                    class="text-decoration-none text-dark d-flex align-items-center gap-1">Email
                    <?= $sort === 'email' ? ($dir === 'ASC' ? '↑' : '↓') : '' ?></a></th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $stu): ?>
                <tr>
                  <td>
                    <input type="checkbox" name="student_ids[]" value="<?= $stu['id'] ?>"
                      class="student-checkbox form-check-input">
                  </td>
                  <td><?= htmlspecialchars($stu['name']) ?></td>
                  <td><?= htmlspecialchars($stu['matric_no']) ?></td>
                  <td><?= htmlspecialchars($stu['email'] ?? '') ?></td>
                  <td>
                    <?php if (in_array($stu['id'], $assigned_ids)): ?>
                      <span class="badge bg-success-light text-success rounded-pill px-3 py-1">Assigned</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-light text-muted rounded-pill px-3 py-1">Not Assigned</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="d-flex flex-wrap gap-2 mb-4 bg-light p-3 rounded-3 align-items-center">
          <span class="fw-bold text-muted small me-2">BULK ACTIONS WITH SELECTED:</span>
          <button type="button" onclick="submitBulk('assign')"
            class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
            Assign Selected
          </button>
          <button type="button" onclick="submitBulk('remove')" class="btn btn-outline-danger rounded-pill px-4 fw-bold">
            Unassign Selected
          </button>
          <div class="ms-auto">
            <button type="submit" name="clear_all" class="btn btn-danger rounded-pill px-4 fw-bold"
              onclick="return confirm('Note: This will remove ALL student assignments (even those not selected) from this exam. Proceed?')">
              Clear All Assignments
            </button>
          </div>
      </form>
    </div>
  </div>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Master checkbox toggle
    document.getElementById('masterCheckbox').addEventListener('change', function () {
      const checkboxes = document.querySelectorAll('.student-checkbox');
      checkboxes.forEach(cb => cb.checked = this.checked);
    });

    function submitBulk(action) {
      const selected = document.querySelectorAll('.student-checkbox:checked').length;
      if (selected === 0) {
        alert('Please select at least one student first.');
        return;
      }

      const confirmMsg = action === 'assign' ?
        `Assign ${selected} selected students to this exam?` :
        `Remove ${selected} selected students from this exam?`;

      if (confirm(confirmMsg)) {
        document.getElementById('bulkActionInput').value = action;
        document.getElementById('bulkForm').submit();
      }
    }

    // Table search filter
    document.getElementById('tableSearch').addEventListener('keyup', function() {
      const query = this.value.toLowerCase();
      const rows = document.querySelectorAll('#studentTable tbody tr');
      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
      });
    });

    // Sidebar toggle (if exists)
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
  </script>
</body>

</html>