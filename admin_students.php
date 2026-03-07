<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['add'])) {
    $full_name = trim($_POST['firstname'] . ' ' . $_POST['middlename'] . ' ' . $_POST['lastname']);
    $full_name = str_replace('  ', ' ', $full_name); // Clean double spaces if no middle name
    $stmt = $db->prepare("INSERT INTO students (name, firstname, middlename, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$full_name, $_POST['firstname'], $_POST['middlename'], $_POST['lastname'], $_POST['email'], $_POST['matric_no'], $_POST['matric_no'], $_POST['password'] ?: '12345']);
    $success = "Student added successfully!";
  } elseif (isset($_POST['delete'])) {
    $stmt = $db->prepare("DELETE FROM students WHERE id = ?");
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
$students = $db->query("SELECT * FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
// Get all exams for enrollment modal
$all_exams = $db->query("SELECT id, name, type FROM exams ORDER BY type DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Students - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><i class="bi bi-list fs-4"></i></button>
          <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
          <span class="navbar-brand fw-bold">Manage Students</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <!-- Add Student Form -->
      <div class="card p-4 shadow-sm mb-4">
        <h4 class="mb-3">Add New Student</h4>
        <form method="post" class="row g-3">
          <div class="col-md-2">
            <label for="firstname" class="form-label">First Name</label>
            <input type="text" class="form-control" id="firstname" name="firstname" required>
          </div>
          <div class="col-md-2">
            <label for="middlename" class="form-label">Middle Name</label>
            <input type="text" class="form-control" id="middlename" name="middlename">
          </div>
          <div class="col-md-2">
            <label for="lastname" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="lastname" name="lastname" required>
          </div>
          <div class="col-md-2">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
          </div>
          <div class="col-md-2">
            <label for="matric_no" class="form-label" title="Usually Username">Matric No</label>
            <input type="text" class="form-control" id="matric_no" name="matric_no" required>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="add" class="btn btn-primary w-100">Add Student</button>
          </div>
        </form>
      </div>

      <!-- Students List -->
      <div class="card p-4 shadow-sm">
        <h4 class="mb-3">Students List (<?= count($students) ?> total)</h4>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Name</th>
                <th>Matric No</th>
                <th>Email</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $student): ?>
                <tr>
                  <td><?= htmlspecialchars($student['name']) ?></td>
                  <td><?= htmlspecialchars($student['matric_no']) ?></td>
                  <td><?= htmlspecialchars($student['email'] ?? '') ?></td>
                  <td>
                    <div class="btn-group">
                      <button class="btn btn-sm btn-outline-primary"
                        onclick="editStudent(<?= $student['id'] ?>, '<?= htmlspecialchars($student['firstname'] ?? '') ?>', '<?= htmlspecialchars($student['middlename'] ?? '') ?>', '<?= htmlspecialchars($student['lastname'] ?? '') ?>', '<?= htmlspecialchars($student['email'] ?? '') ?>', '<?= htmlspecialchars($student['matric_no']) ?>')">
                        Edit
                      </button>
                      <button class="btn btn-sm btn-primary"
                        onclick="openEnrollModal(<?= $student['id'] ?>, '<?= htmlspecialchars($student['name']) ?>')">
                        Enroll
                      </button>
                      <form method="post" class="d-inline"
                        onsubmit="return confirm('Reset this student password to default (12345)?')">
                        <input type="hidden" name="id" value="<?= $student['id'] ?>">
                        <button type="submit" name="reset_password" class="btn btn-sm btn-outline-warning">Reset
                          PW</button>
                      </form>
                      <form method="post" class="d-inline"
                        onsubmit="return confirm('Are you sure you want to delete this student?')">
                        <input type="hidden" name="id" value="<?= $student['id'] ?>">
                        <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">Delete</button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Student</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="id" id="edit_id">
            <div class="mb-3">
              <label for="edit_firstname" class="form-label">First Name</label>
              <input type="text" class="form-control" id="edit_firstname" name="firstname" required>
            </div>
            <div class="mb-3">
              <label for="edit_middlename" class="form-label">Middle Name</label>
              <input type="text" class="form-control" id="edit_middlename" name="middlename">
            </div>
            <div class="mb-3">
              <label for="edit_lastname" class="form-label">Last Name</label>
              <input type="text" class="form-control" id="edit_lastname" name="lastname" required>
            </div>
            <div class="mb-3">
              <label for="edit_email" class="form-label">Email</label>
              <input type="email" class="form-control" id="edit_email" name="email">
            </div>
            <div class="mb-3">
              <label for="edit_matric_no" class="form-label">Matric Number</label>
              <input type="text" class="form-control" id="edit_matric_no" name="matric_no" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit" class="btn btn-primary">Update Student</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

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
            <label class="form-label fw-bold">Select Exams (Multi-select)</label>
            <select name="exam_ids[]" class="form-select" multiple size="10" required>
              <?php foreach ($all_exams as $ex): ?>
                <option value="<?= $ex['id'] ?>">
                  <?= strtoupper($ex['type'] ?? 'PRACTICAL') ?>: <?= htmlspecialchars($ex['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            <div class="small text-muted mt-2 font-italic">Hold Ctrl (Cmd) to select multiple exams.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="enroll" class="btn btn-primary">Enroll Now</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    if (sidebarToggle) {
      // Logic handled by admin_sidebar.php
    }

    // Edit student function
    function editStudent(id, fname, mname, lname, email, matric) {
      document.getElementById('edit_id').value = id;
      document.getElementById('edit_firstname').value = fname;
      document.getElementById('edit_middlename').value = mname;
      document.getElementById('edit_lastname').value = lname;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_matric_no').value = matric;
      new bootstrap.Modal(document.getElementById('editModal')).show();
    }

    // Enroll modal function
    function openEnrollModal(id, name) {
      document.getElementById('enroll_student_id').value = id;
      document.getElementById('enroll_student_name').textContent = name;
      new bootstrap.Modal(document.getElementById('enrollModal')).show();
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
