<?php
require 'auth.php';
check_auth('student');
check_device_auth(); // Phase 3 — device authorization gate
require 'db.php';

$student_id = $_SESSION['student_id'];
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if (!$exam_id)
  die('No exam selected.');

// Check if exam is assigned and active
$stmt = $db->prepare("SELECT * FROM exams e JOIN exam_students es ON e.id=es.exam_id WHERE e.id=? AND es.student_id=? AND e.type='objective' AND e.is_active=1");
$stmt->execute([$exam_id, $student_id]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$exam)
  die('You are not assigned to this exam or it is not active.');

// --- NEW scheduling check ---
$now_ts = time();
$start_ts = !empty($exam['start_time']) ? strtotime($exam['start_time']) : null;
$end_ts = !empty($exam['end_time']) ? strtotime($exam['end_time']) : null;

// Allow finishing or viewing results even if scheduled time passed, but NOT starting or resuming the main exam interface
$is_view_mode = isset($_GET['view_result']) || isset($_GET['review_attempt']);
if (!$is_view_mode) {
  if ($start_ts && $now_ts < $start_ts) {
    header("Location: student_dashboard?error=Exam has not started yet. Scheduled for " . date('M j, Y H:i', $start_ts));
    exit;
  }
  if ($end_ts && $now_ts > $end_ts) {
    // If an attempt is currently IN PROGRESS, we might want to allow it to finish via auto-submit, 
    // but generally prevent new entry/resume if 'EXAM CLOSED'.
    header("Location: student_dashboard?error=Exam access has closed.");
    exit;
  }
}
// -----------------------------

// Handle Final Submission POST - Moved score calculation here to store it for analytics
if (isset($_POST['confirm_final_submit'])) {
  $attempt_no = intval($_POST['current_attempt_no'] ?? 1);

  // Calculate score logic (duplicated here for immediate storage)
  $qs_check = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
  $qs_check->execute([$exam_id]);
  $questions_check = $qs_check->fetchAll(PDO::FETCH_ASSOC);

  // Fetch student answers for this attempt
  $ans_check = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
  $ans_check->execute([$exam_id, $student_id, $attempt_no]);
  $answers_check = $ans_check->fetchAll(PDO::FETCH_KEY_PAIR);

  $calculated_score = 0;
  foreach ($questions_check as $q) {
    if (isset($answers_check[$q['id']])) {
      $sel = $answers_check[$q['id']];
      if (strtoupper($sel) === strtoupper($q['correct_option'])) {
        $calculated_score += (isset($q['mark']) ? floatval($q['mark']) : 1);
      }
    }
  }

  // Update finish time, duration, and score
  $finish_stmt = $db->prepare("UPDATE objective_attempts SET finished_at=NOW(), time_taken = TIMESTAMPDIFF(SECOND, started_at, NOW()), score=? WHERE exam_id=? AND student_id=? AND attempt_no=? AND finished_at IS NULL");
  $finish_stmt->execute([$calculated_score, $exam_id, $student_id, $attempt_no]);

  // Phase 4: Unified submit — destroy session, redirect to login
  $db->prepare("UPDATE students SET current_session_id = NULL WHERE id = ?")->execute([$student_id]);
  session_destroy();
  header("Location: student_login?submitted=1");
  exit;
}

// Pre-fetch last attempt for logic check
$stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id=? AND student_id=? ORDER BY attempt_no DESC LIMIT 1");
$stmt->execute([$exam_id, $student_id]);
$last_attempt = $stmt->fetch();

$current_attempt_no = 1;
$current_attempt_id = 0;
$is_new_attempt = false;

// 1. Check for Review Mode
$is_review_mode = isset($_GET['review_attempt']);
if ($is_review_mode) {
  $current_attempt_no = intval($_GET['review_attempt']);
  // Verify attempt exists and is finished
  $stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id=? AND student_id=? AND attempt_no=?");
  $stmt->execute([$exam_id, $student_id, $current_attempt_no]);
  $attempt_to_review = $stmt->fetch();

  if (!$attempt_to_review || !$attempt_to_review['finished_at']) {
    header("Location: student_objective_exam?exam_id=$exam_id&view_result=1");
    exit;
  }
  $current_attempt_id = $attempt_to_review['id'];
  $view_result = false; // We want to show the exam UI in review mode
} else {
  // 2. If user is explicitly asking for results
  $view_result = isset($_GET['view_result']);
}

// 3. Main Logic Gate
if (!$view_result && !$is_review_mode) {
  if (!$last_attempt) {
    if (isset($_GET['start']) || isset($_POST['confirm_final_submit'])) {
      $login_id = $_SESSION['login_id'] ?? null;
      $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, login_id, attempt_no, started_at) VALUES (?, ?, ?, 1, NOW())")->execute([$exam_id, $student_id, $login_id]);
      $current_attempt_id = $db->lastInsertId();
      $current_attempt_no = 1;
      $started_at = date('Y-m-d H:i:s');
      $is_new_attempt = true; // Fix: Set this to true for first attempt
    } else {
      header("Location: student_dashboard?error=Exam not started.");
      exit;
    }
  } else {
    if ($last_attempt['finished_at']) {
      if (isset($_GET['start_new']) && $last_attempt['attempt_no'] < $exam['max_attempts']) {
        $current_attempt_no = $last_attempt['attempt_no'] + 1;
        $login_id = $_SESSION['login_id'] ?? null;
        $db->prepare("INSERT INTO objective_attempts (exam_id, student_id, login_id, attempt_no, started_at) VALUES (?, ?, ?, ?, NOW())")->execute([$exam_id, $student_id, $login_id, $current_attempt_no]);
        $current_attempt_id = $db->lastInsertId();
        $started_at = date('Y-m-d H:i:s');
        $is_new_attempt = true;
      } else {
        // Redirect to results but only if we aren't already trying to go there
        header("Location: student_objective_exam?exam_id=$exam_id&view_result=1");
        exit;
      }
    } else {
      // Unfinished attempt exists
      if (isset($_GET['resume']) || isset($_GET['start']) || isset($_POST['confirm_final_submit'])) {
        $current_attempt_id = $last_attempt['id'];
        $current_attempt_no = $last_attempt['attempt_no'];
        $started_at = $last_attempt['started_at'];
      } else {
        header("Location: student_objective_exam?exam_id=$exam_id&view_result=1");
        exit;
      }
    }
  }
}

// Result View Mode
if ($view_result) {
  // Fetch ALL attempts (for status check)
  $atmpt_stmt = $db->prepare("SELECT * FROM objective_attempts WHERE exam_id=? AND student_id=? ORDER BY attempt_no ASC");
  $atmpt_stmt->execute([$exam_id, $student_id]);
  $all_attempts = $atmpt_stmt->fetchAll(PDO::FETCH_ASSOC);

  $qs = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
  $qs->execute([$exam_id]);
  $questions = $qs->fetchAll(PDO::FETCH_ASSOC);

  $has_active = false;
  $attempt_results = [];

  // Create a map of question marks for quick lookup
  $q_mark_map = [];
  foreach ($questions as $q) {
    $q_mark_map[$q['id']] = (isset($q['mark']) ? floatval($q['mark']) : 1);
  }

  foreach ($all_attempts as $att) {
    if (!$att['finished_at']) {
      $has_active = true;
      continue;
    }

    // Determine which questions were assigned to this specific attempt
    $assigned_ids = !empty($att['question_order']) ? explode(',', $att['question_order']) : array_column($questions, 'id');
    $this_attempt_possible = 0;
    foreach ($assigned_ids as $qid) {
      $this_attempt_possible += ($q_mark_map[$qid] ?? 1);
    }

    $cur_score = 0;
    // Fetch answers for this specific attempt
    $ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
    $ans_stmt->execute([$exam_id, $student_id, $att['attempt_no']]);
    $answers = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($questions as $q) {
      // Only count score if the question was part of this attempt
      if (!in_array($q['id'], $assigned_ids))
        continue;

      $sel = $answers[$q['id']] ?? '';
      if ($sel && strtoupper($sel) === strtoupper($q['correct_option'])) {
        $cur_score += (isset($q['mark']) ? floatval($q['mark']) : 1);
      }
    }

    // Scale score if exam has a total mark set
    $scaled_score = $cur_score;
    if (isset($exam['total_mark']) && $exam['total_mark'] > 0) {
      $scaled_score = ($this_attempt_possible > 0) ? ($cur_score / $this_attempt_possible) * $exam['total_mark'] : 0;
    }

    $attempt_results[] = [
      'attempt_no' => $att['attempt_no'],
      'score' => $scaled_score,
      'finished_at' => $att['finished_at'],
      'time_seconds' => $att['time_taken'],
      'possible_total' => (isset($exam['total_mark']) && $exam['total_mark'] > 0) ? $exam['total_mark'] : $this_attempt_possible
    ];
  }

  $final_score = 0;
  if (!empty($attempt_results)) {
    if ($exam['grading_method'] === 'highest') {
      $final_score = max(array_column($attempt_results, 'score'));
    } else {
      // Average
      $final_score = array_sum(array_column($attempt_results, 'score')) / count($attempt_results);
    }
  }

  $display_total = !empty($attempt_results) ? $attempt_results[0]['possible_total'] : 0; // Default to 0 if no attempts, otherwise use the possible_total from the first attempt (assuming it's consistent or just for display)

  $can_view = $exam['student_can_view_score'];
  ?>
  <!DOCTYPE html>
  <html lang="en">

  <head>
    <title>Result - <?= htmlspecialchars($exam['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  </head>

  <body class="bg-light">
    <?php
    // Prepare for sidebar inclusion
    $name = $_SESSION['student_name'];
    $matric = $_SESSION['matric_no'];
    // Re-fetch all active exams for sidebar
    $stmt_sb = $db->prepare("SELECT e.*, 
        (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ? AND oa.finished_at IS NOT NULL) as finished_attempts,
        (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ?) as total_attempts
        FROM exams e JOIN exam_students es ON e.id=es.exam_id 
        WHERE es.student_id=? AND e.type='objective' AND e.is_active=1 ORDER BY e.created_at DESC");
    $stmt_sb->execute([$student_id, $student_id, $student_id]);
    $active_exams = $stmt_sb->fetchAll(PDO::FETCH_ASSOC);
    include 'student_sidebar.php';
    ?>

    <div class="main-content">
      <nav class="navbar navbar-light bg-white shadow-sm mb-4 d-lg-none">
        <div class="container-fluid d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-outline-primary" id="sidebarToggleResult">
              <i class="bi bi-list"></i>
            </button>
            <span class="navbar-brand fw-bold">Student Portal</span>
            <a href="javascript:void(0)" onclick="confirmLeave('student_dashboard.php')"
              class="btn btn-sm btn-outline-secondary rounded-pill ms-2"><i class="bi bi-house-door me-1"></i> Home</a>
          </div>
          <a href="javascript:void(0)" onclick="confirmLogout()" class="btn btn-sm btn-outline-danger rounded-pill"><i
              class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
      </nav>

      <div class="container py-4">
        <div class="card p-4 p-md-5 text-center shadow border-0 rounded-5 mx-auto" style="max-width: 800px;">
          <div class="mb-4 text-success">
            <i class="bi bi-check-circle-fill" style="font-size: 5rem;"></i>
          </div>
          <h1 class="fw-bold mb-3"><?= $has_active ? 'Exam in Progress' : 'Exam Submitted!' ?></h1>
          <p class="text-muted mb-4">
            <?php if ($has_active): ?>
              You have an ongoing attempt. Please click the button below to continue.
            <?php else: ?>
              You have completed your attempt successfully.
            <?php endif; ?>
            You have used <b><?= count(array_filter($all_attempts, fn($a) => $a['finished_at'])) ?></b> of
            <b><?= $exam['max_attempts'] ?></b> allowed attempts.
          </p>

          <?php if ($can_view): ?>
            <div class="bg-primary text-white p-4 rounded-4 mb-4 shadow">
              <div class="small opacity-75 text-uppercase letter-spacing-1 mb-1">Final Calculated Result</div>
              <div class="display-3 fw-bold"><?= round($final_score, 2) ?> <span class="fs-4 opacity-75">/
                  <?= round($display_total, 2) ?></span></div>
              <div class="badge bg-white text-primary mt-2">
                <?= $exam['grading_method'] === 'highest' ? 'Highest Score Method' : 'Average Score Method' ?>
              </div>
            </div>
          <?php endif; ?>

          <div class="text-start mb-4">
            <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
              <i class="bi bi-clock-history text-primary"></i> Attempt History
            </h5>
            <div class="table-responsive">
              <table class="table table-hover align-middle border-0">
                <thead class="bg-light">
                  <tr class="small text-uppercase text-muted">
                    <th class="ps-3 border-0">Attempt</th>
                    <th class="border-0">Time Taken</th>
                    <?php if ($can_view): ?>
                      <th class="text-end pe-3 border-0">Score</th><?php endif; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($attempt_results as $res):
                    $h = floor($res['time_seconds'] / 3600);
                    $m = floor(($res['time_seconds'] % 3600) / 60);
                    $s = $res['time_seconds'] % 60;
                    $time_str = ($h > 0 ? "{$h}h " : "") . ($m > 0 ? "{$m}m " : "") . "{$s}s";
                    ?>
                    <td class="ps-3 border-0 py-3">
                      <div class="d-flex justify-content-between align-items-center">
                        <div>
                          <div class="fw-bold">Attempt #<?= $res['attempt_no'] ?></div>
                          <div class="text-muted small"><?= date('M j, Y - H:i', strtotime($res['finished_at'])) ?></div>
                        </div>
                        <a href="student_objective_exam?exam_id=<?= $exam_id ?>&review_attempt=<?= $res['attempt_no'] ?>"
                          class="btn btn-sm btn-outline-primary rounded-pill px-3">
                          <i class="bi bi-eye me-1"></i> Review
                        </a>
                      </div>
                    </td>
                    <td class="border-0 py-3">
                      <span class="badge bg-light text-dark fw-normal"><?= $time_str ?></span>
                    </td>
                    <?php if ($can_view): ?>
                      <td class="text-end pe-3 border-0 py-3">
                        <span class="fw-bold text-success fs-5"><?= round($res['score'], 2) ?></span>
                        <small class="text-muted">/ <?= round($res['possible_total'], 2) ?></small>
                      </td>
                    <?php endif; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="d-grid gap-3">
            <?php if ($has_active): ?>
              <a href="student_objective_exam?exam_id=<?= $exam_id ?>&resume=1"
                class="btn btn-warning btn-lg rounded-pill py-3 shadow-sm border-0 d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-play-fill fs-4"></i> Resume Current Attempt
              </a>
            <?php elseif (count($attempt_results) < $exam['max_attempts']): ?>
              <button type="button"
                class="btn btn-primary btn-lg rounded-pill py-3 shadow-sm border-0 d-flex align-items-center justify-content-center gap-2"
                onclick="confirmExamStart('<?= htmlspecialchars($exam['name']) ?>', 'student_objective_exam.php?exam_id=<?= $exam_id ?>&start_new=1')">
                <i class="bi bi-arrow-repeat fs-5"></i>
                Start Next Attempt (<?= $exam['max_attempts'] - count($attempt_results) ?> Left)
              </button>
            <?php endif; ?>
            <a href="student_dashboard"
              class="btn btn-light btn-lg rounded-pill py-3 border text-secondary d-flex align-items-center justify-content-center gap-2">
              <i class="bi bi-house-door"></i> Back to Dashboard
            </a>
          </div>
        </div>
      </div>
    </div>
    <script>
      const sidebar = document.getElementById('studentSidebar');
      const sidebarBackdrop = document.getElementById('sidebarBackdrop');
      const toggleBtn = document.getElementById('sidebarToggleResult');
      if (toggleBtn) {
        toggleBtn.onclick = function () {
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
      document.addEventListener('DOMContentLoaded', function () {
        // Override global alert
        window.alert = function (msg) {
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
          if (match) {
            let msg = match[1];
            el.removeAttribute('onsubmit');
            el.addEventListener('submit', function (e) {
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
                if (result.isConfirmed) {
                  let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover')
                    || el.querySelector('button[type="submit"], input[type="submit"]');
                  if (btn && btn.name) {
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
          if (match) {
            let msg = match[1];
            el.removeAttribute('onclick');
            el.addEventListener('click', function (e) {
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
                if (result.isConfirmed) {
                  if (el.tagName === 'A' && el.href) {
                    window.location.href = el.href;
                  } else if (el.tagName === 'BUTTON') {
                    // if it's a submit button inside a form
                    if (el.type === 'submit' && el.form) {
                      if (el.name) {
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
  <?php exit;
}

// Pre-fetch student attempt details including persistent question order for THIS attempt
$stmt = $db->prepare("SELECT question_order, started_at, TIMESTAMPDIFF(SECOND, started_at, NOW()) as elapsed FROM objective_attempts WHERE exam_id=? AND student_id=? AND attempt_no=?");
$stmt->execute([$exam_id, $student_id, $current_attempt_no]);
$att_data = $stmt->fetch(PDO::FETCH_ASSOC);
$stored_order = $att_data['question_order'] ?? null;
$db_elapsed = isset($att_data['elapsed']) ? intval($att_data['elapsed']) : 0;

if (!$stored_order) {
  // Generate new order
  $all_qs = $db->prepare("SELECT id FROM objective_questions WHERE exam_id=? ORDER BY id");
  $all_qs->execute([$exam_id]);
  $ids = $all_qs->fetchAll(PDO::FETCH_COLUMN);

  // Always shuffle if it's a pool-based exam or shuffle is on
  if ($exam['shuffle_objective'] || ($exam['questions_to_show'] > 0)) {
    shuffle($ids);
  }

  if ($exam['questions_to_show'] > 0) {
    $ids = array_slice($ids, 0, min(count($ids), $exam['questions_to_show']));
  }

  $stored_order = implode(',', $ids);

  // Save this order to the specific attempt row
  $db->prepare("UPDATE objective_attempts SET question_order=? WHERE exam_id=? AND student_id=? AND attempt_no=?")->execute([$stored_order, $exam_id, $student_id, $current_attempt_no]);
}

// Fetch questions strictly in the assigned order
$questions = [];
if (!empty($stored_order)) {
  // MySQL ORDER BY FIELD ensures the exact ordering from the CSV list
  $qs = $db->prepare("SELECT id, exam_id, question_text, question_image, option_a, option_b, option_c, option_d, option_a_image, option_b_image, option_c_image, option_d_image, mark FROM objective_questions WHERE id IN ($stored_order) ORDER BY FIELD(id, $stored_order)");
  $qs->execute();
  $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate Remaining Time in Seconds
$duration_seconds = intval($exam['duration_minutes']) * 60;
$remaining_seconds = $duration_seconds;
if (!isset($is_new_attempt) || !$is_new_attempt) {
  $remaining_seconds = max(0, $duration_seconds - $db_elapsed);
}

$total = count($questions);
if ($total == 0)
  die('No questions found or assigned for this exam.');

$ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
$ans_stmt->execute([$exam_id, $student_id, $current_attempt_no]);
$finished_answers = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Create correct answer map if student can view scores
$correct_answers_map = [];
if ($exam['student_can_view_score'] && $is_review_mode) {
  $all_qs_full = $db->prepare("SELECT id, correct_option FROM objective_questions WHERE exam_id=?");
  $all_qs_full->execute([$exam_id]);
  $correct_answers_map = $all_qs_full->fetchAll(PDO::FETCH_KEY_PAIR);
}

// Initialize Navigation state
$current_index = 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title><?= htmlspecialchars($exam['name']) ?> - Exam Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background: #f0f2f5;
    }

    .exam-navbar {
      background: rgba(255, 255, 255, 0.9) !important;
      backdrop-filter: blur(12px) saturate(180%);
      border-bottom: 1px solid rgba(0, 0, 0, 0.08);
    }

    .container-fluid.at-a-glance-container {
      padding-top: 5px !important;
      padding-bottom: 5px !important;
    }

    .navbar {
      min-height: 50px !important;
    }

    .timer-container {
      padding: 6px 15px !important;
      font-size: 1rem !important;
    }
    
    /* Ensure Bootstrap Modals are ALWAYS on top of the blocker */
    .modal { z-index: 20000000 !important; }
    .modal-backdrop { z-index: 19999999 !important; }
  </style>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-light">
  <!-- DATA BRIDGE (PEAK PRIORITY) -->
  <script>
    window.examId = <?= $exam_id ?>;
    window.questions = <?= json_encode($questions) ?>;
    window.total = <?= $total ?>;
    window.isLocked = false;
    window.isWebcamReady = false;
    window.currentIndex = 0;
  </script>

  <!-- ISOLATED EMERGENCY CONTROLLER (IMMUNE TO GLOBAL ERRORS) -->
  <script>
    window.finalizeEnrollment = function() {
        console.log("Proctoring Shield Cleared.");
        window.isLocked = false; // Emergency Unlock for Startup
        var blocker = document.getElementById('proctorBlocker');
        if (blocker) {
            blocker.style.display = 'none';
            blocker.remove();
        }
        
        // Restore the original Exam Engine startup
        if (typeof window.startExamFlow === 'function') {
            window.startExamFlow();
        }
    };
    window.initWebcam = function() {
        var iUI = document.getElementById('proctorInitUI');
        var lUI = document.getElementById('proctorLoadingUI');
        if (iUI) iUI.classList.add('d-none');
        if (lUI) lUI.classList.remove('d-none');
        
        navigator.mediaDevices.getUserMedia({ video: true, audio: true })
            .then(function(s) { 
                window.isWebcamReady = true; 
                // Fullscreen is now handled by the user's click gesture
                window.finalizeEnrollment(); 
            })
            .catch(function(e) { 
                window.finalizeEnrollment();
            });
    };
  </script>

  <!-- Proctoring Blocker Overlay -->
  <?php if (($exam['proctor_webcam'] || $exam['proctor_live_video']) && !$is_review_mode): ?>
    <div id="proctorBlocker"
      class="position-fixed top-0 start-0 w-100 h-100 d-flex flex-column align-items-center justify-content-center bg-white"
      style="z-index: 20000000;">
      <!-- High-Integrity Reset: Kill all background modal layers -->
      <script>document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());</script>
      
      <!-- Premium Zero-Trust Enrollment Shield -->
      <div class="proctor-enrollment-card p-0 shadow-2xl border-0 overflow-hidden" style="max-width: 540px; z-index: 20000001; border-radius: 40px; background: white; border: 1px solid #eee;">
        <div class="p-5 text-center" id="proctorInitUI">
          <div class="mb-5 position-relative">
            <div class="bg-dark p-4 rounded-circle d-inline-block shadow-lg border border-4 border-white">
               <i class="bi bi-shield-check text-success" style="font-size: 3.5rem;"></i>
            </div>
            <div class="position-absolute translate-middle-x" style="bottom: -15px; left: 50%;">
                <span class="badge rounded-pill bg-success px-3 py-2 shadow-sm border border-2 border-white" style="font-size: 0.65rem; letter-spacing: 1px;">ENCRYPTION ACTIVE</span>
            </div>
          </div>
          
          <h2 class="fw-bold mb-3 text-dark outfit tracking-tight">Security Environment</h2>
          <p class="text-muted mb-5 fs-5 fw-normal">Establishing a high-integrity, zero-trust examination workspace. <br><span class="opacity-75 small">This session requires hardware-level monitoring.</span></p>
          
          <div class="vstack gap-3 mb-5">
            <div class="d-flex align-items-center justify-content-between p-3 rounded-4 bg-light border border-white shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white p-2 rounded-3 shadow-sm"><i class="bi bi-camera-video text-primary"></i></div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Camera Access</div>
                        <div class="text-muted" style="font-size: 0.65rem;">Continuous identity verification</div>
                    </div>
                </div>
                <div class="text-primary small fw-bold">REQUIRED</div>
            </div>
            <div class="d-flex align-items-center justify-content-between p-3 rounded-4 bg-light border border-white shadow-sm">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white p-2 rounded-3 shadow-sm"><i class="bi bi-fullscreen text-primary"></i></div>
                    <div class="text-start">
                        <div class="fw-bold small text-dark">Mandatory Kiosk</div>
                        <div class="text-muted" style="font-size: 0.65rem;">Locked browser environment</div>
                    </div>
                </div>
                <div class="text-primary small fw-bold">REQUIRED</div>
            </div>
          </div>

          <button id="startProctorBtn" onclick="try { initWebcam(); } catch(e) { alert('Init Error: ' + e.message); }" class="btn btn-dark btn-lg rounded-pill px-5 py-4 fw-bold shadow-xl w-100 hover-lift outfit mb-3" style="letter-spacing: 1px; font-size: 1.1rem; border: 2px solid #198754; position: relative; z-index: 20000005;">
            <i class="bi bi-shield-check me-2 text-success"></i> INITIALIZE SECURE PORTAL
          </button>
          <div class="mt-4">
             <a href="javascript:void(0)" onclick="try { finalizeEnrollment(); } catch(e) { alert('Bypass Error: ' + e.message); }" class="text-secondary text-decoration-none small opacity-75 fw-bold" style="position: relative; z-index: 20000005;">
                <i class="bi bi-exclamation-triangle me-1"></i> EMERGENCY BYPASS (SKIP TO EXAM)
             </a>
          </div>
          <p class="text-muted mt-3" style="font-size: 0.75rem;">By proceeding, you acknowledge the proctoring protocols.</p>
        </div>

        <!-- RESTORED RED POWER BUTTON (Z-MAX) -->
        <div id="lockdownPowerBtn" onclick="try { togglePowerMenu(); } catch(e) { alert('Power Error: ' + e.message); }"
          class="position-fixed d-flex align-items-center justify-content-center shadow-lg"
          style="bottom: 30px; right: 30px; width: 60px; height: 60px; background: #dc3545; color: white; border-radius: 50%; cursor: pointer; z-index: 2147483647; border: 3px solid white; box-shadow: 0 0 20px rgba(220,53,69,0.5);">
          <i class="bi bi-power" style="font-size: 1.8rem;"></i>
        </div>

        <div id="proctorLoadingUI" class="d-none p-5 text-center">
          <div class="mb-4">
            <div class="spinner-border text-success" role="status" style="width: 4rem; height: 4rem; border-width: 5px;"></div>
          </div>
          <h3 class="fw-bold mb-3 outfit text-dark">Enrollment in Progress</h3>
          <p class="text-muted mb-4 fs-6" id="proctorBlockerMsg">Please respond to all browser prompts to authorize your session.</p>
          
          <div class="text-start mx-auto vstack gap-2 mb-4" style="max-width:300px;">
             <div id="check-camera" class="d-flex align-items-center gap-2 text-muted fw-bold small">
                <i class="bi bi-circle"></i> <span>VIDEO ENROLLMENT</span>
             </div>
             <div id="check-mic" class="d-flex align-items-center gap-2 text-muted fw-bold small">
                <i class="bi bi-circle"></i> <span>AUDIO ENROLLMENT</span>
             </div>
             <div id="check-fs" class="d-flex align-items-center gap-2 text-muted fw-bold small">
                <i class="bi bi-circle"></i> <span>KIOSK ACTIVATION</span>
             </div>
          </div>

          <div class="progress rounded-pill bg-light border" style="height: 10px; width: 80%; margin: 0 auto;">
            <div id="enrollmentProgress" class="progress-bar progress-bar-striped progress-bar-animated bg-success" style="width: 10%"></div>
          </div>
        </div>

        <div id="proctorErrorBtn" class="d-none p-5 text-center">
          <div class="bg-danger-subtle p-4 rounded-circle d-inline-block mb-4">
             <i class="bi bi-camera-off text-danger" style="font-size: 3rem;"></i>
          </div>
          <h3 class="fw-bold mb-3 text-dark outfit">Access Blocked</h3>
          <p class="text-muted mb-5" id="proctorErrorMsg">Camera access is mandatory for this assessment. <br>Please verify your settings and try again.</p>
          <div class="vstack gap-2">
            <button onclick="location.reload()" class="btn btn-danger btn-lg rounded-pill px-5 py-3 fw-bold shadow-sm w-100">
                <i class="bi bi-arrow-clockwise me-2"></i> REFRESH & RETRY
            </button>
            <a href="student_dashboard" class="btn btn-link text-muted mt-3 text-decoration-none fw-bold small">Emergency Exit</a>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
  <!-- Exam Navbar -->
  <nav class="navbar navbar-light bg-white shadow-sm sticky-top exam-navbar">
    <div class="container-fluid px-lg-5 py-2">
      <div class="d-flex align-items-center gap-3">
        <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="#">
          <img src="assets/favicon.ico" alt="Logo" width="40" height="40">
          <span class="fs-4 d-none d-md-block">Assessment Portal</span>
        </a>
        <a href="javascript:void(0)" onclick="confirmLeave('student_dashboard.php')"
          class="btn btn-light rounded-pill px-3 fw-bold shadow-sm text-primary border me-3 d-none d-lg-block">
          <i class="bi bi-house-door-fill me-1"></i> Home
        </a>
      </div>

      <div class="d-flex align-items-center gap-4">
        <div id="timer" class="timer-container">
          <i class="bi bi-clock-history text-accent"></i>
          <span id="timerText" class="font-monospace">--:--:--</span>
        </div>
        <!-- Phase 4: System Health Monitor (battery + network) -->
        <div class="d-none d-lg-flex align-items-center gap-2" id="systemMonitor" style="font-size:0.7rem;">
          <span id="batteryStatus" class="badge bg-success-subtle text-success-emphasis rounded-pill px-2"><i class="bi bi-battery-half me-1"></i><span id="batteryPct">--</span>%</span>
          <span id="networkStatus" class="badge bg-primary-subtle text-primary-emphasis rounded-pill px-2"><i class="bi bi-wifi me-1"></i><span id="networkType">--</span></span>
        </div>

          <div class="d-flex align-items-center gap-3 border-start ps-4 d-none d-md-flex">
            <!-- Phase 5: Notification Badge -->
            <div class="position-relative me-2" id="notificationCenter">
              <i class="bi bi-bell-fill fs-5 text-muted"></i>
              <span id="notificationBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger d-none" style="font-size: 0.5rem;">0</span>
            </div>
            <div class="text-end">
              <div class="fw-bold small text-dark"><?= htmlspecialchars($_SESSION['user']) ?></div>
              <div class="fw-bold text-success animate-pulse" style="font-size: 0.6rem; letter-spacing: 1px;">• LIVE
                SESSION</div>
            </div>
          <div
            class="profile-avatar bg-primary shadow-sm d-flex align-items-center justify-content-center text-white fw-bold"
            style="width: 38px; height: 38px; border-radius: 12px;">
            <?= strtoupper(substr($_SESSION['user'] ?? 'S', 0, 1)) ?>
          </div>
          <a href="javascript:void(0)" onclick="confirmLogout()"
            class="btn btn-sm btn-outline-danger rounded-pill ms-2">
            <i class="bi bi-box-arrow-right"></i> Logout
          </a>
        </div>
      </div>
    </div>
  </nav>

  <div class="container-fluid py-2 at-a-glance-container px-lg-4 px-2">
    <div class="row g-3 h-lg-100 flex-column flex-lg-row">
      <!-- Main Exam Content -->
      <div class="col-lg-9 order-1 d-flex flex-column h-lg-100">
        <div class="exam-card <?= $exam['is_strict'] ? 'strict-lockdown' : '' ?>">
          <div class="exam-header px-4 py-2 bg-white border-bottom d-flex justify-content-between align-items-center">
            <div>
              <div class="small text-uppercase fw-bold text-muted mb-0"
                style="letter-spacing: 0.5px; font-size: 0.6rem;">
                Attempt #<?= $current_attempt_no ?> | Question <span id="qNum">1</span> / <?= $total ?>
              </div>
              <h5 class="mb-0 fw-bold text-dark small" style="font-size: 0.85rem;">
                <?= $is_review_mode ? '<span class="text-primary">[REVIEW]</span> ' : '' ?><?= htmlspecialchars($exam['name']) ?>
              </h5>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-primary-light text-primary rounded-pill px-3 py-1 fw-bold" id="markLabel"
                style="font-size:0.65rem;">1.0 Mark</span>
              <?php if ($exam['is_strict']): ?>
                <span class="badge bg-danger-light text-danger rounded-pill px-2 py-1 animate-pulse"
                  style="font-size:0.65rem;"><i class="bi bi-shield-lock-fill"></i></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="card-body exam-card-body p-3 p-lg-4 bg-white flex-grow-1 overflow-auto">
            <div class="exam-progress-container">
              <div id="examProgressBar" class="exam-progress-bar" style="width: 0%"></div>
            </div>
            <div id="questionArea" class="question-text-area mb-4 text-dark">
              <!-- Question text injected here -->
              Preparing your question paper...
            </div>

            <div id="optionsContainer" class="options-wrapper">
              <!-- Options will be injected here -->
            </div>
          </div>

          <div class="px-4 py-3 bg-light border-top mt-auto">
            <div class="d-flex justify-content-between align-items-center">
              <button id="prevBtn" class="btn btn-outline-secondary px-3 py-2 rounded-pill fw-bold btn-sm">
                <i class="bi bi-arrow-left"></i> <span class="d-none d-md-inline ms-1">Prev</span>
              </button>

              <div class="d-flex gap-2">
                <div class="d-lg-none timer-badge p-1 px-3 rounded-pill bg-warning-light text-dark fw-bold my-auto"
                  style="font-size: 0.75rem;">
                  <span id="mobileTimer">--:--:--</span>
                </div>
                <button id="nextBtn" class="btn btn-primary px-4 py-2 rounded-pill fw-bold shadow-sm btn-sm">
                  <span class="d-none d-md-inline me-1">Next Question</span><span class="d-md-none">Next</span> <i
                    class="bi bi-arrow-right"></i>
                </button>
                <button id="submitBtn" class="btn btn-success px-4 py-2 rounded-pill d-none fw-bold shadow-lg btn-sm"
                  data-bs-toggle="modal" data-bs-target="#finalSubmitModal">
                  Finish <i class="bi bi-send-fill ms-1"></i>
                </button>
                <?php if ($is_review_mode): ?>
                  <a href="student_objective_exam?exam_id=<?= $exam_id ?>&view_result=1"
                    class="btn btn-outline-danger px-3 py-2 rounded-pill fw-bold shadow-sm btn-sm">
                    <i class="bi bi-x-circle me-1"></i> Exit Review
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-3 order-2 h-auto h-lg-100 mb-3 mb-lg-0">
        <div class="card palette-card border-0 shadow-sm h-100 overflow-hidden">
          <div class="card-header bg-white border-0 py-3 d-none d-lg-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-primary small d-flex align-items-center gap-2">
              <i class="bi bi-grid-3x3-gap-fill"></i> PALETTE
            </h5>
            <div class="timer-badge p-1 px-3 rounded-pill bg-warning-light text-dark fw-bold"
              style="font-size: 0.75rem;">
              <i class="bi bi-clock-history me-1"></i> <span id="sidebarTimer">--:--:--</span>
            </div>
          </div>

          <div class="card-body palette-body p-2 p-lg-3">
            <div class="question-nav-grid" id="navGrid">
              <!-- Grid blocks injected here by JS -->
            </div>

            <div class="d-none d-lg-block mt-4 pt-3 border-top">
              <div class="d-flex justify-content-around">
                <div class="text-center">
                  <div class="nav-dot answered mx-auto mb-1" style="width:12px; height:12px; border-radius:3px;">
                  </div>
                  <span class="text-muted" style="font-size:0.6rem;">Answered</span>
                </div>
                <div class="text-center">
                  <div class="nav-dot active mx-auto mb-1" style="width:12px; height:12px; border-radius:3px;"></div>
                  <span class="text-muted" style="font-size:0.6rem;">Active</span>
                </div>
                <div class="text-center">
                  <div class="nav-dot mx-auto mb-1"
                    style="width:12px; height:12px; border-radius:3px; background:#e2e8f0;"></div>
                  <span class="text-muted" style="font-size:0.6rem;">Empty</span>
                </div>
              </div>
              <div class="mt-4 p-3 bg-light rounded-4 border border-dashed text-center">
                <div class="small fw-bold text-muted mb-2"><i class="bi bi-shield-check text-success me-1"></i> SYSTEM
                  INTEGRITY</div>
                <div class="d-flex justify-content-center gap-2">
                  <div class="status-pill bg-success" style="width: 8px; height: 8px; border-radius: 50%;"
                    class="animate-pulse"></div>
                  <div class="status-pill bg-success" style="width: 8px; height: 8px; border-radius: 50%;"
                    class="animate-pulse"></div>
                  <div class="status-pill bg-success" style="width: 8px; height: 8px; border-radius: 50%;"
                    class="animate-pulse"></div>
                </div>
                <div style="font-size: 0.55rem; color: #94a3b8;" class="mt-2 text-uppercase fw-bold">Encrypted Sync
                  Active</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Violation Overlay -->
  <div id="violationOverlay"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.9); z-index:9999; align-items:center; justify-content:center; color:white; text-align:center;">
    <div class="violation-content p-5 rounded-5 shadow-lg border border-danger border-2"
      style="max-width: 600px; background: #1a1b1e;">
      <div class="violation-icon mb-4">
        <i class="bi bi-shield-lock-fill text-danger" style="font-size: 5rem;"></i>
      </div>
      <h2 class="fw-bold mb-3 text-uppercase tracking-wider">Security Violation Detected</h2>
      <p class="fs-5 mb-4 text-white-50">You have attempted to leave the examination screen or switch tasks. This action
        is recorded.</p>

      <div class="p-4 bg-danger bg-opacity-10 rounded-4 border border-danger border-opacity-25 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="text-danger-emphasis fw-bold">VIOLATION COUNT:</span>
          <span class="badge bg-danger fs-6 rounded-pill" id="violationCountBadge">1 / 3</span>
        </div>
        <div class="progress bg-dark" style="height: 10px; border-radius: 5px;">
          <div id="violationProgressBar" class="progress-bar bg-danger" style="width: 33%"></div>
        </div>
      </div>

      <div class="alert alert-warning border-0 rounded-4 p-3 mb-4">
        <i class="bi bi-clock-history me-2"></i>
        Your exam will be auto-submitted in <span id="violationTimer" class="fw-bold fs-4">60</span> seconds.
      </div>

      <button id="resumeExamBtn" class="btn btn-primary btn-lg rounded-pill px-5 py-3 fw-bold shadow-lg">
        <i class="bi bi-play-circle me-2"></i> RETURN TO EXAM
      </button>

      <p class="small text-white-50 mt-4">Note: Exiting fullscreen mode or switching tabs counts as a violation. 3
        violations result in immediate failure.</p>
    </div>
  </div>

  <!-- Phase 5: Alert Sound -->
  <audio id="alertBell" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

  <!-- Phase 4: EXAM LOCKED Overlay (5 strikes reached) -->
  <div id="examLockedOverlay"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:#0a0a0a; z-index:99999; align-items:center; justify-content:center; color:white; text-align:center; pointer-events:all;">
    <div style="max-width:600px; padding: 60px 40px;">
      <div style="width:100px;height:100px;background:rgba(239,68,68,0.15);border:3px solid #ef4444;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 28px;">
        <i class="bi bi-shield-x-fill" style="font-size:3rem;color:#ef4444;"></i>
      </div>
      <h1 style="font-size:2rem;font-weight:800;color:#fff;margin-bottom:12px;">EXAM INTERFACE LOCKED</h1>
      <p style="font-size:1.05rem;color:rgba(255,255,255,0.6);margin-bottom:32px;line-height:1.8;">
        You have reached the maximum number of security violations.<br>
        Your exam interface has been locked. No further interaction is permitted.<br>
        <strong style="color:#fbbf24;">Please notify your exam supervisor immediately.</strong>
      </p>
      <div style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:12px;padding:20px;margin-bottom:24px;">
        <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:rgba(255,255,255,0.4);margin-bottom:8px;">Violation Record</div>
        <div style="font-size:2.5rem;font-weight:800;color:#ef4444;" id="lockViolationCount">5 / 5</div>
        <div style="font-size:0.8rem;color:rgba(255,255,255,0.5);margin-top:4px;">Strikes accumulated during this session</div>
      </div>
      <p style="font-size:0.78rem;color:rgba(255,255,255,0.3);">This incident has been logged and reported to the examination board.<br>Your session ID and timestamps have been recorded.</p>
    </div>
  </div>

  <!-- Admin Warning Toast -->
  <div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3" style="z-index: 9999;">
    <div id="adminWarningToast" class="toast align-items-center text-white bg-danger border-0 h-100" role="alert"
      aria-live="assertive" aria-atomic="true" data-bs-autohide="false">
      <div class="d-flex p-3">
        <div class="toast-body">
          <h5 class="fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>ADMIN WARNING</h5>
          <p id="adminWarningMsg" class="mb-0 fs-5"></p>
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
          aria-label="Close"></button>
      </div>
    </div>
  </div>

  <!-- Final Submit Modal -->
  <div class="modal fade" id="finalSubmitModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
        <form id="finalSubmitForm" method="post">
          <div class="modal-body p-5 text-center">
            <div class="mb-4">
              <div class="bg-warning-light p-4 rounded-circle d-inline-block">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-1"></i>
              </div>
            </div>
            <h3 class="fw-bold mb-3 text-dark">Finish Examination</h3>
            <p class="text-muted mb-5 px-3 fs-5">You are about to submit your final responses. You have completed <b
                id="answeredCount" class="text-primary fs-4">0</b> out of <b><?= $total ?></b> questions.</p>

            <div class="vstack gap-3 px-4">
              <button type="submit" name="final_submit" class="btn btn-success btn-lg rounded-pill py-3 fw-bold shadow">
                Yes, Submit My Exam <i class="bi bi-check2-all ms-2"></i>
              </button>
              <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none"
                data-bs-dismiss="modal">
                Return to Exam
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Bridge data from the Peak-Priority header
    const examId = window.examId;
    const questions = window.questions;
    const total = window.total;
    const currentAttemptNo = <?= $current_attempt_no ?>;
    const currentAttemptId = <?= $current_attempt_id ?>;
    
    // Self-Healing Render Guard: If we are not in review mode and questions aren't showing, force them.
    setInterval(() => {
        var loader = document.getElementById('questionArea');
        if (loader && loader.innerHTML.includes('Preparing') && window.questions && window.questions.length > 0) {
            console.log("Self-Healing: Re-triggering question render.");
            renderQuestion(currentIndex);
        }
    }, 2000);

    const isStrict = <?= $exam['is_strict'] ? 'true' : 'false' ?>;
    const pFS = <?= ($exam['proctor_fullscreen'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pTab = <?= ($exam['proctor_tab_lock'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pKB = <?= ($exam['proctor_kb_lock'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pAuto = <?= ($exam['proctor_auto_submit'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pBrowser = <?= ($exam['proctor_browser_check'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pWebcam = <?= ($exam['proctor_webcam'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const pLive = <?= ($exam['proctor_live_video'] || $exam['is_strict']) ? 'true' : 'false' ?>;
    const shuffleOptions = <?= ($exam['shuffle_options'] ?? 0) ? 'true' : 'false' ?>;
    const isReviewMode = <?= $is_review_mode ? 'true' : 'false' ?>;
    const canViewScore = <?= $exam['student_can_view_score'] ? 'true' : 'false' ?>;
    const correctAnswers = <?= json_encode($correct_answers_map) ?>;
    let currentAnswers = <?= json_encode($finished_answers) ?>;
    let currentIndex = window.currentIndex || 0;
    let durationMinutes = <?= (int) ($exam['duration_minutes'] ?? 0) ?>;
    let remainingSeconds = <?= (int) ($remaining_seconds ?? 0) ?>;
    const maxViolations = 5; 
    const maxStrikes = 5;
    
    // Proctoring State Variables
    let isWebcamReady = false;
    let isWebcamInitialized = false;
    let proctorStatus = 'initial';
    let webcamStream = null;
    let recordingInterval = null;
    let pingInterval = null;

    // Debug Timer Setup
    console.log("Timer Init - Remaining:", remainingSeconds, "Duration:", durationMinutes);

    // Elements
    const qNum = document.getElementById('qNum');
    const markLabel = document.getElementById('markLabel');
    const questionArea = document.getElementById('questionArea');
    const optionsContainer = document.getElementById('optionsContainer');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');
    const navGrid = document.getElementById('navGrid');
    const timerText = document.getElementById('timerText');
    const sidebarTimer = document.getElementById('sidebarTimer');
    const mobileTimer = document.getElementById('mobileTimer');
    const timerContainer = document.getElementById('timer');

    // Initialize Question
    function renderQuestion(index) {
      currentIndex = index;
      const q = questions[index];

      // Update Progress
      const progress = ((index + 1) / total) * 100;
      const progressBar = document.getElementById('examProgressBar');
      if (progressBar) progressBar.style.width = progress + '%';
      const answered = !!currentAnswers[q.id];

      // Reset animation
      questionArea.style.opacity = '0';
      questionArea.style.transform = 'translateY(10px)';

      setTimeout(() => {
        qNum.textContent = index + 1;
        markLabel.textContent = (q.mark || 1) + ' Mark' + (q.mark > 1 ? 's' : '');

        let html = `<div class="badge bg-primary-light text-primary mb-3 px-3 py-2 rounded-pill fw-bold" style="font-size: 0.6rem; letter-spacing: 0.5px;">QUESTION ${index + 1} OF ${total}</div>`;
        html += `<div class="question-text fw-bold" style="font-size: 1.25rem; color: #0f172a;">${q.question_text}</div>`;

        if (q.question_image) {
          html += `<div class="mt-4 text-center"><img src="uploads/${q.question_image}" class="img-fluid rounded-4 shadow-sm border p-1" style="max-height: 280px;" alt="question visual"></div>`;
        }
        questionArea.innerHTML = html;
        questionArea.style.opacity = '1';
        questionArea.style.transform = 'translateY(0)';

        // Re-render MathJax
        if (window.MathJax) {
          MathJax.typesetPromise([questionArea]);
        }
      }, 150);

      // Prepare Options to Render
      let choices = ['a', 'b', 'c', 'd'].map(opt => {
        return {
          key: opt.toUpperCase(),
          text: q['option_' + opt],
          image: q['option_' + opt + '_image']
        };
      }).filter(c => c.text !== null && c.text !== '');

      // In Review Mode, don't shuffle options visually if you want to be consistent with original pull
      // OR you can still shuffle. User didn't specify. I'll keep shuffling if enabled.
      if (shuffleOptions) {
        // We use a seed based on student_id + attempt_no + question_id to keep it consistent 
        // OR just random is fine since keys match. I'll keep it simple/random.
        for (let i = choices.length - 1; i > 0; i--) {
          const j = Math.floor(Math.random() * (i + 1));
          [choices[i], choices[j]] = [choices[j], choices[i]];
        }
      }

      optionsContainer.innerHTML = '';
      choices.forEach((choice, choiceIdx) => {
        const displayLetter = String.fromCharCode(65 + choiceIdx); // Always A, B, C, D...
        const isSelected = currentAnswers[q.id] === choice.key;

        const div = document.createElement('div');
        div.className = `option-card ${isSelected ? 'selected' : ''}`;

        if (isReviewMode) {
          div.style.cursor = 'default';
          div.style.pointerEvents = 'none';

          if (canViewScore) {
            const correctOpt = correctAnswers[q.id];
            if (choice.key === correctOpt) {
              div.classList.add('border-success', 'bg-success-light', 'review-correct');
            } else if (isSelected && choice.key !== correctOpt) {
              div.classList.add('border-danger', 'bg-danger-light', 'review-wrong');
            }
          }
        }

        let optHtml = `
                <div class="d-flex align-items-center w-100">
                    <div class="option-label shadow-sm">${displayLetter}</div>
                    <div class="option-text flex-grow-1 fw-medium text-dark">
                        ${choice.text}`;

        if (choice.image) {
          optHtml += `<div class="mt-3"><img src="uploads/${choice.image}" class="img-fluid rounded-3" style="max-height: 120px;" alt="option visual"></div>`;
        }

        optHtml += `</div>
                  <div class="selection-indicator border rounded-2 d-flex align-items-center justify-content-center bg-white ms-3" style="width:24px; height:24px; flex-shrink:0;">
                    <i class="bi bi-check2 text-white fs-6 ${isSelected ? '' : 'd-none'}"></i>
                  </div>
                </div>`;
        div.innerHTML = optHtml;

        div.onclick = (e) => {
          // Update State using ORIGINAL key (A, B, C, D)
          currentAnswers[q.id] = choice.key;
          saveAnswer(q.id, choice.key, true);

          const allOptionsInThisCard = optionsContainer.querySelectorAll('.option-card');
          allOptionsInThisCard.forEach(c => {
            c.classList.remove('selected');
            const indicatorIcon = c.querySelector('.selection-indicator i');
            if (indicatorIcon) indicatorIcon.classList.add('d-none');
          });

          div.classList.add('selected');
          const currentIndicatorIcon = div.querySelector('.selection-indicator i');
          if (currentIndicatorIcon) currentIndicatorIcon.classList.remove('d-none');
          updateNavPalette();
        };
        optionsContainer.appendChild(div);
      });

      // Typeset options
      if (window.MathJax) {
        MathJax.typesetPromise([optionsContainer]);
      }

      // UI Controls
      prevBtn.disabled = index === 0;
      if (index === total - 1) {
        nextBtn.classList.add('d-none');
        if (!isReviewMode) submitBtn.classList.remove('d-none');
      } else {
        nextBtn.classList.remove('d-none');
        submitBtn.classList.add('d-none');
      }

      updateNavPalette();
    }

    function updateNavPalette() {
      navGrid.innerHTML = '';
      questions.forEach((q, idx) => {
        const btn = document.createElement('a');
        btn.className = 'nav-dot';
        btn.href = '#';
        if (idx === currentIndex) {
          btn.classList.add('active');
          btn.title = "Current Question";
        } else if (currentAnswers[q.id]) {
          btn.classList.add('answered');
          btn.title = "Answered";
        } else {
          btn.title = "Not Answered";
        }

        btn.textContent = idx + 1;
        btn.onclick = (e) => {
          e.preventDefault();
          if (idx !== currentIndex) renderQuestion(idx);
        };
        navGrid.appendChild(btn);
      });

      // Update Dashboard counts
      const count = Object.keys(currentAnswers).length;
      const countEl = document.getElementById('answeredCount');
      if (countEl) countEl.textContent = count;
      const countBadge = document.getElementById('answeredCountBadge');
      if (countBadge) countBadge.textContent = count;
    }

    function saveAnswer(questionId, selectedOption, skipRender = false) {
      // Optimistic UI
      currentAnswers[questionId] = selectedOption;
      if (!skipRender) renderQuestion(currentIndex);

      const formData = new FormData();
      formData.append('exam_id', examId);
      formData.append('attempt_no', currentAttemptNo);
      formData.append('question_id', questionId);
      formData.append('option', selectedOption);

      fetch('objective_exam_api?action=save_answer', {
        method: 'POST',
        body: formData
      }).then(r => r.json()).then(data => {
        if (!data.success) console.error('Save failed:', data.error);
      });
    }

    // Timer Logic
    function startTimer() {
      if (isReviewMode) {
        document.getElementById('timerText').textContent = "REVIEWING";
        document.getElementById('sidebarTimer').textContent = "REVIEWING";
        document.getElementById('mobileTimer').textContent = "REVIEW";
        return;
      }
      console.log("Timer Init - Duration:", durationMinutes, "Remaining:", remainingSeconds);

      // Safety: Update display once before anything else
      if (durationMinutes > 0) {
        remainingSeconds = Math.max(0, remainingSeconds);
      } else {
        if (timerText) timerText.textContent = 'UNTRIED/UNTIMED';
        if (sidebarTimer) sidebarTimer.textContent = 'UNTIMED';
        return;
      }

      function updateDisplay() {
        try {
          const h = Math.floor(Math.max(0, remainingSeconds) / 3600);
          const m = Math.floor((Math.max(0, remainingSeconds) % 3600) / 60);
          const s = Math.max(0, remainingSeconds) % 60;

          const timeStr = (h < 10 ? '0' : '') + h + ":" +
            (m < 10 ? '0' : '') + m + ":" +
            (s < 10 ? '0' : '') + s;

          if (timerText) timerText.textContent = timeStr;
          if (sidebarTimer) sidebarTimer.textContent = timeStr;
          if (mobileTimer) mobileTimer.textContent = timeStr;

          if (remainingSeconds <= 0) {
            if (timerText) timerText.textContent = "00:00:00";
            if (sidebarTimer) sidebarTimer.textContent = "00:00:00";
            if (mobileTimer) mobileTimer.textContent = "00:00:00";
            return false;
          }
          return true;
        } catch (e) { console.error("Timer UI Error:", e); return false; }
      }

      // Initial update
      updateDisplay();

      const interval = setInterval(() => {
        try {
          if (remainingSeconds <= 0) {
            clearInterval(interval);
            autoSubmit();
            return;
          }

          remainingSeconds--;
          if (!updateDisplay()) {
            clearInterval(interval);
            autoSubmit();
          }

          // Critical warning
          if (remainingSeconds < 300) { // 5 minutes left
            timerContainer.classList.add('timer-critical');
          }
        } catch (e) { console.error("Timer Interval Error:", e); clearInterval(interval); }
      }, 1000);
    }

    function autoSubmit() {
      // Small visual delay so the student can see the timer hit zero
      setTimeout(() => {
        showGlobalAlert("Time is up!", "Your exam session has expired and will be submitted automatically.", "info", () => {
          submitFinal();
        });
      }, 2000);
    }

    function submitFinal() {
      isLocked = true;
      window.onbeforeunload = null;
      const form = document.getElementById('finalSubmitForm');
      const input = document.createElement('input');
      input.type = 'hidden'; input.name = 'confirm_final_submit'; input.value = '1';
      form.appendChild(input);
      const attInput = document.createElement('input');
      attInput.type = 'hidden'; attInput.name = 'current_attempt_no'; attInput.value = currentAttemptNo;
      form.appendChild(attInput);
      // Phase 4: Unified submit — show confirm, then logout redirect
      Swal.fire({
        title: 'Submitting Exam...',
        text: 'Please wait while your answers are being saved.',
        icon: 'info',
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
      });
      form.submit();
    }

    // Interaction Listeners
    nextBtn.onclick = () => { if (currentIndex < total - 1) renderQuestion(currentIndex + 1); };
    prevBtn.onclick = () => { if (currentIndex > 0) renderQuestion(currentIndex - 1); };
    document.getElementById('finalSubmitForm').onsubmit = (e) => {
      e.preventDefault();
      confirmGlobalAction("Final Submission", "Are you sure you want to submit your exam now? You will not be able to change your answers after submission.", "success", "Submit Now", () => {
        submitFinal();
      });
    };

    function confirmLeave(url) {
      confirmGlobalAction("Leave Exam?", "Leaving the page will not submit your exam. You can resume later if time remains.", "warning", "Go to Dashboard", () => {
        window.location.href = url;
      });
    }

    function confirmLogout() {
      confirmGlobalAction("Logout?", "Logging out during an exam will leave your attempt unfinished. You can return later if time remains.", "danger", "Logout", () => {
        window.location.href = 'student_logout.php';
      });
    }

    function confirmExamStart(name, url) {
      // This is for the result view "Start Next Attempt"
      confirmGlobalAction("Start Next Attempt?", `You are about to start a new attempt for <b>${name}</b>. This will consume one of your allowed attempts.`, "primary", "Start Now", () => {
        window.location.href = url;
      });
    }

    let globalStatusModalInstance = null;
    function showGlobalAlert(title, message, type, callback) {
      if (isLocked && !isReviewMode && title !== 'Final Result') return; // Don't show alerts if locked unless result
      const modal = document.getElementById('globalStatusModal');
      const titleEl = document.getElementById('globalModalTitle');
      const messageEl = document.getElementById('globalModalMessage');
      const okBtn = document.getElementById('globalModalOkBtn');
      const iconEl = document.getElementById('globalModalIcon');

      titleEl.textContent = title;
      messageEl.innerHTML = message;

      // Reset classes
      iconEl.className = 'bi fs-1';
      okBtn.className = 'btn btn-lg rounded-pill py-3 fw-bold shadow w-100 ';

      if (type === 'info') {
        iconEl.classList.add('bi-info-circle', 'text-info');
        okBtn.classList.add('btn-info', 'text-white');
      } else if (type === 'success') {
        iconEl.classList.add('bi-check-circle', 'text-success');
        okBtn.classList.add('btn-success');
      } else if (type === 'warning') {
        iconEl.classList.add('bi-exclamation-triangle', 'text-warning');
        okBtn.classList.add('btn-warning');
      } else if (type === 'danger') {
        iconEl.classList.add('bi-x-circle', 'text-danger');
        okBtn.classList.add('btn-danger');
      }

      if (!globalStatusModalInstance) {
        globalStatusModalInstance = new bootstrap.Modal(modal);
      }

      okBtn.onclick = () => {
        globalStatusModalInstance.hide();
        if (callback) callback();
      };
      globalStatusModalInstance.show();
    }

    function confirmGlobalAction(title, message, type, confirmText, callback) {
      const modal = document.getElementById('globalConfirmModal');
      const titleEl = document.getElementById('globalConfirmTitle');
      const messageEl = document.getElementById('globalConfirmMessage');
      const actionBtn = document.getElementById('globalConfirmActionBtn');
      const iconContainer = document.getElementById('globalConfirmIconContainer');
      const iconEl = iconContainer.querySelector('i');

      titleEl.textContent = title;
      messageEl.innerHTML = message;
      actionBtn.textContent = confirmText;

      // Reset classes
      iconContainer.className = 'p-4 rounded-circle d-inline-block ';
      iconEl.className = 'bi fs-1 ';
      actionBtn.className = 'btn btn-lg rounded-pill py-3 fw-bold shadow ';

      if (type === 'primary') {
        iconContainer.classList.add('bg-primary-light');
        iconEl.classList.add('bi-journal-text', 'text-primary');
        actionBtn.classList.add('btn-primary');
      } else if (type === 'success') {
        iconContainer.classList.add('bg-success-light');
        iconEl.classList.add('bi-check2-circle', 'text-success');
        actionBtn.classList.add('btn-success');
      } else if (type === 'warning') {
        iconContainer.classList.add('bg-warning-light');
        iconEl.classList.add('bi-exclamation-circle', 'text-warning');
        actionBtn.classList.add('btn-warning');
      } else if (type === 'danger') {
        iconContainer.classList.add('bg-danger-light');
        iconEl.classList.add('bi-slash-circle', 'text-danger');
        actionBtn.classList.add('btn-danger');
      }

      const bsModal = new bootstrap.Modal(modal);
      actionBtn.onclick = () => {
        bsModal.hide();
        if (callback) callback();
      };
      bsModal.show();
    }

    // Anti-Cheating Lockdown Logic
    let isCheatingSubmitting = false;
    let violationCount = <?= (int) ($last_attempt['violation_count'] ?? 0) ?>;
    const maxViolations = 3;
    let proctorStatus = 'active';
    let violationTimerInterval = null;
    let isLocked = false;
    let isWebcamReady = false; // Prevents violations during initialization

    // Proctor Ping & WebRTC Live Logic
    let webcamStream = null;
    const webcamVideo = document.createElement('video');
    const webcamCanvas = document.createElement('canvas'); // Keep for snapshots as fallback

    // WebRTC State
    let peerConnection = null;
    let signalingInterval = null;
    let lastSignalId = 0;
    const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };

    window.togglePowerMenu = function() {
        if (typeof confirmGlobalAction !== 'undefined') {
            confirmGlobalAction("Session Control", "What would you like to do with this active session?", "danger", "Logout & Exit", () => {
                window.location.href = "student_logout.php";
            });
        } else {
            if (confirm("Terminate session and logout?")) window.location.href = "student_logout.php";
        }
    };

    // --- GLOBAL SECURITY CONTROLLERS ---
    window.startExamFlow = function() {
      if (typeof renderQuestion === 'function' && questions && questions.length > 0) {
          renderQuestion(currentIndex);
      }
      if (typeof startTimer === 'function') startTimer();
      console.log("Exam logic activated.");
    };

    window.finalizeEnrollment = function() {
        const blocker = document.getElementById('proctorBlocker');
        const eProgress = document.getElementById('enrollmentProgress');
        if (eProgress) eProgress.style.width = '100%';
        
        console.log("Cleaning proctoring layers...");
        if (blocker) {
          blocker.style.transition = 'opacity 0.4s ease';
          blocker.style.opacity = '0';
          blocker.style.pointerEvents = 'none';
          setTimeout(() => {
              blocker.remove();
              window.startExamFlow();
          }, 400);
        } else {
           window.startExamFlow();
        }
    };

    window.initWebcam = function() {
      console.log("Initializing Hardware Handshake...");
      const isLocal = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
      const blocker = document.getElementById('proctorBlocker');
      const blockerMsg = document.getElementById('proctorBlockerMsg');
      const errorBtn = document.getElementById('proctorErrorBtn');
      const initUI = document.getElementById('proctorInitUI');
      const loadUI = document.getElementById('proctorLoadingUI');

      if (initUI) initUI.classList.add('d-none');
      if (loadUI) loadUI.classList.remove('d-none');

      if (!window.isSecureContext && !isLocal) {
        alert("Security Error: Camera requires HTTPS.");
        return;
      }

      navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 }, audio: true })
        .then(stream => {
          window.isWebcamReady = true;
          window.webcamStream = stream;
          if (window.webcamVideo) {
              window.webcamVideo.srcObject = stream;
              window.webcamVideo.play();
          }
          setTimeout(window.triggerKiosk, 400);
        })
        .catch(err => {
          console.error("Camera Hardware Error:", err);
          alert("Hardware Fail: Check your browser camera permissions.");
        });
    };

    window.triggerKiosk = function() {
        const el = document.documentElement;
        const rfs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (rfs) {
            rfs.call(el).then(window.finalizeEnrollment).catch(window.finalizeEnrollment);
        } else {
            window.finalizeEnrollment();
        }
    };

    // Kill ghost backdrops every 3 seconds
    setInterval(() => {
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
        document.body.classList.remove('modal-open');
    }, 3000);

    window.startProctoringServices = function() {
        console.log("Activating Background Proctoring Engine...");
        
        // 1. Force Question Load
        if (typeof renderQuestion === 'function') {
            renderQuestion(0);
        }
        
        // 2. Start Multi-Layer Ping/Heartbeat
        if (typeof sendPing === 'function') {
            setInterval(() => sendPing('active'), 15000); 
            sendPing('active');
        }
        
        // 3. Initialize Background Video if requested
        if (pWebcam || pLive) {
            navigator.mediaDevices.getUserMedia({ video: true, audio: true })
                .then(stream => {
                    window.isWebcamReady = true;
                    window.webcamStream = stream;
                    if (window.webcamVideo) window.webcamVideo.srcObject = stream;
                    console.log("Background Camera Active.");
                })
                .catch(e => console.warn("Background Camera Fail:", e));
        }
    };

    async function startSignaling() {
      if (signalingInterval) return;
      signalingInterval = setInterval(async () => {
        const resp = await fetch(`webrtc_api?action=get&attempt_id=${currentAttemptId}&last_id=${lastSignalId}`);
        const data = await resp.json();
        if (data.signals) {
          for (const signal of data.signals) {
            lastSignalId = signal.id;
            handleAdminSignal(signal).catch(e => console.error("Signal handle error:", e));
          }
        }
      }, 1000); // Polling every 1 second for faster handshake
    }

    async function handleAdminSignal(signal) {
      const payload = JSON.parse(signal.payload);
      if (signal.type === 'offer') {
        if (peerConnection) peerConnection.close();
        peerConnection = new RTCPeerConnection(rtcConfig);

        webcamStream.getTracks().forEach(track => peerConnection.addTrack(track, webcamStream));

        peerConnection.onicecandidate = (event) => {
          if (event.candidate) {
            sendSignal('ice', JSON.stringify(event.candidate));
          }
        };

        await peerConnection.setRemoteDescription(new RTCSessionDescription(payload));
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);
        sendSignal('answer', JSON.stringify(answer));
      } else if (signal.type === 'ice') {
        if (peerConnection && payload) {
          try {
            await peerConnection.addIceCandidate(new RTCIceCandidate(payload));
          } catch (e) { console.error("Error adding ice candidate:", e); }
        }
      }
    }

    function sendSignal(type, payload) {
      const fd = new FormData();
      fd.append('attempt_id', currentAttemptId);
      fd.append('type', type);
      fd.append('payload', payload);
      fetch('webrtc_api?action=send', { method: 'POST', body: fd });
    }

    function getWebcamSnapshot() {
      if (!webcamStream) return null;
      webcamCanvas.width = 160;
      webcamCanvas.height = 120;
      const ctx = webcamCanvas.getContext('2d');
      ctx.drawImage(webcamVideo, 0, 0, 160, 120);
      return webcamCanvas.toDataURL('image/jpeg', 0.4);
    }

    function sendPing(status = 'active', violation = null, details = null) {
      if (isReviewMode || isLocked) return;

      const formData = new FormData();
      formData.append('exam_id', examId);
      formData.append('attempt_no', currentAttemptNo);
      formData.append('status', status);
      if (violation) {
        formData.append('violation_type', violation);
        formData.append('details', details);
      }

      // We still send a small low-res snapshot every ping as a backup thumbnail for the grid
      if (pWebcam && webcamStream) {
        const snap = getWebcamSnapshot();
        if (snap) formData.append('webcam_snapshot', snap);
      }

      fetch('proctor_ping', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
          if (data.status === 'TERMINATE' || data.status === 'FORCED_LOGOUT' || data.status === 'LOCKED') {
            isLocked = true;
            window.onbeforeunload = null;
            
            if (data.status === 'LOCKED') {
                const lockOverlay = document.getElementById('examLockedOverlay');
                const vcDisplay = document.getElementById('lockViolationCount');
                if (vcDisplay) vcDisplay.textContent = `${data.count || violationCount} / ${maxStrikes}`;
                if (lockOverlay) lockOverlay.style.display = 'flex';
                return;
            }

            const target = (data.status === 'TERMINATE') ? 'student_dashboard.php' : 'logout.php';
            // Use window.alert (which we overrode) and handle redirect in its callback/then
            Swal.fire({
              title: 'Security Alert',
              text: data.message,
              icon: 'error',
              confirmButtonColor: '#092c19'
            }).then(() => {
              location.href = target;
            });
            return;
          }

          if (data.violation_count !== undefined) {
            violationCount = data.violation_count;
            const countBadge = document.getElementById('violationCountBadge');
            const progressBar = document.getElementById('violationProgressBar');
            if (countBadge) countBadge.textContent = `${violationCount} / ${maxViolations}`;
            if (progressBar) progressBar.style.width = (violationCount / maxViolations * 100) + '%';
          }

          if (data.is_live_requested && pLive) {
            startSignaling();
          }

          if (data.warning) showAdminWarning(data.warning);

          if (data.remote_command === 'SECURITY_BYPASS') {
            console.warn("Administrative Remote Bypass triggered.");
            if (typeof window.finalizeEnrollment === 'function') {
                window.finalizeEnrollment();
            }
          }
        });
    }

    let notificationCount = 0;
    function showAdminWarning(msg) {
      const toastEl = document.getElementById('adminWarningToast');
      if (toastEl) {
        document.getElementById('adminWarningMsg').textContent = msg;
        new bootstrap.Toast(toastEl).show();
        
        // Phase 5: Sound and Notification Counter
        const bell = document.getElementById('alertBell');
        if (bell) bell.play().catch(e => console.warn("Audio play blocked", e));
        
        notificationCount++;
        const badge = document.getElementById('notificationBadge');
        if (badge) {
            badge.textContent = notificationCount;
            badge.classList.remove('d-none');
        }
      }
    }

    let isResumingFlag = false;
    function handleViolation(type = "Unknown", details = "") {
      if (isLocked || isReviewMode || isResumingFlag || (!isWebcamReady && pWebcam)) return;

      proctorStatus = 'blur';
      sendPing('blur', type, details);

      // After ping, check if strike limit reached
      setTimeout(() => {
        if (violationCount >= maxStrikes) {
          // Phase 4: LOCK the exam — DO NOT auto-submit
          isLocked = true;
          window.onbeforeunload = null;
          const lockOverlay = document.getElementById('examLockedOverlay');
          const vcDisplay = document.getElementById('lockViolationCount');
          if (vcDisplay) vcDisplay.textContent = `${violationCount} / ${maxStrikes}`;
          if (lockOverlay) lockOverlay.style.display = 'flex';
          // Log to server
          const fd = new FormData();
          fd.append('exam_id', examId);
          fd.append('attempt_no', currentAttemptNo);
          fd.append('status', 'locked');
          fetch('proctor_ping', { method: 'POST', body: fd });
          return;
        }
      }, 500);

      const overlay = document.getElementById('violationOverlay');
      const timerDisplay = document.getElementById('violationTimer');
      if (overlay) overlay.style.display = 'flex';
      if (violationTimerInterval) clearInterval(violationTimerInterval);
      let secondsLeft = 60;
      if (timerDisplay) timerDisplay.textContent = secondsLeft;
      violationTimerInterval = setInterval(() => {
        secondsLeft--;
        if (timerDisplay) timerDisplay.textContent = secondsLeft;
        if (secondsLeft <= 0) { clearInterval(violationTimerInterval); resumeExam(); } // Dismiss overlay, don't submit
      }, 1000);
    }

    function resumeExam() {
      if (isLocked || isResumingFlag) return;
      isResumingFlag = true;

      // Request Fullscreen - User gesture provided via button click or Modal OK click
      if (pFS) {
        document.documentElement.requestFullscreen().catch((e) => {
          console.warn("Fullscreen request failed:", e);
        });
      }

      const overlay = document.getElementById('violationOverlay');
      if (overlay) overlay.style.display = 'none';
      if (violationTimerInterval) clearInterval(violationTimerInterval);

      proctorStatus = 'active';
      sendPing('active');

      // Allow state to settle before accepting new violations (e.g. exit fullscreen transitions)
      setTimeout(() => {
        isResumingFlag = false;
      }, 1500);
    }

    // Event Listeners for Proctoring
    if (!isReviewMode) {
      window.addEventListener('blur', () => { if (pTab) handleViolation("WINDOW_BLUR", "Lost focus"); });
      document.addEventListener('visibilitychange', () => { if (document.hidden && pTab) handleViolation("TAB_SWITCH", "Switched tab"); });
      document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && pFS && !isLocked) handleViolation("EXIT_FULLSCREEN", "Left fullscreen");
      });

      if (pKB) {
        document.oncontextmenu = (e) => { e.preventDefault(); return false; };
        document.oncopy = (e) => e.preventDefault();
        document.oncut = (e) => e.preventDefault();
        document.onpaste = (e) => e.preventDefault();
        document.addEventListener('keydown', (e) => {
          if (e.key === 'F12' || (e.ctrlKey && e.shiftKey) || (e.ctrlKey && 'uupPcC'.includes(e.key)) || e.key === 'PrintScreen') {
            e.preventDefault();
            handleViolation("KEYBOARD_LOCK", `Key: ${e.key}`);
          }
        });
      }
    }

    // Refactored flow: Initialization helpers
    function startExamFlow() {
      // Show questions and start clock
      if (questions.length > 0) renderQuestion(currentIndex);
      startTimer();
      // Only show Strict Mode alert if needed (needs user gesture for FS)
      if (pFS && !isReviewMode) {
        showGlobalAlert("Strict Mode", "Fullscreen is required to continue. Click OK to establish a secure workspace.", "info", resumeExam);
      }
    }

    window.onload = function () {
      // 1. HIGH-PRIORITY: Security Initialization Watchdog
      let initWatchdog = null;

      const sGateBtn = document.getElementById('startProctorBtn');
      if (sGateBtn) {
        sGateBtn.addEventListener('click', function() {
          console.log("Initializer triggered. Establishing secure context...");
          
          // Instant UI Feedback
          const iUI = document.getElementById('proctorInitUI');
          const lUI = document.getElementById('proctorLoadingUI');
          if (iUI) iUI.classList.add('d-none');
          if (lUI) lUI.classList.remove('d-none');
          
          // Watchdog: If nothing happens in 10s, show a fallback or retry
          initWatchdog = setTimeout(() => {
              const msg = document.getElementById('proctorBlockerMsg');
              if (msg) msg.innerHTML = '<span class="text-warning fw-bold">HANDSHAKE DELAY</span><br><br>The system is waiting for your hardware. If you have already allowed the camera, please wait or <a href="javascript:location.reload()" class="text-accent fw-bold">REFRESH HERE</a>.';
          }, 10000);

          // Priority 1: Initialize Camera (Triggers browser prompt immediately)
          if (typeof initWebcam === 'function') {
            initWebcam();
          }

          // Priority 2: Request Fullscreen (Polyfill logic)
          try {
            const el = document.documentElement;
            const rfs = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
            if (rfs) rfs.call(el);
          } catch(e) { 
            console.warn("Fullscreen deferred: ", e); 
          }
        });
      }

      if (!isReviewMode) {
        if (pBrowser) {
          const ua = navigator.userAgent;
          if (!/Chrome/.test(ua) && !/Edg/.test(ua)) {
            showGlobalAlert("Browser Compatibility", "Google Chrome or Microsoft Edge is highly recommended for this session.", "warning");
          }
        }

        // Auto-start for non-proctored sessions
        if (!pWebcam && !pLive) {
           startExamFlow();
        }
      } else {
        // Review mode setup
        if (questions && questions.length > 0) renderQuestion(0);
        startTimer();
      }

      setInterval(() => sendPing(proctorStatus), 15000);

      // Phase 4: System Health Monitors — battery + network
      if (navigator.getBattery) {
        navigator.getBattery().then(battery => {
          const updateBattery = () => {
            const pct = Math.round(battery.level * 100);
            const el = document.getElementById('batteryPct');
            const badge = document.getElementById('batteryStatus');
            if (el) el.textContent = pct;
            if (badge) {
              badge.className = `badge rounded-pill px-2 ${pct < 20 ? 'bg-danger-subtle text-danger-emphasis' : 'bg-success-subtle text-success-emphasis'}`;
            }
          };
          updateBattery();
          battery.addEventListener('levelchange', updateBattery);
        });
      }

      if (navigator.connection) {
        const updateNetwork = () => {
          const conn = navigator.connection;
          const el = document.getElementById('networkType');
          if (el) el.textContent = conn.effectiveType || conn.type || '?';
        };
        updateNetwork();
        navigator.connection.addEventListener('change', updateNetwork);
      }
    };

    // Attach Recovery Button
    const resBtn = document.getElementById('resumeExamBtn');
    if (resBtn) {
      resBtn.onclick = resumeExam;
    }
  </script>

  <!-- Global Modals System -->
  <!-- Alert Modal -->
  <div class="modal fade" id="globalStatusModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
        <div class="modal-body p-5 text-center">
          <div class="mb-4">
            <i id="globalModalIcon" class="bi fs-1"></i>
          </div>
          <h3 id="globalModalTitle" class="fw-bold mb-3 text-dark"></h3>
          <p id="globalModalMessage" class="text-muted mb-5 fs-5"></p>
          <button id="globalModalOkBtn" type="button" class="btn btn-lg rounded-pill py-3 fw-bold shadow w-100">
            OK
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal fade" id="globalConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
        <div class="modal-body p-5 text-center">
          <div class="mb-4">
            <div id="globalConfirmIconContainer" class="p-4 rounded-circle d-inline-block">
              <i class="bi fs-1"></i>
            </div>
          </div>
          <h3 id="globalConfirmTitle" class="fw-bold mb-3 text-dark"></h3>
          <p id="globalConfirmMessage" class="text-muted mb-5 fs-5"></p>
          <div class="vstack gap-3 px-2">
            <button id="globalConfirmActionBtn" type="button" class="btn btn-lg rounded-pill py-3 fw-bold shadow">
              Confirm
            </button>
            <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none" data-bs-dismiss="modal">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- sweetalert_custom_injected_v1 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Override global alert
      window.alert = function (msg) {
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
        if (match) {
          let msg = match[1];
          el.removeAttribute('onsubmit');
          el.addEventListener('submit', function (e) {
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
              if (result.isConfirmed) {
                let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover')
                  || el.querySelector('button[type="submit"], input[type="submit"]');
                if (btn && btn.name) {
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
        if (match) {
          let msg = match[1];
          el.removeAttribute('onclick');
          el.addEventListener('click', function (e) {
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
              if (result.isConfirmed) {
                if (el.tagName === 'A' && el.href) {
                  window.location.href = el.href;
                } else if (el.tagName === 'BUTTON') {
                  // if it's a submit button inside a form
                  if (el.type === 'submit' && el.form) {
                    if (el.name) {
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
  <!-- Aggressive Global Lockdown -->
  <script src="assets/js/student_lockdown.js"></script>
</body>


</html>