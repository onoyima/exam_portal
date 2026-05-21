<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';

$name = $_SESSION['user'] ?? 'Examiner';
$error = '';
$success = '';

try {
    // Get examiner id
    $examiner = $db->prepare("SELECT id FROM examiners WHERE username=?");
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
      WHERE (ee.examiner_id IS NOT NULL OR e.type = 'viva') 
      AND e.is_active = 1 
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
      $stmt = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON s.id=es.student_id WHERE es.exam_id=? ORDER BY s.name");
      $stmt->execute([$selected_exam]);
      $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if ($search && $selected_exam) {
      $searchParam = "%$search%";
      $stmt = $db->prepare("
        SELECT s.*, s.id as student_id 
        FROM students s 
        JOIN exam_students es ON s.id = es.student_id 
        WHERE (
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

    // ── AJAX instant save: saves a single score immediately on radio click ──
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

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Examiner Dashboard - Assessment Portal</title>
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css" />
  
  <style>
    /* Custom Radio Styling for Checklist */
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
      color: var(--dark-color);
    }

    /* Table Enhancements */
    .table-assessment thead th {
      background-color: var(--primary-color) !important;
      color: white;
      text-transform: uppercase;
      font-size: 0.85rem;
      letter-spacing: 1px;
      padding: 1.25rem 1rem;
    }

    .table-assessment tbody tr:hover {
      background-color: rgba(9, 44, 25, 0.03);
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

    /* Skeleton Loading or Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem;
      background: #fff;
      border-radius: var(--border-radius);
      border: 2px dashed #e0e7ff;
    }
  </style>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <div class="exam-sidebar d-flex flex-column p-3" id="examSidebar">
    <h4 class="fw-bold mb-4 text-center">Examiner Panel</h4>
    <ul class="nav nav-pills flex-column mb-auto">
      <?php foreach ($all_exams as $exam): ?>
        <li>
          <a href="?exam_id=<?= $exam['id'] ?>" class="nav-link<?= $selected_exam == $exam['id'] ? ' active' : '' ?>">
            <i class="bx bx-book-content me-2"></i>
            <?= htmlspecialchars($exam['name']) ?>
          </a>
        </li>
      <?php endforeach; ?>
      <li class="mt-3"><a href="chat" class="nav-link text-primary"><i class="bx bx-message-square-dots me-2"></i>Chat</a></li> 
      <li class="mt-3"><hr></li>
      <li><a href="logout" class="nav-link text-danger"><i class="bx bx-log-out me-2"></i>Logout</a></li>
    </ul>

    <div class="mt-auto pt-3 border-top">
      <div class="small text-muted">
        <div class="d-flex align-items-center mb-1">
          <span class="status-dot online me-2"></span>
          System Online
        </div>
      </div>
    </div>
  </div>
  
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none" id="sidebarToggle">
            <i class="bx bx-menu"></i>
          </button>
          <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index">
            <img src="assets/favicon.ico" alt="Logo" width="30" height="30">
            <span>Assessment Console</span>
          </a>
        </div>
        <div class="d-flex align-items-center gap-2">
          <div class="d-none d-md-block text-end me-2">
            <div class="fw-bold"><?= htmlspecialchars($name) ?></div>
            <div class="small text-muted text-uppercase" style="font-size: 0.7rem;">Authorized Examiner</div>
          </div>
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <?php display_auth_errors(); ?>
      <!-- Alerts -->
      <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          <?= htmlspecialchars($error) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i>
          <?= htmlspecialchars($success) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endif; ?>

      <!-- Exam Selection & Search -->
      <?php if ($selected_exam): ?>
        <?php 
          $is_active = false;
          $current_exam_name = '';
          $current_exam_type = 'practical';
          $current_exam_total_mark = 0;
          foreach($all_exams as $ex) {
            if ($ex['id'] == $selected_exam) {
              $is_active = $ex['is_active'];
              $current_exam_name = $ex['name'];
              $current_exam_type = $ex['type'] ?? 'practical';
              $current_exam_total_mark = $ex['total_mark'] ?? 0;
              break;
            }
          }
        ?>
        
        <div class="card p-3 mb-4 shadow-sm border-0">
          <form method="get" class="row g-3 align-items-center">
            <input type="hidden" name="exam_id" value="<?= $selected_exam ?>">
            <div class="col-12 col-md-8">
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control border-start-0" name="search" 
                       placeholder="Find student by Name, Matric No, or ID..."
                       value="<?= htmlspecialchars($search) ?>" id="studentSearch">
                <button class="btn btn-primary px-4" type="submit">Identify Student</button>
              </div>
            </div>
            <div class="col-12 col-md-4 text-md-end">
                <span class="badge <?= $is_active ? 'bg-success' : 'bg-secondary' ?> p-2">
                  <i class="bi bi-info-circle me-1"></i> <?= $is_active ? 'Active Session' : 'Inactive Session' ?>
                </span>
            </div>
          </form>
        </div>

        <?php if ($stu): ?>
          <div class="card border-0 mb-4 overflow-hidden">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
              <h5 class="mb-0 text-primary"><i class="bi bi-person-fill me-2"></i>Student Details</h5>
              <span class="text-muted small">ID: #<?= $stu['id'] ?></span>
            </div>
            <div class="card-body bg-light">
              <div class="row g-4">
                <div class="col-sm-4">
                  <label class="small text-muted text-uppercase fw-bold">Full Name</label>
                  <div class="h6 mb-0"><?= htmlspecialchars($stu['name']) ?></div>
                </div>
                <div class="col-sm-4">
                  <label class="small text-muted text-uppercase fw-bold">Matric Number</label>
                  <div class="h6 mb-0"><?= htmlspecialchars($stu['matric_no']) ?></div>
                </div>
                <div class="col-sm-4 text-sm-end">
                  <label class="small text-muted text-uppercase fw-bold">Current Subject</label>
                  <div class="h6 mb-0 text-success">
                    <?= htmlspecialchars($current_exam_name) ?>
                    <?php if (!empty($existing_answers)): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.65rem;">SECOND ATTEMPT / UPDATE</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="card-body p-0">
              <?php if ($is_active): ?>
                <form method="post" id="assessmentForm" class="p-4">
                  <input type="hidden" name="student_id" value="<?= $stu['student_id'] ?>">
                  <?php csrf_field(); ?>
                  <input type="hidden" id="ajaxExamId" value="<?= $selected_exam ?>">
                  <div class="table-responsive">
                    <table class="table table-assessment align-middle">
                      <thead>
                        <tr>
                          <th style="width: 60px;" class="text-center">S/N</th>
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
                            // VIVA scoring starting from 1 up to total_mark
                            $v_max = intval($current_exam_total_mark);
                            $values = [];
                            if ($v_max > 0) {
                                // For VIVA, we show every point from 1 to max to allow precise scoring
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
                            <td class="score-cell" style="position:relative;">
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
                              <small class="save-indicator text-success fw-bold" style="position:absolute;bottom:2px;right:8px;opacity:0;transition:opacity 0.3s;">
                                <i class="bi bi-check-circle-fill"></i>
                              </small>
                            </td>
                          </tr>
                        <?php endwhile; ?>
                      </tbody>
                    </table>
                  </div>
                  <div class="text-center pt-4 border-top mt-4">
                    <button type="submit" name="submit" value="1" class="btn btn-success btn-lg px-5 shadow">
                      <i class="bi bi-cloud-check me-2"></i> Save Assessment Data
                    </button>
                    <p class="text-muted small mt-2">All fields are required. Ensure scoring is accurate before submission.</p>
                  </div>
                </form>
              <?php else: ?>
                <div class="p-5 text-center">
                  <i class="bi bi-lock-fill text-warning" style="font-size: 3rem;"></i>
                  <h4 class="mt-3">Assessment Locked</h4>
                  <p class="text-muted">This examination session is currently inactive. Scoring is disabled.</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php elseif ($search): ?>
          <div class="empty-state">
            <i class="bi bi-person-x text-danger" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Student Not Found</h5>
            <p class="text-muted">We couldn't find any student with "<?= htmlspecialchars($search) ?>" in this exam division.</p>
            <a href="?exam_id=<?= $selected_exam ?>" class="btn btn-outline-primary btn-sm">Clear Search</a>
          </div>
        <?php else: ?>
          <?php if ($current_exam_type === 'viva' && !empty($students)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 text-primary"><i class="bi bi-people-fill me-2"></i>Examinee Quick List</h5>
                    <p class="text-muted small mb-0">Select a student from the list below to begin their VIVA assessment.</p>
                </div>
                <div class="card-body">
                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3">
                        <?php foreach ($students as $s): ?>
                            <div class="col">
                                <a href="?exam_id=<?= $selected_exam ?>&search=<?= urlencode($s['matric_no']) ?>" class="card h-100 text-decoration-none border hover-shadow transition">
                                    <div class="card-body p-3 d-flex align-items-center">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                            <i class="bi bi-person text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark small"><?= htmlspecialchars($s['name']) ?></div>
                                            <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($s['matric_no']) ?></div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
          <?php else: ?>
            <div class="empty-state">
              <i class="bi bi-search text-primary" style="font-size: 3rem;"></i>
              <h5 class="mt-3">Ready for Assessment</h5>
              <p class="text-muted">Please search for a student using their Name or Matric Number to start grading.</p>
            </div>
          <?php endif; ?>
        <?php endif; ?>

      <?php else: ?>
        <div class="row justify-content-center">
          <div class="col-lg-6">
            <div class="card p-5 text-center border-0 bg-primary bg-opacity-10">
              <i class="bi bi-journals text-primary mb-3" style="font-size: 4rem;"></i>
              <h3>Select an Examination</h3>
              <p class="text-muted">Choose a subject from the left panel to begin assessing your assigned students.</p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  
  <script>
    $(function() {
      // Sidebar Toggle Logic
      const sidebar = $('#examSidebar');
      const sidebarBackdrop = $('#sidebarBackdrop');
      const sidebarToggle = $('#sidebarToggle');

      sidebarToggle.on('click', function() {
        sidebar.addClass('show');
        sidebarBackdrop.addClass('show');
      });

      sidebarBackdrop.on('click', function() {
        sidebar.removeClass('show');
        sidebarBackdrop.removeClass('show');
      });

      // Autocomplete Search
      $("[name='search']").autocomplete({
        source: function(request, response) {
          $.ajax({
            url: "search_students",
            dataType: "json",
            data: {
              term: request.term,
              exam_id: <?= json_encode($selected_exam) ?>
            },
            success: function(data) {
              response(data);
            }
          });
        },
        minLength: 2,
        select: function(event, ui) {
          $(this).val(ui.item.value);
          $(this).closest('form').submit();
          return false;
        }
      });

      // ── Instant save: each radio click saves immediately via AJAX ──
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
    });
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
