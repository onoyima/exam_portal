<?php
require 'auth.php';
check_auth('student');
require 'db.php';
require_once 'exam_logic.php';

$student_id = $_SESSION['student_id'];
$name = $_SESSION['student_name'];
$matric = $_SESSION['matric_no'];

// Auto-finalize any expired attempts before loading the dashboard
auto_finalize_expired_attempts($db, null, $student_id);

// Fetch all active exams (Objective and Practical) assigned to this student
$stmt = $db->prepare("SELECT e.*, 
    (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ? AND oa.finished_at IS NOT NULL) as finished_attempts,
    (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ?) as total_attempts,
    (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ? AND oa.is_locked = 1) as locked_attempts
    FROM exams e JOIN exam_students es ON e.id=es.exam_id 
    WHERE es.student_id=? AND e.is_active=1 AND e.deleted_at IS NULL ORDER BY e.created_at DESC");
$stmt->execute([$student_id, $student_id, $student_id, $student_id]);
$all_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate exams by type
$active_exams = array_filter($all_exams, fn($e) => $e['type'] === 'objective');
$practical_exams = array_filter($all_exams, fn($e) => $e['type'] !== 'objective');

$requires_media = false;
foreach($active_exams as $e) {
    if (!empty($e['proctor_webcam']) || !empty($e['proctor_live_video']) || !empty($e['is_strict'])) {
        $requires_media = true;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Student Dashboard - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>

<body>
  <?php include 'student_sidebar.php'; ?>

  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Student Portal</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="student_logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb">
        <ol>
          <li class="current" aria-current="page"><i class="bi bi-house"></i> Dashboard</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Your Objective Examinations</h2>
        <div class="admin-page-actions">
          <span class="admin-badge admin-badge-primary"><i class="bi bi-award"></i> Active: <?= count($active_exams) ?> Objective &bull; <?= count($practical_exams) ?> Practical</span>
        </div>
      </div>

      <p class="text-muted mb-4">Review and attempt your active assigned exams below.</p>

      <?php display_auth_errors(); ?>

      <div class="row g-4">
        <?php if (empty($active_exams)): ?>
          <div class="col-12 mt-5">
            <div class="admin-card">
              <div class="admin-card-body text-center py-5">
              <i class="bi bi-journal-x text-muted mb-3 exam-empty-icon"></i>
              <h3 class="fw-bold">No Active Exams</h3>
              <p class="text-muted mx-auto exam-empty-text">
                You don't have any active objective exams assigned to you at the moment. Please check back later.
              </p>
            </div>
          </div>
          </div>
        <?php else: ?>
          <?php foreach ($active_exams as $exam):
            $has_finished = $exam['finished_attempts'] > 0;
            $can_recompute = $exam['finished_attempts'] < ($exam['max_attempts'] ?? 1);
            $is_active = $exam['total_attempts'] > $exam['finished_attempts'];
            $is_locked = ($exam['locked_attempts'] ?? 0) > 0;

            // Scheduling Logic
            $now = time();
            $start_ts = $exam['start_time'] ? strtotime($exam['start_time']) : null;
            $end_ts = $exam['end_time'] ? strtotime($exam['end_time']) : null;

            $is_started = !$start_ts || ($now >= $start_ts);
            $is_ended = $end_ts && ($now > $end_ts);

            // Security features for this exam
            $sec_features = [];
            if (($exam['proctor_fullscreen'] ?? 0) || ($exam['is_strict'] ?? 0)) $sec_features[] = ['icon' => 'bi-arrows-fullscreen', 'label' => 'Fullscreen'];
            if (($exam['proctor_tab_lock'] ?? 0) || ($exam['is_strict'] ?? 0)) $sec_features[] = ['icon' => 'bi-window-stack', 'label' => 'Tab Lock'];
            if (($exam['proctor_kb_lock'] ?? 0) || ($exam['is_strict'] ?? 0)) $sec_features[] = ['icon' => 'bi-keyboard', 'label' => 'KB Lock'];
            if (($exam['proctor_webcam'] ?? 0) || ($exam['is_strict'] ?? 0)) $sec_features[] = ['icon' => 'bi-camera-video', 'label' => 'Webcam'];
            if (($exam['proctor_live_video'] ?? 0) || ($exam['is_strict'] ?? 0)) $sec_features[] = ['icon' => 'bi-broadcast', 'label' => 'Live Monitor'];
            if ($exam['proctor_auto_submit'] ?? 0) $sec_features[] = ['icon' => 'bi-send-clock', 'label' => 'Auto Submit'];
            if ($exam['proctor_browser_check'] ?? 0) $sec_features[] = ['icon' => 'bi-browser-chrome', 'label' => 'Browser Check'];
            ?>
            <div class="col-12 col-md-6 col-lg-4" id="exam-<?= $exam['id'] ?>">
              <div class="exam-card-student position-relative">
                <div class="exam-status-overlay">
                  <?php if ($is_locked): ?>
                    <span class="badge bg-dark rounded-pill shadow"><i class="bi bi-lock-fill me-1"></i> LOCKED</span>
                  <?php elseif ($has_finished): ?>
                    <span class="badge bg-success rounded-pill shadow">Attempted (<?= $exam['finished_attempts'] ?>)</span>
                  <?php elseif (!$is_started): ?>
                    <span class="badge bg-secondary rounded-pill shadow">NOT YET AVAILABLE</span>
                  <?php elseif ($is_ended): ?>
                    <span class="badge bg-danger rounded-pill shadow">EXAM CLOSED</span>
                  <?php endif; ?>
                </div>

                <div class="card-exam-header">
                  <h4 class="fw-bold mb-1 text-white text-truncate"><?= htmlspecialchars($exam['name']) ?></h4>
                  <div class="small opacity-75"><i class="bi bi-clock me-1"></i> <?= $exam['duration_minutes'] ?> Minutes
                  </div>
                </div>

                <div class="card-exam-body">
                  <p class="text-muted small mb-4 exam-desc">
                    <?= htmlspecialchars($exam['description'] ?: 'No description provided.') ?>
                  </p>

                  <?php if (!empty($sec_features)): ?>
                  <div class="mb-3">
                    <div class="d-flex flex-wrap gap-1">
                      <?php foreach ($sec_features as $sf): ?>
                      <span class="badge bg-light text-dark fw-normal px-2 py-1 small sec-feature-badge">
                        <i class="bi <?= $sf['icon'] ?> me-1 text-primary"></i><?= $sf['label'] ?>
                      </span>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endif; ?>

                  <div class="vstack gap-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="small text-muted fw-semibold">Max Attempts:</span>
                      <span class="badge bg-light text-dark fw-bold"><?= $exam['max_attempts'] ?? 1 ?></span>
                    </div>
                  </div>

                  <div class="d-grid gap-2">
                    <?php if ($is_locked): ?>
                      <button class="btn btn-secondary py-3 rounded-4 fw-bold" disabled><i class="bi bi-lock-fill me-1"></i> LOCKED — CONTACT ADMIN</button>
                    <?php elseif ($is_active): ?>
                      <a href="student_objective_exam?exam_id=<?= $exam['id'] ?>&resume=1" class="btn btn-warning py-3 rounded-4 shadow-sm fw-bold">RESUME EXAM</a>
                    <?php elseif ($can_recompute): ?>
                      <?php if (!$is_started || $is_ended): ?>
                        <button class="btn btn-light py-3 rounded-4 disabled opacity-75 fw-bold border">UNAVAILABLE</button>
                      <?php else: ?>
                        <button type="button" class="btn btn-primary py-3 rounded-4 shadow-sm fw-bold" onclick="confirmExamStart('<?= $exam['name'] ?>', 'student_objective_exam.php?exam_id=<?= $exam['id'] ?><?= $has_finished ? '&start_new=1' : '&start=1' ?>')">START</button>
                      <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($has_finished): ?>
                      <a href="student_objective_exam?exam_id=<?= $exam['id'] ?>&view_result=1" class="btn btn-outline-info py-2 rounded-4 fw-bold">VIEW RESULTS</a>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </div>
  </div>

  <!-- Start Confirmation Modal -->
  <div class="modal fade" id="startExamModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 rounded-5 shadow-lg overflow-hidden">
        <div class="modal-body p-5 text-center">
          <div class="mb-4">
            <div class="bg-primary-light p-4 rounded-circle d-inline-block">
              <i class="bi bi-journal-check text-primary fs-1"></i>
            </div>
          </div>
          <h3 class="fw-bold mb-3 text-dark">Ready to Start?</h3>
          <p class="text-muted mb-4 fs-5">You are about to begin: <br><b id="modalExamName" class="text-primary"></b>
          </p>
          <div class="alert alert-warning border-0 rounded-4 px-3 py-2 small d-flex align-items-center gap-2 mb-5">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <span class="text-start">Once you start, the timer will begin immediately and cannot be paused. Make sure
              you have a stable connection.</span>
          </div>
          <div class="vstack gap-3 px-2">
            <a id="confirmStartBtn" href="#" class="btn btn-primary btn-lg rounded-pill py-3 fw-bold shadow">
              Yes, Start Examination <i class="bi bi-play-fill ms-2"></i>
            </a>
            <button type="button" class="btn btn-link text-muted fw-bold text-decoration-none" data-bs-dismiss="modal">
              Not Now
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    const startModal = new bootstrap.Modal(document.getElementById('startExamModal'));

    function confirmExamStart(name, url) {
      document.getElementById('modalExamName').textContent = name;
      document.getElementById('confirmStartBtn').href = url;
      startModal.show();
    }

    // Hash-based highlighting
    function checkHash() {
      const hash = window.location.hash;
      if (hash) {
        const el = document.querySelector(hash);
        if (el) {
          const card = el.querySelector('.exam-card-student');
          // Remove highlight from all cards
          document.querySelectorAll('.exam-card-student').forEach(c => c.classList.remove('highlighted'));
          // Add to this one
          if (card) {
            card.classList.add('highlighted');
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
      }
    }
    window.addEventListener('hashchange', checkHash);
    window.addEventListener('load', checkHash);

  </script>
  

<?php if ($requires_media): ?>
  <script>
    /* ── Silent full security pre-authorization ─────────────────────────────────
       All security setup happens HERE on the dashboard, before the student ever
       clicks START. This means when they open the exam:
       - Webcam/mic permission is already granted (no dialog)
       - Fullscreen permission is primed (user gesture on START click)
       - AI proctor models can pre-load
       No student action is required — everything runs in the background.
    ── */
    (function () {
        if (sessionStorage.getItem('_portalSecurityReady')) return;

        // 1. Pre-authorize webcam + microphone
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            setTimeout(function () {
                navigator.mediaDevices.getUserMedia({ video: true, audio: true })
                    .then(function (stream) {
                        stream.getTracks().forEach(function (t) { t.stop(); });
                        sessionStorage.setItem('_portalSecurityReady', '1');
                        console.log('[Portal] Webcam & mic pre-authorized. Exam access is ready.');
                    })
                    .catch(function (err) {
                        console.warn('[Portal] Camera/mic pre-authorization declined:', err.name);
                    });
            }, 1200);
        }

        // 2. Pre-request fullscreen pointer lock by listening for the first user gesture
        //    on the dashboard. This primes the browser so fullscreen works instantly
        //    when the exam page loads.
        document.addEventListener('click', function fsPrimer() {
            document.removeEventListener('click', fsPrimer);
            // Request and immediately exit fullscreen to prime permission
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen()
                    .then(function () { document.exitFullscreen(); })
                    .catch(function () {});
            }
        }, { once: true });
    })();
  </script>
<?php endif; ?>

  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>


</html>