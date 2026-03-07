<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['create_exam'])) {
    // Step 1: Create exam only
    $p_fs = isset($_POST['proctor_fullscreen']) ? 1 : 0;
    $p_tab = isset($_POST['proctor_tab_lock']) ? 1 : 0;
    $p_kb = isset($_POST['proctor_kb_lock']) ? 1 : 0;
    $p_auto = isset($_POST['proctor_auto_submit']) ? 1 : 0;
    $p_browser = isset($_POST['proctor_browser_check']) ? 1 : 0;
    $p_webcam = isset($_POST['proctor_webcam']) ? 1 : 0;
    $p_live = isset($_POST['proctor_live_video']) ? 1 : 0;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

    $stmt = $db->prepare("INSERT INTO exams(name, is_active, is_strict, proctor_fullscreen, proctor_tab_lock, proctor_kb_lock, proctor_auto_submit, proctor_browser_check, proctor_webcam, proctor_live_video, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['exam_name'], $_POST['is_active'] ? 1 : 0, $_POST['is_strict'] ? 1 : 0, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time]);
    $success = "Exam '" . htmlspecialchars($_POST['exam_name']) . "' created successfully! You can now add students and questions to this exam.";
  } elseif (isset($_POST['edit_exam'])) {
    // Edit existing exam
    $p_fs = isset($_POST['proctor_fullscreen']) ? 1 : 0;
    $p_tab = isset($_POST['proctor_tab_lock']) ? 1 : 0;
    $p_kb = isset($_POST['proctor_kb_lock']) ? 1 : 0;
    $p_auto = isset($_POST['proctor_auto_submit']) ? 1 : 0;
    $p_browser = isset($_POST['proctor_browser_check']) ? 1 : 0;
    $p_webcam = isset($_POST['proctor_webcam']) ? 1 : 0;
    $p_live = isset($_POST['proctor_live_video']) ? 1 : 0;
    $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
    $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;

    $stmt = $db->prepare("UPDATE exams SET name = ?, is_active = ?, is_strict = ?, proctor_fullscreen = ?, proctor_tab_lock = ?, proctor_kb_lock = ?, proctor_auto_submit = ?, proctor_browser_check = ?, proctor_webcam = ?, proctor_live_video = ?, start_time = ?, end_time = ? WHERE id = ?");
    $stmt->execute([$_POST['exam_name'], $_POST['is_active'] ? 1 : 0, $_POST['is_strict'] ? 1 : 0, $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time, $_POST['exam_id']]);
    $success = "Exam updated successfully!";
  } elseif (isset($_POST['activate'])) {
    $db->prepare("UPDATE exams SET is_active=1 WHERE id=?")->execute([$_POST['exam_id']]);
    $success = "Exam activated successfully!";
  } elseif (isset($_POST['deactivate'])) {
    $db->prepare("UPDATE exams SET is_active=0 WHERE id=?")->execute([$_POST['exam_id']]);
    $success = "Exam deactivated successfully!";
  } elseif (isset($_POST['assign_students'])) {
    // Step 2: Assign students to existing exam
    $exam_id = $_POST['exam_id'];
    if (!empty($_POST['students'])) {
      foreach ($_POST['students'] as $student_id) {
        $db->prepare("INSERT IGNORE INTO exam_students(exam_id, student_id) VALUES (?, ?)")->execute([$exam_id, $student_id]);
      }
    }
    $success = "Students assigned to exam successfully!";
  } elseif (isset($_POST['upload_students'])) {
    // Step 2: Upload bulk students to existing exam
    $exam_id = $_POST['exam_id'];
    $uploaded_count = 0;

    if (isset($_FILES['students_file']) && $_FILES['students_file']['tmp_name']) {
      $file = $_FILES['students_file']['tmp_name'];
      $handle = fopen($file, 'r');

      while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 3) {
          $name = trim($row[0]);
          $matric_no = trim($row[1]);
          $username = trim($row[2]);

          // Check if student already exists
          $stmt = $db->prepare("SELECT id FROM students WHERE matric_no = ? OR username = ?");
          $stmt->execute([$matric_no, $username]);
          $existing_student = $stmt->fetch();

          if ($existing_student) {
            // Student exists, just assign to exam
            $student_id = $existing_student['id'];
          } else {
            // Create new student
            $stmt = $db->prepare("INSERT INTO students(name, matric_no, username) VALUES (?, ?, ?)");
            $stmt->execute([$name, $matric_no, $username]);
            $student_id = $db->lastInsertId();
          }

          // Assign student to exam (avoid duplicates)
          $stmt = $db->prepare("INSERT IGNORE INTO exam_students(exam_id, student_id) VALUES (?, ?)");
          $stmt->execute([$exam_id, $student_id]);
          $uploaded_count++;
        }
      }
      fclose($handle);
      $success = "$uploaded_count students uploaded and assigned to exam successfully!";
    } else {
      $error = "Please select a file to upload.";
    }
  } elseif (isset($_POST['add_question'])) {
    // Step 3: Add new question to existing exam
    $stmt = $db->prepare("INSERT INTO questions(exam_id, text, score_values) VALUES (?, ?, ?)");
    $stmt->execute([$_POST['exam_id'], $_POST['text'], $_POST['score_values']]);
    $qid = $db->lastInsertId();
    $db->prepare("INSERT INTO exam_questions(exam_id, question_id) VALUES (?, ?)")->execute([$_POST['exam_id'], $qid]);
    $success = "Question added to exam successfully!";
  } elseif (isset($_POST['add_existing_question'])) {
    // Step 3: Add existing question to exam
    $db->prepare("INSERT INTO exam_questions(exam_id, question_id) VALUES (?, ?)")->execute([$_POST['exam_id'], $_POST['question_id']]);
    $success = "Existing question added to exam successfully!";
  } elseif (isset($_POST['upload_questions'])) {
    // Step 3: Upload bulk questions to existing exam
    $exam_id = $_POST['exam_id'];
    $uploaded_count = 0;

    if (isset($_FILES['questions_file']) && $_FILES['questions_file']['tmp_name']) {
      $file = $_FILES['questions_file']['tmp_name'];
      $handle = fopen($file, 'r');

      while (($row = fgetcsv($handle)) !== false) {
        if (count($row) >= 2) {
          $stmt = $db->prepare("INSERT INTO questions(exam_id, text, score_values) VALUES (?, ?, ?)");
          $stmt->execute([$exam_id, $row[0], $row[1]]);
          $qid = $db->lastInsertId();
          $db->prepare("INSERT INTO exam_questions(exam_id, question_id) VALUES (?, ?)")->execute([$exam_id, $qid]);
          $uploaded_count++;
        }
      }
      fclose($handle);
      $success = "$uploaded_count questions uploaded and added to exam successfully!";
    } else {
      $error = "Please select a file to upload.";
    }
  } elseif (isset($_POST['remove_question'])) {
    // Remove question from exam
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=? AND question_id=?")->execute([$_POST['exam_id'], $_POST['question_id']]);
    $success = "Question removed from exam successfully!";
  } elseif (isset($_POST['remove_student'])) {
    // Remove student from exam
    $db->prepare("DELETE FROM exam_students WHERE exam_id=? AND student_id=?")->execute([$_POST['exam_id'], $_POST['student_id']]);
    $success = "Student removed from exam successfully!";
  } elseif (isset($_POST['assign_examiners'])) {
    // Step 4: Assign examiners to existing exam
    $exam_id = $_POST['exam_id'];
    if (!empty($_POST['examiners'])) {
      foreach ($_POST['examiners'] as $examiner_id) {
        $db->prepare("INSERT IGNORE INTO exam_examiners(exam_id, examiner_id) VALUES (?, ?)")->execute([$exam_id, $examiner_id]);
      }
    }
    $success = "Examiners assigned to exam successfully!";
  } elseif (isset($_POST['remove_examiner'])) {
    // Remove examiner from exam
    $db->prepare("DELETE FROM exam_examiners WHERE exam_id=? AND examiner_id=?")->execute([$_POST['exam_id'], $_POST['examiner_id']]);
    $success = "Examiner removed from exam successfully!";
  } elseif (isset($_POST['delete_exam'])) {
    // Delete exam and all related data
    $exam_id = $_POST['exam_id'];
    $db->prepare("DELETE FROM exam_students WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM exam_questions WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM exam_examiners WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM answers WHERE exam_id=?")->execute([$exam_id]);
    $db->prepare("DELETE FROM exams WHERE id=?")->execute([$exam_id]);
    $success = "Exam deleted successfully!";
  }

  if (isset($success)) {
    header('Location: admin_exams?success=' . urlencode($success));
    exit;
  } elseif (isset($error)) {
    header('Location: admin_exams?error=' . urlencode($error));
    exit;
  }
}

$exams = $db->query("SELECT * FROM exams ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$students = $db->query("SELECT * FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$questions = $db->query("SELECT * FROM questions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$examiners = $db->query("SELECT * FROM examiners WHERE username != 'admin' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// For each exam, get assigned students, questions, and examiners
$exam_students = [];
$exam_questions = [];
$exam_examiners = [];
foreach ($exams as $exam) {
  $exam_students[$exam['id']] = $db->query("SELECT student_id FROM exam_students WHERE exam_id=" . $exam['id'])->fetchAll(PDO::FETCH_COLUMN);
  $exam_questions[$exam['id']] = $db->query("SELECT q.* FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=" . $exam['id'])->fetchAll(PDO::FETCH_ASSOC);
  $exam_examiners[$exam['id']] = $db->query("SELECT examiner_id FROM exam_examiners WHERE exam_id=" . $exam['id'])->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Exams - Assessment Portal</title>
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
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
              class="navbar-toggler-icon"></span></button>
          <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
          <span class="navbar-brand fw-bold">Manage Exams</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($_GET['success']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <?= htmlspecialchars($_GET['error']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <!-- Step 1: Create New Exam -->
      <div class="card p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center mb-3">
          <span class="badge bg-primary me-3 fs-6">Step 1</span>
          <h4 class="mb-0">Create New Exam</h4>
        </div>
        <p class="text-muted mb-3">First, create a new exam. You can add students and questions to it after creation.
        </p>
        <form method="post" class="row g-3">
          <div class="col-md-6">
            <label for="exam_name" class="form-label">Exam Name</label>
            <input type="text" name="exam_name" id="exam_name" class="form-control" placeholder="Enter exam name"
              required>
          </div>
          <div class="col-md-3">
            <label for="is_active" class="form-label">Initial Status</label>
            <select name="is_active" id="is_active" class="form-select">
              <option value="0">Inactive (Recommended)</option>
              <option value="1">Active</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="is_strict" class="form-label">Tab Security (Strictness)</label>
            <select name="is_strict" id="is_strict" class="form-select mb-2">
              <option value="1">Strict (Enabled)</option>
              <option value="0">Flexible (Disabled)</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Start Time</label>
            <input type="datetime-local" name="start_time" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">End Time</label>
            <input type="datetime-local" name="end_time" class="form-control">
          </div>
          <div class="col-12">
            <div class="d-flex flex-wrap gap-3 p-3 bg-light rounded-3 border">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_fullscreen" id="pfsNew" checked>
                <label class="form-check-label small" for="pfsNew">Fullscreen</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_tab_lock" id="ptabNew" checked>
                <label class="form-check-label small" for="ptabNew">Tab/Focus</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_kb_lock" id="pkbNew" checked>
                <label class="form-check-label small" for="pkbNew">Keyboard/Copy</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_auto_submit" id="pautoNew" checked>
                <label class="form-check-label small" for="pautoNew">3-Strike Auto</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_browser_check" id="pbcNew" checked>
                <label class="form-check-label small" for="pbcNew">Browser Check</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_webcam" id="pwcNew" checked>
                <label class="form-check-label small" for="pwcNew">Webcam Monitor</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="proctor_live_video" id="pliveNew">
                <label class="form-check-label small text-danger fw-bold" for="pliveNew">Live Video Stream</label>
              </div>
            </div>
          </div>
          <div class="col-md-12 text-end">
            <button type="submit" name="create_exam" class="btn btn-primary px-4">Create Exam</button>
          </div>
        </form>
      </div>

      <!-- Step 2: Assign Students to Existing Exams -->
      <div class="card p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center mb-3">
          <span class="badge bg-success me-3 fs-6">Step 2</span>
          <h4 class="mb-0">Assign Students to Exam</h4>
        </div>
        <p class="text-muted mb-3">Select an existing exam and assign students to it. You can select existing students
          or upload new ones in bulk.</p>

        <!-- Select Existing Students -->
        <form method="post" class="row g-3 mb-3">
          <div class="col-md-4">
            <label for="exam_id_students" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_students" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="students" class="form-label">Select Existing Students</label>
            <select name="students[]" id="students" class="form-select" multiple size="4">
              <?php foreach ($students as $stu): ?>
                <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['name']) ?>
                  (<?= htmlspecialchars($stu['matric_no']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple students</small>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="assign_students" class="btn btn-success w-100">Assign Students</button>
          </div>
        </form>

        <!-- Upload Bulk Students -->
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-4">
            <label for="exam_id_upload_students" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_upload_students" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="students_file" class="form-label">Upload Students File (CSV/TXT)</label>
            <input type="file" name="students_file" id="students_file" class="form-control" accept=".csv,.txt" required>
            <small class="text-muted">Format: <code>Name,Matric No,Username</code> (e.g.
              <code>John Doe,MAT1001,johndoe</code>)</small>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="upload_students" class="btn btn-info w-100">Upload Students</button>
          </div>
        </form>
      </div>

      <!-- Step 3: Add Questions to Existing Exams -->
      <div class="card p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center mb-3">
          <span class="badge bg-warning me-3 fs-6">Step 3</span>
          <h4 class="mb-0">Add Questions to Exam</h4>
        </div>
        <p class="text-muted mb-3">Add questions to existing exams. You can use existing questions, create new ones, or
          upload bulk questions.</p>

        <!-- Add Existing Question -->
        <form method="post" class="row g-3 mb-3">
          <div class="col-md-3">
            <label for="exam_id_questions" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_questions" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="question_id" class="form-label">Select Existing Question</label>
            <select name="question_id" id="question_id" class="form-select" required>
              <option value="">-- Select Question --</option>
              <?php foreach ($questions as $q): ?>
                <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['text']) ?>
                  (<?= htmlspecialchars($q['score_values']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="add_existing_question" class="btn btn-outline-primary w-100">Add Existing
              Question</button>
          </div>
        </form>

        <!-- Add New Question -->
        <form method="post" class="row g-3 mb-3">
          <div class="col-md-3">
            <label for="exam_id_new" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_new" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label for="text" class="form-label">New Question Text</label>
            <input type="text" name="text" id="text" class="form-control" placeholder="Enter question text" required>
          </div>
          <div class="col-md-3">
            <label for="score_values" class="form-label">Score Values</label>
            <input type="text" name="score_values" id="score_values" class="form-control" placeholder="e.g. 0.5,1,2"
              required>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="add_question" class="btn btn-warning w-100">Add New Question</button>
          </div>
        </form>

        <!-- Upload Bulk Questions -->
        <form method="post" enctype="multipart/form-data" class="row g-3">
          <div class="col-md-3">
            <label for="exam_id_upload" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_upload" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="questions_file" class="form-label">Upload Questions File (CSV/TXT)</label>
            <input type="file" name="questions_file" id="questions_file" class="form-control" accept=".csv,.txt"
              required>
            <small class="text-muted">Format: <code>Question Text,Score Values</code> (e.g.
              <code>How are you?,0.5,1,2</code>)</small>
          </div>
          <div class="col-md-3">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="upload_questions" class="btn btn-info w-100">Upload Questions</button>
          </div>
        </form>
      </div>

      <!-- Step 4: Assign Examiners to Existing Exams -->
      <div class="card p-4 shadow-sm mb-4">
        <div class="d-flex align-items-center mb-3">
          <span class="badge bg-info me-3 fs-6">Step 4</span>
          <h4 class="mb-0">Assign Examiners to Exam</h4>
        </div>
        <p class="text-muted mb-3">Select an existing exam and assign examiners who will conduct the assessments.</p>
        <form method="post" class="row g-3">
          <div class="col-md-4">
            <label for="exam_id_examiners" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id_examiners" class="form-select" required>
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label for="examiners" class="form-label">Select Examiners</label>
            <select name="examiners[]" id="examiners" class="form-select" multiple size="4">
              <?php foreach ($examiners as $examiner): ?>
                <option value="<?= $examiner['id'] ?>"><?= htmlspecialchars($examiner['name']) ?>
                  (<?= htmlspecialchars($examiner['username']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple examiners</small>
          </div>
          <div class="col-md-2">
            <label class="form-label">&nbsp;</label>
            <button type="submit" name="assign_examiners" class="btn btn-info w-100">Assign Examiners</button>
          </div>
        </form>
      </div>

      <!-- Exam Management Section -->
      <div class="card p-4 shadow-sm mb-4">
        <h4 class="mb-3">📋 Exam Management (<?= count($exams) ?> total)</h4>
        <p class="text-muted mb-3">View, modify, and manage all created exams. You can activate/deactivate exams and
          modify their content.</p>

        <?php if (empty($exams)): ?>
          <div class="alert alert-info">
            <strong>No exams created yet.</strong> Start by creating your first exam using Step 1 above.
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Exam Name</th>
                  <th>Status</th>
                  <th>Created</th>
                  <th>Students</th>
                  <th>Questions</th>
                  <th>Examiners</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $exam): ?>
                  <tr>
                    <td>
                      <strong><?= htmlspecialchars($exam['name']) ?></strong>
                    </td>
                    <td>
                      <?= $exam['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?>
                    </td>
                    <td><?= htmlspecialchars($exam['created_at']) ?></td>
                    <td>
                      <?php
                      $student_count = isset($exam_students[$exam['id']]) ? count($exam_students[$exam['id']]) : 0;
                      ?>
                      <span class="badge bg-info"><?= $student_count ?> students</span>
                      <?php if ($student_count > 0): ?>
                        <button class="btn btn-sm btn-outline-info ms-1"
                          onclick="showStudents(<?= $exam['id'] ?>)">View</button>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                      $question_count = isset($exam_questions[$exam['id']]) ? count($exam_questions[$exam['id']]) : 0;
                      ?>
                      <span class="badge bg-warning"><?= $question_count ?> questions</span>
                      <?php if ($question_count > 0): ?>
                        <button class="btn btn-sm btn-outline-warning ms-1"
                          onclick="showQuestions(<?= $exam['id'] ?>)">View</button>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                      $examiner_count = isset($exam_examiners[$exam['id']]) ? count($exam_examiners[$exam['id']]) : 0;
                      ?>
                      <span class="badge bg-secondary"><?= $examiner_count ?> examiners</span>
                      <?php if ($examiner_count > 0): ?>
                        <button class="btn btn-sm btn-outline-secondary ms-1"
                          onclick="showExaminers(<?= $exam['id'] ?>)">View</button>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="btn-group" role="group">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                          <?php if ($exam['is_active']): ?>
                            <button type="submit" name="deactivate" class="btn btn-warning btn-sm">Deactivate</button>
                          <?php else: ?>
                            <button type="submit" name="activate" class="btn btn-success btn-sm">Activate</button>
                          <?php endif; ?>
                        </form>
                        <button class="btn btn-info btn-sm"
                          onclick="editExam(<?= $exam['id'] ?>, '<?= addslashes($exam['name']) ?>', <?= $exam['is_active'] ?>, <?= $exam['is_strict'] ?>, <?= $exam['proctor_fullscreen'] ?>, <?= $exam['proctor_tab_lock'] ?>, <?= $exam['proctor_kb_lock'] ?>, <?= $exam['proctor_auto_submit'] ?>, <?= $exam['proctor_browser_check'] ?>, <?= $exam['proctor_webcam'] ?>, <?= $exam['proctor_live_video'] ?? 0 ?>, '<?= $exam['start_time'] ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : '' ?>', '<?= $exam['end_time'] ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : '' ?>')">Edit</button>
                        <form method="post" class="d-inline"
                          onsubmit="return confirm('Are you sure you want to delete this exam? This will remove all associated data.')">
                          <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                          <button type="submit" name="delete_exam" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                      </div>
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

  <!-- Edit Exam Modal -->
  <div class="modal fade" id="editExamModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="exam_id" id="edit_exam_id">
            <div class="mb-3">
              <label for="edit_exam_name" class="form-label">Exam Name</label>
              <input type="text" class="form-control" id="edit_exam_name" name="exam_name" required>
            </div>
            <div class="mb-3">
              <label for="edit_is_active" class="form-label">Status</label>
              <select name="is_active" id="edit_is_active" class="form-select">
                <option value="0">Inactive</option>
                <option value="1">Active</option>
              </select>
            </div>
            <div class="row">
              <div class="col-6 mb-3">
                <label for="edit_start_time" class="form-label">Start Time</label>
                <input type="datetime-local" class="form-control" id="edit_start_time" name="start_time">
              </div>
              <div class="col-6 mb-3">
                <label for="edit_end_time" class="form-label">End Time</label>
                <input type="datetime-local" class="form-control" id="edit_end_time" name="end_time">
              </div>
            </div>
            <div class="mb-3">
              <label for="edit_is_strict" class="form-label font-monospace" style="font-size: 0.8rem;">Master Toggle
                (Strict Mode)</label>
              <select name="is_strict" id="edit_is_strict" class="form-select mb-3">
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>

              <div class="p-3 bg-light rounded-4 border">
                <div class="fw-bold mb-2 small text-muted">Proctoring Layers</div>
                <div class="row g-2">
                  <div class="col-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_fullscreen" id="edit_pfs">
                      <label class="form-check-label small" for="edit_pfs">Fullscreen</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_tab_lock" id="edit_ptab">
                      <label class="form-check-label small" for="edit_ptab">Tab/Focus Lock</label>
                    </div>
                  </div>
                  <div class="col-6">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_kb_lock" id="edit_pkb">
                      <label class="form-check-label small" for="edit_pkb">Keyboard Lock</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_auto_submit" id="edit_pauto">
                      <label class="form-check-label small" for="edit_pauto">Auto Submit</label>
                    </div>
                  </div>
                  <div class="col-12">
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_browser_check" id="edit_pbc">
                      <label class="form-check-label small" for="edit_pbc">Browser Integrity Check</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_webcam" id="edit_pwc">
                      <label class="form-check-label small" for="edit_pwc">Webcam Monitor</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" name="proctor_live_video" id="edit_plive">
                      <label class="form-check-label small text-danger fw-bold" for="edit_plive">Live Video
                        Stream</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit_exam" class="btn btn-primary">Update Exam</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Students Modal -->
  <div class="modal fade" id="studentsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Students in Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="studentsModalBody">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <!-- Questions Modal -->
  <div class="modal fade" id="questionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Questions in Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="questionsModalBody">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <!-- Examiners Modal -->
  <div class="modal fade" id="examinersModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Examiners in Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="examinersModalBody">
          <!-- Content will be loaded here -->
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
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

    // Edit exam function
    function editExam(examId, examName, isActive, isStrict, pfs, ptab, pkb, pauto, pbc, pwc, plive, startTime, endTime) {
      document.getElementById('edit_exam_id').value = examId;
      document.getElementById('edit_exam_name').value = examName;
      document.getElementById('edit_is_active').value = isActive ? '1' : '0';
      document.getElementById('edit_is_strict').value = isStrict ? '1' : '0';

      document.getElementById('edit_pfs').checked = pfs == 1;
      document.getElementById('edit_ptab').checked = ptab == 1;
      document.getElementById('edit_pkb').checked = pkb == 1;
      document.getElementById('edit_pauto').checked = pauto == 1;
      document.getElementById('edit_pbc').checked = pbc == 1;
      document.getElementById('edit_pwc').checked = pwc == 1;
      document.getElementById('edit_plive').checked = plive == 1;
      document.getElementById('edit_start_time').value = startTime || '';
      document.getElementById('edit_end_time').value = endTime || '';
    }

    // Show students in modal
    function showStudents(examId) {
      const students = <?= json_encode($exam_students) ?>;
      const allStudents = <?= json_encode($students) ?>;
      const examStudents = students[examId] || [];

      let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Name</th><th>Matric No</th><th>Username</th><th>Action</th></tr></thead><tbody>';

      examStudents.forEach(studentId => {
        const student = allStudents.find(s => s.id == studentId);
        if (student) {
          html += `<tr>
            <td>${student.name}</td>
            <td>${student.matric_no}</td>
            <td>${student.username}</td>
            <td>
              <form method="post" class="d-inline" onsubmit="return confirm('Remove this student from the exam?')">
                <input type="hidden" name="exam_id" value="${examId}">
                <input type="hidden" name="student_id" value="${studentId}">
                <button type="submit" name="remove_student" class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>`;
        }
      });

      html += '</tbody></table></div>';

      if (examStudents.length === 0) {
        html = '<div class="alert alert-info">No students assigned to this exam yet.</div>';
      }

      document.getElementById('studentsModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('studentsModal')).show();
    }

    // Show questions in modal
    function showQuestions(examId) {
      const questions = <?= json_encode($exam_questions) ?>;
      const examQuestions = questions[examId] || [];

      let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Question</th><th>Score Values</th><th>Action</th></tr></thead><tbody>';

      examQuestions.forEach(question => {
        html += `<tr>
          <td>${question.text}</td>
          <td><code>${question.score_values}</code></td>
          <td>
            <form method="post" class="d-inline" onsubmit="return confirm('Remove this question from the exam?')">
              <input type="hidden" name="exam_id" value="${examId}">
              <input type="hidden" name="question_id" value="${question.id}">
              <button type="submit" name="remove_question" class="btn btn-sm btn-outline-danger">Remove</button>
            </form>
          </td>
        </tr>`;
      });

      html += '</tbody></table></div>';

      if (examQuestions.length === 0) {
        html = '<div class="alert alert-info">No questions assigned to this exam yet.</div>';
      }

      document.getElementById('questionsModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('questionsModal')).show();
    }

    // Show examiners in modal
    function showExaminers(examId) {
      const examiners = <?= json_encode($examiners) ?>;
      const examExaminers = <?= json_encode($exam_examiners) ?>[examId] || [];

      let html = '<div class="table-responsive"><table class="table"><thead><tr><th>Examiner</th><th>Action</th></tr></thead><tbody>';

      examExaminers.forEach(examinerId => {
        const examiner = examiners.find(e => e.id == examinerId);
        if (examiner) {
          html += `<tr>
            <td>${examiner.name}</td>
            <td>
              <form method="post" class="d-inline" onsubmit="return confirm('Remove this examiner from the exam?')">
                <input type="hidden" name="exam_id" value="${examId}">
                <input type="hidden" name="examiner_id" value="${examinerId}">
                <button type="submit" name="remove_examiner" class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>`;
        }
      });

      html += '</tbody></table></div>';

      if (examExaminers.length === 0) {
        html = '<div class="alert alert-info">No examiners assigned to this exam yet.</div>';
      }

      document.getElementById('examinersModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('examinersModal')).show();
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
