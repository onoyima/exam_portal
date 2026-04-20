<?php
require 'db.php';
require_once 'exam_logic.php';
require 'auth.php';
check_auth('admin');

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

// Fetch current exam details if specified
$exam_info = null;
if ($exam_id) {
  $stmt = $db->prepare("SELECT * FROM exams WHERE id=?");
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
        WHERE es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
        ORDER BY s.name");
    $students->execute([$exam_id, $exam_id]);
  } else {
    $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
        JOIN exam_students es ON s.id=es.student_id 
        JOIN answers a ON s.id = a.student_id 
        WHERE es.exam_id=? AND a.exam_id=? 
        ORDER BY s.name");
    $students->execute([$exam_id, $exam_id]);
  }
  $students = $students->fetchAll(PDO::FETCH_ASSOC);

  if ($is_objective) {
    $questions = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
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
$exams = $db->query("SELECT * FROM exams ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics & Reports - Admin Portal</title>
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    body {
      font-family: 'Outfit', sans-serif;
      background: #f8fafc;
    }

    .report-card {
      border-radius: 1.5rem;
      border: 1px solid rgba(0, 0, 0, 0.05);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02);
      overflow: hidden;
    }

    .table thead th {
      background: #f1f5f9;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 1px;
      padding: 1rem;
      border: none;
    }

    .stat-card {
      background: #fff;
      border-radius: 1.25rem;
      padding: 1.5rem;
      border: 1px solid rgba(0, 0, 0, 0.05);
    }

    .badge-objective {
      background: #e8f5e9;
      color: #198754;
      border: 1px solid #c8e6c9;
    }

    .badge-practical {
      background: #f0fdfa;
      color: #0d9488;
      border: 1px solid #99f6e4;
    }
  </style>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>

  <div class="main-content">
    <div class="container-xl py-5 px-4">

      <?php if (!$exam_id): ?>
        <!-- List View: All Exams -->
        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h2 class="fw-bold mb-1">Analytics Reports</h2>
            <p class="text-muted">Select an examination to view detailed performance metrics</p>
          </div>
        </div>

        <div class="card report-card bg-white">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th class="ps-4">Examination Name</th>
                  <th>Type</th>
                  <th>Enrolled</th>
                  <th>Status</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($exams as $ex):
                  $stmt = $db->prepare("SELECT COUNT(*) FROM exam_students WHERE exam_id=?");
                  $stmt->execute([$ex['id']]);
                  $enrolled = $stmt->fetchColumn();
                  ?>
                  <tr>
                    <td class="ps-4">
                      <div class="fw-bold text-dark"><?= htmlspecialchars($ex['name']) ?></div>
                      <div class="small text-muted"><?= date('M d, Y', strtotime($ex['created_at'])) ?></div>
                    </td>
                    <td>
                      <span
                        class="badge rounded-pill <?= $ex['type'] === 'objective' ? 'badge-objective' : 'badge-practical' ?>">
                        <i class="bi <?= $ex['type'] === 'objective' ? 'bi-list-check' : 'bi-laptop' ?> me-1"></i>
                        <?= ucfirst($ex['type']) ?>
                      </span>
                    </td>
                    <td>
                      <div class="fw-medium"><?= $enrolled ?> Students</div>
                    </td>
                    <td>
                      <?= $ex['is_active'] ? '<span class="text-success small fw-bold"><i class="bi bi-circle-fill me-1" style="font-size: 0.5rem"></i> Active</span>' : '<span class="text-muted small fw-bold">Inactive</span>' ?>
                    </td>
                    <td class="text-end pe-4">
                      <a href="report?exam_id=<?= $ex['id'] ?>" class="btn btn-primary btn-sm rounded-pill px-3 fw-bold">
                        View Report
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php else: ?>
        <!-- Detailed View: Specific Exam -->
        <nav aria-label="breadcrumb" class="mb-4">
          <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="report" class="text-decoration-none">Reports</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($exam_info['name']) ?></li>
          </ol>
        </nav>

        <div
          class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-5 gap-3">
          <div>
            <h2 class="fw-bold mb-1"><?= htmlspecialchars($exam_info['name']) ?></h2>
            <span
              class="badge rounded-pill <?= $exam_info['type'] === 'objective' ? 'badge-objective' : 'badge-practical' ?> px-3 py-2">
              Detailed Performance Breakdown
            </span>
          </div>
          <div class="d-flex gap-2">
            <a href="report?exam_id=<?= $exam_id ?>&download=1"
              class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
              <i class="bi bi-file-earmark-spreadsheet me-2"></i> Download CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-4 fw-bold border-2">
              <i class="bi bi-printer me-2"></i> Print
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
              WHERE es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
              ORDER BY s.name");
          $students->execute([$exam_id, $exam_id]);
        } else {
          $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
              JOIN exam_students es ON s.id=es.student_id 
              JOIN answers a ON s.id = a.student_id 
              WHERE es.exam_id=? AND a.exam_id=? 
              ORDER BY s.name");
          $students->execute([$exam_id, $exam_id]);
        }
        $students_list = $students->fetchAll(PDO::FETCH_ASSOC);

        if ($is_obj) {
          $q_stmt = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
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
        ?>

        <!-- Stats Overview -->
        <div class="row g-4 mb-5">
          <div class="col-md-4">
            <div class="stat-card">
              <div class="small text-muted fw-bold mb-2">PARTICIPANTS</div>
              <div class="h3 fw-bold mb-0 text-primary"><?= count($processed_rows) ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="small text-muted fw-bold mb-2">AVERAGE SCORE</div>
              <?php
              $total_pts = 0;
              if ($is_obj && isset($exam_info['total_mark']) && $exam_info['total_mark'] > 0) {
                $total_pts = $exam_info['total_mark'];
              } else {
                foreach ($q_ids as $qid) {
                  if ($is_obj) {
                    foreach ($questions_meta as $qm) {
                      if ($qm['id'] == $qid)
                        $total_pts += ($qm['mark'] ?? 1);
                    }
                  } else {
                    // For practical exams, it's harder to get total possible here without more queries
                    // but we can at least show the points.
                  }
                }
              }
              ?>
              <div class="h3 fw-bold mb-0 text-success"><?= $avg_score ?> <small class="text-muted fs-6">/
                  <?= $total_pts > 0 ? $total_pts : 'pts' ?></small></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="small text-muted fw-bold mb-2">TOTAL QUESTIONS</div>
              <div class="h3 fw-bold mb-0 text-dark"><?= count($q_ids) ?></div>
            </div>
          </div>
        </div>

        <div class="card report-card bg-white shadow-sm border-0">
          <div class="table-responsive">
            <table class="table table-bordered mb-0">
              <thead>
                <tr>
                  <th class="ps-4">First Name</th>
                  <th>Middle Name</th>
                  <th>Last Name</th>
                  <th>Matric No.</th>
                  <?php for ($i = 1; $i <= count($q_ids); $i++): ?>
                    <th class="text-center">Q<?= $i ?></th>
                  <?php endfor; ?>
                  <th class="text-center bg-light fw-bold pe-4">Total</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($processed_rows as $p_row): ?>
                  <tr>
                    <td class="ps-4 fw-medium text-dark"><?= htmlspecialchars($p_row['firstname']) ?></td>
                    <td><?= htmlspecialchars($p_row['middlename'] ?? '') ?></td>
                    <td><?= htmlspecialchars($p_row['lastname']) ?></td>
                    <td class="text-muted small"><?= htmlspecialchars($p_row['matric']) ?></td>
                    <?php foreach ($p_row['scores'] as $s_val): ?>
                      <td class="text-center <?= $s_val > 0 ? 'text-success fw-bold' : 'text-danger' ?>">
                        <?= $s_val ?>
                      </td>
                    <?php endforeach; ?>
                    <td class="text-center bg-light fw-bold text-primary pe-4">
                      <?= $p_row['total'] ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
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