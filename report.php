<?php
require 'db.php';
require_once 'exam_logic.php';
require_once __DIR__ . '/includes/student_import_helper.php';
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
/**
 * Objective exam rows for the report, marked by the shared engine so every
 * question type and every grading method is handled the same way here, on
 * the examiner results page and on the student's result page.
 */
function report_objective_rows(PDO $db, array $exam): array
{
  $questions = qe_load_questions($db, (int) $exam['id']);
  $results = qe_exam_results($db, $exam, $questions);
  $rows = [];
  if ($results) {
    $st = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON es.student_id = s.id
        WHERE es.exam_id = ? AND s.deleted_at IS NULL ORDER BY s.name");
    $st->execute([$exam['id']]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $stu) {
      $res = $results[(int) $stu['id']] ?? null;
      if (!$res) {
        continue;
      }
      $scores = [];
      foreach ($questions as $qid => $q) {
        $scores[] = $res['items'][$qid] === null ? '-' : round($res['items'][$qid]['awarded'], 2);
      }
      $rows[] = ['student' => $stu, 'scores' => $scores, 'total' => round($res['score'], 2), 'result' => $res];
    }
  }
  return ['questions' => $questions, 'rows' => $rows];
}

/** Facility per question and, for Multiple True/False, per statement. */
function report_item_analysis(array $questions, array $rows): array
{
  $out = [];
  foreach ($questions as $qid => $q) {
    $out[$qid] = ['n' => 0, 'fraction' => 0, 'full' => 0, 'statements' => array_fill(0, count($q['statements']), [0, 0])];
  }
  foreach ($rows as $r) {
    $res = $r['result'];
    $att = null;
    foreach ($res['all'] as $a) {
      if ($res['attempt_no'] === null || $a['attempt_no'] === $res['attempt_no']) {
        $att = $a; // for "average" the last attempt is used
      }
    }
    if (!$att) {
      continue;
    }
    foreach ($att['items'] as $qid => $it) {
      if (!isset($out[$qid])) {
        continue;
      }
      $out[$qid]['n']++;
      $out[$qid]['fraction'] += $it['fraction'];
      $out[$qid]['full'] += $it['status'] === 'correct' ? 1 : 0;
      foreach (($it['statements'] ?? []) as $i => $ok) {
        $out[$qid]['statements'][$i][1]++;
        $out[$qid]['statements'][$i][0] += $ok === true ? 1 : 0;
      }
    }
  }
  return $out;
}

if ($exam_id && $exam_info && isset($_GET['download'])) {
  $is_objective = ($exam_info['type'] === 'objective');
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment;filename=report_' . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $exam_info['name']) . '.csv');
  $out = fopen('php://output', 'w');

  if ($is_objective) {
    $data = report_objective_rows($db, $exam_info);
    $header = ['First Name', 'Middle Name', 'Last Name', 'Username', 'Matric'];
    $n = 0;
    foreach ($data['questions'] as $q) {
      $header[] = 'Q' . (++$n) . ($q['question_type'] === 'mtf' ? ' (MTF)' : '');
    }
    $header[] = 'Total';
    $header[] = 'Out of';
    fputcsv($out, $header);
    foreach ($data['rows'] as $r) {
      [$first_name, $middle_name, $last_name] = sih_display_name_parts($r['student']);
      fputcsv($out, array_merge(
        [$first_name, $middle_name, $last_name, $r['student']['username'], $r['student']['matric_no']],
        $r['scores'],
        [$r['total'], round($r['result']['total'], 2)]
      ));
    }
    fclose($out);
    exit;
  }

  $students = $db->prepare("SELECT DISTINCT s.* FROM students s
      JOIN exam_students es ON s.id=es.student_id
      JOIN answers a ON s.id = a.student_id
      WHERE s.deleted_at IS NULL AND es.exam_id=? AND a.exam_id=?
      ORDER BY s.name");
  $students->execute([$exam_id, $exam_id]);
  $students = $students->fetchAll(PDO::FETCH_ASSOC);
  $questions = $db->prepare("SELECT q.id FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=? ORDER BY q.id");
  $questions->execute([$exam_id]);
  $question_ids = $questions->fetchAll(PDO::FETCH_COLUMN);

  $header = ['First Name', 'Middle Name', 'Last Name', 'Username', 'Matric'];
  for ($i = 1; $i <= count($question_ids); $i++) {
    $header[] = 'Q' . $i;
  }
  $header[] = 'Total';
  fputcsv($out, $header);
  $scores_stmt = $db->prepare("SELECT question_id, score FROM answers WHERE exam_id=? AND student_id=?");
  foreach ($students as $stu) {
    [$first_name, $middle_name, $last_name] = sih_display_name_parts($stu);
    $row = [$first_name, $middle_name, $last_name, $stu['username'], $stu['matric_no']];
    $scores_stmt->execute([$exam_id, $stu['id']]);
    $map = $scores_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $total = 0;
    foreach ($question_ids as $qid) {
      $sc = $map[$qid] ?? 0;
      $row[] = $sc;
      $total += $sc;
    }
    $row[] = round($total, 2);
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
$total_questions = $db->query("SELECT (SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL) + (SELECT COUNT(*) FROM objective_questions WHERE deleted_at IS NULL)")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics - Admin Portal</title>
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
                    } elseif ($ex['type'] === 'research') {
                        $badge_class = 'admin-badge-primary';
                        $icon_class = 'bi-journal-richtext';
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
        $processed_rows = [];
        $total_scores_sum = 0;
        $max_possible = 0;
        $analysis = [];
        if ($is_obj) {
          $data = report_objective_rows($db, $exam_info);
          $questions_meta = $data['questions'];
          $q_ids = array_keys($questions_meta);
          foreach ($data['rows'] as $r) {
            [$row_first, $row_middle, $row_last] = sih_display_name_parts($r['student']);
            $processed_rows[] = [
              'firstname' => $row_first, 'middlename' => $row_middle, 'lastname' => $row_last,
              'matric' => $r['student']['matric_no'], 'scores' => $r['scores'], 'total' => $r['total'],
            ];
            $total_scores_sum += $r['total'];
            $max_possible = max($max_possible, $r['result']['total']);
          }
          $analysis = report_item_analysis($questions_meta, $data['rows']);
        } else {
          $students = $db->prepare("SELECT DISTINCT s.* FROM students s
              JOIN exam_students es ON s.id=es.student_id
              JOIN answers a ON s.id = a.student_id
              WHERE s.deleted_at IS NULL AND es.exam_id=? AND a.exam_id=?
              ORDER BY s.name");
          $students->execute([$exam_id, $exam_id]);
          $q_stmt = $db->prepare("SELECT q.id, q.score_values FROM exam_questions eq JOIN questions q ON eq.question_id=q.id WHERE eq.exam_id=? ORDER BY q.id");
          $q_stmt->execute([$exam_id]);
          $q_rows = $q_stmt->fetchAll(PDO::FETCH_ASSOC);
          $q_ids = array_column($q_rows, 'id');
          foreach ($q_rows as $qr) {
            $vals = array_map('floatval', explode(',', (string) $qr['score_values']));
            $max_possible += $vals ? max($vals) : 0;
          }
          $sc_stmt = $db->prepare("SELECT question_id, score FROM answers WHERE exam_id=? AND student_id=?");
          foreach ($students->fetchAll(PDO::FETCH_ASSOC) as $stu) {
            [$row_first, $row_middle, $row_last] = sih_display_name_parts($stu);
            $row = ['firstname' => $row_first, 'middlename' => $row_middle, 'lastname' => $row_last, 'matric' => $stu['matric_no'], 'scores' => [], 'total' => 0];
            $sc_stmt->execute([$exam_id, $stu['id']]);
            $sc_map = $sc_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($q_ids as $qid) {
              $v = $sc_map[$qid] ?? 0;
              $row['scores'][] = $v;
              $row['total'] += $v;
            }
            $total_scores_sum += $row['total'];
            $processed_rows[] = $row;
          }
        }
        $avg_score = count($processed_rows) > 0 ? round($total_scores_sum / count($processed_rows), 2) : 0;
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

        <?php if ($is_obj && !empty($processed_rows)): ?>
          <div class="admin-card mt-4">
            <div class="admin-card-header">
              <h3 class="admin-card-title"><i class="bi bi-bar-chart-line"></i> Question Analysis</h3>
              <span class="admin-badge admin-badge-neutral">Facility = average share of marks earned</span>
            </div>
            <div class="admin-card-body-flush">
              <div class="table-responsive">
                <table class="admin-table mb-0">
                  <thead>
                    <tr>
                      <th width="50">#</th>
                      <th>Question</th>
                      <th width="140">Type</th>
                      <th width="90" class="text-center">Answered</th>
                      <th width="110" class="text-center">Full marks</th>
                      <th width="200">Facility</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $qn = 0; foreach ($questions_meta as $qid => $qm): $qn++; $a = $analysis[$qid];
                      $fac = $a['n'] ? round($a['fraction'] / $a['n'] * 100) : 0;
                      $fac_class = $fac >= 70 ? 'excellent' : ($fac >= 40 ? 'average' : 'poor'); ?>
                      <tr>
                        <td><?= $qn ?></td>
                        <td>
                          <div class="fw-medium"><?= htmlspecialchars(mb_strimwidth($qm['question_text'], 0, 140, '…')) ?></div>
                          <?php if ($qm['question_type'] === 'mtf'): ?>
                            <div class="small text-muted mt-1">
                              <?php foreach ($qm['statements'] as $si => $stext): [$ok, $tot] = $a['statements'][$si];
                                $sp = $tot ? round($ok / $tot * 100) : 0; ?>
                                <div><?= chr(97 + $si) ?>) <?= htmlspecialchars(mb_strimwidth($stext, 0, 90, '…')) ?>
                                  <span class="ms-1 fw-bold <?= $sp >= 70 ? 'text-success' : ($sp >= 40 ? 'text-warning' : 'text-danger') ?>"><?= $sp ?>% correct</span>
                                  <span class="ms-1">(key <?= $qm['correct_option'][$si] ?? '?' ?>)</span></div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </td>
                        <td><span class="admin-badge admin-badge-neutral"><?= qe_type_label($qm['question_type']) ?></span></td>
                        <td class="text-center"><?= $a['n'] ?></td>
                        <td class="text-center"><?= $a['n'] ? round($a['full'] / $a['n'] * 100) : 0 ?>%</td>
                        <td>
                          <div class="d-flex align-items-center gap-2">
                            <div class="perf-bar-track"><div class="perf-bar-fill <?= $fac_class ?>" style="width:<?= $fac ?>%;"></div></div>
                            <span class="perf-pct"><?= $fac ?>%</span>
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
