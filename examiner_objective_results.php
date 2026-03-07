<?php
require 'db.php';
session_start();
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'examiner'])) {
  die('Access denied.');
}
// Fetch all active objective exams
$exams = $db->query("SELECT * FROM exams WHERE type='objective' AND is_active=1 ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$selected_exam = null;
foreach ($exams as $ex)
  if ($ex['id'] == $exam_id)
    $selected_exam = $ex;

// Auto-finalize any expired attempts for this exam so they show up in results
if ($exam_id) {
  auto_finalize_expired_attempts($db, $exam_id);
}
// Download CSV
if ($exam_id && isset($_GET['download'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="objective_exam_results_' . $exam_id . '.csv"');
  $out = fopen('php://output', 'w');
  // Fetch questions
  $qs = $db->prepare("SELECT * FROM objective_questions WHERE exam_id=? ORDER BY id");
  $qs->execute([$exam_id]);
  $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
  // Header
  $header = ['First Name', 'Middle Name', 'Last Name', 'Matric No'];
  foreach ($questions as $q)
    $header[] = strip_tags($q['question_text']);
  $header[] = 'Score';
  fputcsv($out, $header);
  // Fetch students
  // Fetch students who have attempted the exam
  $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
      JOIN exam_students es ON s.id=es.student_id 
      JOIN objective_attempts oa ON s.id = oa.student_id 
      WHERE es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
      ORDER BY s.name");
  $students->execute([$exam_id, $exam_id]);
  $students = $students->fetchAll(PDO::FETCH_ASSOC);
  $q_mark_map = [];
  foreach ($questions as $q)
    $q_mark_map[$q['id']] = (isset($q['mark']) ? floatval($q['mark']) : 1);

  foreach ($students as $stu) {
    $row = [$stu['firstname'], $stu['middlename'], $stu['lastname'], $stu['matric_no']];

    // Fetch latest attempt to get question order
    $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY attempt_no DESC LIMIT 1");
    $att_stmt->execute([$exam_id, $stu['id']]);
    $latest_att = $att_stmt->fetch(PDO::FETCH_ASSOC);

    $score = 0;
    $total_possible = 0;
    $assigned_ids = [];
    if ($latest_att) {
      $assigned_ids = explode(',', $latest_att['question_order']);
      foreach ($assigned_ids as $qid)
        $total_possible += ($q_mark_map[$qid] ?? 1);
    } else {
      foreach ($questions as $q)
        $total_possible += (isset($q['mark']) ? floatval($q['mark']) : 1);
    }

    foreach ($questions as $q) {
      if ($latest_att && !in_array($q['id'], $assigned_ids)) {
        $row[] = '-'; // Not assigned to this student
        continue;
      }

      $ans = $db->prepare("SELECT selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND question_id=? AND attempt_no=?");
      $ans->execute([$exam_id, $stu['id'], $q['id'], $latest_att['attempt_no'] ?? 1]);
      $sel = $ans->fetchColumn();
      $row[] = $sel ? strtoupper($sel) : '';
      if ($sel && strtoupper($sel) === strtoupper($q['correct_option']))
        $score += (isset($q['mark']) ? floatval($q['mark']) : 1);
    }

    // Scale score if exam has a total mark set
    $display_score = $score;
    $display_total = $total_possible;
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
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <div class="container-xl py-4">
      <h2 class="mb-4">Objective Exam Results</h2>
      <form method="get" class="mb-4">
        <div class="row g-2 align-items-center">
          <div class="col-auto">
            <select name="exam_id" class="form-select" required>
              <option value="">Select Exam</option>
              <?php foreach ($exams as $ex): ?>
                <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($ex['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-auto">
            <button type="submit" class="btn btn-primary">View Results</button>
          </div>
          <?php if ($exam_id): ?>
            <div class="col-auto">
              <a href="?exam_id=<?= $exam_id ?>&download=1" class="btn btn-success">Download CSV</a>
            </div>
          <?php endif; ?>
        </div>
      </form>
      <?php if ($selected_exam): ?>
        <?php
        // Fetch questions
        $qs = $db->prepare("SELECT * FROM objective_questions WHERE exam_id=? ORDER BY id");
        $qs->execute([$exam_id]);
        $questions = $qs->fetchAll(PDO::FETCH_ASSOC);
        // Fetch students who have attempted the exam
        $students = $db->prepare("SELECT DISTINCT s.* FROM students s 
            JOIN exam_students es ON s.id=es.student_id 
            JOIN objective_attempts oa ON s.id = oa.student_id 
            WHERE es.exam_id=? AND oa.exam_id=? AND oa.finished_at IS NOT NULL 
            ORDER BY s.name");
        $students->execute([$exam_id, $exam_id]);
        $students = $students->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th>First Name</th>
                <th>Middle Name</th>
                <th>Last Name</th>
                <th>Matric No</th>
                <?php foreach ($questions as $q): ?>
                  <th><?= htmlspecialchars($q['question_text']) ?></th>
                <?php endforeach; ?>
                <th>Score</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $q_mark_map = [];
              foreach ($questions as $q)
                $q_mark_map[$q['id']] = (isset($q['mark']) ? floatval($q['mark']) : 1);

              foreach ($students as $stu): ?>
                <tr>
                  <td><?= htmlspecialchars($stu['firstname']) ?></td>
                  <td><?= htmlspecialchars($stu['middlename'] ?? '') ?></td>
                  <td><?= htmlspecialchars($stu['lastname']) ?></td>
                  <td><?= htmlspecialchars($stu['matric_no']) ?></td>
                  <?php
                  $att_stmt = $db->prepare("SELECT attempt_no, question_order FROM objective_attempts WHERE exam_id=? AND student_id=? AND finished_at IS NOT NULL ORDER BY attempt_no DESC LIMIT 1");
                  $att_stmt->execute([$exam_id, $stu['id']]);
                  $latest_att = $att_stmt->fetch(PDO::FETCH_ASSOC);

                  $score = 0;
                  $total_possible = 0;
                  $assigned_ids = [];
                  if ($latest_att) {
                    $assigned_ids = explode(',', $latest_att['question_order']);
                    foreach ($assigned_ids as $qid)
                      $total_possible += ($q_mark_map[$qid] ?? 1);
                  } else {
                    foreach ($questions as $q)
                      $total_possible += (isset($q['mark']) ? floatval($q['mark']) : 1);
                  }

                  foreach ($questions as $q):
                    if ($latest_att && !in_array($q['id'], $assigned_ids)) {
                      echo '<td class="text-center text-muted">-</td>';
                      continue;
                    }

                    $ans = $db->prepare("SELECT selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND question_id=? AND attempt_no=?");
                    $ans->execute([$exam_id, $stu['id'], $q['id'], $latest_att['attempt_no']]);
                    $sel = $ans->fetchColumn();
                    echo '<td>' . ($sel ? strtoupper($sel) : '') . '</td>';
                    if ($sel && strtoupper($sel) === strtoupper($q['correct_option']))
                      $score += (isset($q['mark']) ? floatval($q['mark']) : 1);
                  endforeach;

                  // Scale score if exam has a total mark set
                  $display_score = $score;
                  $display_total = $total_possible;
                  if (isset($selected_exam['total_mark']) && $selected_exam['total_mark'] > 0 && $total_possible > 0) {
                    $display_score = ($score / $total_possible) * $selected_exam['total_mark'];
                    $display_total = $selected_exam['total_mark'];
                  }
                  ?>
                  <td><span
                      class="fw-bold text-success"><?= round($display_score, 2) ?>/<?= round($display_total, 2) ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php elseif ($exam_id): ?>
        <div class="alert alert-warning">No such exam or no results yet.</div>
      <?php endif; ?>
    </div>
  </div>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>

</html>