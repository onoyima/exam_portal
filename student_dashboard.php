<?php
require 'auth.php';
check_auth('student');
require 'db.php';
$student_id = $_SESSION['student_id'];
$name = $_SESSION['student_name'];
$matric = $_SESSION['matric_no'];

// Auto-finalize any expired attempts before loading the dashboard
auto_finalize_expired_attempts($db, null, $student_id);

// Fetch all active objective exams assigned to this student
$stmt = $db->prepare("SELECT e.*, 
    (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ? AND oa.finished_at IS NOT NULL) as finished_attempts,
    (SELECT COUNT(*) FROM objective_attempts oa WHERE oa.exam_id = e.id AND oa.student_id = ?) as total_attempts
    FROM exams e JOIN exam_students es ON e.id=es.exam_id 
    WHERE es.student_id=? AND e.type='objective' AND e.is_active=1 ORDER BY e.created_at DESC");
$stmt->execute([$student_id, $student_id, $student_id]);
$active_exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Student Dashboard - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <style>
    .exam-card-student {
      border-radius: 1.5rem;
      border: 1px solid rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
      background: #fff;
      overflow: hidden;
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .exam-card-student:hover {
      transform: translateY(-8px);
      box-shadow: 0 15px 35px rgba(9, 44, 25, 0.1);
      border-color: var(--primary-light);
    }

    .exam-status-overlay {
      position: absolute;
      top: 15px;
      right: 15px;
      z-index: 2;
    }

    .card-exam-header {
      background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
      padding: 2rem 1.5rem;
      position: relative;
      color: white;
    }

    .card-exam-body {
      padding: 1.5rem;
      flex-grow: 1;
    }

    .stat-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--secondary-color);
      border-radius: 2rem;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--primary-color);
    }

    .exam-card-student.highlighted {
      animation: highlightPulse 2s ease-in-out;
      border: 2px solid var(--accent-color);
    }

    @keyframes highlightPulse {
      0% {
        box-shadow: 0 0 0 0 rgba(52, 208, 88, 0.4);
        transform: scale(1);
      }

      50% {
        box-shadow: 0 0 0 15px rgba(52, 208, 88, 0);
        transform: scale(1.02);
      }

      100% {
        box-shadow: 0 0 0 0 rgba(52, 208, 88, 0);
        transform: scale(1);
      }
    }
  </style>
</head>

<body>
  <?php include 'student_sidebar.php'; ?>

  <div class="main-content">
    <!-- Top Nav -->
    <nav class="navbar navbar-light bg-white shadow-sm mb-4 d-lg-none">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary" id="sidebarToggle">
            <i class="bi bi-list"></i>
          </button>
          <span class="navbar-brand fw-bold">Student Portal</span>
        </div>
        <a href="student_logout.php" class="btn btn-sm btn-outline-danger rounded-pill"><i
            class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </nav>

    <div class="container-xl">
      <div class="row align-items-center mb-4">
        <div class="col-md-8">
          <h2 class="fw-bold mb-1">Your Objective Examinations</h2>
          <p class="text-muted">Review and attempt your active assigned exams below.</p>
        </div>
        <div class="col-md-4 text-md-end">
          <div class="stat-badge shadow-sm">
            <i class="bi bi-award text-warning"></i>
            <span>Active Exams: <?= count($active_exams) ?></span>
          </div>
        </div>
      </div>

      <?php display_auth_errors(); ?>

      <div class="row g-4">
        <?php if (empty($active_exams)): ?>
          <div class="col-12 mt-5">
            <div class="card p-5 text-center border-0 shadow-sm bg-white rounded-5">
              <i class="bi bi-journal-x text-muted mb-3" style="font-size: 4rem;"></i>
              <h3 class="fw-bold">No Active Exams</h3>
              <p class="text-muted mx-auto" style="max-width: 400px;">
                You don't have any active objective exams assigned to you at the moment. Please check back later or
                contact your instructor.
              </p>
            </div>
          </div>
        <?php endif; ?>

        <?php foreach ($active_exams as $exam):
          $has_finished = $exam['finished_attempts'] > 0;
          $can_recompute = $exam['finished_attempts'] < ($exam['max_attempts'] ?? 1);
          $is_active = $exam['total_attempts'] > $exam['finished_attempts'];
          ?>
          <div class="col-12 col-md-6 col-lg-4" id="exam-<?= $exam['id'] ?>">
            <div class="exam-card-student position-relative">
              <div class="exam-status-overlay">
                <?php if ($has_finished): ?>
                  <span class="badge bg-success rounded-pill shadow">Attempted (<?= $exam['finished_attempts'] ?>)</span>
                <?php else: ?>
                  <!-- <span class="badge bg-warning text-dark rounded-pill shadow">NOT STARTED</span> -->
                <?php endif; ?>
              </div>

              <div class="card-exam-header">
                <h4 class="fw-bold mb-1 text-white text-truncate"><?= htmlspecialchars($exam['name']) ?></h4>
                <div class="small opacity-75"><i class="bi bi-clock me-1"></i> <?= $exam['duration_minutes'] ?> Minutes
                </div>
              </div>

              <div class="card-exam-body">
                <p class="text-muted small mb-4"
                  style="min-height: 48px; border-bottom: 1px dashed #eee; padding-bottom: 15px;">
                  <?= htmlspecialchars($exam['description'] ?: 'No description provided for this examination.') ?>
                </p>

                <div class="vstack gap-3 mb-4">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="small text-muted fw-semibold">Max Attempts:</span>
                    <span class="badge bg-light text-dark fw-bold"><?= $exam['max_attempts'] ?? 1 ?></span>
                  </div>
                  <?php if ($has_finished): ?>
                    <div class="d-flex justify-content-between align-items-center">
                      <span class="small text-muted fw-semibold">Grading Method:</span>
                      <span class="badge bg-info-subtle text-info text-uppercase fw-bold" style="font-size: 0.65rem;">
                        <?= ($exam['grading_method'] ?? 'highest') === 'highest' ? 'Highest Score' : 'Average Score' ?>
                      </span>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="d-grid gap-2">
                  <?php if ($is_active): ?>
                    <a href="student_objective_exam.php?exam_id=<?= $exam['id'] ?>&resume=1"
                      class="btn btn-warning py-3 rounded-4 shadow-sm fw-bold">
                      <i class="bi bi-play-circle-fill me-2"></i> RESUME EXAM
                    </a>
                  <?php elseif ($can_recompute): ?>
                    <button type="button" class="btn btn-primary py-3 rounded-4 shadow-sm fw-bold"
                      onclick="confirmExamStart('<?= $exam['name'] ?>', 'student_objective_exam.php?exam_id=<?= $exam['id'] ?><?= $has_finished ? '&start_new=1' : '&start=1' ?>')">
                      <i class="bi bi-plus-circle me-2"></i>
                      <?= $has_finished ? 'START NEW ATTEMPT' : 'START EXAMINATION' ?>
                    </button>
                  <?php else: ?>
                    <button class="btn btn-dark py-3 rounded-4 disabled opacity-50 fw-bold">
                      <i class="bi bi-lock-fill me-2"></i> ATTEMPTS FINALIZED
                    </button>
                  <?php endif; ?>

                  <?php if ($has_finished): ?>
                    <a href="student_objective_exam.php?exam_id=<?= $exam['id'] ?>&view_result=1"
                      class="btn btn-outline-info py-2 rounded-4 fw-bold">
                      <i class="bi bi-eye me-2"></i> VIEW RESULTS
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
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
    // Sidebar toggle for mobile
    const sidebar = document.getElementById('studentSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.onclick = function () {
        sidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarBackdrop.onclick = function () {
        sidebar.classList.remove('show');
        sidebarBackdrop.classList.remove('show');
      };
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
</body>

</html>