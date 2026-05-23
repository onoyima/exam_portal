<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';

$back_url = 'admin_dashboard';
$name = $_SESSION['user'] ?? 'Examiner';
$error = '';
$success = '';

try {
    // Get examiner id
    $examiner = $db->prepare("SELECT id FROM examiners WHERE username=? AND deleted_at IS NULL");
    $examiner->execute([$_SESSION['user']]);
    $examiner_id = $examiner->fetchColumn();

    if (!$examiner_id) {
        throw new Exception("Examiner profile not found.");
    }

    // Get exams: Assigned Practical/Objective + ALL Active VIVA exams
    $all_exams_stmt = $db->prepare("
      SELECT DISTINCT e.* 
      FROM exams e 
      LEFT JOIN exam_examiners ee ON e.id = ee.exam_id AND ee.examiner_id = ?
      WHERE ee.examiner_id IS NOT NULL 
      AND e.is_active = 1 AND e.deleted_at IS NULL 
      ORDER BY e.type = 'viva' DESC, e.created_at DESC
    ");
    $all_exams_stmt->execute([$examiner_id]);
    $all_exams = $all_exams_stmt->fetchAll(PDO::FETCH_ASSOC);

    $active_exams = $all_exams; // filtered already in SQL

    $selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
    $search = $_GET['search'] ?? '';
    $stu = null;
    $students = [];

    if ($selected_exam) {
      $stmt = $db->prepare("
        SELECT s.*,
          (SELECT COUNT(*) FROM answers a WHERE a.exam_id=? AND a.student_id=s.id) as answer_count,
          (SELECT SUM(a2.score) FROM answers a2 WHERE a2.exam_id=? AND a2.student_id=s.id) as total_score
        FROM students s
        JOIN exam_students es ON s.id=es.student_id
        WHERE es.exam_id=? AND s.deleted_at IS NULL
        ORDER BY s.name
      ");
      $stmt->execute([$selected_exam, $selected_exam, $selected_exam]);
      $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($search && $selected_exam) {
      $searchParam = "%$search%";
      $stmt = $db->prepare("
        SELECT s.*, s.id as student_id 
        FROM students s 
        JOIN exam_students es ON s.id = es.student_id 
        WHERE s.deleted_at IS NULL AND (
          s.name LIKE :search1 
          OR s.username LIKE :search2
          OR s.matric_no LIKE :search3 
          OR RIGHT(s.matric_no, 6) = :exact1 
          OR RIGHT(s.matric_no, 5) = :exact2 
          OR RIGHT(s.matric_no, 4) = :exact3
        ) 
        AND es.exam_id = :exam_id 
        LIMIT 1
      ");
      $stmt->execute([
        ':search1' => $searchParam,
        ':search2' => $searchParam,
        ':search3' => $searchParam,
        ':exact1' => $search,
        ':exact2' => $search,
        ':exact3' => $search,
        ':exam_id' => $selected_exam
      ]);
      $stu = $stmt->fetch();
      
      if ($stu) {
          // Pre-fetch existing answers if any
          $ans_stmt = $db->prepare("SELECT question_id, score FROM answers WHERE exam_id=? AND student_id=?");
          $ans_stmt->execute([$selected_exam, $stu['student_id']]);
          $existing_answers = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
      }
    }

    // -- AJAX instant save: saves a single score immediately on radio click --
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_score') {
        header('Content-Type: application/json');
        try {
            $qid = intval($_POST['question_id'] ?? 0);
            $sid = intval($_POST['student_id'] ?? 0);
            $eid = intval($_POST['exam_id'] ?? 0);
            $score = floatval($_POST['score'] ?? 0);
            validate_csrf();

            if (!$qid || !$sid || !$eid) {
                throw new Exception('Missing parameters');
            }

            $stmt = $db->prepare("SELECT id FROM answers WHERE exam_id=? AND question_id=? AND student_id=?");
            $stmt->execute([$eid, $qid, $sid]);
            $answer_id = $stmt->fetchColumn();

            if ($answer_id) {
                $db->prepare("UPDATE answers SET score=?, examiner_id=?, updated_by=?, updated_at=NOW() WHERE id=?")
                    ->execute([$score, $examiner_id, $examiner_id, $answer_id]);
            } else {
                $db->prepare("INSERT INTO answers(exam_id, question_id, student_id, examiner_id, score, created_at, updated_at) VALUES (?,?,?,?,?, NOW(), NOW())")
                    ->execute([$eid, $qid, $sid, $examiner_id, $score]);
            }

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit']) && $selected_exam) {
        // Verify exam is active and assigned
        $is_valid_exam = false;
        foreach ($active_exams as $ae) {
            if ($ae['id'] == $selected_exam) {
                $is_valid_exam = true;
                break;
            }
        }
        
        if (!$is_valid_exam) {
            throw new Exception("Invalid or inactive exam selected.");
        }

        $student_id = $_POST['student_id'];
        
        // Verify student exists in this exam
        $check_stu = $db->prepare("SELECT 1 FROM exam_students WHERE exam_id=? AND student_id=?");
        $check_stu->execute([$selected_exam, $student_id]);
        if (!$check_stu->fetch()) {
            throw new Exception("Student is not assigned to this exam.");
        }

        $db->beginTransaction();
        
        // Re-fetch student name for the success message if needed
        $display_name = $stu['name'] ?? "Student";

        if (isset($_POST['q']) && is_array($_POST['q'])) {
            foreach ($_POST['q'] as $qid => $score) {
                // If it's VIVA, we might want to log who updated it
                $stmt = $db->prepare("SELECT id FROM answers WHERE exam_id=? AND question_id=? AND student_id=?");
                $stmt->execute([$selected_exam, $qid, $student_id]);
                $answer_id = $stmt->fetchColumn();
                
                if ($answer_id) {
                    $db->prepare("UPDATE answers SET score=?, examiner_id=?, updated_by=NOW() WHERE id=?")
                      ->execute([floatval($score), $examiner_id, $answer_id]);
                } else {
                    $db->prepare("INSERT INTO answers(exam_id, question_id, student_id, examiner_id, score, created_at) VALUES (?,?,?,?,?, NOW())")
                      ->execute([$selected_exam, $qid, $student_id, $examiner_id, floatval($score)]);
                }
            }
        }
        
        $db->commit();
        $is_update = !empty($existing_answers);
        $success = ($is_update ? "Updated Assessment" : "New Assessment") . " for " . htmlspecialchars($display_name) . " has been saved successfully! " . ($is_update ? "(Second Attempt/Revision)" : "");
        $stu = null;
        $search = '';
    }
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $error = $e->getMessage();
}

function decimalToFractionLabel($decimal) {
    $map = [
        '0.125' => '1/8',
        '0.166' => '1/6',
        '0.25' => '1/4',
        '0.333' => '1/3',
        '0.5' => '1/2',
        '0.666' => '2/3',
        '0.75' => '3/4',
        '1' => '1',
        '0' => '0',
    ];
    $decimalStr = rtrim(rtrim(number_format((float)$decimal, 3, '.', ''), '0'), '.');
    return $map[$decimalStr] ?? $decimalStr;
}

// Pre-compute current exam details for use in HTML
$exam_details_computed = false;
$is_active = false;
$current_exam_name = '';
$current_exam_type = 'practical';
$current_exam_total_mark = 0;
$current_exam_show_score = false;
if ($selected_exam) {
    foreach ($all_exams as $ex) {
        if ($ex['id'] == $selected_exam) {
            $is_active = $ex['is_active'];
            $current_exam_name = $ex['name'];
            $current_exam_type = $ex['type'] ?? 'practical';
            $current_exam_total_mark = $ex['total_mark'] ?? 0;
            $current_exam_show_score = !empty($ex['student_can_view_score']);
            $exam_details_computed = true;
            break;
        }
    }
}

// Compute score summary for the student if found
$total_score = 0;
$max_score = 0;
if ($stu && $selected_exam) {
    // Get questions for this exam to compute max
    $qs_sum = $db->prepare("SELECT q.* FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=?");
    $qs_sum->execute([$selected_exam]);
    while ($q = $qs_sum->fetch()) {
        if ($current_exam_type === 'viva') {
            $max_score += intval($current_exam_total_mark);
        } else {
            $vals = array_map('trim', explode(',', $q['score_values']));
            $max_score += max(array_map('floatval', $vals));
        }
    }
    foreach (($existing_answers ?? []) as $sc) {
        $total_score += floatval($sc);
    }
}
$score_pct = $max_score > 0 ? round(($total_score / $max_score) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Assessment - Examiner Portal</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  
  <style>
    .assessment-radio-group {
      display: flex;
      flex-wrap: nowrap;
      gap: 1.5rem;
      align-items: center;
      justify-content: flex-start;
      padding: 0.5rem 0;
    }

    input[type="radio"].form-check-input {
      appearance: none;
      -webkit-appearance: none;
      width: 24px;
      height: 24px;
      border: 2px solid #092c19;
      border-radius: 4px;
      background-color: #fff;
      cursor: pointer;
      position: relative;
      margin: 0;
      transition: all 0.2s ease-in-out;
      flex-shrink: 0;
    }

    input[type="radio"].form-check-input:checked {
      background-color: #fff;
      border-color: #092c19;
      box-shadow: inset 0 0 0 2px #fff;
    }

    input[type="radio"].form-check-input:checked::after {
      content: "";
      position: absolute;
      left: 7px;
      top: 2px;
      width: 8px;
      height: 14px;
      border: solid #34d058;
      border-width: 0 3.5px 3.5px 0;
      transform: rotate(45deg);
    }

    .form-check {
      display: flex;
      align-items: center;
      margin-bottom: 0;
      padding-left: 0;
      white-space: nowrap;
    }

    .form-check-label {
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      margin-left: 8px;
      user-select: none;
      color: var(--dark-color, #0f172a);
    }

    .question-cell {
      min-width: 300px;
    }

    .score-cell {
      min-width: 250px;
    }

    @media (max-width: 768px) {
      .assessment-radio-group {
        gap: 1rem;
        overflow-x: auto;
        padding-bottom: 5px;
      }
      .question-cell {
        min-width: 200px;
      }
    }

    .save-indicator {
      position: absolute;
      bottom: 2px;
      right: 8px;
      opacity: 0;
      transition: opacity 0.3s;
    }

    .admin-kbd-hint {
      margin-top: 4px;
      font-size: var(--font-size-xs);
      color: var(--color-text-muted);
    }

    .admin-score-footer {
      position: sticky;
      bottom: 0;
      background: #fff;
      border-top: 1px solid var(--color-border);
      padding: var(--space-3) var(--space-6);
    }

    .admin-progress-wrap {
      max-width: 300px;
    }

    .admin-exam-card-link {
      cursor: pointer;
      display: block;
      margin-bottom: 0;
      text-decoration: none;
    }

    .admin-icon-dot {
      font-size: 0.5rem;
    }

    .admin-empty-icon-lg i {
      font-size: 2rem;
    }

    .admin-empty-icon-xl i {
      font-size: 2.5rem;
    }

    .admin-viva-matric {
      font-size: 0.75rem;
    }

    .admin-stat-success {
      color: var(--color-success);
    }

    .admin-stat-lg {
      font-size: 1.5rem;
    }

    .admin-bar-track {
      flex: 1;
      background: var(--color-border-light);
      border-radius: 4px;
      height: 8px;
      overflow: hidden;
    }

    .admin-pct-label {
      width: 36px;
      text-align: right;
    }
  </style>
</head>

<body>
  <?php include 'examiner_sidebar.php'; ?>
  
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <a href="<?= $back_url ?>" class="admin-back-btn" title="Back to Dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Assessment Console</h1>
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
          <li><a href="assess">Assessments</a></li>
          <?php if ($selected_exam && $current_exam_name): ?>
            <li class="sep">/</li>
            <li class="current"><?= htmlspecialchars($current_exam_name) ?></li>
          <?php endif; ?>
          <?php if ($stu): ?>
            <li class="sep">/</li>
            <li class="current"><?= htmlspecialchars($stu['name']) ?></li>
          <?php endif; ?>
        </ol>
      </nav>

      <div class="admin-page-header">
        <div>
          <h2 class="admin-page-title">
            <?= ($selected_exam && $current_exam_name) ? htmlspecialchars($current_exam_name) : 'Assessments' ?>
          </h2>
          <?php if ($stu && $is_active): ?>
            <div class="admin-kbd-hint"><kbd>S</kbd> Save <kbd>N</kbd> Next <kbd>P</kbd> Prev</div>
          <?php endif; ?>
        </div>
        <div class="admin-page-actions">
          <?php if ($selected_exam): ?>
            <span class="admin-badge <?= $is_active ? 'admin-badge-success' : 'admin-badge-neutral' ?>">
              <i class="bi bi-info-circle"></i> <?= $is_active ? 'Active Session' : 'Inactive Session' ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <?php display_auth_errors(); ?>

      <?php if ($error): ?>
        <div class="admin-alert admin-alert-danger">
          <i class="bi bi-exclamation-triangle"></i>
          <span><?= htmlspecialchars($error) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle"></i>
          <span><?= htmlspecialchars($success) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>

      <?php if ($selected_exam): ?>

        <div class="admin-card">
          <div class="admin-card-body">
            <form method="get" class="row g-3 align-items-end">
              <input type="hidden" name="exam_id" value="<?= $selected_exam ?>">
              <div class="col-12 col-md-7">
                <label class="admin-form-label" for="studentSearch">Search Student</label>
                <div class="d-flex gap-2">
                  <input type="text" class="admin-input flex-grow-1" name="search" list="studentList"
                         placeholder="Find student by Name, Matric No, or ID..."
                         value="<?= htmlspecialchars($search) ?>" id="studentSearch" autocomplete="off">
                  <datalist id="studentList">
                    <?php if (!empty($students)): ?>
                      <?php foreach ($students as $s): ?>
                        <option value="<?= htmlspecialchars($s['name']) ?>"><?= htmlspecialchars($s['matric_no']) ?></option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </datalist>
                  <button class="admin-btn admin-btn-primary" type="submit">
                    <i class="bi bi-search"></i> Identify
                  </button>
                </div>
              </div>
              <div class="col-12 col-md-3">
                <label class="admin-form-label" for="examSelect">Switch Exam</label>
                <select class="admin-input" id="examSelect" onchange="if(this.value) window.location='assess?exam_id='+this.value">
                  <option value="">-- Select Exam --</option>
                  <?php foreach ($all_exams as $ex): ?>
                    <option value="<?= $ex['id'] ?>" <?= $selected_exam == $ex['id'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($ex['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </form>
          </div>
        </div>

        <?php if ($stu): ?>
          <div class="admin-card">
            <div class="admin-card-header">
              <div class="d-flex align-items-center gap-3">
                <img src="passport_image.php?type=student&id=<?= $stu['student_id'] ?? $stu['id'] ?>"
                     class="rounded-circle border border-2 border-success"
                     style="width:48px;height:48px;object-fit:cover;object-position:center top;">
                <div>
                  <h3 class="admin-card-title mb-0">
                    <i class="bi bi-person-badge"></i> <?= htmlspecialchars($stu['name']) ?>
                  </h3>
                  <span class="text-muted small">ID: #<?= $stu['id'] ?></span>
                </div>
              </div>
            </div>
            <div class="admin-card-body">
              <div class="row g-3">
                <div class="col-sm-4">
                  <div class="text-muted text-uppercase small fw-bold">Matric Number</div>
                  <div class="fw-semibold"><?= htmlspecialchars($stu['matric_no']) ?></div>
                </div>
                <div class="col-sm-4">
                  <div class="text-muted text-uppercase small fw-bold">Subject</div>
                  <div class="fw-semibold"><?= htmlspecialchars($current_exam_name) ?></div>
                </div>
                <div class="col-sm-4 text-sm-end">
                  <?php if (!empty($existing_answers)): ?>
                    <span class="admin-badge admin-badge-warning">Second Attempt / Update</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            
            <div class="admin-card-body-flush">
              <?php if ($is_active): ?>
                <form method="post" id="assessmentForm">
                  <input type="hidden" name="student_id" value="<?= $stu['student_id'] ?>">
                  <?php csrf_field(); ?>
                  <input type="hidden" id="ajaxExamId" value="<?= $selected_exam ?>">
                  <div class="table-responsive">
                    <table class="admin-table mb-0">
                      <thead>
                        <tr>
                          <th class="text-center assess-th-num">#</th>
                          <th class="question-cell">Assessment Criteria / Questions</th>
                          <th class="score-cell text-center">Scores / Degree of Performance</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $sn = 1;
                        $qs = $db->prepare("SELECT q.* FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=?");
                        $qs->execute([$selected_exam]);
                        while ($q = $qs->fetch()):
                          if ($current_exam_type === 'viva') {
                            $v_max = intval($current_exam_total_mark);
                            $values = [];
                            if ($v_max > 0) {
                                for ($i = 1; $i <= $v_max; $i++) {
                                    $values[] = $i;
                                }
                            } else {
                                $values = [0];
                            }
                          } else {
                            $values = array_map('trim', explode(',', $q['score_values']));
                          }
                          $current_val = isset($existing_answers[$q['id']]) ? $existing_answers[$q['id']] : null;
                        ?>
                          <tr id="qrow-<?= $q['id'] ?>">
                            <td class="text-center fw-bold text-muted"><?= $sn++ ?></td>
                            <td class="question-cell fw-medium"><?= htmlspecialchars($q['text']) ?></td>
                            <td class="score-cell score-cell-relative">
                              <div class="assessment-radio-group">
                                <?php foreach ($values as $col_score):
                                  $input_id = "q{$q['id']}_" . str_replace('.', '_', $col_score);
                                  $label_display = ($current_exam_type === 'viva') ? $col_score : decimalToFractionLabel($col_score);
                                  $checked = ($current_val !== null && abs($current_val - $col_score) < 0.001) ? 'checked' : '';
                                ?>
                                  <div class="form-check">
                                    <input type="radio" id="<?= $input_id ?>" name="q[<?= $q['id'] ?>]" 
                                           value="<?= $col_score ?>" required class="form-check-input" <?= $checked ?>
                                           data-question-id="<?= $q['id'] ?>">
                                    <label for="<?= $input_id ?>" class="form-check-label"><?= $label_display ?></label>
                                  </div>
                                <?php endforeach; ?>
                              </div>
                              <small class="save-indicator text-success fw-bold">
                                <i class="bi bi-check-circle-fill"></i>
                              </small>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                  
                  <div class="p-4 border-top">
                    <button type="submit" name="submit" value="1" class="admin-btn admin-btn-primary admin-btn-lg w-100">
                      <i class="bi bi-cloud-check"></i> Save Assessment Data
                    </button>
                    <p class="text-muted small mt-2 mb-0 text-center">All fields are required. Ensure scoring is accurate before submission.</p>
                  </div>
                </form>

                <?php if ($total_score > 0 || !empty($existing_answers)): ?>
                <div class="admin-score-footer">
                  <div class="d-flex align-items-center justify-content-between gap-3">
                    <div class="fw-semibold small">Score: <?= $total_score ?> / <?= $max_score ?> (<?= $score_pct ?>%)</div>
                    <div class="admin-progress flex-grow-1 admin-progress-wrap">
                      <div class="admin-progress-bar" style="width:<?= $score_pct ?>%;"></div>
                    </div>
                  </div>
                </div>
                <?php endif; ?>
              <?php else: ?>
                <div class="admin-empty">
                  <div class="admin-empty-icon"><i class="bi bi-lock-fill"></i></div>
                  <div class="admin-empty-title">Assessment Locked</div>
                  <div class="admin-empty-desc">This examination session is currently inactive. Scoring is disabled.</div>
                </div>
              <?php endif; ?>
            </div>
          </div>

        <?php elseif ($search): ?>
          <div class="admin-empty">
            <div class="admin-empty-icon"><i class="bi bi-person-x"></i></div>
            <div class="admin-empty-title">Student Not Found</div>
            <div class="admin-empty-desc">We couldn't find any student with "<?= htmlspecialchars($search) ?>" in this exam division.</div>
            <a href="?exam_id=<?= $selected_exam ?>" class="admin-btn admin-btn-outline admin-btn-sm">Clear Search</a>
          </div>

        <?php else: ?>
          <?php if ($current_exam_type !== 'objective' && !empty($students)): ?>
            <div class="admin-card">
              <div class="admin-card-header">
                <h3 class="admin-card-title"><i class="bi bi-people"></i> Assigned Students</h3>
                <div class="d-flex align-items-center gap-2">
                  <span class="text-muted small">Sort:</span>
                  <button class="admin-btn admin-btn-sm admin-btn-outline sort-students active" data-sort="status"><i class="bi bi-check2-circle"></i> Status</button>
                  <button class="admin-btn admin-btn-sm admin-btn-outline sort-students" data-sort="name"><i class="bi bi-sort-alpha-down"></i> Name</button>
                </div>
              </div>
              <div class="admin-card-body">
                <p class="text-muted small mb-3">Select a student from the list below to begin assessing.</p>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3" id="studentCardGrid">
                  <?php foreach ($students as $s):
                    $is_graded = ($s['answer_count'] ?? 0) > 0;
                  ?>
                    <div class="col" data-status="<?= ($s['answer_count'] ?? 0) > 0 ? 'submitted' : 'pending' ?>" data-name="<?= htmlspecialchars(strtolower($s['name'])) ?>">
                      <a href="?exam_id=<?= $selected_exam ?>&search=<?= urlencode($s['matric_no']) ?>" class="admin-card h-100 text-decoration-none assess-card-link">
                        <div class="admin-card-body">
                          <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-circle <?= $is_graded ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10' ?> p-2">
                              <i class="bi bi-person <?= $is_graded ? 'text-success' : 'text-warning' ?>"></i>
                            </div>
                            <div class="flex-grow-1 min-width-0">
                              <div class="fw-bold small text-truncate"><?= htmlspecialchars($s['name']) ?></div>
                              <div class="text-muted assess-matric-text"><?= htmlspecialchars($s['matric_no']) ?></div>
                            </div>
                            <span class="badge rounded-pill <?= $is_graded ? 'bg-success' : 'bg-warning text-dark' ?> badge-xs flex-shrink-0">
                              <?= $is_graded ? ($current_exam_show_score ? 'Graded' : 'Submitted') : 'Pending' ?>
                            </span>
                          </div>
                          <?php if ($is_graded): ?>
                            <div class="d-flex align-items-center gap-2 px-1">
                              <i class="bi bi-check-circle-fill text-success" style="font-size:0.6rem;"></i>
                              <?php if ($current_exam_show_score): ?>
                                <small class="text-success fw-medium">Score: <?= number_format($s['total_score'] ?? 0, 1) ?></small>
                              <?php else: ?>
                                <small class="text-success fw-medium">Submitted</small>
                              <?php endif; ?>
                            </div>
                          <?php else: ?>
                            <div class="d-flex align-items-center gap-2 px-1">
                              <i class="bi bi-hourglass-split text-warning" style="font-size:0.6rem;"></i>
                              <small class="text-warning">Not yet submitted</small>
                            </div>
                          <?php endif; ?>
                        </div>
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php else: ?>
            <div class="admin-empty">
              <div class="admin-empty-icon"><i class="bi bi-search assess-empty-icon-lg"></i></div>
              <div class="admin-empty-title">Ready for Assessment</div>
              <div class="admin-empty-desc">Please search for a student using their Name or Matric Number to start grading.</div>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      <?php else: ?>
        <div class="admin-empty">
          <div class="admin-empty-icon"><i class="bi bi-journals assess-empty-icon-xl"></i></div>
          <div class="admin-empty-title">Select an Examination</div>
          <div class="admin-empty-desc">Choose a subject from the list below or use the sidebar to begin assessing your assigned students.</div>
        </div>

        <?php if (!empty($all_exams)): ?>
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-book"></i> Your Assigned Examinations</h3>
            </div>
            <div class="admin-card-body">
              <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                <?php foreach ($all_exams as $exam): ?>
                  <div class="col">
                    <a href="?exam_id=<?= $exam['id'] ?>" class="admin-card h-100 text-decoration-none assess-card-link">
                      <div class="admin-card-body">
                        <div class="fw-bold mb-1"><?= htmlspecialchars($exam['name']) ?></div>
                        <div class="d-flex align-items-center gap-2">
                          <span class="admin-badge <?= $exam['type'] === 'viva' ? 'admin-badge-warning' : ($exam['type'] === 'objective' ? 'admin-badge-info' : 'admin-badge-success') ?>">
                            <?= ucfirst($exam['type'] ?? 'practical') ?>
                          </span>
                          <?php if ($exam['is_active']): ?>
                            <span class="admin-badge admin-badge-success"><i class="bi bi-circle-fill assess-active-dot"></i> Active</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </a>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  
  <script>
    $(function() {
      // Autocomplete via datalist - submit on selection
      var searchInput = document.getElementById('studentSearch');
      if (searchInput) {
        searchInput.addEventListener('change', function() {
          var form = this.closest('form');
          var datalist = document.getElementById('studentList');
          if (datalist) {
            var options = datalist.querySelectorAll('option');
            for (var i = 0; i < options.length; i++) {
              if (options[i].value === this.value) {
                form.submit();
                break;
              }
            }
          }
        });
      }

      // -- Instant save: each radio click saves immediately via AJAX --
      $(document).on('change', 'input[type="radio"][data-question-id]', function() {
        const radio = $(this);
        const qid = radio.data('question-id');
        const score = radio.val();
        const sid = $('input[name="student_id"]').val();
        const eid = $('#ajaxExamId').val();
        const row = $('#qrow-' + qid);
        const indicator = row.find('.save-indicator');
        const csrf = $('input[name="csrf_token"]').val();

        indicator.css('opacity', '0.5').html('<i class="bi bi-arrow-repeat"></i>');

        $.ajax({
          url: 'assess?exam_id=' + eid,
          method: 'POST',
          data: {
            action: 'save_score',
            question_id: qid,
            student_id: sid,
            exam_id: eid,
            score: score,
            csrf_token: csrf
          },
          success: function(res) {
            if (res.success) {
              indicator.css('opacity', '1').html('<i class="bi bi-check-circle-fill"></i>');
              setTimeout(function() {
                indicator.css('opacity', '0');
              }, 2000);
            } else {
              indicator.css('opacity', '1').html('<i class="bi bi-exclamation-circle-fill text-danger"></i>');
              console.error('Save failed:', res.error);
            }
          },
          error: function() {
            indicator.css('opacity', '1').html('<i class="bi bi-exclamation-circle-fill text-danger"></i>');
          }
        });
      });

      // Show saved indicators for already-checked radios on page load
      $('input[type="radio"][data-question-id]:checked').each(function() {
        const qid = $(this).data('question-id');
        $('#qrow-' + qid).find('.save-indicator').css('opacity', '1').html('<i class="bi bi-check-circle-fill text-success"></i>');
        setTimeout(function() {
          $('#qrow-' + qid).find('.save-indicator').css('opacity', '0');
        }, 3000);
      });

      // Keyboard shortcuts
      $(document).on('keydown', function(e) {
        if ($(e.target).is('input, textarea, select, [contenteditable]')) return;
        if (e.key === 's' || e.key === 'S') {
          e.preventDefault();
          var btn = $('button[name="submit"]');
          if (btn.length) btn.click();
        }
        if (e.key === 'n' || e.key === 'N') {
          e.preventDefault();
          var radios = $('input[type="radio"][data-question-id]:not(:checked)');
          if (radios.length) {
            var firstRow = radios.first().closest('tr');
            firstRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            radios.first().focus();
          }
        }
        if (e.key === 'p' || e.key === 'P') {
          e.preventDefault();
          var checked = $('input[type="radio"][data-question-id]:checked');
          if (checked.length) {
            var currentRow = checked.last().closest('tr');
            var prevRow = currentRow.prev('tr');
            if (prevRow.length) {
              prevRow[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
              prevRow.find('input[type="radio"]').first().focus();
            }
          }
        }
      });
    });
  </script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var grid = document.getElementById('studentCardGrid');
      if (!grid) return;
      var cards = Array.from(grid.querySelectorAll('.col'));
      var sortBtns = document.querySelectorAll('.sort-students');
      sortBtns.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          sortBtns.forEach(function(b) { b.classList.remove('active'); });
          this.classList.add('active');
          var key = this.getAttribute('data-sort');
          cards.sort(function(a, b) {
            var va = a.getAttribute('data-' + key) || '';
            var vb = b.getAttribute('data-' + key) || '';
            if (key === 'status') {
              if (va === vb) {
                var na = a.getAttribute('data-name') || '';
                var nb = b.getAttribute('data-name') || '';
                return na.localeCompare(nb);
              }
              return va === 'pending' ? -1 : 1;
            }
            return va.localeCompare(vb);
          });
          cards.forEach(function(c) { grid.appendChild(c); });
        });
      });
      // Trigger default sort on load
      var defaultBtn = document.querySelector('.sort-students.active');
      if (defaultBtn) defaultBtn.click();
    });
  </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>

</html>
