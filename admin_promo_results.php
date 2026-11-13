<?php
/**
 * admin_promo_results.php — View and download promotional exam results (admin only)
 *
 * Lists all promo exam attempts with scores, per-exam breakdown, and CSV export.
 * Isolated from the core results system.
 */

require 'auth.php';
check_auth('admin');
require 'db.php';
require_once __DIR__ . '/includes/promo_schema.php';
promo_ensure_schema($db);

$filterExam = isset($_GET['exam_id']) ? (int) $_GET['exam_id'] : 0;

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sql = "SELECT pea.id, pe.name AS exam_name, ps.name AS staff_name, ps.email, pea.attempt_no,
                   pea.started_at, pea.finished_at, pea.is_submitted, pea.score
            FROM promo_exam_attempts pea
            JOIN promo_exams pe ON pe.id = pea.exam_id
            JOIN promo_staff ps ON ps.id = pea.staff_id
            WHERE 1=1 " . ($filterExam ? " AND pea.exam_id = $filterExam" : "") . "
            ORDER BY pea.finished_at DESC, pea.id DESC";
    $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="promo_results_' . date('Ymd_His') . '.csv"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Attempt ID', 'Exam', 'Staff Name', 'Email', 'Attempt No', 'Started At', 'Finished At', 'Submitted', 'Score']);
    foreach ($rows as $r) {
        fputcsv($out, [
            (int) $r['id'],
            $r['exam_name'],
            $r['staff_name'],
            $r['email'],
            (int) $r['attempt_no'],
            $r['started_at'],
            $r['finished_at'] ?: '',
            (int) $r['is_submitted'] ? 'Yes' : 'No',
            number_format((float) $r['score'], 2),
        ]);
    }
    fclose($out);
    exit;
}

// Load exams for filter dropdown
$exams = $db->query("SELECT * FROM promo_exams ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Load attempts with filters
$sql = "SELECT pea.id, pe.name AS exam_name, pe.type AS exam_type, ps.name AS staff_name, ps.email,
               pea.attempt_no, pea.started_at, pea.finished_at, pea.is_submitted, pea.score
        FROM promo_exam_attempts pea
        JOIN promo_exams pe ON pe.id = pea.exam_id
        JOIN promo_staff ps ON ps.id = pea.staff_id
        WHERE 1=1";
$params = [];
if ($filterExam) {
    $sql .= " AND pea.exam_id = ?";
    $params[] = $filterExam;
}
$sql .= " ORDER BY pea.finished_at DESC, pea.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Per-exam summary
$summary = [];
foreach ($exams as $e) {
    $eid = (int) $e['id'];
    $summary[$eid] = [
        'name' => $e['name'],
        'attempts' => 0,
        'submitted' => 0,
        'avg_score' => 0,
        'max_score' => 0,
    ];
    $scores = [];
    foreach ($attempts as $a) {
        if ((int) $a['exam_id'] === $eid) {
            $summary[$eid]['attempts']++;
            if ((int) $a['is_submitted'] === 1) {
                $summary[$eid]['submitted']++;
                $scores[] = (float) $a['score'];
            }
        }
    }
    if ($scores) {
        $summary[$eid]['avg_score'] = array_sum($scores) / count($scores);
        $summary[$eid]['max_score'] = max($scores);
    }
}

$admin_name = $_SESSION['user'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Promo Exam Results - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <a href="admin_promo_exams" class="admin-back-btn" aria-label="Back to exams">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Promo Results</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($admin_name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li><a href="admin_promo_exams">Promo Exams</a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Results</li>
        </ol>
      </nav>

      <?php display_auth_errors(); ?>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Promo Exam Results</h2>
        <div class="admin-page-actions">
          <a href="admin_promo_results?export=csv<?= $filterExam ? '&exam_id=' . $filterExam : '' ?>" class="admin-btn admin-btn-success">
            <i class="bi bi-download me-1"></i> Download CSV
          </a>
          <a href="admin_promo_exams" class="admin-btn admin-btn-outline">
            <i class="bi bi-mortarboard me-1"></i> Exams
          </a>
        </div>
      </div>

      <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= count($attempts) ?></div>
            <div class="admin-stat-label">Total Attempts</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= array_sum(array_map(fn($a) => (int) $a['is_submitted'], $attempts)) ?></div>
            <div class="admin-stat-label">Submitted</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value">
              <?= count($attempts) > 0 ? number_format(array_sum(array_map(fn($a) => (float) $a['score'], $attempts)) / count($attempts), 2) : '0.00' ?>
            </div>
            <div class="admin-stat-label">Avg Score</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value">
              <?= count($attempts) > 0 ? number_format(max(array_map(fn($a) => (float) $a['score'], $attempts)), 2) : '0.00' ?>
            </div>
            <div class="admin-stat-label">Best Score</div>
          </div>
        </div>
      </div>

      <form method="get" class="admin-search-form mb-3" style="display:flex;gap:8px;max-width:360px;">
        <label class="form-label d-flex align-items-center" style="margin:0;">Filter by exam</label>
        <select name="exam_id" class="form-select" style="width:auto;min-width:240px;" onchange="this.form.submit()">
          <option value="">All exams</option>
          <?php foreach ($exams as $e): ?>
            <option value="<?= (int) $e['id'] ?>" <?= $filterExam === (int) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $e['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($filterExam): ?>
          <a href="admin_promo_results" class="admin-btn admin-btn-outline">Clear</a>
        <?php endif; ?>
      </form>

      <?php if (!$attempts): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <p class="text-muted text-center py-4"><i class="bi bi-clipboard me-2"></i>No attempts yet. Staff results will appear here once they take promotional exams.</p>
          </div>
        </div>
      <?php else: ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Exam</th>
                <th>Staff</th>
                <th>Email</th>
                <th>Attempt</th>
                <th>Started</th>
                <th>Finished</th>
                <th>Status</th>
                <th>Score</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($attempts as $idx => $a): ?>
              <tr>
                <td><?= ($idx + 1) ?></td>
                <td style="font-size:0.9rem;color:#334155;"><?= htmlspecialchars((string) $a['exam_name']) ?></td>
                <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars((string) $a['staff_name']) ?></td>
                <td class="text-truncate" style="max-width:160px;"><?= htmlspecialchars((string) $a['email']) ?></td>
                <td>#<?= (int) $a['attempt_no'] ?></td>
                <td style="font-size:0.88rem;color:#64748b;"><?= date('M j, g:i A', strtotime((string) $a['started_at'])) ?></td>
                <td style="font-size:0.88rem;color:#64748b;"><?= $a['finished_at'] ? date('M j, g:i A', strtotime((string) $a['finished_at'])) : '—' ?></td>
                <td><span class="admin-badge admin-badge-<?= (int) $a['is_submitted'] ? 'success' : 'neutral' ?>"><?= (int) $a['is_submitted'] ? 'Submitted' : 'Draft' ?></span></td>
                <td class="text-muted">
                  <strong><?= number_format((float) $a['score'], 2) ?></strong>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>

    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
</body>
</html>
