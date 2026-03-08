<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

if (isset($_GET['action'])) {
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

// Fetch exams for filter
$all_exams = $db->query("SELECT id, name FROM exams WHERE is_active=1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>Live Video Proctoring - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="style_enhanced.css" rel="stylesheet">
    <meta http-equiv="refresh" content="60">
    <style>
        .proctor-card {
            border-radius: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.08) !important;
            overflow: hidden;
            background: #fff;
        }

        .video-wrapper {
            width: 100%;
            height: 200px;
            background: #111;
            position: relative;
        }

        .student-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: none;
        }

        .placeholder-snap {
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0.6;
        }

        .video-status-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }

        .live-tag {
            background: #ef4444;
            color: #fff;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 800;
            display: none;
            animation: pulse-red 2s infinite;
        }

        @keyframes pulse-red {
            0% {
                opacity: 1;
            }

            50% {
                opacity: 0.5;
            }

            100% {
                opacity: 1;
            }
        }

        .btn-live {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 20;
        }

        .filter-bar {
            background: #fff;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: var(--shadow);
        }

        .zoom-img {
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }

        .zoom-img:hover {
            opacity: 0.9;
        }

        .stat-dot {
            height: 10px;
            width: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .dot-active {
            background-color: #22c55e;
            box-shadow: 0 0 10px #22c55e;
            animation: pulse 2s infinite;
        }

        .dot-idle {
            background-color: #f59e0b;
        }

        .dot-offline {
            background-color: #94a3b8;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            }

            70% {
                transform: scale(1);
                box-shadow: 0 0 0 10px rgba(34, 197, 94, 0);
            }

            100% {
                transform: scale(0.95);
                box-shadow: 0 0 0 0 rgba(34, 197, 94, 0);
            }
        }
    </style>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <?php include 'admin_sidebar.php'; ?>
    <div class="main-content">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
            <div class="container-fluid">
                <span class="navbar-brand fw-bold text-success"><i class="bi bi-shield-lock-fill me-2"></i>Live
                    Proctoring Control</span>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <span class="badge bg-danger-light text-danger pulse fw-bold py-2"><i
                            class="bi bi-record-circle me-1"></i> VIDEO SERVER ACTIVE</span>
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
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 mb-0">Apply</button>
                    </div>
                </form>
            </div>
            <div class="row g-4">
                <?php if (empty($live_attempts)): ?>
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-camera-video-off text-muted display-1"></i>
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
                                    <video id="video-<?= $att['id'] ?>" class="student-video" autoplay playsinline
                                        muted></video>

                                    <div class="video-status-overlay">
                                        <span id="live-tag-<?= $att['id'] ?>" class="live-tag">LIVE VIDEO</span>
                                        <?php if (!$att['finished_at']): ?>
                                            <span class="badge bg-success rounded-pill shadow-sm">
                                                <span class="stat-dot dot-active"></span> ACTIVE
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary rounded-pill shadow-sm">FINISHED</span>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!$att['finished_at'] && $att['idle_seconds'] < 60): ?>
                                        <button class="btn btn-sm btn-danger rounded-pill fw-bold btn-live shadow"
                                            onclick="startLiveVideo('<?= $att['id'] ?>')">
                                            <i class="bi bi-play-fill"></i> Stream
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
                                        <div class="d-flex gap-2">
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
                                            <button
                                                onclick="forceSubmit(<?= $att['id'] ?>, '<?= addslashes($att['student_name']) ?>')"
                                                class="btn btn-sm btn-danger flex-grow-1 fw-bold" style="font-size: 0.7rem;">
                                                Submit
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-grid">
                                            <div class="alert alert-success py-1 px-2 mb-0 text-center fw-bold"
                                                style="font-size: 0.7rem;">
                                                Score: <?= $att['score'] ?> | Completed
                                            </div>
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
        const rtcConfig = { iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] };
        const activeConnections = {};
        const lastSignalIds = {};

        async function startLiveVideo(attemptId) {
            const btn = document.querySelector(`#card-${attemptId} .btn-live`);
            if (btn) btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Syncing...';

            // PHASE 3: SCALE OPTIMIZATION
            // First, trigger the student to start their signaling loop
            await fetch(`webrtc_api?action=request_live&attempt_id=${attemptId}`);

            // We don't clear signals anymore to avoid race conditions
            // signals are attempt-specific anyway.

            const pc = new RTCPeerConnection(rtcConfig);
            activeConnections[attemptId] = pc;

            // CRITICAL: Since the admin/proctor is only RECEIVING video, we must explicitly 
            // add a transceiver to the connection to tell the browser to negotiate a video track.
            // Without this, createOffer() will produce an SDP with no media sections.
            pc.addTransceiver('video', { direction: 'recvonly' });

            pc.oniceconnectionstatechange = () => {
                console.log(`ICE Connection [${attemptId}]:`, pc.iceConnectionState);
            };

            pc.ontrack = (event) => {
                const video = document.getElementById(`video-${attemptId}`);
                const snap = document.getElementById(`snap-${attemptId}`);
                const liveTag = document.getElementById(`live-tag-${attemptId}`);

                video.srcObject = event.streams[0];
                video.muted = true;
                video.style.display = 'block';
                video.play().catch(e => console.error("Autoplay prevented:", e));
                if (snap) snap.style.display = 'none';
                if (liveTag) liveTag.style.display = 'block';
                if (btn) btn.style.display = 'none';
            };

            pc.onicecandidate = (event) => {
                if (event.candidate) {
                    sendSignal(attemptId, 'ice', JSON.stringify(event.candidate));
                }
            };

            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            sendSignal(attemptId, 'offer', JSON.stringify(offer));

            // Start Monitoring Answer
            lastSignalIds[attemptId] = 0;
            const checkSignals = setInterval(async () => {
                const resp = await fetch(`webrtc_api?action=get&attempt_id=${attemptId}&last_id=${lastSignalIds[attemptId]}`);
                const data = await resp.json();
                if (data.signals) {
                    for (const signal of data.signals) {
                        lastSignalIds[attemptId] = signal.id;
                        const payload = JSON.parse(signal.payload);
                        if (signal.type === 'answer') {
                            console.log("Received answer for", attemptId);
                            await pc.setRemoteDescription(new RTCSessionDescription(payload));
                        } else if (signal.type === 'ice') {
                            console.log("Received ICE candidate for", attemptId);
                            try {
                                await pc.addIceCandidate(new RTCIceCandidate(payload));
                            } catch (e) { console.error("ICE error:", e); }
                        }
                    }
                }
            }, 1000); // Poll faster (1s) for handshake
        }

        function sendSignal(attemptId, type, payload) {
            const fd = new FormData();
            fd.append('attempt_id', attemptId);
            fd.append('type', type);
            fd.append('payload', payload);
            fetch('webrtc_api?action=send', { method: 'POST', body: fd });
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