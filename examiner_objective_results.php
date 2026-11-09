<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
require_once __DIR__ . '/includes/student_import_helper.php';
require_once __DIR__ . '/includes/access.php';
$role = $_SESSION['role'] ?? '';
$name = $_SESSION['user'] ?? $role;
$back_url = acc_home();

$exams = $db->query("SELECT e.* FROM exams e WHERE e.type='objective' AND e.is_active=1 AND e.deleted_at IS NULL AND " . acc_exam_sql($db) . " ORDER BY e.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
if ($exam_id) {
  acc_require_exam($db, $exam_id);
}
$selected_exam = null;
foreach ($exams as $ex)
  if ($ex['id'] == $exam_id)
    $selected_exam = $ex;

if ($exam_id) {
  auto_finalize_expired_attempts($db, $exam_id);
}

/** Marked rows for every student who has finished, in name order. */
function eor_rows(PDO $db, array $exam, array $questions): array
{
  $results = qe_exam_results($db, $exam, $questions);
  if (!$results) {
    return [];
  }
  $answers = qe_load_answers($db, (int) $exam['id']);
  $st = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON s.id = es.student_id
      WHERE s.deleted_at IS NULL AND es.exam_id = ? ORDER BY s.name");
  $st->execute([$exam['id']]);
  $rows = [];
  foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $stu) {
    $res = $results[(int) $stu['id']] ?? null;
    if ($res) {
      $res['answers'] = $res['attempt_no'] !== null ? ($answers[(int) $stu['id']][$res['attempt_no']] ?? []) : null;
      $rows[] = ['student' => $stu, 'result' => $res];
    }
  }
  return $rows;
}

if ($exam_id && $selected_exam && isset($_GET['download'])) {
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="objective_exam_results_' . $exam_id . '.csv"');
  $out = fopen('php://output', 'w');
  $questions = qe_load_questions($db, $exam_id);
  $header = ['First Name', 'Middle Name', 'Last Name', 'Matric No'];
  foreach ($questions as $q) {
    $header[] = strip_tags($q['question_text']);
  }
  $header[] = 'Score';
  $header[] = 'Total';
  fputcsv($out, $header);
  foreach (eor_rows($db, $selected_exam, $questions) as $r) {
    [$first_name, $middle_name, $last_name] = sih_display_name_parts($r['student']);
    $row = [$first_name, $middle_name, $last_name, $r['student']['matric_no']];
    foreach ($questions as $qid => $q) {
      $item = $r['result']['items'][$qid];
      if ($item === null) {
        $row[] = '-';
      } elseif ($r['result']['answers'] === null) {
        $row[] = round($item['awarded'], 2);
      } else {
        $row[] = qe_answer_label($q, $r['result']['answers'][$qid] ?? '');
      }
    }
    $row[] = round($r['result']['score'], 2);
    $row[] = round($r['result']['total'], 2);
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
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include acc_sidebar(); ?>
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
        $questions = qe_load_questions($db, $exam_id);
        $rows = eor_rows($db, $selected_exam, $questions);
      ?>
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title">
              <i class="bi bi-table"></i> <?= htmlspecialchars($selected_exam['name']) ?>
            </h3>
            <span class="admin-badge admin-badge-primary"><?= count($rows) ?> Submissions</span>
          </div>
          <?php if (empty($rows)): ?>
            <div class="admin-card-body">
              <div class="admin-empty">
                <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
                <div class="admin-empty-title">No submissions yet</div>
                <div class="admin-empty-desc">No students have submitted this exam.</div>
              </div>
            </div>
          <?php else: ?>
            <div class="admin-card-body pb-0 small text-muted">
              Cells show the student's answer (MTF: one letter per statement, in authoring order).
              <span class="text-success fw-bold">Green</span> full marks ·
              <span class="text-warning fw-bold">amber</span> partial ·
              <span class="text-danger">red</span> no marks. Hover a cell for the marks awarded.
            </div>
            <div class="admin-card-body-flush">
              <div class="table-responsive">
                <table class="admin-table mb-0">
                  <thead>
                    <tr>
                      <th>First Name</th>
                      <th>Middle Name</th>
                      <th>Last Name</th>
                      <th>Matric No</th>
                      <?php $n = 0; foreach ($questions as $q): $n++; ?>
                        <th class="text-center" title="<?= htmlspecialchars(qe_type_label($q['question_type']) . ': ' . strip_tags($q['question_text'])) ?>">
                          Q<?= $n ?><?= $q['question_type'] === 'mtf' ? '<div class="small fw-normal text-muted">MTF</div>' : '' ?>
                        </th>
                      <?php endforeach; ?>
                      <th class="text-center">Score</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($rows as $r):
                      [$stu_first, $stu_middle, $stu_last] = sih_display_name_parts($r['student']);
                      $res = $r['result']; ?>
                      <tr>
                        <td class="fw-medium"><?= htmlspecialchars($stu_first) ?></td>
                        <td><?= htmlspecialchars($stu_middle) ?></td>
                        <td><?= htmlspecialchars($stu_last) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($r['student']['matric_no']) ?></td>
                        <?php foreach ($questions as $qid => $q):
                          $item = $res['items'][$qid];
                          if ($item === null) {
                            echo '<td class="text-center text-muted">-</td>';
                            continue;
                          }
                          $label = $res['answers'] === null ? round($item['awarded'], 2) : qe_answer_label($q, $res['answers'][$qid] ?? '');
                          $cls = ['correct' => 'text-success fw-bold', 'partial' => 'text-warning fw-bold', 'wrong' => 'text-danger', 'unanswered' => 'text-muted'][$item['status']] ?? '';
                          if ($res['answers'] === null) {
                            $cls = $item['awarded'] > 0 ? 'text-success' : 'text-danger';
                          } ?>
                          <td class="text-center font-monospace <?= $cls ?>" title="<?= round($item['awarded'], 2) ?> / <?= round($q['mark'], 2) ?>"><?= htmlspecialchars((string) $label) ?></td>
                        <?php endforeach; ?>
                        <td class="text-center fw-bold"><?= round($res['score'], 2) ?>/<?= round($res['total'], 2) ?></td>
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
