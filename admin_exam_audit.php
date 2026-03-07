<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

// Fetch all objective exams for filter
$exams = $db->query("SELECT id, name FROM exams WHERE type='objective' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$student_query = isset($_GET['student']) ? trim($_GET['student']) : '';

// Base query for auditing
$query_parts = "
    FROM objective_attempts oa
    JOIN students s ON oa.student_id = s.id
    JOIN exams e ON oa.exam_id = e.id
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Exam Audit Log - Admin Console</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        .audit-card {
            border-radius: 1.25rem;
            border: none;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.05);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
        }
    </style>
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><i
                            class="bi bi-list"></i></button>
                    <span class="navbar-brand fw-bold">Exam Audit Trail</span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-semibold">
                        <?= htmlspecialchars($name) ?>
                    </span>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger ms-2 rounded-pill">Logout</a>
                </div>
            </div>
        </nav>

        <div class="container-xl">
            <!-- Summary Stats -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card audit-card bg-primary text-white p-3">
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
                    <div class="card audit-card bg-success text-white p-3">
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
                    <div class="card audit-card bg-info text-white p-3">
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
            <div class="card audit-card p-4 mb-4">
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
                        <a href="admin_exam_audit.php" class="btn btn-outline-secondary rounded-pill w-100">Reset</a>
                    </div>
                </form>
            </div>

            <!-- Log Table -->
            <div class="card audit-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
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
                                    <td colspan="7" class="text-center py-5">
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
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <?= date('d M, Y', strtotime($log['portal_login_time'])) ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted italic small">No sync login</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <?= date('H:i:s', strtotime($log['started_at'])) ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.7rem;">
                                                <?= date('d M, Y', strtotime($log['started_at'])) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($log['finished_at']): ?>
                                                <div class="small">
                                                    <?= date('H:i:s', strtotime($log['finished_at'])) ?>
                                                </div>
                                                <div class="text-muted" style="font-size: 0.7rem;">
                                                    <?= date('d M, Y', strtotime($log['finished_at'])) ?>
                                                </div>
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
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link shadow-sm border-0 rounded-pill mx-1"
                                    href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebarMenu');
            if (toggle && sidebar) {
                toggle.addEventListener('click', () => sidebar.classList.toggle('show'));
            }
        });
    </script>
</body>

</html>