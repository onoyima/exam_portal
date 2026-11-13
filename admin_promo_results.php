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

$notice = '';
$error = '';

// Filter by exam
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
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <style>
        body { background: #f4f7f6; font-family: 'Outfit', sans-serif; color: #1e293b; }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 80px; }
        .page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; gap: 12px; flex-wrap: wrap; }
        .page-title { font-weight: 800; font-size: 1.25rem; color: #092c19; margin: 0; display: flex; align-items: center; gap: 8px; }
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 16px; }
        .card-body { padding: 18px 20px; }
        .form-label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #64748b; display: block; margin-bottom: 6px; }
        .form-control { width: 100%; box-sizing: border-box; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem; background: #fff; }
        .form-control:focus { border-color: #092c19; outline: none; box-shadow: 0 0 0 3px rgba(9,44,25,0.1); }
        .btn { display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; text-decoration: none; border: 1px solid transparent; cursor: pointer; transition: all .2s; }
        .btn-primary { background: #092c19; color: #fff; border-color: #092c19; }
        .btn-primary:hover { background: #061d10; }
        .btn-outline { background: #fff; color: #475569; border-color: #cbd5e1; }
        .btn-outline:hover { border-color: #092c19; color: #092c19; }
        .btn-success { background: #22c55e; color: #060d16; border-color: #22c55e; }
        .btn-success:hover { background: #4ade80; }
        .btn-sm { padding: 6px 12px; font-size: 0.8rem; }
        .table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .table th { text-align: left; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
        .table td { padding: 10px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.92rem; vertical-align: middle; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #f1f5f9; color: #64748b; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.9rem; }
        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .stat-row { display: flex; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .stat { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; flex: 1; min-width: 140px; }
        .stat-value { font-size: 1.5rem; font-weight: 800; color: #092c19; }
        .stat-label { font-size: 0.75rem; color: #64748b; text-transform: uppercase; letter-spacing: .05em; }
        .filter-bar { display: flex; gap: 10px; align-items: end; flex-wrap: wrap; margin-bottom: 16px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px 18px; }
        .score-good { color: #166534; font-weight: 700; }
        .score-bad { color: #991b1b; font-weight: 700; }
        .d-none { display: none !important; }
        @media (max-width: 640px) { .stat-row { flex-direction: column; } .filter-bar { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
<nav class="admin-navbar" style="background:#fff;border-bottom:1px solid #e2e8f0;position:sticky;top:0;z-index:50;">
    <div class="admin-navbar-inner" style="padding:0 16px;">
        <div class="admin-navbar-start" style="display:flex;align-items:center;gap:10px;">
            <a href="admin_promo_exams" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;"><i class="bi bi-arrow-left me-1"></i>Back to exams</a>
        </div>
        <div class="admin-navbar-end">
            <span style="background:rgba(9,44,25,0.08);color:#092c19;padding:6px 12px;border-radius:8px;font-weight:700;font-size:.85rem;"><?= htmlspecialchars(strtoupper(substr($admin_name, 0, 1))) ?></span>
            <a href="logout" class="btn btn-outline" style="padding:8px 14px;font-size:.85rem;">Sign out</a>
        </div>
    </div>
</nav>

<div class="wrap">
    <div class="page-header">
        <h1 class="page-title"><i class="bi bi-bar-chart" style="color:#092c19;"></i> Promo Exam Results</h1>
        <a href="admin_promo_results?export=csv<?= $filterExam ? '&exam_id=' . $filterExam : '' ?>" class="btn btn-success"><i class="bi bi-download me-1"></i> Download CSV</a>
        <a href="admin_promo_exams" class="btn btn-outline"><i class="bi bi-mortarboard me-1"></i> Exams</a>
    </div>

    <?php if (isset($_SESSION['promo_notice'])): ?>
        <div class="flash flash-success"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['promo_notice']); unset($_SESSION['promo_notice']); ?></div>
    <?php endif; ?>

    <div class="stat-row">
        <div class="stat">
            <div class="stat-value"><?= count($attempts) ?></div>
            <div class="stat-label">Total attempts</div>
        </div>
        <div class="stat">
            <div class="stat-value"><?= array_sum(array_map(fn($a) => (int) $a['is_submitted'], $attempts)) ?></div>
            <div class="stat-label">Submitted</div>
        </div>
        <div class="stat">
            <div class="stat-value">
                <?= count($attempts) > 0 ? number_format(array_sum(array_map(fn($a) => (float) $a['score'], $attempts)) / count($attempts), 2) : '0.00' ?>
            </div>
            <div class="stat-label">Avg score</div>
        </div>
    </div>

    <div class="filter-bar">
        <form method="get" style="margin:0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <label class="form-label" style="margin:0;font-size:0.85rem;">Filter by exam</label>
            <select name="exam_id" class="form-control" style="width:auto;min-width:220px;max-width:320px;" onchange="this.form.submit()">
                <option value="">All exams</option>
                <?php foreach ($exams as $e): ?>
                    <option value="<?= (int) $e['id'] ?>" <?= $filterExam === (int) $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $e['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <a href="admin_promo_results" class="btn btn-outline btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Reset</a>
        </form>
    </div>

    <?php if (!$attempts): ?>
        <div class="empty"><i class="bi bi-clipboard"></i><p style="font-weight:600;margin-bottom:6px;">No attempts yet</p><p class="small">Staff results will appear here once they take promotional exams.</p></div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Exam</th>
                    <th style="width:170px;">Staff</th>
                    <th style="width:180px;">Email</th>
                    <th style="width:90px;">Attempt</th>
                    <th style="width:130px;">Started</th>
                    <th style="width:130px;">Finished</th>
                    <th style="width:90px;">Status</th>
                    <th style="width:100px;">Score</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attempts as $idx => $a): ?>
                <tr>
                    <td><?= ($idx + 1) ?></td>
                    <td style="font-size:0.9rem;color:#334155;"><?= htmlspecialchars((string) $a['exam_name']) ?></td>
                    <td style="font-weight:600;color:#0f172a;"><?= htmlspecialchars((string) $a['staff_name']) ?></td>
                    <td style="font-size:0.88rem;color:#64748b;"><?= htmlspecialchars((string) $a['email']) ?></td>
                    <td>#<?= (int) $a['attempt_no'] ?></td>
                    <td style="font-size:0.88rem;color:#64748b;"><?= date('M j, g:i A', strtotime((string) $a['started_at'])) ?></td>
                    <td style="font-size:0.88rem;color:#64748b;"><?= $a['finished_at'] ? date('M j, g:i A', strtotime((string) $a['finished_at'])) : '—' ?></td>
                    <td><span class="badge <?= (int) $a['is_submitted'] ? 'badge-active' : 'badge-inactive' ?>"><?= (int) $a['is_submitted'] ? 'Submitted' : 'Draft' ?></span></td>
                    <td class="<?= (float) $a['score'] > 0 ? 'score-good' : 'score-bad' ?>"><?= number_format((float) $a['score'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
