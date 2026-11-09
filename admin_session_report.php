<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

// Fetch all sessions
$sessions = $db->query("SELECT * FROM academic_sessions WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$selected_session = intval($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$exam_type_filter = $_GET['exam_type'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$export_csv = isset($_GET['export']);

if (!$selected_session) {
    $selected_session = $sessions[0]['id'] ?? 0;
}

// Fetch exams in the session
$exam_sql = "SELECT id, name, type, grading_method FROM exams WHERE session_id = ? AND deleted_at IS NULL";
$exam_params = [$selected_session];
if ($exam_type_filter !== 'all') {
    $exam_sql .= " AND type = ?";
    $exam_params[] = $exam_type_filter;
}
$exam_sql .= " ORDER BY type, name";
$exams_in_session = $db->prepare($exam_sql);
$exams_in_session->execute($exam_params);
$session_exams = $exams_in_session->fetchAll(PDO::FETCH_ASSOC);

$exam_ids = array_column($session_exams, 'id');

if (empty($exam_ids)) {
    $students_data = [];
} else {
    // Fetch all students who attempted any exam in this session
    $placeholders = implode(',', array_fill(0, count($exam_ids), '?'));
    $student_sql = "SELECT DISTINCT s.id, s.name, s.matric_no 
                    FROM students s 
                    WHERE s.deleted_at IS NULL AND s.id IN (
                        SELECT student_id FROM objective_attempts WHERE exam_id IN ($placeholders)
                        UNION
                        SELECT student_id FROM exam_students WHERE exam_id IN ($placeholders)
                    )";
    $student_params = array_merge($exam_ids, $exam_ids);
    if ($search) {
        $student_sql .= " AND (s.name LIKE ? OR s.matric_no LIKE ?)";
        $student_params[] = "%$search%";
        $student_params[] = "%$search%";
    }
    $student_sql .= " ORDER BY s.name ASC";
    $student_stmt = $db->prepare($student_sql);
    $student_stmt->execute($student_params);
    $students = $student_stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each student, fetch their best score for each exam
    $students_data = [];
    foreach ($students as $student) {
        $row = [
            'id'       => $student['id'],
            'name'     => $student['name'],
            'matric_no'=> $student['matric_no'],
            'scores'   => [],
            'total'    => 0
        ];

        foreach ($session_exams as $exam) {
            // Best finished attempt score
            if ($exam['type'] === 'objective') {
                $method = $exam['grading_method'] ?? 'highest';
                if ($method === 'average') {
                    $score_stmt = $db->prepare("SELECT AVG(score) FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND finished_at IS NOT NULL");
                } else {
                    $score_stmt = $db->prepare("SELECT MAX(score) FROM objective_attempts WHERE exam_id = ? AND student_id = ? AND finished_at IS NOT NULL");
                }
                $score_stmt->execute([$exam['id'], $student['id']]);
                $score = $score_stmt->fetchColumn();
            } else {
                // Practical exam score from answers table (sum of all question scores)
                $score_stmt = $db->prepare("SELECT SUM(score) FROM answers WHERE exam_id = ? AND student_id = ?");
                $score_stmt->execute([$exam['id'], $student['id']]);
                $score = $score_stmt->fetchColumn();
            }
            $s = ($score !== false && $score !== null) ? round(floatval($score), 2) : null;
            $row['scores'][$exam['id']] = $s;
            $row['total'] += ($s ?? 0);
        }

        $students_data[] = $row;
    }
}

// CSV Export
if ($export_csv && !empty($students_data)) {
    $session_name = '';
    foreach ($sessions as $s) { if ($s['id'] == $selected_session) $session_name = $s['session_name']; }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="session_report_' . preg_replace('/\W/', '_', $session_name) . '.csv"');
    $out = fopen('php://output', 'w');
    // Header row
    $headers = ['Student Name', 'Matric No'];
    foreach ($session_exams as $e) $headers[] = $e['name'] . ' (' . strtoupper($e['type']) . ')';
    $headers[] = 'Total Aggregate';
    fputcsv($out, $headers);
    foreach ($students_data as $row) {
        $line = [$row['name'], $row['matric_no']];
        foreach ($session_exams as $e) $line[] = $row['scores'][$e['id']] ?? 'N/A';
        $line[] = round($row['total'], 2);
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
$back_url = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Session Reports - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
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
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <?php if (isset($back_url)): ?>
            <a href="<?= $back_url ?>" class="admin-back-btn"><i class="bi bi-arrow-left"></i></a>
          <?php endif; ?>
          <h1 class="admin-navbar-title">Session Reports</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Session Reports</li>
        </ol>
      </nav>

      <!-- Filters -->
      <div class="admin-card mb-4">
        <div class="admin-card-body">
          <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label small fw-bold text-muted">Academic Session</label>
              <select name="session_id" class="form-select border-0 bg-light" onchange="this.form.submit()">
                <?php foreach ($sessions as $s): ?>
                  <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_session ? 'selected' : '' ?>>
                    <?= htmlspecialchars($s['session_name']) ?> <?= $s['is_active'] ? '(Active)' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-muted">Exam Type</label>
              <select name="exam_type" class="form-select border-0 bg-light" onchange="this.form.submit()">
                <option value="all" <?= $exam_type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="objective" <?= $exam_type_filter === 'objective' ? 'selected' : '' ?>>CBT / Objective</option>
                <option value="practical" <?= $exam_type_filter === 'practical' ? 'selected' : '' ?>>Practical</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-bold text-muted">Search Student</label>
              <input type="text" name="search" class="form-control border-0 bg-light" placeholder="Name or Matric No" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2 text-end">
              <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
          </form>
        </div>
        <div class="admin-card-footer text-end">
          <a href="?session_id=<?= $selected_session ?>&exam_type=<?= urlencode($exam_type_filter) ?>&search=<?= urlencode($search) ?>&export=1"
             class="admin-btn admin-btn-success <?= empty($students_data) ? 'disabled' : '' ?>">
            <i class="bi bi-download"></i> Export CSV
          </a>
        </div>
      </div>

      <?php if (empty($session_exams)): ?>
        <div class="text-center py-5">
          <i class="bi bi-journal-x display-3 text-muted d-block mb-3"></i>
          <h5 class="text-muted">No exams found for this session</h5>
          <p class="text-muted small">To include exams in a session, set their <strong>Session</strong> field when creating or editing exams.</p>
        </div>
      <?php elseif (empty($students_data)): ?>
        <div class="text-center py-5">
          <i class="bi bi-people display-3 text-muted d-block mb-3"></i>
          <h5 class="text-muted">No students found for this session</h5>
        </div>
      <?php else: ?>
        <!-- Summary -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
              <div class="display-6 fw-bold text-primary"><?= count($students_data) ?></div>
              <div class="text-muted small fw-bold mt-1">STUDENTS</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
              <div class="display-6 fw-bold text-success"><?= count($session_exams) ?></div>
              <div class="text-muted small fw-bold mt-1">EXAMS IN SESSION</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 text-center">
              <?php $avg = count($students_data) > 0 ? array_sum(array_column($students_data, 'total')) / count($students_data) : 0; ?>
              <div class="display-6 fw-bold text-warning"><?= round($avg, 1) ?></div>
              <div class="text-muted small fw-bold mt-1">AVG AGGREGATE</div>
            </div>
          </div>
        </div>

        <!-- Report Table -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-bar-chart-line-fill"></i> Comprehensive Session Report</h3>
            <?php foreach ($sessions as $s): if ($s['id'] == $selected_session): ?>
              <span class="badge <?= $s['is_active'] ? 'bg-success' : 'bg-secondary' ?> ms-2">
                <?= htmlspecialchars($s['session_name']) ?>
              </span>
            <?php endif; endforeach; ?>
          </div>
          <div class="admin-card-body p-0">
            <div class="table-responsive">
              <table class="admin-table">
                <thead>
                  <tr class="text-muted">
                    <th class="ps-4 py-3 border-0 text-nowrap">Student</th>
                    <th class="py-3 border-0 text-nowrap">Matric No</th>
                    <?php foreach ($session_exams as $e): ?>
                      <th class="py-3 border-0 text-center text-nowrap">
                        <div><?= htmlspecialchars(substr($e['name'], 0, 20)) ?><?= strlen($e['name']) > 20 ? '...' : '' ?></div>
                        <span class="badge <?= $e['type'] === 'objective' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-success-subtle text-success-emphasis' ?> rounded-pill small">
                          <?= strtoupper($e['type']) ?>
                        </span>
                      </th>
                    <?php endforeach; ?>
                    <th class="py-3 border-0 text-center pe-4">Aggregate</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($students_data as $row): ?>
                  <tr>
                    <td class="ps-4 py-3">
                      <div class="fw-bold text-dark small"><?= htmlspecialchars($row['name']) ?></div>
                    </td>
                    <td class="py-3">
                      <span class="font-monospace text-muted small"><?= htmlspecialchars($row['matric_no']) ?></span>
                    </td>
                    <?php foreach ($session_exams as $e): ?>
                      <?php $s = $row['scores'][$e['id']]; ?>
                      <td class="py-3 text-center fw-bold">
                        <?php if ($s === null): ?>
                          <span class="text-muted fst-italic">&mdash;</span>
                        <?php else: ?>
                          <span class="<?= $s >= 70 ? 'text-success' : ($s >= 50 ? 'text-warning' : 'text-danger') ?>"><?= $s ?></span>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                    <td class="py-3 text-center pe-4 fw-bold fs-5"><?= round($row['total'], 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <p class="text-muted small mt-3 text-end">
          <i class="bi bi-info-circle me-1"></i>
          Score colour: <span class="text-success fw-bold">≥70 Pass</span> &middot; <span class="text-warning fw-bold">50–69 Average</span> &middot; <span class="text-danger fw-bold">&lt;50 Fail</span> &middot; <span class="text-muted">&mdash; Not attempted</span>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
