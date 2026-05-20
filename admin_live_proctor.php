<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

if (isset($_GET['action'])) {

    // ── Download single student's recording ──
    if ($_GET['action'] === 'download_recording') {
        $id = intval($_GET['attempt_id'] ?? 0);
        $stmt = $db->prepare("SELECT oa.id, s.matric_no, s.name as student_name, e.name as exam_name FROM objective_attempts oa JOIN students s ON oa.student_id = s.id JOIN exams e ON oa.exam_id = e.id WHERE oa.id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) { http_response_code(404); die('Attempt not found'); }

        $dir = "uploads/recordings/attempt_{$id}";
        $files = is_dir($dir) ? glob($dir . "/*.webm") : [];
        if (empty($files)) { http_response_code(404); die('No recordings found'); }

        $zip = new ZipArchive();
        $zip_name = tempnam(sys_get_temp_dir(), 'rec_') . '.zip';
        if ($zip->open($zip_name, ZipArchive::CREATE) !== true) { http_response_code(500); die('Failed to create ZIP'); }

        $matric = preg_replace('/[^A-Za-z0-9_\-]/', '_', $row['matric_no']);
        foreach ($files as $f) { $zip->addFile($f, "{$matric}/" . basename($f)); }
        $zip->close();

        $exam_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $row['exam_name']);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="recording_' . $matric . '_' . $exam_name . '.zip"');
        header('Content-Length: ' . filesize($zip_name));
        readfile($zip_name);
        @unlink($zip_name);
        exit;
    }

    // ── Download all recordings for a specific exam/date ──
    if ($_GET['action'] === 'download_all_recordings') {
        $exam_id = intval($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $date = $_GET['date'] ?? $_POST['date'] ?? '';

        $attempts = $db->prepare("SELECT oa.id, s.matric_no, s.name, e.name as exam_name FROM objective_attempts oa JOIN students s ON oa.student_id = s.id JOIN exams e ON oa.exam_id = e.id WHERE oa.exam_id = ? AND (DATE(oa.started_at) = ? OR ? = '')");
        $attempts->execute([$exam_id, $date, $date]);
        $rows = $attempts->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) { http_response_code(404); die('No recordings found for the selected criteria'); }

        $zip = new ZipArchive();
        $zip_name = tempnam(sys_get_temp_dir(), 'recordings_') . '.zip';
        if ($zip->open($zip_name, ZipArchive::CREATE) !== true) { http_response_code(500); die('Failed to create ZIP'); }

        $added = 0;
        foreach ($rows as $r) {
            $dir = "uploads/recordings/attempt_{$r['id']}";
            if (!is_dir($dir)) continue;
            $files = glob($dir . "/*.webm");
            if (empty($files)) continue;
            $matric = preg_replace('/[^A-Za-z0-9_\-]/', '_', $r['matric_no']);
            foreach ($files as $f) { $zip->addFile($f, "{$matric}/" . basename($f)); $added++; }
        }
        $zip->close();

        if ($added === 0) { @unlink($zip_name); http_response_code(404); die('No video files found'); }

        $exam_name = preg_replace('/[^A-Za-z0-9_\-]/', '_', $rows[0]['exam_name']);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="all_recordings_' . $exam_name . '_' . ($date ?: 'all') . '.zip"');
        header('Content-Length: ' . filesize($zip_name));
        readfile($zip_name);
        @unlink($zip_name);
        exit;
    }

    header('Content-Type: application/json');
    $id = $_POST['attempt_id'] ?? 0;

    if ($_GET['action'] === 'warn') {
        $msg = $_POST['message'] ?? 'Please focus on your exam.';
        $stmt = $db->prepare("UPDATE objective_attempts SET warning_message = ?, last_warning_at = NOW() WHERE id = ?");
        $stmt->execute([$msg, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'violation') {
        $reason = $_POST['reason'] ?? 'Manual Violation Tag';
        $stmt = $db->prepare("UPDATE objective_attempts SET violation_count = violation_count + 1 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'force_submit') {
        // Fetch attempt details
        $stmt = $db->prepare("SELECT * FROM objective_attempts WHERE id = ?");
        $stmt->execute([$id]);
        $attempt = $stmt->fetch();

        if ($attempt && !$attempt['finished_at']) {
            $e_id = $attempt['exam_id'];
            $s_id = $attempt['student_id'];
            $a_no = $attempt['attempt_no'];

            // Calculate current score
            $qs_check = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
            $qs_check->execute([$e_id]);
            $questions_check = $qs_check->fetchAll(PDO::FETCH_ASSOC);

            $ans_check = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
            $ans_check->execute([$e_id, $s_id, $a_no]);
            $answers_check = $ans_check->fetchAll(PDO::FETCH_KEY_PAIR);

            $calculated_score = 0;
            foreach ($questions_check as $q) {
                if (isset($answers_check[$q['id']])) {
                    if (strtoupper($answers_check[$q['id']]) === strtoupper($q['correct_option'])) {
                        $calculated_score += (isset($q['mark']) ? floatval($q['mark']) : 1);
                    }
                }
            }

            // Finalize attempt
            $stmt = $db->prepare("UPDATE objective_attempts SET finished_at=NOW(), time_taken = TIMESTAMPDIFF(SECOND, started_at, NOW()), score=?, proctor_status='submitted' WHERE id=?");
            $stmt->execute([$calculated_score, $id]);

            echo json_encode(['success' => true, 'score' => $calculated_score]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Attempt not found or already finished']);
        }
        exit;
    }

    if ($_GET['action'] === 'unlock') {
        $stmt = $db->prepare("UPDATE objective_attempts SET is_locked = 0, violation_count = 0 WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_GET['action'] === 'force_submit_all') {
        $exam_id = intval($_POST['exam_id']);
        if ($exam_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'No exam selected']);
            exit;
        }

        // Fetch all active attempts for this exam
        $stmt = $db->prepare("SELECT id FROM objective_attempts WHERE exam_id = ? AND finished_at IS NULL");
        $stmt->execute([$exam_id]);
        $active_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($active_ids as $att_id) {
            // Re-use logic for each
            $st = $db->prepare("SELECT * FROM objective_attempts WHERE id = ?");
            $st->execute([$att_id]);
            $attempt = $st->fetch();

            if ($attempt) {
                $e_id = $attempt['exam_id'];
                $s_id = $attempt['student_id'];
                $a_no = $attempt['attempt_no'];

                // Calculate current score
                $qs_check = $db->prepare("SELECT id, correct_option, mark FROM objective_questions WHERE exam_id=? ORDER BY id");
                $qs_check->execute([$e_id]);
                $questions_check = $qs_check->fetchAll(PDO::FETCH_ASSOC);

                $ans_check = $db->prepare("SELECT question_id, selected_option FROM objective_answers WHERE exam_id=? AND student_id=? AND attempt_no=?");
                $ans_check->execute([$e_id, $s_id, $a_no]);
                $answers_check = $ans_check->fetchAll(PDO::FETCH_KEY_PAIR);

                $calculated_score = 0;
                foreach ($questions_check as $q) {
                    if (isset($answers_check[$q['id']])) {
                        if (strtoupper($answers_check[$q['id']]) === strtoupper($q['correct_option'])) {
                            $calculated_score += (isset($q['mark']) ? floatval($q['mark']) : 1);
                        }
                    }
                }

                // Finalize attempt
                $finish_stmt = $db->prepare("UPDATE objective_attempts SET finished_at=NOW(), time_taken = TIMESTAMPDIFF(SECOND, started_at, NOW()), score=?, proctor_status='submitted' WHERE id=?");
                $finish_stmt->execute([$calculated_score, $att_id]);
                $count++;
            }
        }

        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }
}

// Filter Logic
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
$status_filter = $_GET['status'] ?? 'active'; // 'active' or 'history'
$search_query = trim($_GET['search'] ?? '');
$sort_by = $_GET['sort'] ?? 'active_desc';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$where = "WHERE 1=1";
$params = [];

if ($selected_exam > 0) {
    $where .= " AND oa.exam_id = ?";
    $params[] = $selected_exam;
}

if ($search_query !== '') {
    $where .= " AND (s.name LIKE ? OR s.matric_no LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($status_filter === 'active') {
    $where .= " AND oa.finished_at IS NULL AND oa.last_active > DATE_SUB(NOW(), INTERVAL 30 MINUTE)";
} else {
    $where .= " AND (oa.finished_at IS NOT NULL OR oa.last_active <= DATE_SUB(NOW(), INTERVAL 30 MINUTE))";
}

// Sorting logic
$order_by = "oa.last_active DESC";
switch ($sort_by) {
    case 'active_asc':
        $order_by = "oa.last_active ASC";
        break;
    case 'violations':
        $order_by = "oa.violation_count DESC, oa.last_active DESC";
        break;
    case 'name':
        $order_by = "s.name ASC";
        break;
    case 'matric':
        $order_by = "s.matric_no ASC";
        break;
}

// Fetch total count for pagination
$total_count = $db->prepare("SELECT COUNT(*) FROM objective_attempts oa JOIN students s ON oa.student_id = s.id $where");
$total_count->execute($params);
$total_records = $total_count->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fetch attempts
$stmt = $db->prepare("
    SELECT 
        oa.*, 
        s.name as student_name, 
        s.matric_no, 
        e.name as exam_name,
        e.type as exam_type,
        TIMESTAMPDIFF(SECOND, oa.last_active, NOW()) as idle_seconds
    FROM objective_attempts oa
    JOIN students s ON oa.student_id = s.id
    JOIN exams e ON oa.exam_id = e.id
    $where
    ORDER BY $order_by
    LIMIT $limit OFFSET $offset
");
$stmt->execute($params);
$live_attempts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add has_recordings flag to each attempt and collect dates for bulk download
$recording_dates = [];
foreach ($live_attempts as &$att) {
    $rec_dir = "uploads/recordings/attempt_{$att['id']}";
    $att['has_recordings'] = is_dir($rec_dir) && count(glob($rec_dir . "/*.webm")) > 0;
    if ($att['started_at']) {
        $d = date('Y-m-d', strtotime($att['started_at']));
        $recording_dates[$d] = $d;
    }
}
unset($att);
krsort($recording_dates);

// Fetch exams for filter
$all_exams = $db->query("SELECT id, name FROM exams WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

function svg_icon($name, $extra = '') {
    $paths = [
        'shield' => '<path d="M10 2 3 5.5v5.5c0 5 3.5 8.5 7 10 3.5-1.5 7-5 7-10V5.5L10 2Z"/><path d="m7.5 10.5 2 2 4-4"/>',
        'camera' => '<path d="M11 5H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z"/><path d="M13 8l3-2v8l-3-2"/>',
        'rec' => '<circle cx="10" cy="10" r="7"/><circle cx="10" cy="10" r="3" fill="currentColor"/>',
        'exit' => '<path d="M12 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h7"/><path d="M17 10H9"/><path d="M13 6l4 4-4 4"/>',
        'download' => '<path d="M10 2v11"/><path d="m5 9 5 5 5-5"/><path d="M3 17h14"/>',
        'play' => '<path d="m6 3 10 7-10 7V3Z"/>',
        'stop' => '<rect x="5" y="5" width="10" height="10" rx="2"/>',
        'lock' => '<rect x="6" y="9" width="8" height="7" rx="1.5"/><path d="M7 9V6a3 3 0 0 1 6 0v3"/>',
        'unlock' => '<rect x="6" y="9" width="8" height="7" rx="1.5"/><path d="M7 9V6a3 3 0 0 1 5.5-1.5"/><circle cx="10" cy="13" r="1" fill="currentColor"/>',
        'mic' => '<rect x="7.5" y="2" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0"/><path d="M10 15v3"/><path d="M7 18h6"/>',
        'mic-mute' => '<rect x="7.5" y="2" width="5" height="9" rx="2.5"/><path d="M5 10a5 5 0 0 0 10 0"/><path d="M10 15v3"/><path d="M7 18h6"/><path d="m2 2 16 16"/>',
        'megaphone' => '<path d="M3 9a3 3 0 0 1 3-3h1l7-4v14l-7-4H6a3 3 0 0 1-3-3Z"/><path d="M14 8.5a3 3 0 0 1 0 3"/>',
        'cam-off' => '<path d="M11 5H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Z"/><path d="M13 8l3-2v8l-3-2"/><path d="m1 1 18 18"/>',
    ];
    $svg = '<svg class="icon '.$extra.'" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">'.($paths[$name] ?? '').'</svg>';
    return $svg;
}
?>
<style>
.icon { width: 1em; height: 1em; display: inline-block; vertical-align: -0.125em; }
.icon.display-1 { width: 4rem; height: 4rem; }
</style>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Live Video Proctoring - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <meta http-equiv="refresh" content="60">
</head>

<body class="bg-light">
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <span class="navbar-brand fw-bold text-success"><?= svg_icon('shield', 'me-2') ?>Live
                    Proctoring Control</span>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <?php
                    $total_rec = 0;
                    foreach ($live_attempts as $att) { if ($att['has_recordings']) $total_rec++; }
                    ?>
                    <?php if ($total_rec > 0): ?>
                    <span class="badge bg-dark fw-bold py-2" style="font-size:0.7rem;">
                        <?= svg_icon('camera', 'me-1') ?> <?= $total_rec ?> with recordings
                    </span>
                    <?php endif; ?>
                    <span class="badge bg-danger-light text-danger pulse fw-bold py-2"><?= svg_icon('rec', 'me-1') ?> VIDEO SERVER ACTIVE</span>
                </div>
            </div>
        </nav>

        <div class="container-xl">
            <!-- Filter Bar -->
            <div class="filter-bar">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Search Student</label>
                        <input type="text" name="search" class="form-control border-0 bg-light rounded-3"
                            placeholder="Name or Matric No" value="<?= htmlspecialchars($search_query) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filter by Exam</label>
                        <select name="exam_id" class="form-select border-0 bg-light rounded-3"
                            onchange="this.form.submit()">
                            <option value="0">All Scheduled Exams</option>
                            <?php foreach ($all_exams as $ex): ?>
                                <option value="<?= $ex['id'] ?>" <?= $selected_exam == $ex['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($ex['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Sort By</label>
                        <select name="sort" class="form-select border-0 bg-light rounded-3"
                            onchange="this.form.submit()">
                            <option value="active_desc" <?= $sort_by === 'active_desc' ? 'selected' : '' ?>>Activity
                                (Newest)</option>
                            <option value="active_asc" <?= $sort_by === 'active_asc' ? 'selected' : '' ?>>Activity (Oldest)
                            </option>
                            <option value="violations" <?= $sort_by === 'violations' ? 'selected' : '' ?>>Violations (High)
                            </option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Student Name</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select border-0 bg-light rounded-3"
                            onchange="this.form.submit()">
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="history" <?= $status_filter === 'history' ? 'selected' : '' ?>>History</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1 rounded-3 mb-0">Apply</button>
                        <?php if ($selected_exam > 0 && $status_filter === 'active'): ?>
                            <button type="button" onclick="forceSubmitAll(<?= $selected_exam ?>)" class="btn btn-danger rounded-3 mb-0" title="Submit all for this exam">
                                <?= svg_icon('exit') ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if ($selected_exam > 0 && !empty($recording_dates)): ?>
                <div class="mt-3 pt-3 border-top d-flex align-items-center gap-3 flex-wrap">
                    <span class="small fw-bold text-muted"><?= svg_icon('camera', 'me-1') ?>Recordings:</span>
                    <form method="get" action="admin_live_proctor" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="action" value="download_all_recordings">
                        <input type="hidden" name="exam_id" value="<?= $selected_exam ?>">
                        <select name="date" class="form-select form-select-sm border-0 bg-light rounded-3" style="width:auto;">
                            <option value="">All dates</option>
                            <?php foreach ($recording_dates as $d): ?>
                                <option value="<?= $d ?>"><?= htmlspecialchars($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-dark fw-bold rounded-3">
                            <?= svg_icon('download', 'me-1') ?> Download ZIP
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div class="row g-4">
                <?php if (empty($live_attempts)): ?>
                    <div class="col-12 text-center py-5">
                        <?= svg_icon('cam-off', 'text-muted display-1') ?>
                        <p class="text-muted mt-3 fs-5">No active sessions.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($live_attempts as $att): ?>
                        <div class="col-12 col-md-6 col-lg-4" id="card-<?= $att['id'] ?>">
                            <div class="card proctor-card shadow-sm h-100">
                                <div class="video-wrapper">
                                    <?php
                                    $snap_src = 'assets/img/user_placeholder.png';
                                    if ($att['snapshot_path'] && file_exists($att['snapshot_path'])) {
                                        $snap_src = $att['snapshot_path'];
                                    } elseif ($att['webcam_snapshot']) {
                                        $snap_src = $att['webcam_snapshot'];
                                    }
                                    ?>
                                    <img src="<?= $snap_src ?>" class="placeholder-snap zoom-img" id="snap-<?= $att['id'] ?>"
                                        onclick="zoomImage(this.src, '<?= addslashes($att['student_name']) ?>')">
                                    <video id="video-<?= $att['id'] ?>" class="student-video" autoplay playsinline controls></video>

                                    <div class="video-status-overlay d-flex flex-column align-items-end gap-1">
                                        <span id="live-tag-<?= $att['id'] ?>" class="live-tag">LIVE VIDEO</span>
                                        <?php if ($att['has_recordings']): ?>
                                            <span class="badge bg-dark rounded-pill shadow-sm" style="font-size:0.55rem;">
                                                <?= svg_icon('rec', 'me-1') ?> REC
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($att['is_locked']): ?>
                                            <span class="badge bg-dark rounded-pill shadow-sm">
                                                <?= svg_icon('lock', 'me-1') ?> LOCKED
                                            </span>
                                        <?php elseif (!$att['finished_at']): ?>
                                            <span class="badge bg-success rounded-pill shadow-sm">
                                                <span class="stat-dot dot-active"></span> ACTIVE
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill shadow-sm">FINISHED</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!$att['finished_at'] && $att['idle_seconds'] < 60): ?>
                                        <button class="btn btn-sm btn-danger rounded-pill fw-bold btn-live shadow"
                                            onclick="startLiveVideo('<?= $att['id'] ?>')" id="btn-stream-<?= $att['id'] ?>">
                                            <i class="bi bi-play-fill"></i> Stream
                                        </button>
                                        <button class="btn btn-sm btn-dark rounded-pill fw-bold btn-stop shadow"
                                            onclick="stopLiveVideo('<?= $att['id'] ?>')" id="btn-stop-<?= $att['id'] ?>" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); z-index:20;">
                                            <i class="bi bi-stop-fill"></i> Stop
                                        </button>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($att['student_name']) ?>
                                            </h6>
                                            <small class="text-muted fw-bold"><?= htmlspecialchars($att['matric_no']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($att['is_locked']): ?>
                                            <span class="badge bg-dark rounded-pill mb-1"
                                                style="font-size: 0.6rem;">
                                                <i class="bi bi-lock-fill"></i> LOCKED
                                            </span>
                                            <?php endif; ?>
                                            <span
                                                class="badge <?= $att['violation_count'] > 0 ? 'bg-danger' : 'bg-success' ?> rounded-pill mb-1"
                                                style="font-size: 0.6rem;">
                                                <?= $att['violation_count'] ?>/3 Strikes
                                            </span>
                                            <div class="small fw-bold text-primary" style="font-size: 0.65rem;">Attempt
                                                #<?= $att['attempt_no'] ?></div>
                                        </div>
                                    </div>

                                    <div class="p-2 bg-light rounded-3 mb-3" style="font-size: 0.75rem;">
                                        <div class="text-truncate fw-bold text-muted"><?= htmlspecialchars($att['exam_name']) ?>
                                        </div>
                                    </div>

                                    <?php if (!$att['finished_at']): ?>
                                        <?php if ($att['is_locked']): ?>
                                        <div class="d-grid gap-2">
                                            <button
                                                onclick="unlockAttempt(<?= $att['id'] ?>, '<?= addslashes($att['student_name']) ?>')"
                                                class="btn btn-success fw-bold py-2" style="font-size: 0.75rem;">
                                                <i class="bi bi-unlock-fill me-1"></i> Unlock Student
                                            </button>
                                            <?php if ($att['has_recordings']): ?>
                                            <a href="admin_live_proctor?action=download_recording&attempt_id=<?= $att['id'] ?>"
                                               class="btn btn-sm btn-outline-dark fw-bold" style="font-size: 0.7rem;">
                                                <i class="bi bi-download me-1"></i> Download Recording
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <div class="d-flex flex-wrap gap-2">
                                            <button
                                                onclick="warnStudent(<?= $att['id'] ?>, '<?= addslashes($att['student_name']) ?>')"
                                                class="btn btn-sm btn-outline-warning flex-grow-1 fw-bold"
                                                style="font-size: 0.7rem;">
                                                Warn
                                            </button>
                                            <button
                                                onclick="tagViolation(<?= $att['id'] ?>, '<?= addslashes($att['student_name']) ?>')"
                                                class="btn btn-sm btn-outline-danger flex-grow-1 fw-bold"
                                                style="font-size: 0.7rem;">
                                                Tag
                                            </button>
                                            <button id="btn-speak-<?= $att['id'] ?>"
                                                onclick="speakToStudent('<?= $att['id'] ?>')"
                                                class="btn btn-sm btn-outline-primary flex-grow-1 fw-bold"
                                                style="font-size: 0.7rem;">
                                                <i class="bi bi-mic-fill me-1"></i>Speak
                                            </button>
                                            <button
                                                onclick="forceSubmit(<?= $att['id'] ?>, '<?= addslashes($att['student_name']) ?>')"
                                                class="btn btn-sm btn-danger flex-grow-1 fw-bold" style="font-size: 0.7rem;">
                                                Submit
                                            </button>
                                            <?php if ($att['has_recordings']): ?>
                                            <a href="admin_live_proctor?action=download_recording&attempt_id=<?= $att['id'] ?>"
                                               class="btn btn-sm btn-outline-dark fw-bold" style="font-size: 0.7rem;">
                                                <i class="bi bi-download me-1"></i> Recording
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="d-grid gap-2">
                                            <div class="alert alert-success py-1 px-2 mb-0 text-center fw-bold"
                                                style="font-size: 0.7rem;">
                                                Score: <?= $att['score'] ?> | Completed
                                            </div>
                                            <?php if ($att['has_recordings']): ?>
                                            <a href="admin_live_proctor?action=download_recording&attempt_id=<?= $att['id'] ?>"
                                               class="btn btn-sm btn-outline-dark fw-bold" style="font-size: 0.7rem;">
                                                <i class="bi bi-download me-1"></i> Download Recording
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-5">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $page == $i ? 'active' : '' ?>">
                                <a class="page-link"
                                    href="?exam_id=<?= $selected_exam ?>&status=<?= $status_filter ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modals (Warning Modal similar to before) -->
    <div class="modal fade" id="warnModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-4 border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="fw-bold"><i class="bi bi-megaphone me-2"></i>Warn Student</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="warnAttemptId">
                    <p class="text-muted small">Send an immediate warning to <b id="warnStudentName"></b>.</p>
                    <textarea id="warnMessage" class="form-control mb-3 border-0 bg-light rounded-4" rows="3"
                        placeholder="Enter warning message..."></textarea>
                    <button onclick="submitWarning()" class="btn btn-warning w-100 fw-bold rounded-pill py-3">SEND
                        WARNING</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Zoom Modal -->
    <div class="modal fade" id="zoomModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content overflow-hidden border-0 rounded-4">
                <div class="modal-header py-2 px-3 bg-dark text-white border-0">
                    <h6 class="mb-0" id="zoomTitle">Student View</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-dark text-center">
                    <img id="zoomImg" src="" class="img-fluid" style="min-height: 400px; object-fit: contain;">
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script>
        const CSRF_TOKEN = '<?= generate_csrf_token() ?>';
        const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
        const activeConnections = {};
        const lastSignalIds = {};

        async function startLiveVideo(attemptId) {
            const btn = document.querySelector(`#card-${attemptId} .btn-live`);
            const stopBtn = document.getElementById(`btn-stop-${attemptId}`);
            if (btn) btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';

            // Reset button state if something goes wrong
            function resetButton(msg) {
                if (btn) { btn.innerHTML = '<i class="bi bi-play-fill"></i> Stream'; btn.disabled = false; btn.style.display = 'block'; }
                if (stopBtn) stopBtn.style.display = 'none';
                if (msg) Swal.fire('Stream Error', msg, 'error');
            }

            // Trigger the student to start their signaling loop
            try {
                const reqResp = await fetch(`webrtc_api?action=request_live&attempt_id=${attemptId}`);
                if (!reqResp.ok) { resetButton('Failed to request live feed from student.'); return; }
            } catch (e) {
                resetButton('Network error while requesting live feed.');
                return;
            }

            const pc = new RTCPeerConnection(rtcConfig);
            activeConnections[attemptId] = pc;
            let connected = false;

            pc.addTransceiver('video', { direction: 'recvonly' });
            pc.addTransceiver('audio', { direction: 'recvonly' });

            pc.oniceconnectionstatechange = () => {
                console.log(`ICE Connection [${attemptId}]:`, pc.iceConnectionState);
                if (['connected', 'completed'].includes(pc.iceConnectionState)) connected = true;
                if (pc.iceConnectionState === 'failed' || pc.iceConnectionState === 'disconnected') {
                    if (!connected) resetButton('Could not establish connection to student.');
                }
            };

            pc.ontrack = (event) => {
                const video = document.getElementById(`video-${attemptId}`);
                const snap = document.getElementById(`snap-${attemptId}`);
                const liveTag = document.getElementById(`live-tag-${attemptId}`);

                video.srcObject = event.streams[0];
                video.muted = false;
                video.volume = 1.0;
                video.style.display = 'block';
                video.play().catch(e => console.error("Autoplay prevented:", e));
                if (snap) snap.style.display = 'none';
                if (liveTag) liveTag.style.display = 'block';
                if (btn) btn.style.display = 'none';
                if (stopBtn) stopBtn.style.display = 'block';

                startRecording(attemptId, event.streams[0]);
                connected = true;
                if (checkInterval) clearInterval(checkInterval);
            };

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal(attemptId, 'ice', JSON.stringify(event.candidate)).catch(() => {});
                }
            };

            try {
                const offer = await pc.createOffer();
                await pc.setLocalDescription(offer);
                await sendSignal(attemptId, 'offer', JSON.stringify(offer));
            } catch (e) {
                resetButton('Failed to create or send connection offer.');
                return;
            }

            // Monitor for answer with timeout
            lastSignalIds[attemptId] = 0;
            let pollCount = 0;
            const checkInterval = setInterval(async () => {
                pollCount++;
                if (pollCount > 30) { // 30 second timeout
                    clearInterval(checkInterval);
                    if (!connected) resetButton('Connection timed out. Student may not be online.');
                    return;
                }
                try {
                    const resp = await fetch(`webrtc_api?action=get&attempt_id=${attemptId}&last_id=${lastSignalIds[attemptId]}`);
                    const data = await resp.json();
                    if (data.signals) {
                        for (const signal of data.signals) {
                            lastSignalIds[attemptId] = signal.id;
                            const payload = JSON.parse(signal.payload);
                            if (signal.type === 'answer') {
                                await pc.setRemoteDescription(new RTCSessionDescription(payload));
                            } else if (signal.type === 'ice') {
                                try { await pc.addIceCandidate(new RTCIceCandidate(payload)); } catch (e) {}
                            }
                        }
                    }
                } catch (e) {}
            }, 1000);
        }

        function sendSignal(attemptId, type, payload) {
            const fd = new FormData();
            fd.append('attempt_id', attemptId);
            fd.append('type', type);
            fd.append('payload', payload);
            fd.append('csrf_token', CSRF_TOKEN);
            return fetch('webrtc_api?action=send', { method: 'POST', body: fd })
                .then(r => { if (!r.ok) throw new Error('Signal send failed: ' + r.status); return r.json(); });
        }

        async function stopLiveVideo(attemptId) {
            const stopBtn = document.getElementById(`btn-stop-${attemptId}`);
            if (stopBtn) stopBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Closing...';

            // Phase 5: Stop recording if active
            if (activeRecorders[attemptId]) {
                activeRecorders[attemptId].stop();
                delete activeRecorders[attemptId];
            }

            await fetch(`webrtc_api?action=stop_live&attempt_id=${attemptId}`);
            
            if (activeConnections[attemptId]) {
                activeConnections[attemptId].close();
                delete activeConnections[attemptId];
            }

            const video = document.getElementById(`video-${attemptId}`);
            const liveTag = document.getElementById(`live-tag-${attemptId}`);
            const btn = document.querySelector(`#card-${attemptId} .btn-live`);
            const snap = document.getElementById(`snap-${attemptId}`);

            if (video) { video.srcObject = null; video.style.display = 'none'; }
            if (liveTag) liveTag.style.display = 'none';
            if (stopBtn) stopBtn.style.display = 'none';
            if (stopBtn) stopBtn.innerHTML = '<i class="bi bi-stop-fill"></i> Stop';
            if (btn) { btn.style.display = 'block'; btn.innerHTML = '<i class="bi bi-play-fill"></i> Stream'; }
            if (snap) snap.style.display = 'block';
        }

        // Phase 5: Video recording storage (MediaRecorder)
        const activeRecorders = {};
        const recordingChunks = {};

        function startRecording(attemptId, stream) {
            if (!window.MediaRecorder) return;
            const recorder = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8' });
            recordingChunks[attemptId] = [];
            recorder.ondataavailable = (e) => {
                if (e.data && e.data.size > 0) {
                    uploadVideoChunk(attemptId, e.data);
                }
            };
            recorder.start(5000); // Upload chunk every 5 seconds
            activeRecorders[attemptId] = recorder;
            // Show recording badge
            const liveTag = document.getElementById(`live-tag-${attemptId}`);
            if (liveTag) liveTag.innerHTML = '⏺ RECORDING';
        }

        async function uploadVideoChunk(attemptId, blob) {
            const fd = new FormData();
            fd.append('attempt_id', attemptId);
            fd.append('chunk', blob, `rec_${attemptId}_${Date.now()}.webm`);
            await fetch('upload_recording?action=chunk', { method: 'POST', body: fd });
        }

        // Phase 5: Admin speaks to student (audio injection)
        const activeMicStreams = {};
        async function speakToStudent(attemptId) {
            if (activeMicStreams[attemptId]) {
                // Toggle off
                activeMicStreams[attemptId].getTracks().forEach(t => t.stop());
                delete activeMicStreams[attemptId];
                document.getElementById(`btn-speak-${attemptId}`).innerHTML = '<i class="bi bi-mic-fill me-1"></i>Speak';
                return;
            }
            try {
                const micStream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });
                const pc = activeConnections[attemptId];
                if (!pc) {
                    micStream.getTracks().forEach(t => t.stop());
                    Swal.fire('No Stream', 'Start video streaming first before speaking.', 'warning');
                    return;
                }
                activeMicStreams[attemptId] = micStream;
                micStream.getAudioTracks().forEach(track => pc.addTrack(track, micStream));
                // Trigger renegotiation so student receives the audio track
                await pc.setLocalDescription(await pc.createOffer());
                sendSignal(attemptId, 'offer', JSON.stringify(pc.localDescription));
                document.getElementById(`btn-speak-${attemptId}`).innerHTML = '<i class="bi bi-mic-mute-fill me-1 text-danger"></i>Stop';
            } catch (e) {
                alert('Microphone access denied.');
            }
        }

        // Warning/Violation logic (same as before)
        const warnModal = new bootstrap.Modal(document.getElementById('warnModal'));
        function warnStudent(id, name) {
            document.getElementById('warnAttemptId').value = id;
            document.getElementById('warnStudentName').textContent = name;
            warnModal.show();
        }
        function submitWarning() {
            const id = document.getElementById('warnAttemptId').value;
            const msg = document.getElementById('warnMessage').value;
            const fd = new FormData();
            fd.append('attempt_id', id); fd.append('message', msg);
            fetch('admin_live_proctor?action=warn', { method: 'POST', body: fd }).then(r => r.json()).then(d => { if (d.success) warnModal.hide(); });
        }
        function tagViolation(id, name) {
            Swal.fire({
                title: 'Tag Violation?',
                text: `Tag manual violation for ${name}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#092c19'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('attempt_id', id);
                    fetch('admin_live_proctor?action=violation', { method: 'POST', body: fd }).then(() => location.reload());
                }
            });
        }

        function unlockAttempt(id, name) {
            Swal.fire({
                title: 'Unlock Attempt?',
                text: `Remove the lock on ${name}'s exam? They will be able to resume.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('attempt_id', id);
                    fetch('admin_live_proctor?action=unlock', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                Swal.fire('Unlocked', `${name}'s exam has been unlocked. Violations cleared.`, 'success');
                                location.reload();
                            }
                        });
                }
            });
        }

        function forceSubmit(id, name) {
            Swal.fire({
                title: 'Force Submit?',
                text: `CAUTION: Are you sure you want to FORCE SUBMIT this exam for ${name}? This will calculate their current score and end the session immediately.`,
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e3342f'
            }).then((result) => {
                if (result.isConfirmed) {
                    const fd = new FormData();
                    fd.append('attempt_id', id);
                    fetch('admin_live_proctor?action=force_submit', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
                        if (d.success) {
                            alert(`Exam submitted for ${name}. Score: ${d.score}`);
                            location.reload();
                        } else {
                            alert('Error: ' + d.error);
                        }
                    });
                }
            });
        }

        function forceSubmitAll(examId) {
            Swal.fire({
                title: 'Force Submit ALL?',
                text: "CRITICAL: This will immediately end and finalize EVERY active attempt for this exam. Students will be logged out. Continue?",
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#e3342f',
                confirmButtonText: 'Yes, Submit All'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Processing...',
                        text: 'Grading and finalizing all active sessions.',
                        didOpen: () => Swal.showLoading(),
                        allowOutsideClick: false
                    });
                    const fd = new FormData();
                    fd.append('exam_id', examId);
                    fetch('admin_live_proctor?action=force_submit_all', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(d => {
                            if (d.success) {
                                Swal.fire('Success', `Finished ${d.count} active attempts.`, 'success').then(() => location.reload());
                            } else {
                                Swal.fire('Error', d.error, 'error');
                            }
                        });
                }
            });
        }

        const zoomModal = new bootstrap.Modal(document.getElementById('zoomModal'));
        function zoomImage(src, name) {
            document.getElementById('zoomImg').src = src;
            document.getElementById('zoomTitle').textContent = 'Monitoring: ' + name;
            zoomModal.show();
        }
    </script>
    <!-- sweetalert_custom_injected_v1 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Override global alert
            window.alert = function (msg) {
                Swal.fire({
                    title: 'Message',
                    text: msg,
                    icon: 'info',
                    confirmButtonColor: '#092c19'
                });
            };

            // Intercept inline onsubmit confirms
            document.querySelectorAll('form[onsubmit*="return confirm"]').forEach(el => {
                let match = el.getAttribute('onsubmit').match(/return confirm\(['"`](.*?)['"`]\)/);
                if (match) {
                    let msg = match[1];
                    el.removeAttribute('onsubmit');
                    el.addEventListener('submit', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#092c19',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, proceed'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                HTMLFormElement.prototype.submit.call(el);
                            }
                        });
                    });
                }
            });

            // Intercept inline onclick confirms for anchors
            document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
                let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
                if (match) {
                    let msg = match[1];
                    el.removeAttribute('onclick');
                    el.addEventListener('click', function (e) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Are you sure?',
                            text: msg,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#092c19',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, proceed'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                if (el.tagName === 'A' && el.href) {
                                    window.location.href = el.href;
                                } else if (el.tagName === 'BUTTON') {
                                    // if it's a submit button inside a form
                                    if (el.type === 'submit' && el.form) {
                                        HTMLFormElement.prototype.submit.call(el.form);
                                    }
                                }
                            }
                        });
                    });
                }
            });
        });
    </script>
</body>


</html>