<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = $error = '';
$back_url = 'admin_dashboard';

// Fetch all exams for dropdowns
$all_exams = $db->query("SELECT id, name, type FROM exams WHERE deleted_at IS NULL ORDER BY type DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
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

      $check = $db->prepare("SELECT id FROM students WHERE matric_no = ? AND deleted_at IS NULL");
      $check->execute([$matric]);
      $existing = $check->fetch(PDO::FETCH_ASSOC);

      if ($existing) {
        $sid = $existing['id'];
        if ($update_existing) {
          $stmt = $db->prepare("UPDATE students SET name=?, firstname=?, middlename=?, lastname=?, email=?, matric_no=?, username=?, password=? WHERE id=?");
          $stmt->execute([$full_name, $fname, $mname, $lname, $email, $matric, $matric, $pass, $sid]);
        }
      } else {
        $stmt = $db->prepare("INSERT INTO students (name, firstname, middlename, lastname, email, matric_no, username, password) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$full_name, $fname, $mname, $lname, $email, $matric, $matric, $pass]);
        $sid = $db->lastInsertId();
      }

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

          $stmt = $db->prepare("INSERT INTO questions(exam_id, text, score_values) VALUES (?, ?, ?)");
          $stmt->execute([$exam_id, $q_text, $scores]);

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
  <meta charset="UTF-8">
  <title>Bulk Upload - Assessment Portal</title>
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
          <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Data Upload Center</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
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
          <li class="current" aria-current="page">Upload Center</li>
        </ol>
      </nav>

      <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= $success ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= $error ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Student Upload -->
        <div class="col-12 col-xl-6">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-people-fill"></i> Upload Student List</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted small mb-4">Import students and auto-assign them to exams.</p>

              <form method="post" enctype="multipart/form-data" id="studentUploadForm">
                <div class="mb-4">
                  <label class="admin-form-label">1. Select CSV File</label>
                  <div class="admin-dropzone" id="studentDropzone" onclick="document.getElementById('studentFileInput').click()">
                    <div class="admin-dropzone-icon"><i class="bi bi-cloud-upload"></i></div>
                    <div class="admin-dropzone-text" id="studentDropzoneText">Drop your CSV file here or click to browse</div>
                    <div class="admin-dropzone-hint">Format: Firstname, Middlename, Lastname, Email, Matric No, Password</div>
                    <input type="file" name="students" id="studentFileInput" accept=".csv" class="upload-hidden" onchange="handleFileSelect(this, 'student')">
                  </div>
                  <div id="studentFileInfo" class="mt-2 upload-hidden">
                    <span class="admin-file-chip" id="studentFileName"></span>
                  </div>
                  <div class="admin-progress mt-2 upload-hidden" id="studentProgress">
                    <div class="admin-progress-bar" id="studentProgressBar" style="width:0%"></div>
                  </div>
                  <div class="mt-2">
                    <a href="download_template.php?type=student_bulk" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>Download CSV Template</a>
                  </div>
                </div>

                <div class="admin-checkbox-group mb-3">
                  <label class="admin-checkbox">
                    <input type="checkbox" name="update_existing" id="updateExisting">
                    <span>Update existing student details</span>
                  </label>
                  <div class="w-100 small text-muted">If Matric No exists, their details/password will be overwritten.</div>
                </div>

                <div class="mb-4">
                  <label class="admin-form-label">2. Assign to Exams (Optional - Hold Ctrl to select multiple)</label>
                  <select name="student_exam_ids[]" class="admin-input admin-select" multiple size="6">
                    <?php
                    $practical_group = array_filter($all_exams, function($e) { return $e['type'] != 'objective'; });
                    $objective_group = array_filter($all_exams, function($e) { return $e['type'] == 'objective'; });
                    ?>
                    <?php if (!empty($practical_group)): ?>
                      <optgroup label="Practical Exams">
                        <?php foreach ($practical_group as $ex): ?>
                          <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($objective_group)): ?>
                      <optgroup label="Objective Exams">
                        <?php foreach ($objective_group as $ex): ?>
                          <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  </select>
                  <div class="admin-dropzone-hint mt-1">Tip: Hold the <b>Ctrl (Cmd)</b> key to select multiple exams.</div>
                </div>

                <div class="admin-alert admin-alert-info">
                  <i class="bi bi-info-circle"></i>
                  <span>New students will be assigned the default password: <b>12345</b></span>
                </div>

                <button type="submit" class="admin-btn admin-btn-primary w-100 py-3" onclick="return showUploadProgress('student')">
                  <i class="bi bi-cloud-arrow-up-fill me-2"></i> Process Student Upload
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Practical Question Upload -->
        <div class="col-12 col-xl-6">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-journal-text"></i> Practical Question Bank</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted small mb-4">Upload checklist steps into a specific exam bank.</p>

              <form method="post" enctype="multipart/form-data" id="questionUploadForm">
                <div class="mb-4">
                  <label class="admin-form-label">1. Target Exam (Required)</label>
                  <select name="exam_id" class="admin-input admin-select" required>
                    <option value="">-- Choose Target Exam --</option>
                    <?php if (!empty($practical_exams)): ?>
                      <optgroup label="Practical Exams">
                        <?php foreach ($practical_exams as $ex): ?>
                          <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                    <?php
                    $objective_exams = array_filter($all_exams, function($e) { return $e['type'] == 'objective'; });
                    if (!empty($objective_exams)):
                    ?>
                      <optgroup label="Objective Exams">
                        <?php foreach ($objective_exams as $ex): ?>
                          <option value="<?= $ex['id'] ?>"><?= htmlspecialchars($ex['name']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endif; ?>
                  </select>
                </div>

                <div class="mb-4">
                  <label class="admin-form-label">2. Select CSV/TXT File</label>
                  <div class="admin-dropzone" id="questionDropzone" onclick="document.getElementById('questionFileInput').click()">
                    <div class="admin-dropzone-icon"><i class="bi bi-file-earmark-plus"></i></div>
                    <div class="admin-dropzone-text" id="questionDropzoneText">Drop your CSV/TXT file here or click to browse</div>
                    <div class="admin-dropzone-hint">Format: Step Text, Score Values (0,1,2...)</div>
                    <input type="file" name="questions" id="questionFileInput" accept=".csv,.txt" class="upload-hidden" onchange="handleFileSelect(this, 'question')">
                  </div>
                  <div id="questionFileInfo" class="mt-2 upload-hidden">
                    <span class="admin-file-chip" id="questionFileName"></span>
                  </div>
                  <div class="admin-progress mt-2 upload-hidden" id="questionProgress">
                    <div class="admin-progress-bar" id="questionProgressBar" style="width:0%"></div>
                  </div>
                  <div class="mt-2">
                    <a href="download_template.php?type=practical_steps" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>Download CSV Template</a>
                  </div>
                </div>

                <div class="admin-alert admin-alert-tip">
                  <i class="bi bi-lightbulb-fill"></i>
                  <span><b>Tip:</b> If questions already exist for this exam, the upload will <u>append</u> new items to the bank.</span>
                </div>

                <button type="submit" class="admin-btn admin-btn-success w-100 py-3" onclick="return showUploadProgress('question')">
                  <i class="bi bi-file-earmark-plus-fill me-2"></i> Upload to Exam Bank
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Objective Note -->
      <div class="admin-card admin-card-border-primary mt-4">
        <div class="admin-card-body">
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
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    function handleFileSelect(input, type) {
      var file = input.files[0];
      if (!file) return;
      var info = document.getElementById(type + 'FileInfo');
      var name = document.getElementById(type + 'FileName');
      name.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
      info.style.display = 'block';
      var text = document.getElementById(type + 'DropzoneText');
      text.textContent = 'Selected: ' + file.name;
    }

    function showUploadProgress(type) {
      var progress = document.getElementById(type + 'Progress');
      var bar = document.getElementById(type + 'ProgressBar');
      progress.style.display = 'block';
      var width = 0;
      var interval = setInterval(function () {
        if (width >= 90) {
          clearInterval(interval);
        } else {
          width += Math.random() * 15;
          if (width > 90) width = 90;
          bar.style.width = width + '%';
        }
      }, 300);
      return true;
    }

    // Drag & drop support
    document.querySelectorAll('.admin-dropzone').forEach(function (zone) {
      zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
      });
      zone.addEventListener('dragleave', function () {
        zone.classList.remove('dragover');
      });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        var input = zone.querySelector('input[type="file"]');
        if (input && e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          handleFileSelect(input, input.name === 'students' ? 'student' : 'question');
        }
      });
    });
  </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
