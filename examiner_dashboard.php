<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';
require_once 'includes/access.php';

$name = $_SESSION['user'] ?? 'Examiner';
$xid = acc_examiner_id($db);
$st = $db->prepare("SELECT x.*, g.name AS group_name, g.exam_ends, g.ended_at FROM examiners x LEFT JOIN exam_groups g ON g.id = x.group_id WHERE x.id = ?");
$st->execute([$xid]);
$me = $st->fetch(PDO::FETCH_ASSOC) ?: ['name' => $name, 'group_id' => null];
$display = trim(($me['firstname'] ?? '') . ' ' . ($me['lastname'] ?? '')) ?: ($me['name'] ?: $name);

$TYPE_LABEL = ['objective' => 'Objective', 'practical' => 'Practical', 'viva' => 'Viva', 'research' => 'Research'];
$TYPE_ICON = ['objective' => 'bi-ui-checks', 'practical' => 'bi-clipboard2-pulse', 'viva' => 'bi-mic', 'research' => 'bi-journal-richtext'];

$ids = acc_exam_ids($db) ?? [];
$exams = [];
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    // Only show active exams for this examiner
    $exams = $db->query("
        SELECT e.*, g.name AS group_name,
            (SELECT COUNT(*) FROM exam_students es WHERE es.exam_id = e.id) AS enrolled,
            CASE WHEN e.type = 'objective'
                THEN (SELECT COUNT(DISTINCT a.student_id) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NOT NULL)
                ELSE (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id) END AS done,
            (SELECT COUNT(DISTINCT a.student_id) FROM answers a WHERE a.exam_id = e.id AND a.examiner_id = $xid) AS mine,
            CASE WHEN e.type = 'objective'
                THEN (SELECT COUNT(*) FROM objective_attempts a WHERE a.exam_id = e.id AND a.finished_at IS NULL AND a.last_active > DATE_SUB(NOW(), INTERVAL 2 MINUTE))
                ELSE 0 END AS writing
        FROM exams e LEFT JOIN exam_groups g ON g.id = e.group_id
        WHERE e.id IN ($in) AND e.deleted_at IS NULL AND e.is_active = 1
        ORDER BY e.is_active DESC, FIELD(e.type, 'practical', 'viva', 'research', 'objective'), e.name")->fetchAll(PDO::FETCH_ASSOC);
}

$recent = $db->prepare("SELECT s.name AS sname, s.matric_no, e.name AS ename, e.type, MAX(COALESCE(a.updated_at, a.created_at)) AS dt, SUM(a.score) AS total
    FROM answers a JOIN students s ON s.id = a.student_id JOIN exams e ON e.id = a.exam_id
    WHERE a.examiner_id = ? GROUP BY a.exam_id, a.student_id ORDER BY dt DESC LIMIT 8");
$recent->execute([$xid]);
$recent = $recent->fetchAll(PDO::FETCH_ASSOC);
$scored_today = $db->prepare("SELECT COUNT(DISTINCT exam_id, student_id) FROM answers WHERE examiner_id = ? AND DATE(COALESCE(updated_at, created_at)) = CURDATE()");
$scored_today->execute([$xid]);
$scored_today = (int) $scored_today->fetchColumn();

$hour = (int) date('H');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$open = fn($e) => $e['is_active'] && (!$e['start_time'] || strtotime($e['start_time']) <= time()) && (!$e['end_time'] || strtotime($e['end_time']) >= time());
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My exams</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <link href="assets/css/dashboard.css?v=1" rel="stylesheet">
    <style>
        /* Examiner exam card styles */
        .examiner-exam-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .examiner-exam-card:hover {
            box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08);
            transform: translateY(-2px);
        }
        .examiner-exam-card .card-header {
            padding: 1.25rem 1.25rem 0.75rem;
        }
        .examiner-exam-card .card-body {
            padding: 0 1.25rem 1rem;
        }
        .examiner-exam-card .card-footer {
            padding: 1rem 1.25rem;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
        }
        .examiner-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
        }
        .examiner-card-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .examiner-card-title .exam-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .exam-type-objective { background: #dcfce7; color: #166534; }
        .exam-type-practical { background: #fef3c7; color: #92400e; }
        .exam-type-viva { background: #e0e7ff; color: #3730a3; }
        .exam-type-research { background: #fce7f3; color: #9d174d; }
        .examiner-card-title h4 {
            font-size: 1.05rem;
            font-weight: 600;
            color: #0f172a;
            margin: 0;
        }
        .examiner-card-title small {
            color: #64748b;
            font-size: 0.85rem;
        }
        .examiner-card-status {
            flex-shrink: 0;
        }
        .examiner-card-body {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            padding-top: 1rem;
        }
        .examiner-stat {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .examiner-stat-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: 600;
        }
        .examiner-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1;
        }
        .examiner-stat-value.writing { color: #22c55e; }

        .examiner-card-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            align-items: center;
            padding: 1rem 1.25rem;
            border-top: 1px solid #e2e8f0;
            background: #fafafa;
        }
        .examiner-card-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            flex: 1;
        }
        .examiner-card-actions .admin-btn { flex: 1; min-width: 140px; }

        /* Responsive grid */
        .examiner-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1.25rem;
        }
        @media (max-width: 640px) {
            .examiner-cards-grid { grid-template-columns: 1fr; }
        }

        /* Empty state */
        .exams-empty {
            text-align: center;
            padding: 4rem 2rem;
            background: #fafafa;
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
        }
        .exams-empty-icon { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; }
        .exams-empty-title { font-size: 1.25rem; font-weight: 600; color: #475569; margin-bottom: 0.5rem; }
        .exams-empty-text { color: #94a3b8; }

        /* Status badges */
        .admin-badge-success { background: #dcfce7; color: #166534; }
        .admin-badge-secondary { background: #f1f5f9; color: #475569; }
        .admin-badge-neutral { background: #f1f5f9; color: #475569; }
    </style>
</head>

<body>
    <?php include 'examiner_sidebar.php'; ?>
    <div class="main-content">
        <nav class="admin-navbar">
            <div class="admin-navbar-inner">
                <div class="admin-navbar-start">
                    <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
                    <h1 class="admin-navbar-title">My exams</h1>
                </div>
                <div class="admin-navbar-end">
                    <div class="admin-user-badge">
                        <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():'?'}}"></span>
                        <span class="admin-user-name"><?= htmlspecialchars($display) ?></span>
                    </div>
                    <a href="logout" class="admin-logout-btn" title="Sign out"><i class="bi bi-box-arrow-right"></i></a>
                </div>
            </div>
        </nav>

        <div class="admin-content db-page">
            <header class="db-head">
                <div>
                    <p class="db-date"><?= date('l, j F Y') ?></p>
                    <h2><?= $greeting ?>, <?= htmlspecialchars(explode(' ', $display)[0]) ?>.</h2>
                    <p class="db-lead"><?= count($exams) ?> exam<?= count($exams) === 1 ? '' : 's' ?> assigned to you<?= $scored_today ? ' · you scored <b>' . $scored_today . '</b> student' . ($scored_today === 1 ? '' : 's') . ' today' : '' ?>.</p>
                </div>
            </header>

            <?php if (!empty($me['group_id'])): ?>
                <div class="xd-group-note">
                    <i class="bi bi-shield-lock fs-4"></i>
                    <div>This account is for <b><?= htmlspecialchars($me['group_name'] ?? 'one exam group') ?></b> only. It works on one computer at a time and closes
                        <?= !empty($me['exam_ends']) ? 'on <b>' . date('j M Y, g:i A', strtotime($me['exam_ends'])) . '</b>' : 'when the centre manager ends the exam' ?>. Sign out when you leave this computer.</div>
                </div>
            <?php endif; ?>

            <?php if (!$exams): ?>
                <div class="exams-empty">
                    <div class="exams-empty-icon"><i class="bi bi-journal-x"></i></div>
                    <h3 class="exams-empty-title">No active exams assigned</h3>
                    <p class="exams-empty-text">Ask the <?= !empty($me['group_id']) ? 'centre manager' : 'administrator' ?> to assign an active exam to you.</p>
                </div>
            <?php else: ?>
                <div class="examiner-cards-grid">
                    <?php foreach ($exams as $e): $is_open = $open($e); $pct = $e['enrolled'] ? min(100, round($e['done'] / $e['enrolled'] * 100)) : 0; ?>
                        <article class="examiner-exam-card">
                            <div class="card-header">
                                <div class="examiner-card-header">
                                    <div class="examiner-card-title">
                                        <span class="exam-type-badge exam-type-<?= $e['type'] ?>"><i class="bi bi-<?= $TYPE_ICON[$e['type']] ?? 'journal-text' ?>"></i> <?= $TYPE_LABEL[$e['type']] ?? $e['type'] ?></span>
                                        <div>
                                            <h4><?= htmlspecialchars($e['name']) ?></h4>
                                            <small><?= $e['group_name'] ? htmlspecialchars($e['group_name']) . ' · ' : '' ?><?= $e['start_time'] ? date('j M, g:i A', strtotime($e['start_time'])) : 'no fixed time' ?></small>
                                        </div>
                                    </div>
                                    <span class="examiner-card-status admin-badge admin-badge-<?= $is_open ? 'success' : 'secondary' ?>"><?= $is_open ? 'Open' : ($e['is_active'] ? 'Scheduled' : 'Off') ?></span>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="examiner-card-body">
                                    <div class="examiner-stat">
                                        <span class="examiner-stat-label">Students</span>
                                        <span class="examiner-stat-value"><?= (int) $e['enrolled'] ?></span>
                                    </div>
                                    <?php if ($e['type'] === 'objective'): ?>
                                        <div class="examiner-stat">
                                            <span class="examiner-stat-label">Writing now</span>
                                            <span class="examiner-stat-value writing"><?= (int) $e['writing'] ?></span>
                                        </div>
                                        <div class="examiner-stat">
                                            <span class="examiner-stat-label">Submitted</span>
                                            <span class="examiner-stat-value"><?= (int) $e['done'] ?></span>
                                        </div>
                                    <?php else: ?>
                                        <div class="examiner-stat">
                                            <span class="examiner-stat-label">Scored</span>
                                            <span class="examiner-stat-value"><?= (int) $e['done'] ?></span>
                                        </div>
                                        <div class="examiner-stat">
                                            <span class="examiner-stat-label">By you</span>
                                            <span class="examiner-stat-value"><?= (int) $e['mine'] ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="db-bar"><i style="width:<?= $pct ?>%"></i></div>
                            </div>
                            <div class="card-footer">
                                <div class="examiner-card-actions">
                                    <?php if ($e['type'] === 'objective'): ?>
                                        <a class="admin-btn admin-btn-sm admin-btn-primary" href="exam_overview?exam_id=<?= (int) $e['id'] ?>">Overview</a>
                                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="admin_live_proctor?exam_id=<?= (int) $e['id'] ?>">Live monitor</a>
                                        <a class="admin-btn admin-btn-sm admin-btn-outline" href="examiner_objective_results?exam_id=<?= (int) $e['id'] ?>">Answer sheets</a>
                                    <?php elseif ($e['is_active']): ?>
                                        <a class="admin-btn admin-btn-sm admin-btn-primary" href="assess?exam_id=<?= (int) $e['id'] ?>">Score students</a>
                                    <?php else: ?>
                                        <span class="small text-muted">Scoring opens when the exam is switched on.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="db-panel">
                <div class="db-panel-head"><h3>Your latest scores</h3></div>
                <?php if (!$recent): ?>
                    <p class="db-empty">Students you score appear here.</p>
                <?php else: ?>
                    <ul class="db-feed">
                        <?php foreach ($recent as $r): ?>
                            <li>
                                <span class="db-dot db-t-<?= $r['type'] ?>"></span>
                                <div><b><?= htmlspecialchars($r['sname']) ?></b> <small><?= htmlspecialchars($r['matric_no']) ?></small>
                                    <span><?= htmlspecialchars($r['ename']) ?> · total <?= rtrim(rtrim(number_format((float) $r['total'], 2, '.', ''), '0'), '.') ?></span></div>
                                <time><?= $r['dt'] ? date('j M, g:i A', strtotime($r['dt'])) : '' ?></time>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin-common.js"></script>
    <script src="assets/js/admin-swal.js"></script>
</body>

</html>