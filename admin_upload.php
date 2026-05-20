<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = $error = '';

// Fetch all exams for dropdowns
// Fetch all exams for different categories
$all_exams = $db->query("SELECT id, name, type FROM exams ORDER BY type DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$practical_exams = array_filter($all_exams, function ($e) {
  return ($e['type'] != 'objective');
});

// Handle Student Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['students'])) {
  $exam_ids = isset($_POST['student_exam_ids']) ? (array) $_POST['student_exam_ids'] : [];
  $update_existing = isset($_POST['update_existing']);
  $file = $_FILES['students']['tmp_name'];

  if ($file) {
    $csv = fopen($file, 'r');
    $count = 0;

    // Check if first row is header
    $first_row = fgetcsv($csv);
    $header_found = false;
    if ($first_row) {
      $first_col = strtolower(str_replace(' ', '', $first_row[0]));
      if ($first_col === 'firstname' || $first_col === 'first_name' || $first_col === 'name') {
        $header_found = true;
      }
    }

    if (!$header_found) {
      rewind($csv);
    }

    while ($row = fgetcsv($csv)) {
      if (count($row) < 3)
        continue;

      $fname = trim($row[0] ?? '');
      $mname = trim($row[1] ?? '');
      $lname = trim($row[2] ?? '');
      $email = trim($row[3] ?? '');
      $matric = trim($row[4] ?? '');
      $pass = trim($row[5] ?? '12345');

      $full_name = trim($fname . ' ' . $mname . ' ' . $lname);
      $full_name = str_replace('  ', ' ', $full_name);
      if (empty($full_name))
        $full_name = $matric;

      // Check if student exists by Matric No
      $check = $db->prepare("SELECT id FROM students WHERE matric_no = ?");
      $check->execute([$matric]);
      $existing = $check->fetch(PDO::FETCH_ASSOC);

      if ($existing) {
        $sid = $existing['id'];
        if ($update_existing) {
          $stmt = $db->prepare("UPDATE students SET name=?, firstname=?, middlename=?, lastname=?, email=?, matric_no=?, username=?, password=? WHERE id=?");
          $stmt->execute([$full_name, $fname, $mname, $lname, $email, $matric, $matric, $pass, $sid]);
        }
      } else {
        // New Student
        $stmt = $db->prepare("INSERT INTO students (name, firstname, middlename, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $fname, $mname, $lname, $email, $matric, $matric, $pass]);
        $sid = $db->lastInsertId();
      }

      // Auto-assign to exams if selected
      if (!empty($exam_ids) && $sid) {
        foreach ($exam_ids as $eid) {
          $eid = intval($eid);
          if ($eid > 0) {
            $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")->execute([$eid, $sid]);
          }
        }
      }
      $count++;
    }
    fclose($csv);
    $success = "Successfully processed $count students!" . (!empty($exam_ids) ? " Assigned to " . count($exam_ids) . " exams." : "");
  }
}

// Handle Practical Question Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['questions'])) {
  $exam_id = intval($_POST['exam_id']);
  if (!$exam_id) {
    $error = "Please select an exam for the questions.";
  } else {
    $file = $_FILES['questions']['tmp_name'];
    if ($file) {
      $handle = fopen($file, 'r');
      $count = 0;
      while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 2) {
          $q_text = trim($row[0]);
          $scores = trim($row[1]);
          if (empty($q_text))
            continue;

          // Insert into specific exam bank
          $stmt = $db->prepare("INSERT INTO questions(exam_id, text, score_values) VALUES (?, ?, ?)");
          $stmt->execute([$exam_id, $q_text, $scores]);

          // Legacy mapping
          $qid = $db->lastInsertId();
          $db->prepare("INSERT INTO exam_questions(exam_id, question_id) VALUES (?, ?)")->execute([$exam_id, $qid]);

          $count++;
        }
      }
      fclose($handle);
      $success = "$count practical steps uploaded to the selected exam!";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Bulk Upload - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid px-4">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none" id="sidebarToggle"><i class="bi bi-list"></i></button>
          <span class="navbar-brand fw-bold">Data Upload Center</span>
        </div>
        <div class="d-flex align-items-center gap-3">
          <div class="text-end d-none d-md-block">
            <div class="fw-bold small"><?= htmlspecialchars($name) ?></div>
            <div class="text-muted small" style="font-size:0.7rem">ADMINISTRATOR</div>
          </div>
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
        </div>
      </div>
    </nav>

    <div class="container-xl px-4">
      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Student Upload -->
        <div class="col-12 col-xl-6">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-md-5">
              <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-primary-light text-primary p-3 rounded-4">
                  <i class="bi bi-people-fill fs-3"></i>
                </div>
                <div>
                  <h4 class="fw-bold mb-0">Upload Student List</h4>
                  <p class="text-muted small mb-0">Import students and auto-assign them.</p>
                </div>
              </div>

              <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                  <label class="form-label fw-bold">1. Select CSV File</label>
                  <input type="file" name="students" class="form-control rounded-3" accept=".csv" required>
                  <div class="mt-2 small text-muted">Format:
                    <code>Firstname, Middlename, Lastname, Email, Matric No, Password</code>
                    <br>
                    <a href="download_template.php?type=student_bulk" class="btn btn-sm btn-link p-0 text-primary fw-bold"><i class="bi bi-download me-1"></i>Download CSV Template</a>
                  </div>
                </div>

                <div class="form-check form-switch mb-3">
                  <input class="form-check-input" type="checkbox" name="update_existing" id="updateExisting">
                  <label class="form-check-label fw-bold" for="updateExisting">Update existing student details</label>
                  <div class="small text-muted">If Matric No exists, their details/password will be overwritten.</div>
                </div>

                <div class="mb-4">
                  <label class="form-label fw-bold">2. Assign to Exams (Optional - Hold Ctrl to select multiple)</label>
                  <select name="student_exam_ids[]" class="form-select rounded-3" multiple size="6">
                    <?php foreach ($all_exams as $ex): ?>
                      <option value="<?= $ex['id'] ?>">
                        <?= strtoupper($ex['type'] ?? 'PRACTICAL') ?>: <?= htmlspecialchars($ex['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="mt-1 small text-muted font-italic">Tip: You can select multiple exams by holding the
                    <b>Ctrl (Cmd)</b> key.
                  </div>
                </div>

                <div class="bg-light p-3 rounded-4 mb-4 small text-muted">
                  <i class="bi bi-info-circle me-1"></i> New students will be assigned the default password:
                  <b>12345</b>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold">
                  <i class="bi bi-cloud-arrow-up-fill me-2"></i> Process Student Upload
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Practical Question Upload -->
        <div class="col-12 col-xl-6">
          <div class="card border-0 shadow-sm rounded-4 h-100">
            <div class="card-body p-4 p-md-5">
              <div class="d-flex align-items-center gap-3 mb-4">
                <div class="bg-success-light text-success p-3 rounded-4">
                  <i class="bi bi-journal-text fs-3"></i>
                </div>
                <div>
                  <h4 class="fw-bold mb-0">Practical Question Bank</h4>
                  <p class="text-muted small mb-0">Upload checklist steps into a specific exam bank.</p>
                </div>
              </div>

              <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                  <label class="form-label fw-bold">1. Target Exam (Required)</label>
                  <select name="exam_id" class="form-select rounded-3" required>
                    <option value="">-- Choose Target Exam --</option>
                    <?php foreach ($practical_exams as $ex): ?>
                      <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>

                <div class="mb-4">
                  <label class="form-label fw-bold">2. Select CSV/TXT File</label>
                  <input type="file" name="questions" class="form-control rounded-3" accept=".csv,.txt" required>
                  <div class="mt-2 small text-muted">Format: <code>Step Text, Score Values (0,1,2...)</code>
                    <br>
                    <a href="download_template.php?type=practical_steps" class="btn btn-sm btn-link p-0 text-success fw-bold"><i class="bi bi-download me-1"></i>Download CSV Template</a>
                  </div>
                </div>

                <div class="alert bg-success-light text-success border-0 mb-4 p-3 rounded-4 small">
                  <i class="bi bi-lightbulb-fill me-1"></i> <b>Tip:</b> If questions already exist for this exam, the
                  upload will <u>append</u> new items to the bank.
                </div>

                <button type="submit" class="btn btn-success w-100 py-3 rounded-pill fw-bold">
                  <i class="bi bi-file-earmark-plus-fill me-2"></i> Upload to Exam Bank
                </button>
              </form>
            </div>
          </div>
        </div>

      </div> <!-- End Row -->

      <!-- Objective Note -->
      <div class="mt-5 p-4 bg-white rounded-4 shadow-sm border-start border-primary border-5">
        <div class="d-flex gap-3 align-items-center">
          <i class="bi bi-info-circle text-primary fs-2"></i>
          <div>
            <h6 class="fw-bold mb-1">Objective Exams Note</h6>
            <p class="mb-0 text-muted small">Objective Multiple Choice (A-D) questions are managed directly within the
              <a href="admin_objective_exams" class="text-decoration-none fw-bold">Objective Exams</a> module to
              support specialized features like shuffling and subsets.
            </p>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
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
