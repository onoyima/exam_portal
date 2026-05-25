<?php
require 'db.php';
require_once 'exam_logic.php';
require 'auth.php';
check_auth('admin');

$back_url = 'admin_dashboard';

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Fetch current exam details if specified
$exam_info = null;
if ($exam_id) {
  $stmt = $db->prepare("SELECT * FROM exams WHERE id=? AND deleted_at IS NULL");
  $stmt->execute([$exam_id]);
  $exam_info = $stmt->fetch(PDO::FETCH_ASSOC);

  // Auto-finalize any expired attempts for this exam so analytics are accurate
  auto_finalize_expired_attempts($db, $exam_id);
}

// Logic to handle both practical and objective reports (CSV Download)
if ($exam_id && $exam_info && isset($_GET['download'])) {
  $is_objective = ($exam_info['type'] === 'objective');

  // Get all students assigned to this exam
  // Get students who have actually attempted the exam
  if ($is_objective) {
    $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
        JOIN exam_students es ON s.id=es.student_id 
        JOIN objective_attempts oa ON s.id = oa.student_id 
        WHERE s.deleted_at IS NULL AND es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
        ORDER BY s.name");
    $students->execute([$exam_id, $exam_id]);
  } else {
    $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
        JOIN exam_students es ON s.id=es.student_id 
        JOIN answers a ON s.id = a.student_id 
        WHERE s.deleted_at IS NULL AND es.exam_id=? AND a.exam_id=? 
        ORDER BY s.name");
    $students->execute([$exam_id, $exam_id]);
  }
  $students = $students->fetchAll(PDO::FETCH_ASSOC);

  if ($is_objective) {
    $questions = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
    $questions->execute([$exam_id]);
    $questions_data = $questions->fetchAll(PDO::FETCH_ASSOC);
    $question_ids = array_column($questions_data, 'id');
  } else {
    $questions = $db->prepare("SELECT q.id FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=? ORDER BY q.id");
    $questions->execute([$exam_id]);
    $question_ids = $questions->fetchAll(PDO::FETCH_COLUMN);
  }

  header('Content-Type: text/csv');
  header('Content-Disposition: attachment;filename=report_' . str_replace(' ', '_', $exam_info['name']) . '.csv');
  $out = fopen('php://output', 'w');
  $header = ['First Name', 'Middle Name', 'Last Name', 'Username', 'Matric'];
  for ($i = 1; $i <= count($question_ids); $i++)
    $header[] = 'Q' . $i;
  $header[] = 'Total';
  fputcsv($out, $header);

    foreach ($students as $stu) {
      $row = [$stu['firstname'], $stu['middlename'], $stu['lastname'], $stu['username'], $stu['matric_no']];
      $total = 0;

      if ($is_objective) {
        $method = $exam_info['grading_method'] ?? 'highest';
        if ($method === 'average') {
          // Average logic: Get all finished attempts
          $all_att = $db->prepare("SELECT attempt_no, score, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL");
          $all_att->execute([$exam_id, $stu['id']]);
          $attempts_data = $all_att->fetchAll(PDO::FETCH_ASSOC);
          
          if (!empty($attempts_data)) {
            $total_pts = 0; $count = count($attempts_data);
            $q_sums = array_fill_keys($question_ids, 0);
            $q_counts = array_fill_keys($question_ids, 0);

            foreach ($attempts_data as $att) {
              $total_pts += $att['score'];
              $att_no = $att['attempt_no'];
              $ans_st = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
              $ans_st->execute([$exam_id, $stu['id'], $att_no]);
              $ans_map = $ans_st->fetchAll(PDO::FETCH_KEY_PAIR);
              
              foreach ($questions_data as $q) {
                $sel = $ans_map[$q['id']] ?? '';
                $sc = (strtoupper($sel) === strtoupper($q['correct_option'])) ? ($q['mark'] ?? 1) : 0;
                $q_sums[$q['id']] += $sc;
                $q_counts[$q['id']]++;
              }
            }

            foreach ($questions_data as $q) {
              $avg_q = ($q_counts[$q['id']] > 0) ? round($q_sums[$q['id']] / $q_counts[$q['id']], 2) : 0;
              $row[] = $avg_q;
            }
            $display_total = round($total_pts / $count, 2);
          } else {
            foreach ($question_ids as $qid) $row[] = 0;
            $display_total = 0;
          }
        } else {
          // Highest (Override) logic
          $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY score DESC LIMIT 1");
          $att_stmt->execute([$exam_id, $stu['id']]);
          $att_row = $att_stmt->fetch(PDO::FETCH_ASSOC);
          $best_attempt = $att_row['attempt_no'] ?? null;
          $assigned_ids = !empty($att_row['question_order']) ? explode(',', $att_row['question_order']) : [];

          if ($best_attempt) {
            $ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
            $ans_stmt->execute([$exam_id, $stu['id'], $best_attempt]);
            $submitted_answers = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $possible_total = 0;
            foreach ($assigned_ids as $aid) {
              foreach ($questions_data as $qd) {
                if ($qd['id'] == $aid) $possible_total += ($qd['mark'] ?? 1);
              }
            }

            foreach ($questions_data as $q) {
              if (!empty($assigned_ids) && !in_array($q['id'], $assigned_ids)) {
                $row[] = '-'; continue;
              }
              $sel = $submitted_answers[$q['id']] ?? '';
              $sc = (strtoupper($sel) === strtoupper($q['correct_option'])) ? ($q['mark'] ?? 1) : 0;
              $row[] = $sc;
              $total += $sc;
            }
            $display_total = $total;
            if (isset($exam_info['total_mark']) && $exam_info['total_mark'] > 0 && $possible_total > 0) {
              $display_total = ($total / $possible_total) * $exam_info['total_mark'];
            }
          } else {
            foreach ($question_ids as $qid) $row[] = 0;
            $display_total = 0;
          }
      }
    } else {
      $scores_stmt = $db->prepare("SELECT question_id, score FROM answers WHERE exam_id=? AND student_id=?");
      $scores_stmt->execute([$exam_id, $stu['id']]);
      $map = $scores_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
      foreach ($question_ids as $qid) {
        $sc = $map[$qid] ?? 0;
        $row[] = $sc;
        $total += $sc;
      }
      $display_total = $total;
    }

    $row[] = round($display_total, 2);
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}

// Fetch all exams for the list view
$exams = $db->query("SELECT * FROM exams WHERE deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Global stats
$total_students = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
$total_exams = $db->query("SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL")->fetchColumn();
$active_exams_count = $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1 AND deleted_at IS NULL")->fetchColumn();
$total_questions = $db->query("SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics - Admin Portal</title>
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
          <a href="<?= $back_url ?>" class="admin-back-btn" title="Back to Dashboard">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Reports & Analytics</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($_SESSION['user'] ?? 'Admin') ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">

      <?php if (!$exam_id): ?>
        <!-- List View: All Exams -->
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
          <ol>
            <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
            <li class="sep">/</li>
            <li class="current" aria-current="page">Reports</li>
          </ol>
        </nav>

        <div class="admin-page-header">
          <h2 class="admin-page-title">Reports & Analytics</h2>
          <div class="admin-page-actions">
            <p class="text-muted mb-0">Select an examination to view detailed performance metrics</p>
          </div>
        </div>

        <!-- Global Stats -->
        <div class="admin-stats-row mb-4">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_students ?></div>
            <div class="admin-stat-label">Total Students</div>
          </div>
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_exams ?></div>
            <div class="admin-stat-label">Total Exams</div>
          </div>
          <div class="admin-stat-card">
            <div class="admin-stat-value admin-stat-value-success"><?= $active_exams_count ?></div>
            <div class="admin-stat-label">Active Exams</div>
          </div>
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_questions ?></div>
            <div class="admin-stat-label">Total Questions</div>
          </div>
        </div>

        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-journal-check"></i> All Examinations</h3>
          </div>
          <div class="admin-card-body-flush">
            <div class="table-responsive">
              <table class="admin-table mb-0">
                <thead>
                  <tr>
                    <th>Examination Name</th>
                    <th>Type</th>
                    <th>Enrolled</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($exams as $ex):
                    $stmt = $db->prepare("SELECT COUNT(*) FROM exam_students WHERE exam_id=?");
                    $stmt->execute([$ex['id']]);
                    $enrolled = $stmt->fetchColumn();
                    $badge_class = 'admin-badge-success';
                    $icon_class = 'bi-laptop';
                    if ($ex['type'] === 'objective') {
                        $badge_class = 'admin-badge-info';
                        $icon_class = 'bi-list-check';
                    } elseif ($ex['type'] === 'viva') {
                        $badge_class = 'admin-badge-warning';
                        $icon_class = 'bi-mic';
                    }
                  ?>
                    <tr>
                      <td>
                        <div class="fw-semibold"><?= htmlspecialchars($ex['name']) ?></div>
                        <div class="text-muted small"><?= date('M d, Y', strtotime($ex['created_at'])) ?></div>
                      </td>
                      <td>
                        <span class="admin-badge <?= $badge_class ?>">
                          <i class="bi <?= $icon_class ?>"></i> <?= ucfirst($ex['type'] ?? 'practical') ?>
                        </span>
                      </td>
                      <td>
                        <div class="fw-medium"><?= $enrolled ?> Students</div>
                      </td>
                      <td>
                        <?php if ($ex['is_active']): ?>
                          <span class="admin-badge admin-badge-success"><i class="bi bi-circle-fill"></i> Active</span>
                        <?php else: ?>
                          <span class="admin-badge admin-badge-neutral">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <a href="report?exam_id=<?= $ex['id'] ?>" class="admin-btn admin-btn-primary admin-btn-sm">
                          <i class="bi bi-eye"></i> View Report
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <?php else: ?>
        <!-- Detailed View: Specific Exam -->
        <nav class="admin-breadcrumb" aria-label="Breadcrumb">
          <ol>
            <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
            <li class="sep">/</li>
            <li><a href="report">Reports</a></li>
            <li class="sep">/</li>
            <li class="current"><?= htmlspecialchars($exam_info['name']) ?></li>
          </ol>
        </nav>

        <div class="admin-page-header">
          <h2 class="admin-page-title"><?= htmlspecialchars($exam_info['name']) ?></h2>
          <div class="admin-page-actions">
            <a href="report?exam_id=<?= $exam_id ?>&download=1" class="admin-btn admin-btn-primary admin-btn-sm">
              <i class="bi bi-file-earmark-spreadsheet"></i> Download CSV
            </a>
            <button onclick="window.print()" class="admin-btn admin-btn-outline admin-btn-sm">
              <i class="bi bi-printer"></i> Print / PDF
            </button>
          </div>
        </div>

        <?php
        // Pre-process data for detailed table
        $is_obj = ($exam_info['type'] === 'objective');
        if ($is_obj) {
          $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
              JOIN exam_students es ON s.id=es.student_id 
              JOIN objective_attempts oa ON s.id = oa.student_id 
              WHERE s.deleted_at IS NULL AND es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
              ORDER BY s.name");
          $students->execute([$exam_id, $exam_id]);
        } else {
          $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
              JOIN exam_students es ON s.id=es.student_id 
              JOIN answers a ON s.id = a.student_id 
              WHERE s.deleted_at IS NULL AND es.exam_id=? AND a.exam_id=? 
              ORDER BY s.name");
          $students->execute([$exam_id, $exam_id]);
        }
        $students_list = $students->fetchAll(PDO::FETCH_ASSOC);

        if ($is_obj) {
          $q_stmt = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
          $q_stmt->execute([$exam_id]);
          $questions_meta = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
          $q_ids = array_column($questions_meta, 'id');
        } else {
          $q_stmt = $db->prepare("SELECT q.id FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=? ORDER BY q.id");
          $q_stmt->execute([$exam_id]);
          $q_ids = $q_stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $total_scores_sum = 0;
        $processed_rows = [];
        foreach ($students_list as $stu) {
          $row = [
            'firstname' => $stu['firstname'],
            'middlename' => $stu['middlename'],
            'lastname' => $stu['lastname'],
            'matric' => $stu['matric_no'],
            'scores' => [],
            'total' => 0
          ];
          if ($is_obj) {
            $method = $exam_info['grading_method'] ?? 'highest';
            if ($method === 'average') {
              $all_att = $db->prepare("SELECT attempt_no, score FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL");
              $all_att->execute([$exam_id, $stu['id']]);
              $attempts = $all_att->fetchAll(PDO::FETCH_ASSOC);
              
              if (!empty($attempts)) {
                $count = count($attempts); $total_pts = 0;
                $q_sums = array_fill_keys($q_ids, 0);

                foreach ($attempts as $att) {
                  $total_pts += $att['score'];
                  $ans_st = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
                  $ans_st->execute([$exam_id, $stu['id'], $att['attempt_no']]);
                  $ans_m = $ans_st->fetchAll(PDO::FETCH_KEY_PAIR);
                  foreach ($questions_meta as $q_meta) {
                    $sc = (strtoupper($ans_m[$q_meta['id']] ?? '') === strtoupper($q_meta['correct_option'])) ? ($q_meta['mark'] ?? 1) : 0;
                    $q_sums[$q_meta['id']] += $sc;
                  }
                }
                foreach ($q_ids as $qid) $row['scores'][] = round($q_sums[$qid] / $count, 2);
                $row['total'] = round($total_pts / $count, 2);
              } else {
                foreach ($q_ids as $nil) $row['scores'][] = 0;
              }
            } else {
              // Highest (Override)
              $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY score DESC LIMIT 1");
              $att_stmt->execute([$exam_id, $stu['id']]);
              $best_att_row = $att_stmt->fetch(PDO::FETCH_ASSOC);
              $best_att = $best_att_row['attempt_no'] ?? null;
              $assigned_ids = !empty($best_att_row['question_order']) ? explode(',', $best_att_row['question_order']) : [];

              if ($best_att) {
                $ans_stmt = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
                $ans_stmt->execute([$exam_id, $stu['id'], $best_att]);
                $ans_map = $ans_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

                $possible_total = 0;
                foreach ($assigned_ids as $aid) {
                  foreach ($questions_meta as $qm) {
                    if ($qm['id'] == $aid) $possible_total += ($qm['mark'] ?? 1);
                  }
                }

                foreach ($questions_meta as $q_meta) {
                  if (!empty($assigned_ids) && !in_array($q_meta['id'], $assigned_ids)) {
                    $row['scores'][] = '-'; continue;
                  }
                  $sc = (strtoupper($ans_map[$q_meta['id']] ?? '') === strtoupper($q_meta['correct_option'])) ? ($q_meta['mark'] ?? 1) : 0;
                  $row['scores'][] = $sc;
                  $row['total'] += $sc;
                }
                if (isset($exam_info['total_mark']) && $exam_info['total_mark'] > 0 && $possible_total > 0) {
                  $row['total'] = ($row['total'] / $possible_total) * $exam_info['total_mark'];
                }
              } else {
                foreach ($q_ids as $nil) $row['scores'][] = 0;
              }
            }
          } else {
            $sc_stmt = $db->prepare("SELECT question_id, score FROM answers WHERE exam_id=? AND student_id=?");
            $sc_stmt->execute([$exam_id, $stu['id']]);
            $sc_map = $sc_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($q_ids as $qid) {
              $v = $sc_map[$qid] ?? 0;
              $row['scores'][] = $v;
              $row['total'] += $v;
            }
          }
          $total_scores_sum += $row['total'];
          $processed_rows[] = $row;
        }
        $avg_score = count($processed_rows) > 0 ? round($total_scores_sum / count($processed_rows), 2) : 0;

        // compute max possible points for bars
        $max_possible = 0;
        if ($is_obj && isset($exam_info['total_mark']) && $exam_info['total_mark'] > 0) {
          $max_possible = $exam_info['total_mark'];
        } else {
          foreach ($q_ids as $qid) {
            if ($is_obj) {
              foreach ($questions_meta as $qm) {
                if ($qm['id'] == $qid)
                  $max_possible += ($qm['mark'] ?? 1);
              }
            }
          }
        }
        if ($max_possible <= 0) $max_possible = 100;
        ?>

        <!-- Stats Overview -->
        <div class="admin-stats-row mb-4">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= count($processed_rows) ?></div>
            <div class="admin-stat-label">Participants</div>
          </div>
          <div class="admin-stat-card">
            <div class="admin-stat-value admin-stat-value-success"><?= $avg_score ?></div>
            <div class="admin-stat-label">Average Score</div>
          </div>
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= count($q_ids) ?></div>
            <div class="admin-stat-label">Total Questions</div>
          </div>
          <?php
          // Enrolled count
          $enr_stmt = $db->prepare("SELECT COUNT(*) FROM exam_students WHERE exam_id=?");
          $enr_stmt->execute([$exam_id]);
          $enrolled_count = $enr_stmt->fetchColumn();
          $submission_rate = $enrolled_count > 0 ? round((count($processed_rows) / $enrolled_count) * 100, 1) : 0;
          ?>
          <div class="admin-stat-card">
            <div class="admin-stat-value admin-stat-value-sm"><?= $submission_rate ?>%</div>
            <div class="admin-stat-label">Submission Rate (<?= count($processed_rows) ?>/<?= $enrolled_count ?>)</div>
          </div>
        </div>

        <?php if (empty($processed_rows)): ?>
          <div class="admin-empty">
            <div class="admin-empty-icon"><i class="bi bi-inbox admin-empty-icon-lg"></i></div>
            <div class="admin-empty-title">No Submissions Yet</div>
            <div class="admin-empty-desc">No students have submitted this examination yet. Reports will be available once submissions are made.</div>
          </div>
        <?php else: ?>
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-table"></i> Performance Breakdown</h3>
              <span class="admin-badge admin-badge-neutral"><?= count($processed_rows) ?> Students</span>
            </div>
            <div class="admin-card-body-flush">
              <div class="table-responsive">
                <table class="admin-table mb-0">
                  <thead>
                    <tr>
                      <th>First Name</th>
                      <th>Middle Name</th>
                      <th>Last Name</th>
                      <th>Matric No.</th>
                      <?php for ($i = 1; $i <= count($q_ids); $i++): ?>
                        <th class="text-center">Q<?= $i ?></th>
                      <?php endfor; ?>
                      <th class="text-center">Total</th>
                      <th class="perf-th">Performance</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($processed_rows as $p_row):
                      $pct = $max_possible > 0 ? round(($p_row['total'] / $max_possible) * 100, 1) : 0;
                      $bar_class = $pct >= 70 ? 'excellent' : ($pct >= 40 ? 'average' : 'poor');
                    ?>
                      <tr>
                        <td class="fw-medium"><?= htmlspecialchars($p_row['firstname']) ?></td>
                        <td><?= htmlspecialchars($p_row['middlename'] ?? '') ?></td>
                        <td><?= htmlspecialchars($p_row['lastname']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($p_row['matric']) ?></td>
                        <?php foreach ($p_row['scores'] as $s_val): ?>
                          <td class="text-center <?= is_numeric($s_val) && $s_val > 0 ? 'text-success fw-bold' : (is_numeric($s_val) && $s_val == 0 ? 'text-danger' : '') ?>">
                            <?= $s_val ?>
                          </td>
                        <?php endforeach; ?>
                        <td class="text-center fw-bold"><?= $p_row['total'] ?></td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <div class="perf-bar-track">
                              <div class="perf-bar-fill <?= $bar_class ?>" style="width:<?= min($pct, 100) ?>%;"></div>
                            </div>
                            <span class="perf-pct"><?= $pct ?>%</span>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>

</html>
