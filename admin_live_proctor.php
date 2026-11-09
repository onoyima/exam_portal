<?php
require 'auth.php';
check_auth(['admin', 'examiner']);
require 'db.php';
require_once __DIR__ . '/includes/access.php';

$name = $_SESSION['user'] ?? 'Admin';
$back_url = acc_home();

/** Attempt row (with exam id) if this user may act on it, otherwise stop. */
function lp_attempt(PDO $db, int $id): array
{
    $st = $db->prepare("SELECT * FROM objective_attempts WHERE id = ?");
    $st->execute([$id]);
    $a = $st->fetch(PDO::FETCH_ASSOC);
    if (!$a) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Attempt not found']);
        exit;
    }
    acc_require_exam($db, (int) $a['exam_id']);
    return $a;
}

function lp_recordings(int $attempt_id): array
{
    $files = glob(__DIR__ . "/uploads/recordings/attempt_{$attempt_id}/*.webm") ?: [];
    $out = [];
    foreach ($files as $f) {
        $base = basename($f);
        preg_match('/_(\d{10,})\.webm$/', $base, $m);
        $started = isset($m[1]) ? (int) floor((int) $m[1] / 1000) : filemtime($f);
        $out[] = [
            'file' => $base,
            'who' => strpos($base, 'proctor_') === 0 ? 'Live view' : 'Student camera',
            'started' => date('g:i:s A', $started),
            'ended' => date('g:i:s A', filemtime($f)),
            'minutes' => max(0, round((filemtime($f) - $started) / 60, 1)),
            'size' => round(filesize($f) / 1048576, 1),
            'ts' => $started,
        ];
    }
    usort($out, fn($a, $b) => $a['ts'] <=> $b['ts']);
    return $out;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // ── Stream one recording file for playback ──
    if ($action === 'recording_file') {
        $att = lp_attempt($db, intval($_GET['attempt_id'] ?? 0));
        $file = basename((string) ($_GET['file'] ?? ''));
        $path = __DIR__ . "/uploads/recordings/attempt_{$att['id']}/$file";
        if (!preg_match('/^[a-z_]+\d*\.webm$|^[a-z]+_\d+\.webm$|^[A-Za-z0-9_\-]+\.webm$/', $file) || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        session_write_close();
        $size = filesize($path);
        $start = 0;
        $end = $size - 1;
        header('Content-Type: video/webm');
        header('Accept-Ranges: bytes');
        if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            $start = $m[1] === '' ? 0 : (int) $m[1];
            $end = $m[2] === '' ? $end : min((int) $m[2], $end);
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fh = fopen($path, 'rb');
        fseek($fh, $start);
        $left = $end - $start + 1;
        while ($left > 0 && !feof($fh)) {
            $chunk = fread($fh, min(1048576, $left));
            echo $chunk;
            $left -= strlen($chunk);
        }
        fclose($fh);
        exit;
    }

    // ── Download single student's recording ──
    if ($action === 'download_recording') {
        $att = lp_attempt($db, intval($_GET['attempt_id'] ?? 0));
        $stmt = $db->prepare("SELECT s.matric_no, e.name AS exam_name FROM students s, exams e WHERE s.id = ? AND e.id = ?");
        $stmt->execute([$att['student_id'], $att['exam_id']]);
        $row = $stmt->fetch();
        $files = glob(__DIR__ . "/uploads/recordings/attempt_{$att['id']}/*.webm") ?: [];
        if (!$files) { http_response_code(404); die('No recordings found'); }
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

    // ── Download all recordings for an exam ──
    if ($action === 'download_all_recordings') {
        $exam_id = intval($_GET['exam_id'] ?? 0);
        acc_require_exam($db, $exam_id);
        $attempts = $db->prepare("SELECT oa.id, s.matric_no, e.name AS exam_name FROM objective_attempts oa JOIN students s ON oa.student_id = s.id JOIN exams e ON oa.exam_id = e.id WHERE oa.exam_id = ?");
        $attempts->execute([$exam_id]);
        $rows = $attempts->fetchAll(PDO::FETCH_ASSOC);
        $zip = new ZipArchive();
        $zip_name = tempnam(sys_get_temp_dir(), 'recordings_') . '.zip';
        $zip->open($zip_name, ZipArchive::CREATE);
        $added = 0;
        foreach ($rows as $r) {
            $matric = preg_replace('/[^A-Za-z0-9_\-]/', '_', $r['matric_no']);
            foreach (glob(__DIR__ . "/uploads/recordings/attempt_{$r['id']}/*.webm") ?: [] as $f) {
                $zip->addFile($f, "{$matric}/" . basename($f));
                $added++;
            }
        }
        $zip->close();
        if ($added === 0) { @unlink($zip_name); http_response_code(404); die('No video files found for this exam'); }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="recordings_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $rows[0]['exam_name']) . '.zip"');
        header('Content-Length: ' . filesize($zip_name));
        readfile($zip_name);
        @unlink($zip_name);
        exit;
    }

    header('Content-Type: application/json');
    header('Cache-Control: no-store');

    // ── Tiles for the wall ──
    if ($action === 'wall') {
        session_write_close();
        $exam_id = intval($_GET['exam_id'] ?? 0);
        $view = $_GET['view'] ?? 'live';
        $search = trim($_GET['search'] ?? '');
        $where = ['s.deleted_at IS NULL', acc_exam_sql($db, 'oa.exam_id')];
        $args = [];
        if ($exam_id) {
            $where[] = 'oa.exam_id = ?';
            $args[] = $exam_id;
        }
        if ($view === 'live') {
            $where[] = 'oa.finished_at IS NULL';
        } elseif ($view === 'finished') {
            $where[] = 'oa.finished_at IS NOT NULL AND oa.finished_at > DATE_SUB(NOW(), INTERVAL 1 DAY)';
        } else {
            $where[] = 'oa.started_at > DATE_SUB(NOW(), INTERVAL 1 DAY)';
        }
        if ($search !== '') {
            $where[] = '(s.name LIKE ? OR s.matric_no LIKE ?)';
            $args[] = "%$search%";
            $args[] = "%$search%";
        }
        $st = $db->prepare("SELECT oa.id, oa.exam_id, oa.student_id, oa.attempt_no, oa.started_at, oa.finished_at, oa.violation_count,
                oa.is_locked, oa.is_live_requested, oa.proctor_status, oa.snapshot_path, oa.question_order, oa.time_used,
                TIMESTAMPDIFF(SECOND, oa.last_active, NOW()) AS idle, s.name, s.matric_no, e.name AS exam_name,
                e.duration_minutes, e.end_time,
                (SELECT MAX(v.created_at) FROM exam_violations v WHERE v.attempt_id = oa.id) AS last_violation_at,
                (SELECT v.violation_type FROM exam_violations v WHERE v.attempt_id = oa.id ORDER BY v.id DESC LIMIT 1) AS last_violation
            FROM objective_attempts oa
            JOIN students s ON s.id = oa.student_id
            JOIN exams e ON e.id = oa.exam_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.name LIMIT 400");
        $st->execute($args);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);

        $answered = [];
        if ($rows) {
            $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
            foreach ($db->query("SELECT oa.id, COUNT(a.id) FROM objective_attempts oa
                    JOIN objective_answers a ON a.exam_id = oa.exam_id AND a.student_id = oa.student_id AND a.attempt_no = oa.attempt_no
                    WHERE oa.id IN ($ids) GROUP BY oa.id")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
                $answered[(int) $k] = (int) $v;
            }
        }
        $tiles = [];
        foreach ($rows as $r) {
            $state = 'writing';
            if ($r['finished_at']) {
                $state = 'submitted';
            } elseif ((int) $r['is_locked'] === 1) {
                $state = 'locked';
            } elseif ($r['idle'] > 120) {
                $state = 'offline';
            } elseif ($r['proctor_status'] === 'blur' || $r['idle'] > 45) {
                $state = 'away';
            }
            $snap = null;
            if ($r['snapshot_path'] && is_file(__DIR__ . '/' . $r['snapshot_path'])) {
                $snap = $r['snapshot_path'] . '?t=' . filemtime(__DIR__ . '/' . $r['snapshot_path']);
            }
            $total = $r['question_order'] ? count(explode(',', $r['question_order'])) : 0;
            $tiles[] = [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'matric' => $r['matric_no'],
                'exam' => $r['exam_name'],
                'exam_id' => (int) $r['exam_id'],
                'attempt' => (int) $r['attempt_no'],
                'state' => $state,
                'violations' => (int) $r['violation_count'],
                'alert' => $r['last_violation_at'] && strtotime($r['last_violation_at']) > time() - 90 && !$r['finished_at'],
                'last_violation' => $r['last_violation'] ? str_replace('_', ' ', strtolower($r['last_violation'])) : null,
                'snap' => $snap,
                'answered' => $answered[(int) $r['id']] ?? 0,
                'total' => $total,
                'left' => $r['finished_at'] ? null : qe_remaining_seconds($r, $r),
                'started' => $r['started_at'] ? date('g:i A', strtotime($r['started_at'])) : '',
                'idle' => (int) $r['idle'],
                'live' => (int) $r['is_live_requested'] === 1,
                'rec' => is_dir(__DIR__ . "/uploads/recordings/attempt_{$r['id']}"),
            ];
        }
        echo json_encode(['success' => true, 'tiles' => $tiles, 'time' => date('g:i:s A')]);
        exit;
    }

    if ($action === 'recordings') {
        $att = lp_attempt($db, intval($_GET['attempt_id'] ?? 0));
        echo json_encode(['success' => true, 'files' => lp_recordings((int) $att['id'])]);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'error' => 'POST required']);
        exit;
    }
    validate_csrf();
    $id = intval($_POST['attempt_id'] ?? 0);

    if ($action === 'warn') {
        lp_attempt($db, $id);
        $msg = substr(trim($_POST['message'] ?? '') ?: 'Please focus on your exam.', 0, 500);
        $db->prepare("UPDATE objective_attempts SET warning_message = ?, last_warning_at = NOW() WHERE id = ?")->execute([$msg, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'violation') {
        lp_attempt($db, $id);
        $reason = substr(trim($_POST['reason'] ?? '') ?: 'Tagged by proctor', 0, 500);
        $db->prepare("INSERT INTO exam_violations (attempt_id, student_id, exam_id, violation_type, details)
            SELECT id, student_id, exam_id, 'PROCTOR_TAG', ? FROM objective_attempts WHERE id = ?")->execute([$reason, $id]);
        $db->prepare("UPDATE objective_attempts SET violation_count = violation_count + 1,
            is_locked = IF(violation_count >= 5, 1, is_locked) WHERE id = ? AND finished_at IS NULL")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'force_submit') {
        lp_attempt($db, $id);
        echo json_encode(qe_finalize_attempt($db, $id) ? ['success' => true] : ['success' => false, 'error' => 'Attempt not found or already finished']);
        exit;
    }

    if ($action === 'unlock') {
        lp_attempt($db, $id);
        $db->prepare("UPDATE objective_attempts SET is_locked = 0, violation_count = 0 WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'release') {
        // Frees the account so the student can sign in on another computer
        // straight away (their time is kept).
        $att = lp_attempt($db, $id);
        $db->prepare("UPDATE students SET current_session_id = NULL WHERE id = ?")->execute([$att['student_id']]);
        $db->prepare("UPDATE objective_attempts SET last_active = DATE_SUB(NOW(), INTERVAL 5 MINUTE), proctor_status = 'disconnected' WHERE id = ? AND finished_at IS NULL")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'force_submit_all') {
        $exam_id = intval($_POST['exam_id'] ?? 0);
        acc_require_exam($db, $exam_id);
        $stmt = $db->prepare("SELECT id FROM objective_attempts WHERE exam_id = ? AND finished_at IS NULL");
        $stmt->execute([$exam_id]);
        $count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $att_id) {
            if (qe_finalize_attempt($db, (int) $att_id)) {
                $count++;
            }
        }
        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

$exams = $db->query("SELECT id, name FROM exams e WHERE type = 'objective' AND is_active = 1 AND deleted_at IS NULL AND " . acc_exam_sql($db) . " ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$selected_exam = intval($_GET['exam_id'] ?? 0);
if ($selected_exam && !acc_can_exam($db, $selected_exam)) {
    $selected_exam = 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Live Monitor - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="assets/css/live_monitor.css?v=1" rel="stylesheet">
</head>

<body>
  <?php include acc_sidebar(); ?>
  <div class="main-content lm-page">
    <header class="lm-bar">
      <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
      <h1>Live Monitor</h1>
      <div class="lm-counts" id="lmCounts"></div>
      <span class="lm-clock" id="lmClock"></span>
    </header>

    <div class="lm-tools">
      <select id="lmExam" class="lm-input" aria-label="Exam">
        <option value="0">All exams</option>
        <?php foreach ($exams as $ex): ?>
          <option value="<?= (int) $ex['id'] ?>" <?= $selected_exam === (int) $ex['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ex['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="lm-seg" role="tablist" id="lmView">
        <button type="button" data-view="live" class="is-on">Writing now</button>
        <button type="button" data-view="today">Today</button>
        <button type="button" data-view="finished">Submitted</button>
      </div>
      <input type="search" id="lmSearch" class="lm-input" placeholder="Name or matric number">
      <div class="lm-seg" id="lmSize" aria-label="Tile size">
        <button type="button" data-size="s" title="Small tiles">S</button>
        <button type="button" data-size="m" class="is-on" title="Medium tiles">M</button>
        <button type="button" data-size="l" title="Large tiles">L</button>
      </div>
      <label class="lm-check"><input type="checkbox" id="lmAlertsFirst" checked> Alerts first</label>
      <span class="lm-spacer"></span>
      <a class="lm-btn" id="lmDownloadAll" href="#" hidden><i class="bi bi-download"></i> Recordings</a>
      <button type="button" class="lm-btn lm-btn-danger" id="lmSubmitAll" hidden><i class="bi bi-send"></i> Submit all</button>
    </div>

    <main class="lm-wall lm-size-m" id="lmWall" aria-live="polite"></main>
    <div class="lm-empty" id="lmEmpty" hidden>No students match. When students start the exam they appear here.</div>
  </div>

  <!-- spotlight -->
  <div class="lm-spot" id="lmSpot" hidden>
    <div class="lm-spot-box" role="dialog" aria-modal="true">
      <div class="lm-spot-media">
        <img id="spImg" alt="">
        <video id="spVideo" autoplay playsinline hidden></video>
        <span class="lm-live" id="spLive" hidden>LIVE</span>
      </div>
      <div class="lm-spot-side">
        <button type="button" class="lm-x" id="spClose" aria-label="Close">&times;</button>
        <h2 id="spName"></h2>
        <div class="lm-spot-matric" id="spMatric"></div>
        <dl class="lm-dl" id="spFacts"></dl>
        <div class="lm-actions">
          <button type="button" class="lm-btn lm-btn-primary" id="spStream"><i class="bi bi-camera-video"></i> Live view</button>
          <button type="button" class="lm-btn" id="spSpeak" disabled><i class="bi bi-mic"></i> Speak</button>
          <button type="button" class="lm-btn" id="spWarn"><i class="bi bi-megaphone"></i> Warn</button>
          <button type="button" class="lm-btn" id="spTag"><i class="bi bi-flag"></i> Tag violation</button>
          <button type="button" class="lm-btn" id="spUnlock"><i class="bi bi-unlock"></i> Unlock</button>
          <button type="button" class="lm-btn" id="spRelease" title="Let the student sign in on another computer now"><i class="bi bi-pc-display"></i> Move to another PC</button>
          <button type="button" class="lm-btn lm-btn-danger" id="spSubmit"><i class="bi bi-send"></i> Submit for student</button>
        </div>
        <h3>Recordings</h3>
        <div id="spRecs" class="lm-recs"></div>
        <video id="spPlayer" controls hidden></video>
      </div>
    </div>
  </div>

  <div class="lm-toast" id="lmToast" hidden></div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
  <script>window.LM = { csrf: <?= json_encode(generate_csrf_token()) ?> };</script>
  <script src="assets/js/live_monitor.js?v=1"></script>
</body>

</html>
