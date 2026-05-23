<?php
require 'db.php';
require_once 'exam_logic.php';
session_start();
$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['admin', 'examiner'])) {
  die('Access denied.');
}
$name = $_SESSION['user'] ?? $role;
$back_url = ($role === 'admin') ? 'admin_dashboard' : 'assess';

$exams = $db->query("SELECT * FROM exams WHERE type='objective' AND is_active=1 AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_exam = null;
foreach ($exams as $ex)
  if ($ex['id'] == $exam_id)
    $selected_exam = $ex;

if ($exam_id) {
  auto_finalize_expired_attempts($db, $exam_id);
}

if ($exam_id && isset($_GET['download'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="objective_exam_results_' . $exam_id . '.csv"');
  $out = fopen('php://output', 'w');
  $qs = $db->prepare("SELECT * FROM objective_questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
  $qs->execute([$exam_id]);
  $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
  $header = ['First Name', 'Middle Name', 'Last Name', 'Matric No'];
  foreach ($questions as $q)
    $header[] = strip_tags($q['question_text']);
  $header[] = 'Score';
  fputcsv($out, $header);

  $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
      JOIN exam_students es ON s.id=es.student_id 
      JOIN objective_attempts oa ON s.id = oa.student_id 
      WHERE s.deleted_at IS NULL AND es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
      ORDER BY s.name");
  $students->execute([$exam_id, $exam_id]);
  $students = $students->fetchAll(PDO::FETCH_ASSOC);
  $q_mark_map = [];
  foreach ($questions as $q)
    $q_mark_map[$q['id']] = (isset($q['mark']) ? floatval($q['mark']) : 1);

  foreach ($students as $stu) {
    $row = [$stu['firstname'], $stu['middlename'], $stu['lastname'], $stu['matric_no']];
    $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY attempt_no DESC LIMIT 1");
    $att_stmt->execute([$exam_id, $stu['id']]);
    $latest_att = $att_stmt->fetch(PDO::FETCH_ASSOC);
    $score = 0; $total_possible = 0;
    $assigned_ids = [];
    if ($latest_att) {
      $assigned_ids = explode(',', $latest_att['question_order']);
      foreach ($assigned_ids as $qid) $total_possible += ($q_mark_map[$qid] ?? 1);
    } else {
      foreach ($questions as $q) $total_possible += (isset($q['mark']) ? floatval($q['mark']) : 1);
    }
    foreach ($questions as $q) {
      if ($latest_att && !in_array($q['id'], $assigned_ids)) { $row[] = '-'; continue; }
      $ans = $db->prepare("SELECT selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND question_id=? AND attempt_no=?");
      $ans->execute([$exam_id, $stu['id'], $q['id'], $latest_att['attempt_no'] ?? 1]);
      $sel = $ans->fetchColumn();
      $row[] = $sel ? strtoupper($sel) : '';
      if ($sel && strtoupper($sel) === strtoupper($q['correct_option']))
        $score += (isset($q['mark']) ? floatval($q['mark']) : 1);
    }
    $display_score = $score; $display_total = $total_possible;
    if (isset($selected_exam['total_mark']) && $selected_exam['total_mark'] > 0 && $total_possible > 0) {
      $display_score = ($score / $total_possible) * $selected_exam['total_mark'];
      $display_total = $selected_exam['total_mark'];
    }
    $row[] = round($display_score, 2) . "/" . round($display_total, 2);
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Objective Exam Results - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'examiner_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Objective Results</h1>
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
          <li><a href="<?= $back_url ?>"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Objective Results</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title"><i class="bi bi-list-check"></i> Objective Exam Results</h2>
      </div>

      <div class="admin-card">
        <div class="admin-card-body">
          <form method="get" class="row g-3 align-items-end">
            <div class="col-auto flex-grow-1">
              <label class="admin-form-label">Select Objective Exam</label>
              <select name="exam_id" class="admin-input admin-select" required>
                <option value="">-- Choose Exam --</option>
                <?php foreach ($exams as $ex): ?>
                  <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ex['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-auto">
              <button type="submit" class="admin-btn admin-btn-primary">
                <i class="bi bi-search"></i> View Results
              </button>
            </div>
            <?php if ($exam_id): ?>
              <div class="col-auto">
                <a href="?exam_id=<?= $exam_id ?>&download=1" class="admin-btn admin-btn-outline">
                  <i class="bi bi-file-earmark-spreadsheet"></i> Download CSV
                </a>
              </div>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <?php if ($selected_exam):
        $qs = $db->prepare("SELECT * FROM objective_questions WHERE exam_id=? AND deleted_at IS NULL ORDER BY id");
        $qs->execute([$exam_id]);
        $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
        $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
            JOIN exam_students es ON s.id=es.student_id 
            JOIN objective_attempts oa ON s.id = oa.student_id 
            WHERE s.deleted_at IS NULL AND es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
            ORDER BY s.name");
        $students->execute([$exam_id, $exam_id]);
        $students = $students->fetchAll(PDO::FETCH_ASSOC);
      ?>
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title">
              <i class="bi bi-table"></i> <?= htmlspecialchars($selected_exam['name']) ?>
            </h3>
            <span class="admin-badge admin-badge-primary"><?= count($students) ?> Submissions</span>
          </div>
          <?php if (empty($students)): ?>
            <div class="admin-card-body">
              <div class="admin-empty">
                <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                <div class="admin-empty-title">No submissions yet</div>
                <div class="admin-empty-desc">No students have submitted this exam.</div>
              </div>
            </div>
          <?php else:
            $q_mark_map = [];
            foreach ($questions as $q) $q_mark_map[$q['id']] = (isset($q['mark']) ? floatval($q['mark']) : 1);
          ?>
            <div class="admin-card-body-flush">
              <div class="table-responsive">
                <table class="admin-table mb-0">
                  <thead>
                    <tr>
                      <th>First Name</th>
                      <th>Middle Name</th>
                      <th>Last Name</th>
                      <th>Matric No</th>
                      <?php foreach ($questions as $q): ?>
                        <th class="text-center" title="<?= htmlspecialchars(strip_tags($q['question_text'])) ?>">
                          Q<?= $q['id'] ?>
                        </th>
                      <?php endforeach; ?>
                      <th class="text-center">Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($students as $stu):
                      $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY attempt_no DESC LIMIT 1");
                      $att_stmt->execute([$exam_id, $stu['id']]);
                      $latest_att = $att_stmt->fetch(PDO::FETCH_ASSOC);
                      $score = 0; $total_possible = 0;
                      $assigned_ids = [];
                      if ($latest_att) {
                        $assigned_ids = explode(',', $latest_att['question_order']);
                        foreach ($assigned_ids as $qid) $total_possible += ($q_mark_map[$qid] ?? 1);
                      } else {
                        foreach ($questions as $q) $total_possible += (isset($q['mark']) ? floatval($q['mark']) : 1);
                      }
                    ?>
                      <tr>
                        <td class="fw-medium"><?= htmlspecialchars($stu['firstname']) ?></td>
                        <td><?= htmlspecialchars($stu['middlename'] ?? '') ?></td>
                        <td><?= htmlspecialchars($stu['lastname']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($stu['matric_no']) ?></td>
                        <?php foreach ($questions as $q):
                          if ($latest_att && !in_array($q['id'], $assigned_ids)):
                            echo '<td class="text-center text-muted">-</td>';
                            continue;
                          endif;
                          $ans = $db->prepare("SELECT selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND question_id=? AND attempt_no=?");
                          $ans->execute([$exam_id, $stu['id'], $q['id'], $latest_att['attempt_no']]);
                          $sel = $ans->fetchColumn();
                          $is_correct = $sel && strtoupper($sel) === strtoupper($q['correct_option']);
                          if ($is_correct) $score += (isset($q['mark']) ? floatval($q['mark']) : 1);
                          echo '<td class="text-center ' . ($is_correct ? 'text-success fw-bold' : ($sel ? 'text-danger' : 'text-muted')) . '">';
                          echo $sel ? strtoupper($sel) : '-';
                          echo '</td>';
                        endforeach;
                        $display_score = $score; $display_total = $total_possible;
                        if (isset($selected_exam['total_mark']) && $selected_exam['total_mark'] > 0 && $total_possible > 0) {
                          $display_score = ($score / $total_possible) * $selected_exam['total_mark'];
                          $display_total = $selected_exam['total_mark'];
                        }
                        ?>
                        <td class="text-center fw-bold"><?= round($display_score, 2) ?>/<?= round($display_total, 2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php elseif ($exam_id): ?>
        <div class="admin-alert admin-alert-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span>No such exam or no results yet.</span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
