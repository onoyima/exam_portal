<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
require 'db_exeat2.php';
require_once __DIR__ . '/includes/student_import_helper.php';

$name = $_SESSION['user'] ?? 'Admin';
$success = $error = '';
$import_errors = [];
$back_url = 'admin_dashboard';

// Fetch all exams for dropdowns
$all_exams = $db->query("SELECT id, name, type FROM exams WHERE deleted_at IS NULL ORDER BY type DESC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
$practical_exams = array_filter($all_exams, function ($e) {
    return ($e['type'] != 'objective');
});

// Fetch exeat2 filter options
$departments = [];
$faculties = [];
$levels = [];
$course_studies = [];
if ($exeat2_db) {
    try {
        $departments = $exeat2_db->query("SELECT id, name, abb, faculty_id FROM departments WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $faculties = $exeat2_db->query("SELECT id, name, abb FROM faculties WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $levels = $exeat2_db->query("SELECT id, level FROM levels WHERE status = 1 ORDER BY CAST(level AS UNSIGNED)")->fetchAll(PDO::FETCH_ASSOC);
        $course_studies = $exeat2_db->query("SELECT id, name, abb, department_id FROM course_studies WHERE status = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('exeat2 filter options fetch failed: ' . $e->getMessage());
    }
}

/**
 * Save passport blob helper
 */
function save_passport_blob($data, $path) {
    $prefix = substr($data, 0, 4);
    $hex = bin2hex($prefix);
    if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $hex)) {
        $ext = strncmp($hex, 'ffd8', 4) === 0 ? 'jpg' : (strncmp($hex, '8950', 4) === 0 ? 'png' : 'gif');
        $new_path = substr($path, 0, -4) . ".$ext";
        file_put_contents($new_path, $data);
        return ['format' => 'raw', 'saved_path' => $new_path];
    }
    $b64 = $data;
    if (strncmp($b64, 'data:image/', 11) === 0) {
        $pos = strpos($b64, 'base64,');
        if ($pos !== false) $b64 = substr($b64, $pos + 7);
    }
    $decoded = base64_decode($b64, true);
    if ($decoded !== false) {
        $dhex = bin2hex(substr($decoded, 0, 4));
        if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $dhex)) {
            $ext = strncmp($dhex, 'ffd8', 4) === 0 ? 'jpg' : 'png';
            $new_path = substr($path, 0, -4) . ".$ext";
            file_put_contents($new_path, $decoded);
            return ['format' => 'base64', 'saved_path' => $new_path];
        }
    }
    file_put_contents($path, $data);
    return ['format' => 'unknown', 'saved_path' => $path];
}

// Handle Student Upload (CSV)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['students'])) {
    $exam_ids = isset($_POST['student_exam_ids']) ? (array) $_POST['student_exam_ids'] : [];
    $update_existing = isset($_POST['update_existing']);
    $file = $_FILES['students']['tmp_name'];

    if ($file) {
        [$col_map, $header_found, $records] = sih_read_student_csv($file);
        if (empty($records)) {
            $error = 'Empty file or no readable rows.';
        } else {
            $parsed = [];
            $skipped = 0;
            foreach ($records as $record) {
                $fields = sih_parse_fields($record, $col_map, $header_found);
                if ($fields['matric_no'] === '') {
                    $skipped++;
                    continue;
                }
                $parsed[] = $fields;
            }

            $school_by_matric = sih_exeat2_lookup($exeat2_db, array_column($parsed, 'matric_no'));
            $count = 0;

            foreach ($parsed as $fields) {
                $matric = $fields['matric_no'];
                $saved = sih_upsert_student(
                    $db,
                    $fields,
                    $school_by_matric[$matric] ?? null,
                    $update_existing,
                    !empty($fields['password_provided'])
                );
                $sid = $saved['id'];

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

            $success = "Successfully processed $count students!" . (!empty($exam_ids) ? " Assigned to " . count($exam_ids) . " exams." : "")
                . ($skipped ? " $skipped row(s) skipped without a matric number." : "");
        }
    }
}

// Handle exeat2 Import (School Portal)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['exeat2_import'])) {
    if (!$exeat2_db) {
        $error = 'School portal database connection not available.';
    } else {
        $level_str = trim($_POST['level_str'] ?? '');
        $dept_filter = intval($_POST['department_id'] ?? 0);
        $faculty_filter = intval($_POST['faculty_id'] ?? 0);
        $course_study_filter = intval($_POST['course_study_id'] ?? 0);

        $base_query = "
            SELECT
              s.id                 AS exeat2_id,
              CONCAT(s.fname, ' ', COALESCE(s.mname,''), ' ', s.lname) AS name,
              s.fname              AS firstname,
              s.mname              AS middlename,
              s.lname              AS lastname,
              s.email,
              sa.matric_no,
              s.username,
              s.password,
              s.passport,
              sa.level,
              sa.department_id,
              sa.faculty_id,
              sa.course_study_id,
              d.name AS department_name,
              l.level AS level_name
            FROM students s
            JOIN student_academics sa ON sa.student_id = s.id
              AND sa.academic_session_id = (
                SELECT MAX(sa2.academic_session_id)
                FROM student_academics sa2
                WHERE sa2.student_id = s.id
              )
            LEFT JOIN departments d ON d.id = sa.department_id
            LEFT JOIN levels l ON l.id = sa.level
            WHERE s.status = 1 AND sa.status = 1
        ";

        $where_clauses = ["1=1"];
        $params = [];

        if (!empty($level_str)) {
            $level_num = intval($level_str);
            if ($level_num > 0) {
                $where_clauses[] = "sa.level = ?";
                $params[] = $level_num * 10;
            }
        }
        if (intval($_POST['department_id'] ?? 0) > 0) {
            $where_clauses[] = "sa.department_id = ?";
            $params[] = intval($_POST['department_id']);
        }
        if (intval($_POST['faculty_id'] ?? 0) > 0) {
            $where_clauses[] = "sa.faculty_id = ?";
            $params[] = intval($_POST['faculty_id']);
        }
        if (intval($_POST['course_study_id'] ?? 0) > 0) {
            $where_clauses[] = "sa.course_study_id = ?";
            $params[] = intval($_POST['course_study_id']);
        }

        $sql = "
            SELECT
              s.id                 AS exeat2_id,
              CONCAT(s.fname, ' ', COALESCE(s.mname,''), ' ', s.lname) AS name,
              s.fname              AS firstname,
              s.mname              AS middlename,
              s.lname              AS lastname,
              s.email,
              sa.matric_no,
              s.username,
              s.password,
              s.passport,
              sa.level,
              sa.department_id,
              sa.faculty_id,
              sa.course_study_id,
              d.name AS department_name,
              l.level AS level_name
            FROM students s
            JOIN student_academics sa ON sa.student_id = s.id
              AND sa.academic_session_id = (
                SELECT MAX(sa2.academic_session_id)
                FROM student_academics sa2
                WHERE sa2.student_id = s.id
              )
            LEFT JOIN departments d ON d.id = sa.department_id
            LEFT JOIN levels l ON l.id = sa.level
            WHERE s.status = 1 AND sa.status = 1
        ";

        if (!empty($params)) {
            $sql .= ' WHERE ' . implode(' AND ', ['s.status = 1', 'sa.status = 1'] + $where_clauses) . ' ORDER BY sa.matric_no';
        } else {
            $sql .= ' WHERE s.status = 1 AND sa.status = 1 ORDER BY sa.matric_no';
        }

        try {
            $stmt = $exeat2_db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'exeat2 query failed: ' . $e->getMessage();
            $rows = [];
        }

        if (empty($rows)) {
            $error = 'No students found matching the criteria.';
        } else {
            $exam_ids = isset($_POST['exam_id']) ? (array) $_POST['exam_id'] : [];
            $exam_ids = array_filter(array_map('intval', $exam_ids));

            $passport_dir_students = __DIR__ . '/uploads/passports/students';
            if (!is_dir($passport_dir_students)) {
                @mkdir($passport_dir_students, 0755, true);
            }

            function save_passport_blob($data, $path) {
                $prefix = substr($data, 0, 4);
                $hex = bin2hex($prefix);
                if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $hex)) {
                    $ext = strncmp($hex, 'ffd8', 4) === 0 ? 'jpg' : (strncmp($hex, '8950', 4) === 0 ? 'png' : 'gif');
                    $new_path = substr($path, 0, -4) . ".$ext";
                    file_put_contents($new_path, $data);
                    return ['format' => 'raw', 'saved_path' => $new_path];
                }
                $b64 = $data;
                if (strncmp($b64, 'data:image/', 11) === 0) {
                    $pos = strpos($b64, 'base64,');
                    if ($pos !== false) $b64 = substr($b64, $pos + 7);
                }
                $decoded = base64_decode($b64, true);
                if ($decoded !== false) {
                    $dhex = bin2hex(substr($decoded, 0, 4));
                    if (preg_match('/^(ffd8|8950|47494638|52494646)/i', $dhex)) {
                        $ext = strncmp($dhex, 'ffd8', 4) === 0 ? 'jpg' : 'png';
                        $new_path = substr($path, 0, -4) . ".$ext";
                        file_put_contents($new_path, $decoded);
                        return ['format' => 'base64', 'saved_path' => $new_path];
                    }
                }
                file_put_contents($path, $data);
                return ['format' => 'unknown', 'saved_path' => $path];
            }

            $results = [
                'imported' => 0,
                'updated' => 0,
                'skipped_upload' => 0,
                'skipped_manual' => 0,
                'passport_found' => 0,
                'passport_saved' => 0,
                'details' => [],
            ];

            try {
                foreach ($rows as $row) {
                    $matric_no = $row['matric_no'];
                    $existing = $db->prepare("SELECT id, source FROM students WHERE matric_no = ?");
                    $existing->execute([$matric_no]);
                    $local = $existing->fetch(PDO::FETCH_ASSOC);

                    if (!$local) {
                        $insert = $db->prepare("
                            INSERT INTO students
                                (name, firstname, middlename, lastname, email,
                                 matric_no, username, password, source, external_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'exeat2', ?)
                        ");
                        $insert->execute([
                            $row['name'], $row['firstname'], $row['middlename'],
                            $row['lastname'], $row['email'], $row['matric_no'],
                            $row['username'], $row['password'], $row['exeat2_id']
                        ]);
                        $local_id = $db->lastInsertId();

                        $detail = ['matric_no' => $matric_no, 'status' => 'imported', 'name' => $row['name']];
                        if (!empty($row['passport'])) {
                            $path = __DIR__ . '/uploads/passports/students/' . $local_id . '.jpg';
                            $info = save_passport_blob($row['passport'], $path);
                            $detail['passport_saved'] = file_exists($info['saved_path']);
                            $detail['passport_path'] = $info['saved_path'];
                        }
                        $results['imported']++;
                        $results['details'][] = $detail;
                    } elseif ($local['source'] === 'exeat2') {
                        $db->prepare("
                            UPDATE students SET
                                name=?, firstname=?, middlename=?, lastname=?,
                                email=?, username=?, password=?, external_id=?, deleted_at=NULL
                            WHERE id=?
                        ")->execute([
                            $row['name'], $row['firstname'], $row['middlename'],
                            $row['lastname'], $row['email'], $row['username'],
                            $row['password'], $row['exeat2_id'], $local['id']
                        ]);
                        $detail = ['matric_no' => $matric_no, 'status' => 'updated', 'name' => $row['name']];
                        if (!empty($row['passport'])) {
                            $path = __DIR__ . '/uploads/passports/students/' . $local['id'] . '.jpg';
                            $info = save_passport_blob($row['passport'], $path);
                            $detail['passport_saved'] = file_exists($info['saved_path']);
                            $detail['passport_path'] = $info['saved_path'];
                        }
                        $results['updated']++;
                        $local_id = $local['id'];
                    } else {
                        $local_id = $local['id'];
                        $key = ($local['source'] === 'upload') ? 'skipped_upload' : 'skipped_manual';
                        $results[$key]++;
                        $results['details'][] = ['matric_no' => $matric_no, 'status' => 'skipped_' . $local['source'], 'name' => $row['name']];
                    }

                    if (!empty($exam_ids) && $local_id) {
                        foreach ($exam_ids as $eid) {
                            $eid = intval($eid);
                            if ($eid > 0) {
                                $db->prepare("INSERT IGNORE INTO exam_students (exam_id, student_id) VALUES (?, ?)")->execute([$eid, $local_id]);
                            }
                        }
                    }
                }

                $total = array_sum($results);
                $success = "exeat2 import completed: {$results['imported']} imported, {$results['updated']} updated, skipped.";
            } catch (Exception $e) {
                $error = 'Import error: ' . $e->getMessage();
            }
        }
    }
}

// Handle Question Upload (practical steps or objective questions, by exam type)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['questions'])) {
    $exam_id = intval($_POST['exam_id']);
    $target = null;
    foreach ($all_exams as $ex) {
        if ((int) $ex['id'] === $exam_id) {
            $target = $ex;
        }
    }
    if (!$exam_id || !$target) {
        $error = "Please select an exam for the questions.";
    } elseif ($target['type'] === 'objective') {
        $f = $_FILES['questions'];
        $ext = strtolower(pathinfo($f['name'] ?? '', PATHINFO_EXTENSION));
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Upload failed. Please choose a .docx, .txt or .csv file.';
        } elseif (!in_array($ext, ['txt', 'csv', 'docx'], true)) {
            $error = 'Objective questions must be a Word .docx, .txt or .csv file.';
        } else {
            $result = qe_import_file($db, $exam_id, $f['tmp_name'], $ext);
            if ($result === null) {
                $error = 'Import failed and nothing was saved. Check the file and try again.';
            } else {
                [$added, $import_errors] = $result;
                $msg = "$added objective question" . ($added === 1 ? '' : 's') . ' added to ' . htmlspecialchars($target['name'])
                    . (count(array_filter($import_errors, fn($e) => $e['line'] > 0)) ? '; ' . count(array_filter($import_errors, fn($e) => $e['line'] > 0)) . ' skipped (see below).' : ($import_errors ? ' (see notes below).' : '.'));
                if ($added) {
                    $success = $msg . ' <a href="admin_objective_questions?exam_id=' . $exam_id . '">Open question bank</a>';
                } else {
                    $error = $msg;
                }
            }
        }
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
          <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Data Upload Center</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():"?"}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="main-content">
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
      <?php if ($import_errors): ?>
        <div class="admin-alert admin-alert-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <div>
            <strong>Skipped questions</strong>
            <ul class="mb-0 mt-1 small" style="max-height:180px;overflow:auto;">
              <?php foreach (array_slice($import_errors, 0, 50) as $ie): ?>
                <li><?= $ie['line'] ? 'Line ' . (int) $ie['line'] . ': ' : '' ?><?= htmlspecialchars($ie['message']) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <div class="row g-4">
        <!-- Student Upload (CSV) -->
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
                    <div class="admin-dropzone-hint">Format: firstname, middlename, lastname, email, matric_no, password</div>
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

        <!-- Import from School Portal (exeat2) -->
        <div class="col-12 col-xl-6">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-database-fill-gear"></i> Import from School Portal (exeat2)</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted small mb-4">Import students directly from the school portal database by level, department, or faculty.</p>

              <form method="post" id="exeat2ImportForm" class="exeat2-import-form">
                <div class="row g-3 mb-4">
                  <div class="col-md-6">
                    <label class="admin-form-label">Level</label>
                    <select name="level_str" class="admin-input admin-select" id="exeat2Level">
                      <option value="">-- Any Level --</option>
                      <?php foreach ($levels as $l): ?>
                        <option value="<?= (int)$l['level'] ?>"><?= htmlspecialchars($l['level']) ?>L</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="admin-form-label">Faculty</label>
                    <select name="faculty_id" class="admin-input admin-select" id="exeat2Faculty">
                      <option value="">-- Any Faculty --</option>
                      <?php foreach ($faculties as $f): ?>
                        <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?> (<?= htmlspecialchars($f['abb']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="admin-form-label">Department</label>
                    <select name="department_id" class="admin-input admin-select" id="exeat2Department">
                      <option value="">-- Any Department --</option>
                      <?php foreach ($departments as $d): ?>
                        <option value="<?= $d['id'] ?>" data-faculty="<?= $d['faculty_id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['abb']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="admin-form-label">Course Study</label>
                    <select name="course_study_id" class="admin-input admin-select" id="exeat2CourseStudy">
                      <option value="">-- Any Course --</option>
                      <?php foreach ($course_studies as $cs): ?>
                        <option value="<?= $cs['id'] ?>" data-dept="<?= $cs['department_id'] ?>"><?= htmlspecialchars($cs['name']) ?> (<?= htmlspecialchars($cs['abb']) ?>)</option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                </div>

                <div class="mb-4">
                  <label class="admin-form-label">Assign to Exams (Optional)</label>
                  <select name="exam_id[]" class="admin-input admin-select" multiple size="5" id="exeat2ExamSelect">
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
                  <div class="admin-dropzone-hint mt-1">Hold <b>Ctrl (Cmd)</b> to select multiple exams.</div>
                </div>

                <div class="admin-alert admin-alert-info">
                  <i class="bi bi-info-circle"></i>
                  <span>Leave filters empty to import all active students. At least one filter is recommended for large imports.</span>
                </div>

                <button type="submit" name="exeat2_import" class="admin-btn admin-btn-secondary w-100 py-3">
                  <i class="bi bi-database-fill-add me-2"></i> Import from School Portal
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Practical Question Upload -->
        <div class="col-12 col-xl-6">
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-journal-text"></i> Question Bank Upload</h3>
            </div>
            <div class="admin-card-body">
              <p class="text-muted small mb-4">Upload checklist steps for a practical exam, or questions of any type (including Multiple True/False) for an objective exam.</p>

              <form method="post" enctype="multipart/form-data" id="questionUploadForm">
                <div class="mb-4">
                  <label class="admin-form-label">1. Target Exam (Required)</label>
                  <select name="exam_id" id="questionExamSelect" class="admin-input admin-select" required>
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
                          <option value="<?= $ex['id'] ?>" data-objective="1"><?= htmlspecialchars($ex['name']) ?></option>
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
                    <div class="admin-dropzone-hint" id="questionFormatHint">Practical: Step Text, Score Values (0,1,2...) · Objective: TXT or CSV (all question types, including Multiple True/False)</div>
                    <input type="file" name="questions" id="questionFileInput" accept=".csv,.txt,.docx" class="upload-hidden" onchange="handleFileSelect(this, 'question')">
                  </div>
                  <div id="questionFileInfo" class="mt-2 upload-hidden">
                    <span class="admin-file-chip" id="questionFileName"></span>
                  </div>
                  <div class="admin-progress mt-2 upload-hidden" id="questionProgress">
                    <div class="admin-progress-bar" id="questionProgressBar" style="width:0%"></div>
                  </div>
                  <div class="mt-2 d-flex flex-wrap gap-2" id="practicalTemplates">
                    <a href="download_template.php?type=practical_steps" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>Practical CSV Template</a>
                  </div>
                  <div class="mt-2 d-none flex-wrap gap-2" id="objectiveTemplates">
                    <a href="download_template.php?type=objective_docx" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-file-earmark-word me-1"></i>Word (science)</a>
                    <a href="download_template.php?type=objective_mixed_txt" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>All types TXT</a>
                    <a href="download_template.php?type=objective_mtf_txt" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>Multiple True/False TXT</a>
                    <a href="download_template.php?type=objective_aiken" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>Aiken TXT</a>
                    <a href="download_template.php?type=objective_csv_simple" class="admin-btn admin-btn-sm admin-btn-outline"><i class="bi bi-download me-1"></i>MCQ CSV</a>
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
              <p class="mb-0 text-muted small">Objective questions can be uploaded here or from each exam's question bank in
                <a href="admin_objective_exams" class="text-decoration-none fw-bold">Objective Exams</a>. Both accept the same
                files: single answer, multiple answer, True/False and Multiple True/False (MTF), mixed in one file.
                In TXT, put <code>TYPE: MTF</code> above a question and end each statement with <code>[T]</code> or <code>[F]</code>.
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
    (function () {
      var sel = document.getElementById('questionExamSelect');
      if (!sel) return;
      function sync() {
        var opt = sel.options[sel.selectedIndex];
        var obj = !!(opt && opt.getAttribute('data-objective'));
        document.getElementById('practicalTemplates').classList.toggle('d-none', obj);
        document.getElementById('practicalTemplates').classList.toggle('d-flex', !obj);
        document.getElementById('objectiveTemplates').classList.toggle('d-none', !obj);
        document.getElementById('objectiveTemplates').classList.toggle('d-flex', obj);
        document.getElementById('questionFormatHint').textContent = obj
          ? 'Objective: Word .docx (equations & symbols kept), .txt (Aiken + TYPE: MCQ / MULTI / TF / MTF) or .csv'
          : 'Practical: Step Text, Score Values (0,1,2...)';
      }
      sel.addEventListener('change', sync);
      sync();
    })();

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