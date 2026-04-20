<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

// Fetch all sessions
$sessions = $db->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$selected_session = intval($_GET['session_id'] ?? ($sessions[0]['id'] ?? 0));
$exam_type_filter = $_GET['exam_type'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$export_csv = isset($_GET['export']);

if (!$selected_session) {
    $selected_session = $sessions[0]['id'] ?? 0;
}

// Fetch exams in the session
$exam_sql = "SELECT id, name, type, grading_method FROM exams WHERE session_id = ?";
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
                    WHERE s.id IN (
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Session Reports - Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .report-table th { font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.05em; white-space: nowrap; }
    .report-table td { font-size: 0.85rem; }
    .score-cell { font-weight: 700; }
    .score-null { color: #94a3b8; font-style: italic; font-weight: 400; }
    .score-high { color: #15803d; }
    .score-mid { color: #b45309; }
    .score-low { color: #b91c1c; }
    .total-cell { font-weight: 800; font-size: 1rem; color: #0f172a; }
    .session-badge { font-size: 0.72rem; padding: 4px 12px; border-radius: 20px; font-weight: 700; }
    .active-session { background: rgba(34,197,94,0.15); color: #15803d; }
  </style>
</head>
<body class="bg-light">
<?php include 'admin_sidebar.php'; ?>
<div class="main-content">
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-fluid">
      <span class="navbar-brand fw-bold text-primary"><i class="bi bi-bar-chart-line-fill me-2"></i>Session Reports</span>
      <div class="ms-auto d-flex gap-2">
        <a href="?session_id=<?= $selected_session ?>&exam_type=<?= urlencode($exam_type_filter) ?>&search=<?= urlencode($search) ?>&export=1"
           class="btn btn-success btn-sm rounded-pill fw-bold px-4 <?= empty($students_data) ? 'disabled' : '' ?>">
          <i class="bi bi-download me-1"></i> Export CSV
        </a>
      </div>
    </div>
  </nav>

  <div class="container-xl">
    <!-- Filters -->
    <div class="card border-0 shadow-sm rounded-4 p-4 mb-4">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label small fw-bold text-muted">Academic Session</label>
          <select name="session_id" class="form-select rounded-3 border-0 bg-light" onchange="this.form.submit()">
            <?php foreach ($sessions as $s): ?>
              <option value="<?= $s['id'] ?>" <?= $s['id'] == $selected_session ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['session_name']) ?> <?= $s['is_active'] ? '(Active)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">Exam Type</label>
          <select name="exam_type" class="form-select rounded-3 border-0 bg-light" onchange="this.form.submit()">
            <option value="all" <?= $exam_type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
            <option value="objective" <?= $exam_type_filter === 'objective' ? 'selected' : '' ?>>CBT / Objective</option>
            <option value="practical" <?= $exam_type_filter === 'practical' ? 'selected' : '' ?>>Practical</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label small fw-bold text-muted">Search Student</label>
          <input type="text" name="search" class="form-control rounded-3 border-0 bg-light" placeholder="Name or Matric No" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-2 text-end">
          <button type="submit" class="btn btn-primary w-100 rounded-3">Filter</button>
        </div>
      </form>
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
      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-white border-0 p-4">
          <div class="d-flex align-items-center gap-3">
            <h5 class="mb-0 fw-bold">Comprehensive Session Report</h5>
            <?php foreach ($sessions as $s): if ($s['id'] == $selected_session): ?>
              <span class="session-badge <?= $s['is_active'] ? 'active-session' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                <?= htmlspecialchars($s['session_name']) ?>
              </span>
            <?php endif; endforeach; ?>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 report-table">
            <thead class="bg-light">
              <tr class="text-muted">
                <th class="ps-4 py-3 border-0" style="min-width:160px;">Student</th>
                <th class="py-3 border-0" style="min-width:130px;">Matric No</th>
                <?php foreach ($session_exams as $e): ?>
                  <th class="py-3 border-0 text-center" style="min-width:110px;">
                    <div><?= htmlspecialchars(substr($e['name'], 0, 20)) ?><?= strlen($e['name']) > 20 ? '...' : '' ?></div>
                    <span class="badge <?= $e['type'] === 'objective' ? 'bg-primary-subtle text-primary-emphasis' : 'bg-success-subtle text-success-emphasis' ?> rounded-pill" style="font-size:0.6rem;">
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
                  <span class="font-monospace text-muted" style="font-size:0.78rem;"><?= htmlspecialchars($row['matric_no']) ?></span>
                </td>
                <?php foreach ($session_exams as $e): ?>
                  <?php $s = $row['scores'][$e['id']]; ?>
                  <td class="py-3 text-center score-cell">
                    <?php if ($s === null): ?>
                      <span class="score-null">—</span>
                    <?php else: ?>
                      <span class="<?= $s >= 70 ? 'score-high' : ($s >= 50 ? 'score-mid' : 'score-low') ?>"><?= $s ?></span>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td class="py-3 text-center pe-4 total-cell"><?= round($row['total'], 2) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <p class="text-muted small mt-3 text-end">
        <i class="bi bi-info-circle me-1"></i>
        Score colour: <span class="text-success fw-bold">≥70 Pass</span> · <span class="text-warning fw-bold">50–69 Average</span> · <span class="text-danger fw-bold">&lt;50 Fail</span> · <span class="text-muted">— Not attempted</span>
      </p>
    <?php endif; ?>
  </div>
</div>
<script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
