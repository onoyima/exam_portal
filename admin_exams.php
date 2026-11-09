<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require 'db_exeat2.php';
require_once __DIR__ . '/includes/student_import_helper.php';
$name = $_SESSION['user'] ?? 'Admin';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // a centre may switch received exams on/off and choose examiners; the content stays as the main server sent it
  $cs_free = ['activate', 'deactivate', 'assign_examiners', 'examiners', 'remove_examiner'];
  if (!empty($_POST['exam_id']) && !array_intersect($cs_free, array_keys($_POST))) {
    cs_guard_exam($db, (int) $_POST['exam_id'], 'admin_exams');
  }
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

    $stmt = $db->prepare("INSERT INTO exams(name, is_active, is_strict, session_id, proctor_fullscreen, proctor_tab_lock, proctor_kb_lock, proctor_auto_submit, proctor_browser_check, proctor_webcam, proctor_live_video, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$_POST['exam_name'], $_POST['is_active'] ? 1 : 0, $_POST['is_strict'] ? 1 : 0, $_POST['session_id'], $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time]);
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

    $stmt = $db->prepare("UPDATE exams SET name = ?, is_active = ?, is_strict = ?, session_id = ?, proctor_fullscreen = ?, proctor_tab_lock = ?, proctor_kb_lock = ?, proctor_auto_submit = ?, proctor_browser_check = ?, proctor_webcam = ?, proctor_live_video = ?, start_time = ?, end_time = ? WHERE id = ?");
    $stmt->execute([$_POST['exam_name'], $_POST['is_active'] ? 1 : 0, $_POST['is_strict'] ? 1 : 0, $_POST['session_id'], $p_fs, $p_tab, $p_kb, $p_auto, $p_browser, $p_webcam, $p_live, $start_time, $end_time, $_POST['exam_id']]);
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
    $updated_count = 0;
    $skipped_count = 0;

    if (isset($_FILES['students_file']) && $_FILES['students_file']['tmp_name']) {
      [$col_map, $header_found, $records] = sih_read_student_csv($_FILES['students_file']['tmp_name']);

      if (empty($records)) {
        $error = 'Empty file or no readable rows.';
      } else {
        $parsed = [];
        foreach ($records as $record) {
          $fields = sih_parse_fields($record, $col_map, $header_found);
          if ($fields['matric_no'] === '') {
            $skipped_count++;
            continue;
          }
          $parsed[] = $fields;
        }

        $school_by_matric = sih_exeat2_lookup($exeat2_db, array_column($parsed, 'matric_no'));

        foreach ($parsed as $fields) {
          $matric = $fields['matric_no'];
          $school = $school_by_matric[$matric] ?? null;

          $saved = sih_upsert_student(
            $db,
            $fields,
            $school,
            true,
            !empty($fields['password_provided'])
          );
          $sid = $saved['id'];
          $saved['created'] ? $uploaded_count++ : $updated_count++;

          // Assign student to exam
          $db->prepare("INSERT IGNORE INTO exam_students(exam_id, student_id) VALUES (?, ?)")->execute([$exam_id, $sid]);
        }

        $success = "$uploaded_count new student(s) imported, $updated_count updated, and assigned to the exam"
          . ($skipped_count ? " ($skipped_count row(s) skipped without a matric number)." : ".");
      }
    } else {
      $error = "Please select a file to upload.";
    }
  } elseif (isset($_POST['assign_all_students'])) {
    $exam_id = $_POST['exam_id'];
    $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE deleted_at IS NULL")->execute([$exam_id]);
    $success = "All students assigned to exam successfully!";
  } elseif (isset($_POST['assign_by_matric_prefix'])) {
    $exam_id = $_POST['exam_id'];
    $prefix = trim($_POST['matric_prefix']);
    if (!empty($prefix)) {
      $stmt = $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE matric_no LIKE ? AND deleted_at IS NULL");
      $stmt->execute([$exam_id, $prefix . '%']);
      $success = "Students with matric prefix '$prefix' assigned to exam successfully!";
    } else {
      $error = "Please enter a matric prefix.";
    }
  } elseif (isset($_POST['assign_by_email_domain'])) {
    $exam_id = $_POST['exam_id'];
    $domain = '%' . trim($_POST['email_domain']);
    if (!empty($domain)) {
      $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) SELECT ?, id FROM students WHERE email LIKE ? AND deleted_at IS NULL")->execute([$exam_id, $domain]);
      $success = "Students with email domain assigned to exam successfully!";
    } else {
      $error = "Please enter an email domain.";
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
    // Soft delete exam (mark as deleted, keep child data for audit)
    $exam_id = $_POST['exam_id'];
    $db->prepare("UPDATE exams SET deleted_at=NOW() WHERE id=?")->execute([$exam_id]);
    $success = "Exam deleted successfully!";
  }

  $tab_param = isset($_POST['current_tab']) ? '&tab=' . urlencode($_POST['current_tab']) : '';
  if (isset($success)) {
    header('Location: admin_exams?success=' . urlencode($success) . $tab_param);
    exit;
  } elseif (isset($error)) {
    header('Location: admin_exams?error=' . urlencode($error) . $tab_param);
    exit;
  }
}

$sessions = $db->query("SELECT * FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$exams = $db->query("SELECT * FROM exams WHERE deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$students = $db->query("SELECT * FROM students WHERE deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$questions = $db->query("SELECT * FROM questions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$examiners = $db->query("SELECT * FROM examiners WHERE username != 'admin' AND deleted_at IS NULL ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

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

// For each exam, get assigned students, questions, and examiners
$exam_students = [];
$exam_questions = [];
$exam_examiners = [];
$stmt = $db->prepare("SELECT student_id FROM exam_students WHERE exam_id=?");
$stmt2 = $db->prepare("SELECT q.* FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=? AND q.deleted_at IS NULL");
$stmt3 = $db->prepare("SELECT examiner_id FROM exam_examiners WHERE exam_id=?");
foreach ($exams as $exam) {
  $stmt->execute([$exam['id']]);
  $exam_students[$exam['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $stmt2->execute([$exam['id']]);
  $exam_questions[$exam['id']] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
  $stmt3->execute([$exam['id']]);
  $exam_examiners[$exam['id']] = $stmt3->fetchAll(PDO::FETCH_COLUMN);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Manage Exams - Assessment Portal</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
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
          <h1 class="admin-navbar-title">Manage Exams</h1>
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
          <li class="current" aria-current="page">Manage Exams</li>
        </ol>
      </nav>

      <?php if (isset($_GET['success'])): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($_GET['success']) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($_GET['error'])): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span><?= htmlspecialchars($_GET['error']) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Exams</h2>
        <div class="admin-page-actions">
          <span class="admin-badge admin-badge-primary"><i class="bi bi-collection"></i> <?= count($exams) ?> total</span>
        </div>
      </div>

      <!-- Tabbed Navigation -->
      <ul class="nav nav-tabs admin-exam-tabs mb-4" id="examTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="tab-create" data-bs-toggle="tab" data-bs-target="#panel-create" type="button" role="tab">
            <i class="bi bi-plus-circle"></i> Create Exam
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-students" data-bs-toggle="tab" data-bs-target="#panel-students" type="button" role="tab">
            <i class="bi bi-people"></i> Students
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-questions" data-bs-toggle="tab" data-bs-target="#panel-questions" type="button" role="tab">
            <i class="bi bi-question-circle"></i> Questions
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-examiners" data-bs-toggle="tab" data-bs-target="#panel-examiners" type="button" role="tab">
            <i class="bi bi-person-badge"></i> Examiners
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="tab-manage" data-bs-toggle="tab" data-bs-target="#panel-manage" type="button" role="tab">
            <i class="bi bi-collection"></i> Manage Exams
            <span class="admin-badge admin-badge-primary ms-1"><?= count($exams) ?></span>
          </button>
        </li>
      </ul>

      <div class="tab-content" id="examTabsContent">

        <!-- ── Tab: Create Exam ── -->
        <div class="tab-pane fade show active" id="panel-create" role="tabpanel">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-plus-circle"></i> Create New Exam</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted mb-4">Create a new exam. You can add students and questions to it after creation.</p>
              <form method="post">
                <div class="row g-3">
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label for="session_id" class="admin-form-label">Academic Session</label>
                      <select name="session_id" id="session_id" class="admin-input admin-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($sessions as $s): ?>
                          <option value="<?= $s['id'] ?>" <?= $s['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($s['session_name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label for="exam_name" class="admin-form-label">Exam Name</label>
                      <input type="text" name="exam_name" id="exam_name" class="admin-input" placeholder="Enter exam name" required>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label for="is_active" class="admin-form-label">Initial Status</label>
                      <select name="is_active" id="is_active" class="admin-input admin-select">
                        <option value="0">Inactive (Recommended)</option>
                        <option value="1">Active</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label for="is_strict" class="admin-form-label">Tab Security</label>
                      <select name="is_strict" id="is_strict" class="admin-input admin-select">
                        <option value="1">Strict (Enabled)</option>
                        <option value="0">Flexible (Disabled)</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label class="admin-form-label">Start Time</label>
                      <input type="datetime-local" name="start_time" class="admin-input">
                    </div>
                  </div>
                  <div class="col-md-3">
                    <div class="admin-form-group">
                      <label class="admin-form-label">End Time</label>
                      <input type="datetime-local" name="end_time" class="admin-input">
                    </div>
                  </div>
                </div>
                <details class="mb-3 cursor-pointer">
                  <summary class="admin-form-label fw-semibold"><i class="bi bi-shield-check"></i> Proctoring Features</summary>
                  <div class="admin-checkbox-group mt-2">
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_fullscreen" checked> Fullscreen</label>
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_tab_lock" checked> Tab/Focus</label>
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_kb_lock" checked> Keyboard/Copy</label>
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_auto_submit" checked> 3-Strike Auto</label>
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_browser_check" checked> Browser Check</label>
                    <label class="admin-checkbox"><input type="checkbox" name="proctor_webcam" checked> Webcam Monitor</label>
                    <label class="admin-checkbox text-danger fw-semibold"><input type="checkbox" name="proctor_live_video"> Live Video Stream</label>
                  </div>
                </details>
                <div class="text-end mt-4">
                  <button type="reset" class="admin-btn admin-btn-outline me-2" onclick="document.querySelector('#panel-create form').reset()">Reset</button>
                  <button type="submit" name="create_exam" class="admin-btn admin-btn-primary"><i class="bi bi-plus-lg"></i> Create Exam</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- ── Tab: Students ── -->
        <div class="tab-pane fade" id="panel-students" role="tabpanel">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-people"></i> Assign Students</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted mb-4">Select an existing exam and assign students to it.</p>

              <div class="admin-card border mb-4">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-person-plus"></i> Select Existing Students</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-4">
                      <label for="exam_id_students" class="admin-form-label">Select Exam</label>
                      <select name="exam_id" id="exam_id_students" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="students" class="admin-form-label">Select Existing Students</label>
                      <select name="students[]" id="students" class="admin-input" multiple size="4">
                        <?php foreach ($students as $stu): ?>
                          <option value="<?= $stu['id'] ?>"><?= htmlspecialchars($stu['name']) ?> (<?= htmlspecialchars($stu['matric_no']) ?>)</option>
                        <?php endforeach; ?>
                      </select>
                      <small class="admin-form-label opacity-50">Hold Ctrl/Cmd to select multiple</small>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="submit" name="assign_students" class="admin-btn admin-btn-primary w-100"><i class="bi bi-person-check"></i> Assign</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="admin-card border">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-upload"></i> Bulk Upload Students</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-4">
                      <label for="exam_id_upload_students" class="admin-form-label">Select Exam</label>
                      <select name="exam_id" id="exam_id_upload_students" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="students_file" class="admin-form-label">Upload CSV/TXT File</label>
                      <input type="file" name="students_file" id="students_file" class="admin-input" accept=".csv,.txt" required>
                      <small class="admin-form-label opacity-50">Format: <code>firstname,middlename,lastname,email,matric_no,password</code></small>
                      <br>
                      <a href="download_template.php?type=student_bulk" class="admin-btn admin-btn-sm admin-btn-outline mt-1"><i class="bi bi-download"></i> CSV Template</a>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="submit" name="upload_students" class="admin-btn admin-btn-primary w-100"><i class="bi bi-cloud-upload"></i> Upload</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Import from School Portal (exeat2) -->
              <div class="admin-card border mb-4">
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
                        <input type="text" id="exeat2StudentSearch" class="admin-filter-input" style="width:auto;min-width:200px" placeholder="Search by name or email...">
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

                      <!-- Exam selector for exeat2 -->
                      <div class="mb-3">
                        <label class="admin-form-label fw-semibold">Assign to Exam</label>
                        <select id="exeat2ExamSelect" class="admin-input admin-select" required>
                          <option value="">-- Select Exam --</option>
                          <?php foreach ($exams as $exam): ?>
                            <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
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
                      <div class="d-flex flex-wrap gap-2 align-items-center mt-3" id="exeat2Actions">
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

              <div class="admin-card border mb-4">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-person-check-fill"></i> Assign All Students</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-4">
                      <select name="exam_id" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-8 d-flex align-items-end">
                      <button type="submit" name="assign_all_students" class="admin-btn admin-btn-primary" data-swal="Assign every student in the system to this exam?"><i class="bi bi-person-plus-fill"></i> Assign All Students</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="admin-card border mb-4">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-filter"></i> Assign by Matric Prefix</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-4">
                      <select name="exam_id" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <input type="text" name="matric_prefix" class="admin-input" placeholder="e.g. VUG/BNS/22" required>
                      <small class="admin-form-label opacity-50">Assigns all students whose matric starts with this prefix</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                      <button type="submit" name="assign_by_matric_prefix" class="admin-btn admin-btn-primary w-100"><i class="bi bi-funnel"></i> Assign by Prefix</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="admin-card border">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-globe"></i> Assign by Email Domain</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-4">
                      <select name="exam_id" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <input type="text" name="email_domain" class="admin-input" placeholder="@domain.com" required>
                      <small class="admin-form-label opacity-50">Assigns all students whose email contains this domain</small>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                      <button type="submit" name="assign_by_email_domain" class="admin-btn admin-btn-primary w-100"><i class="bi bi-mailbox"></i> Assign by Domain</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Tab: Questions ── -->
        <div class="tab-pane fade" id="panel-questions" role="tabpanel">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-question-circle"></i> Add Questions</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted mb-4">Add questions to existing exams.</p>

              <div class="admin-card border mb-4">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-bookmark-plus"></i> Add Existing Question</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-3">
                      <label for="exam_id_questions" class="admin-form-label">Select Exam</label>
                      <select name="exam_id" id="exam_id_questions" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="question_id" class="admin-form-label">Select Question</label>
                      <select name="question_id" id="question_id" class="admin-input admin-select" required>
                        <option value="">-- Select Question --</option>
                        <?php foreach ($questions as $q): ?>
                          <option value="<?= $q['id'] ?>"><?= htmlspecialchars($q['text']) ?> (<?= htmlspecialchars($q['score_values']) ?>)</option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                      <button type="submit" name="add_existing_question" class="admin-btn admin-btn-primary w-100"><i class="bi bi-plus"></i> Add Question</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="admin-card border mb-4">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-pencil-square"></i> Create New Question</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" class="row g-3">
                    <div class="col-md-3">
                      <label for="exam_id_new" class="admin-form-label">Select Exam</label>
                      <select name="exam_id" id="exam_id_new" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label for="text" class="admin-form-label">Question Text</label>
                      <input type="text" name="text" id="text" class="admin-input" placeholder="Enter question text" required>
                    </div>
                    <div class="col-md-3">
                      <label for="score_values" class="admin-form-label">Score Values</label>
                      <input type="text" name="score_values" id="score_values" class="admin-input" placeholder="e.g. 0.5,1,2" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                      <button type="submit" name="add_question" class="admin-btn admin-btn-primary w-100"><i class="bi bi-plus"></i> Create</button>
                    </div>
                  </form>
                </div>
              </div>

              <div class="admin-card border">
                <div class="admin-card-header">
                  <span class="admin-card-title fw-semibold"><i class="bi bi-upload"></i> Bulk Upload Questions</span>
                </div>
                <div class="admin-card-body">
                  <form method="post" enctype="multipart/form-data" class="row g-3">
                    <div class="col-md-3">
                      <label for="exam_id_upload" class="admin-form-label">Select Exam</label>
                      <select name="exam_id" id="exam_id_upload" class="admin-input admin-select" required>
                        <option value="">-- Select Exam --</option>
                        <?php foreach ($exams as $exam): ?>
                          <option value="<?= $exam['id'] ?>"><?= htmlspecialchars($exam['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="questions_file" class="admin-form-label">Upload CSV/TXT File</label>
                      <input type="file" name="questions_file" id="questions_file" class="admin-input" accept=".csv,.txt" required>
                      <small class="admin-form-label opacity-50">Format: <code>Question Text,Score Values</code></small>
                      <br>
                      <a href="download_template.php?type=practical_steps" class="admin-btn admin-btn-sm admin-btn-outline mt-1"><i class="bi bi-download"></i> CSV Template</a>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                      <button type="submit" name="upload_questions" class="admin-btn admin-btn-primary w-100"><i class="bi bi-cloud-upload"></i> Upload</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ── Tab: Examiners ── -->
        <div class="tab-pane fade" id="panel-examiners" role="tabpanel">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-person-badge"></i> Assign Examiners</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted mb-4">Select an existing exam and assign examiners.</p>
              <form method="post" class="row g-3">
                <div class="col-md-4">
                  <label for="exam_id_examiners" class="admin-form-label">Select Exam</label>
                  <select name="exam_id" id="exam_id_examiners" class="admin-input admin-select" required>
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $exam): ?>
                      <option value="<?= $exam['id'] ?>" <?= ($_GET['exam_id'] ?? '') == $exam['id'] ? 'selected' : '' ?>><?= htmlspecialchars($exam['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-6">
                  <label for="examiners" class="admin-form-label">Select Examiners</label>
                  <select name="examiners[]" id="examiners" class="admin-input" multiple size="4">
                    <?php foreach ($examiners as $examiner): ?>
                      <option value="<?= $examiner['id'] ?>"><?= htmlspecialchars($examiner['name']) ?> (<?= htmlspecialchars($examiner['username']) ?>)</option>
                    <?php endforeach; ?>
                  </select>
                  <small class="admin-form-label opacity-50">Hold Ctrl/Cmd to select multiple</small>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                  <button type="submit" name="assign_examiners" class="admin-btn admin-btn-primary w-100"><i class="bi bi-person-check"></i> Assign</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <!-- ── Tab: Manage Exams ── -->
        <div class="tab-pane fade" id="panel-manage" role="tabpanel">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-collection"></i> All Exams</h3>
              <span class="admin-badge admin-badge-primary"><i class="bi bi-collection"></i> <?= count($exams) ?> total</span>
            </div>
            <div class="admin-card-body-flush">
              <?php if (empty($exams)): ?>
                <div class="admin-empty">
                  <div class="admin-empty-icon"><i class="bi bi-inbox admin-empty-icon-lg"></i></div>
                  <div class="admin-empty-title">No exams created yet</div>
                  <div class="admin-empty-desc">Create your first exam to get started.</div>
                  <button class="admin-btn admin-btn-primary" onclick="document.getElementById('tab-create').click()"><i class="bi bi-plus-circle"></i> Create Exam</button>
                </div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="admin-table">
                    <thead>
                      <tr>
                        <th>Exam Name</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Students</th>
                        <th>Questions</th>
                        <th>Examiners</th>
                        <th class="actions-cell">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($exams as $exam): ?>
                        <tr>
                          <td><strong><?= htmlspecialchars($exam['name']) ?></strong></td>
                          <td><?= $exam['is_active'] ? '<span class="admin-badge admin-badge-success"><i class="bi bi-check-circle"></i> Active</span>' : '<span class="admin-badge admin-badge-neutral"><i class="bi bi-pause-circle"></i> Inactive</span>' ?></td>
                          <td><?= htmlspecialchars($exam['created_at']) ?></td>
                          <td>
                            <?php $student_count = isset($exam_students[$exam['id']]) ? count($exam_students[$exam['id']]) : 0; ?>
                            <span class="admin-badge admin-badge-info"><?= $student_count ?></span>
                            <?php if ($student_count > 0): ?>
                              <button class="admin-btn admin-btn-sm admin-btn-outline ms-1" onclick="showStudents(<?= $exam['id'] ?>)"><i class="bi bi-eye"></i></button>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php $question_count = isset($exam_questions[$exam['id']]) ? count($exam_questions[$exam['id']]) : 0; ?>
                            <span class="admin-badge admin-badge-warning"><?= $question_count ?></span>
                            <?php if ($question_count > 0): ?>
                              <button class="admin-btn admin-btn-sm admin-btn-outline ms-1" onclick="showQuestions(<?= $exam['id'] ?>)"><i class="bi bi-eye"></i></button>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php $examiner_count = isset($exam_examiners[$exam['id']]) ? count($exam_examiners[$exam['id']]) : 0; ?>
                            <span class="admin-badge admin-badge-neutral"><?= $examiner_count ?></span>
                            <?php if ($examiner_count > 0): ?>
                              <button class="admin-btn admin-btn-sm admin-btn-outline ms-1" onclick="showExaminers(<?= $exam['id'] ?>)"><i class="bi bi-eye"></i></button>
                            <?php endif; ?>
                          </td>
                          <td class="actions-cell">
                            <form method="post" class="d-inline">
                              <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                              <?php if ($exam['is_active']): ?>
                                <button type="submit" name="deactivate" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-pause"></i></button>
                              <?php else: ?>
                                <button type="submit" name="activate" class="admin-btn admin-btn-sm admin-btn-primary"><i class="bi bi-play"></i> Activate</button>
                              <?php endif; ?>
                            </form>
                            <button class="admin-btn admin-btn-sm admin-btn-outline" onclick="editExam(<?= $exam['id'] ?>, '<?= addslashes($exam['name']) ?>', <?= $exam['is_active'] ?>, <?= $exam['is_strict'] ?>, <?= $exam['proctor_fullscreen'] ?>, <?= $exam['proctor_tab_lock'] ?>, <?= $exam['proctor_kb_lock'] ?>, <?= $exam['proctor_auto_submit'] ?>, <?= $exam['proctor_browser_check'] ?>, <?= $exam['proctor_webcam'] ?>, <?= $exam['proctor_live_video'] ?? 0 ?>, '<?= $exam['start_time'] ? date('Y-m-d\TH:i', strtotime($exam['start_time'])) : '' ?>', '<?= $exam['end_time'] ? date('Y-m-d\TH:i', strtotime($exam['end_time'])) : '' ?>', <?= $exam['session_id'] ?? 0 ?>)"><i class="bi bi-pencil"></i> Edit</button>
                            <form method="post" class="d-inline" data-swal="Are you sure you want to delete this exam? This will remove all associated data.">
                              <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                              <button type="submit" name="delete_exam" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Delete</button>
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
    </div>
  </div>

  <!-- Edit Exam Modal -->
  <div class="modal fade" id="editExamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Exam</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="post">
          <div class="modal-body">
            <input type="hidden" name="exam_id" id="edit_exam_id">
            <div class="admin-form-group">
              <label for="edit_session_id" class="admin-form-label">Academic Session</label>
              <select name="session_id" id="edit_session_id" class="admin-input admin-select" required>
                <?php foreach ($sessions as $s): ?>
                  <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['session_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="admin-form-group">
              <label for="edit_exam_name" class="admin-form-label">Exam Name</label>
              <input type="text" class="admin-input" id="edit_exam_name" name="exam_name" required>
            </div>
            <div class="admin-form-group">
              <label for="edit_is_active" class="admin-form-label">Status</label>
              <select name="is_active" id="edit_is_active" class="admin-input admin-select">
                <option value="0">Inactive</option>
                <option value="1">Active</option>
              </select>
            </div>
            <div class="row">
              <div class="col-6">
                <div class="admin-form-group">
                  <label for="edit_start_time" class="admin-form-label">Start Time</label>
                  <input type="datetime-local" class="admin-input" id="edit_start_time" name="start_time">
                </div>
              </div>
              <div class="col-6">
                <div class="admin-form-group">
                  <label for="edit_end_time" class="admin-form-label">End Time</label>
                  <input type="datetime-local" class="admin-input" id="edit_end_time" name="end_time">
                </div>
              </div>
            </div>
            <div class="admin-form-group">
              <label for="edit_is_strict" class="admin-form-label">Master Toggle (Strict Mode)</label>
              <select name="is_strict" id="edit_is_strict" class="admin-input admin-select mb-3">
                <option value="1">Enabled</option>
                <option value="0">Disabled</option>
              </select>
              <div class="admin-checkbox-group">
                <div class="fw-semibold mb-2 w-100 text-muted small text-uppercase">Proctoring Layers</div>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_fullscreen" id="edit_pfs"> Fullscreen</label>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_tab_lock" id="edit_ptab"> Tab/Focus Lock</label>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_kb_lock" id="edit_pkb"> Keyboard Lock</label>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_auto_submit" id="edit_pauto"> Auto Submit</label>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_browser_check" id="edit_pbc"> Browser Check</label>
                <label class="admin-checkbox"><input type="checkbox" name="proctor_webcam" id="edit_pwc"> Webcam Monitor</label>
                <label class="admin-checkbox text-danger fw-semibold"><input type="checkbox" name="proctor_live_video" id="edit_plive"> Live Video Stream</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="admin-btn admin-btn-outline" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit_exam" class="admin-btn admin-btn-primary"><i class="bi bi-check-lg"></i> Update Exam</button>
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
        <div class="modal-body" id="studentsModalBody"></div>
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
        <div class="modal-body" id="questionsModalBody"></div>
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
        <div class="modal-body" id="examinersModalBody"></div>
      </div>
    </div>
  </div>

  <!-- exeat2 Import Result Modal -->
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

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>
    // ── Tab persistence: remember last active tab across reloads ──
    document.addEventListener('DOMContentLoaded', function() {
      var tabs = document.querySelectorAll('#examTabs .nav-link');
      var stored = sessionStorage.getItem('admin_exams_tab');

      <?php if (isset($_GET['exam_id'])): ?>
      stored = 'tab-examiners';
      <?php elseif (isset($_GET['tab'])): ?>
      stored = 'tab-<?= preg_replace('/[^a-z0-9_-]/', '', $_GET['tab']) ?>';
      <?php endif; ?>

      if (stored) {
        var target = document.getElementById(stored);
        if (target) {
          target.click();
        } else {
          sessionStorage.removeItem('admin_exams_tab');
        }
      }

      tabs.forEach(function(tab) {
        tab.addEventListener('shown.bs.tab', function() {
          sessionStorage.setItem('admin_exams_tab', this.id);
        });
      });

      <?php if (isset($_GET['exam_id'])): ?>
      var examSelect = document.getElementById('exam_id_examiners');
      if (examSelect) examSelect.value = '<?= (int)$_GET['exam_id'] ?>';
      <?php endif; ?>

      // ── Inject current tab into every form submit ──
      document.querySelectorAll('.admin-content form').forEach(function(form) {
        form.addEventListener('submit', function() {
          var activeTab = document.querySelector('#examTabs .nav-link.active');
          if (activeTab) {
            var h = document.createElement('input');
            h.type = 'hidden';
            h.name = 'current_tab';
            h.value = activeTab.id;
            this.appendChild(h);
          }
        });
      });
    });
    function switchTab(tabId) {
      var tab = document.getElementById(tabId);
      if (tab) tab.click();
    }

    function editExam(examId, examName, isActive, isStrict, pfs, ptab, pkb, pauto, pbc, pwc, plive, startTime, endTime, sessionId) {
      document.getElementById('edit_exam_id').value = examId;
      document.getElementById('edit_exam_name').value = examName;
      document.getElementById('edit_is_active').value = isActive ? '1' : '0';
      document.getElementById('edit_is_strict').value = isStrict ? '1' : '0';
      document.getElementById('edit_session_id').value = sessionId;
      document.getElementById('edit_pfs').checked = pfs == 1;
      document.getElementById('edit_ptab').checked = ptab == 1;
      document.getElementById('edit_pkb').checked = pkb == 1;
      document.getElementById('edit_pauto').checked = pauto == 1;
      document.getElementById('edit_pbc').checked = pbc == 1;
      document.getElementById('edit_pwc').checked = pwc == 1;
      document.getElementById('edit_plive').checked = plive == 1;
      document.getElementById('edit_start_time').value = startTime || '';
      document.getElementById('edit_end_time').value = endTime || '';
      new bootstrap.Modal(document.getElementById('editExamModal')).show();
    }

    function showStudents(examId) {
      var students = <?= json_encode($exam_students) ?>;
      var allStudents = <?= json_encode($students) ?>;
      var examStudents = students[examId] || [];
      var html = '<div class="table-responsive"><table class="admin-table"><thead><tr><th>Name</th><th>Matric No</th><th>Username</th><th class="actions-cell">Action</th></tr></thead><tbody>';
      examStudents.forEach(function(studentId) {
        var student = allStudents.find(function(s) { return s.id == studentId; });
        if (student) {
          html += '<tr><td>' + student.name + '</td><td>' + student.matric_no + '</td><td>' + student.username + '</td><td class="actions-cell"><form method="post" class="d-inline" data-swal="Remove this student from the exam?"><input type="hidden" name="exam_id" value="' + examId + '"><input type="hidden" name="student_id" value="' + studentId + '"><button type="submit" name="remove_student" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Remove</button></form></td></tr>';
        }
      });
      html += '</tbody></table></div>';
      if (examStudents.length === 0) html = '<div class="admin-empty"><div class="admin-empty-icon"><i class="bi bi-people admin-empty-icon-lg"></i></div><div class="admin-empty-title">No students assigned</div></div>';
      document.getElementById('studentsModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('studentsModal')).show();
    }

    function showQuestions(examId) {
      var questions = <?= json_encode($exam_questions) ?>;
      var examQuestions = questions[examId] || [];
      var html = '<div class="table-responsive"><table class="admin-table"><thead><tr><th>Question</th><th>Score Values</th><th class="actions-cell">Action</th></tr></thead><tbody>';
      examQuestions.forEach(function(question) {
        html += '<tr><td>' + question.text + '</td><td><code>' + question.score_values + '</code></td><td class="actions-cell"><form method="post" class="d-inline" data-swal="Remove this question from the exam?"><input type="hidden" name="exam_id" value="' + examId + '"><input type="hidden" name="question_id" value="' + question.id + '"><button type="submit" name="remove_question" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Remove</button></form></td></tr>';
      });
      html += '</tbody></table></div>';
      if (examQuestions.length === 0) html = '<div class="admin-empty"><div class="admin-empty-icon"><i class="bi bi-question-circle admin-empty-icon-lg"></i></div><div class="admin-empty-title">No questions assigned</div></div>';
      document.getElementById('questionsModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('questionsModal')).show();
    }

    function showExaminers(examId) {
      var examiners = <?= json_encode($examiners) ?>;
      var examExaminers = <?= json_encode($exam_examiners) ?>[examId] || [];
      var html = '<div class="table-responsive"><table class="admin-table"><thead><tr><th>Examiner</th><th class="actions-cell">Action</th></tr></thead><tbody>';
      examExaminers.forEach(function(examinerId) {
        var examiner = examiners.find(function(e) { return e.id == examinerId; });
        if (examiner) {
          html += '<tr><td>' + examiner.name + '</td><td class="actions-cell"><form method="post" class="d-inline" data-swal="Remove this examiner from the exam?"><input type="hidden" name="exam_id" value="' + examId + '"><input type="hidden" name="examiner_id" value="' + examinerId + '"><button type="submit" name="remove_examiner" class="admin-btn admin-btn-sm admin-btn-danger"><i class="bi bi-trash"></i> Remove</button></form></td></tr>';
        }
      });
      html += '</tbody></table></div>';
      if (examExaminers.length === 0) html = '<div class="admin-empty"><div class="admin-empty-icon"><i class="bi bi-person-badge admin-empty-icon-lg"></i></div><div class="admin-empty-title">No examiners assigned</div></div>';
      document.getElementById('examinersModalBody').innerHTML = html;
      new bootstrap.Modal(document.getElementById('examinersModal')).show();
    }

    // ─── exeat2: search on Enter key ───
    var exeat2StudentSearch = document.getElementById('exeat2StudentSearch');
    if (exeat2StudentSearch) {
      exeat2StudentSearch.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') document.getElementById('exeat2RefreshBtn').click();
      });
    }

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
    var exeat2RefreshBtn = document.getElementById('exeat2RefreshBtn');
    if (exeat2RefreshBtn) {
      exeat2RefreshBtn.addEventListener('click', function () {
        var search = document.getElementById('exeat2StudentSearch').value.trim();
        var dept = document.getElementById('exeat2Dept').value;
        var level = document.getElementById('exeat2Level').value;
        var courseStudyId = document.getElementById('exeat2CourseStudy').value;

        var url = 'includes/fetch_exeat2_students.php';
        var sep = '?';
        if (search) { url += sep + 'search=' + encodeURIComponent(search); sep = '&'; }
        if (dept) { url += sep + 'department_id=' + dept; sep = '&'; }
        if (level) { url += sep + 'level_id=' + level; sep = '&'; }
        if (courseStudyId) { url += sep + 'course_study_id=' + courseStudyId; sep = '&'; }

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
    }

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
    var exeat2ImportSelectedBtn = document.getElementById('exeat2ImportSelectedBtn');
    if (exeat2ImportSelectedBtn) {
      exeat2ImportSelectedBtn.addEventListener('click', function () {
        var examId = document.getElementById('exeat2ExamSelect').value;
        if (!examId) {
          Swal.fire({ title: 'No Exam Selected', text: 'Please select an exam to assign students to.', icon: 'warning', confirmButtonColor: '#198754' });
          return;
        }
        var checked = document.querySelectorAll('.exeat2-checkbox:checked');
        if (checked.length === 0) {
          Swal.fire({ title: 'No Selection', text: 'Select at least one student.', icon: 'warning', confirmButtonColor: '#198754' });
          return;
        }
        var ids = [];
        checked.forEach(function (cb) { ids.push(parseInt(cb.value)); });
        doExeat2Import(ids, examId);
      });
    }

    // ─── exeat2: Import All ───
    var exeat2ImportAllBtn = document.getElementById('exeat2ImportAllBtn');
    if (exeat2ImportAllBtn) {
      exeat2ImportAllBtn.addEventListener('click', function () {
        var examId = document.getElementById('exeat2ExamSelect').value;
        if (!examId) {
          Swal.fire({ title: 'No Exam Selected', text: 'Please select an exam to assign students to.', icon: 'warning', confirmButtonColor: '#198754' });
          return;
        }
        var ids = exeat2Students.map(function (s) { return s.exeat2_id; });
        Swal.fire({
          title: 'Import All?',
          text: 'This will import ' + ids.length + ' student(s) from the school portal and assign to the selected exam.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#198754',
          confirmButtonText: 'Yes, import all'
        }).then(function (result) {
          if (result.isConfirmed) doExeat2Import(ids, examId);
        });
      });
    }

    function doExeat2Import(ids, examId) {
      var btn = document.getElementById('exeat2ImportSelectedBtn');
      btn.disabled = true;
      btn.innerHTML = '<span class="btn-spinner"></span> Importing...';
      document.getElementById('exeat2ImportAllBtn').disabled = true;

      fetch('includes/import_exeat2_student.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'student_ids=' + encodeURIComponent(JSON.stringify(ids)) + '&exam_id=' + examId
      })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-person-check"></i> Import Selected';
        document.getElementById('exeat2ImportAllBtn').disabled = false;

        if (res.error) {
          Swal.fire({ title: 'Import Error', text: res.error, icon: 'error', confirmButtonColor: '#198754' });
          return;
        }

        var html = '';
        html += '<div class="mb-3">';
        html += '<div class="d-flex justify-content-around text-center">';
        html += '<div><span class="fs-4 text-success">' + (res.imported ?? 0) + '</span><br><small>Newly Imported</small></div>';
        html += '<div><span class="fs-4 text-info">' + (res.updated ?? 0) + '</span><br><small>Updated</small></div>';
        html += '<div><span class="fs-4 text-warning">' + (res.skipped_upload ?? 0) + '</span><br><small>Skipped (upload)</small></div>';
        html += '<div><span class="fs-4 text-warning">' + (res.skipped_manual ?? 0) + '</span><br><small>Skipped (manual)</small></div>';
        html += '</div></div>';
        html += '<div class="d-flex justify-content-around text-center mt-2">';
        html += '<div><span class="fs-5 text-secondary">' + (res.passport_found ?? 0) + '</span><br><small>Passports Found</small></div>';
        html += '<div><span class="fs-5 text-success">' + (res.passport_saved ?? 0) + '</span><br><small>Passports Saved</small></div>';
        html += '</div>';
        html += '<p class="text-center fw-bold">Total: ' + (res.total ?? 0) + ' students processed</p>';

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
  <script>
        document.querySelectorAll('form[onsubmit*="return confirm"], form[data-swal]').forEach(function(el) {
            let match = el.getAttribute('onsubmit') ? el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/) : null;
            if (!match && el.hasAttribute('data-swal')) {
                let msg = el.getAttribute('data-swal');
                el.removeAttribute('data-swal');
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
                            var activeTab = document.querySelector('#examTabs .nav-link.active');
                            if (activeTab && !el.querySelector('input[name="current_tab"]')) {
                                var th = document.createElement('input');
                                th.type = 'hidden'; th.name = 'current_tab'; th.value = activeTab.id;
                                el.appendChild(th);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
                return;
            }
            if(!match) return;
            let msg = match[1];
            el.removeAttribute('onsubmit');
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
                            // Inject current tab before native submit
                            var activeTab = document.querySelector('#examTabs .nav-link.active');
                            if (activeTab && !el.querySelector('input[name="current_tab"]')) {
                                var th = document.createElement('input');
                                th.type = 'hidden'; th.name = 'current_tab'; th.value = activeTab.id;
                                el.appendChild(th);
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
                                    var activeTab = document.querySelector('#examTabs .nav-link.active');
                                    if (activeTab && !el.form.querySelector('input[name="current_tab"]')) {
                                        var th = document.createElement('input');
                                        th.type = 'hidden'; th.name = 'current_tab'; th.value = activeTab.id;
                                        el.form.appendChild(th);
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
