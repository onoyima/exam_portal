<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

// Fetch all objective exams for filter
$exams = $db->query("SELECT id, name FROM exams WHERE type='objective' AND deleted_at IS NULL ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_query = isset($_GET['student']) ? trim($_GET['student']) : '';

// Base query for auditing
$query_parts = "
    FROM objective_attempts oa
    JOIN students s ON oa.student_id = s.id AND s.deleted_at IS NULL
    JOIN exams e ON oa.exam_id = e.id AND e.deleted_at IS NULL
    LEFT JOIN login_history lh ON oa.login_id = lh.id
    WHERE 1=1
";
$params = [];

if ($exam_id) {
    $query_parts .= " AND oa.exam_id = ?";
    $params[] = $exam_id;
}

if ($student_query) {
    $query_parts .= " AND (s.name LIKE ? OR s.matric_no LIKE ? OR s.email LIKE ?)";
    $like = "%$student_query%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// Pagination
$per_page = 50;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

$total_stmt = $db->prepare("SELECT COUNT(*) $query_parts");
$total_stmt->execute($params);
$total_records = $total_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Fetch the audit logs
$audit_stmt = $db->prepare("
    SELECT 
        oa.*, 
        s.name as student_name, 
        s.matric_no, 
        e.name as exam_name,
        lh.login_time as portal_login_time,
        lh.ip_address as login_ip
    $query_parts
    ORDER BY oa.started_at DESC
    LIMIT $per_page OFFSET $offset
");
$audit_stmt->execute($params);
$logs = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

function formatInterval($seconds)
{
    if ($seconds === null)
        return "N/A";
    $h = floor($seconds / 3600);
    $m = floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $h, $m, $s);
}
$back_url = 'admin_dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Exam Audit Log - Assessment Portal</title>
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
          <h1 class="admin-navbar-title">Exam Audit Trail</h1>
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
          <li class="current" aria-current="page">Exam Audit</li>
        </ol>
      </nav>

      <!-- Summary Stats -->
      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <div class="card border-0 shadow-sm rounded-4 bg-primary text-white p-3">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="text-white-50">Total Attempts</h6>
                <h3 class="fw-bold mb-0">
                  <?= number_format($total_records) ?>
                </h3>
              </div>
              <div class="fs-1"><i class="bi bi-person-workspace"></i></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <?php
          $on_stmt = $db->query("SELECT COUNT(*) FROM objective_attempts WHERE finished_at IS NULL AND started_at > DATE_SUB(NOW(), INTERVAL 2 HOUR)");
          $ongoing = $on_stmt->fetchColumn();
          ?>
          <div class="card border-0 shadow-sm rounded-4 bg-success text-white p-3">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="text-white-50">Ongoing Exams</h6>
                <h3 class="fw-bold mb-0">
                  <?= number_format($ongoing) ?>
                </h3>
              </div>
              <div class="fs-1"><i class="bi bi-play-circle-fill"></i></div>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card border-0 shadow-sm rounded-4 bg-info text-white p-3">
            <div class="d-flex justify-content-between">
              <div>
                <h6 class="text-white-50">Avg. Time Taken</h6>
                <?php
                $avg_stmt = $db->query("SELECT AVG(time_taken) FROM objective_attempts WHERE finished_at IS NOT NULL");
                $avg = $avg_stmt->fetchColumn();
                ?>
                <h3 class="fw-bold mb-0">
                  <?= formatInterval($avg ?: 0) ?>
                </h3>
              </div>
              <div class="fs-1"><i class="bi bi-stopwatch"></i></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="admin-card mb-4">
        <div class="admin-card-body">
          <form method="get" class="row g-3 align-items-end">
            <div class="col-md-4">
              <label class="form-label fw-bold small">Filter by Exam</label>
              <select name="exam_id" class="form-select rounded-pill">
                <option value="">All Objective Exams</option>
                <?php foreach ($exams as $ex): ?>
                  <option value="<?= $ex['id'] ?>" <?= $exam_id == $ex['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($ex['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label fw-bold small">Search Student</label>
              <input type="text" name="student" class="form-control rounded-pill"
                value="<?= htmlspecialchars($student_query) ?>" placeholder="Name or Matric No...">
            </div>
            <div class="col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-primary rounded-pill px-4 px-md-5 w-100">
                <i class="bi bi-search me-1"></i> Apply Filters
              </button>
              <a href="admin_exam_audit" class="btn btn-outline-secondary rounded-pill w-100">Reset</a>
            </div>
          </form>
        </div>
      </div>

      <!-- Log Table -->
      <div class="admin-card">
        <div class="admin-card-body p-0">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Student & Exam</th>
                  <th>Portal Login</th>
                  <th>Exam Started</th>
                  <th>Exam Finished</th>
                  <th>Duration</th>
                  <th>Violations</th>
                  <th>Score</th>
                  <th>IP Log</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($logs)): ?>
                  <tr>
                    <td colspan="8" class="text-center py-5">
                      <div class="text-muted">No exam attempts found for the given criteria.</div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($logs as $log): ?>
                    <tr>
                      <td>
                        <div class="fw-bold">
                          <?= htmlspecialchars($log['student_name']) ?>
                        </div>
                        <div class="small text-primary">
                          <?= htmlspecialchars($log['matric_no']) ?>
                        </div>
                        <div class="badge bg-light text-dark fw-normal mt-1">
                          <?= htmlspecialchars($log['exam_name']) ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($log['portal_login_time']): ?>
                          <div class="small"><i class="bi bi-door-open me-1"></i>
                            <?= date('H:i:s', strtotime($log['portal_login_time'])) ?>
                          </div>
                          <small class="text-muted">
                            <?= date('d M, Y', strtotime($log['portal_login_time'])) ?>
                          </small>
                        <?php else: ?>
                          <span class="text-muted fst-italic small">No sync login</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="small">
                          <?= date('H:i:s', strtotime($log['started_at'])) ?>
                        </div>
                        <small class="text-muted">
                          <?= date('d M, Y', strtotime($log['started_at'])) ?>
                        </small>
                      </td>
                      <td>
                        <?php if ($log['finished_at']): ?>
                          <div class="small">
                            <?= date('H:i:s', strtotime($log['finished_at'])) ?>
                          </div>
                          <small class="text-muted">
                            <?= date('d M, Y', strtotime($log['finished_at'])) ?>
                          </small>
                        <?php else: ?>
                          <span class="badge bg-warning text-dark">Ongoing</span>
                        <?php endif; ?>
                      </td>
                      <td class="fw-bold font-monospace">
                        <?= formatInterval($log['time_taken']) ?>
                      </td>
                      <td>
                        <?php if ($log['violation_count'] > 0): ?>
                          <span class="badge rounded-pill bg-danger">
                            <i class="bi bi-shield-exclamation me-1"></i> <?= $log['violation_count'] ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted small">None</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($log['finished_at']): ?>
                          <span class="fw-bold <?= $log['score'] > 0 ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($log['score'], 2) ?>
                          </span>
                        <?php else: ?>
                          <span class="text-muted">-</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="small text-muted">
                          <?= htmlspecialchars($log['login_ip'] ?: 'Unknown') ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <?php if ($total_pages > 1): ?>
            <div class="p-3 border-top">
              <nav>
                <ul class="pagination justify-content-center mb-0">
                  <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                      <a class="page-link rounded-pill mx-1"
                        href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                        <?= $i ?>
                      </a>
                    </li>
                  <?php endfor; ?>
                </ul>
              </nav>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
