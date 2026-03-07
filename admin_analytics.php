<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';

// Basic statistics
$total_students = $db->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_exams = $db->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$active_exams = $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1")->fetchColumn();
$total_questions = $db->query("SELECT (SELECT COUNT(*) FROM questions) + (SELECT COUNT(*) FROM objective_questions)")->fetchColumn();

// Fixed top scores query - properly join and aggregate (Combined)
$top_scores = $db->query("
    SELECT s.name, s.matric_no, 
           SUM(scores.score) as total_score,
           COUNT(DISTINCT scores.exam_id) as exams_taken
    FROM students s 
    JOIN (
        SELECT student_id, exam_id, score FROM answers
        UNION ALL
        SELECT student_id, exam_id, score FROM objective_attempts WHERE finished_at IS NOT NULL
    ) scores ON s.id = scores.student_id 
    GROUP BY s.id, s.name, s.matric_no 
    ORDER BY total_score DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// Exam performance data for charts (Combined)
$exam_performance = $db->query("
    SELECT e.name, e.type,
           COUNT(DISTINCT CASE WHEN e.type='practical' THEN a.student_id ELSE oa.student_id END) as students_assessed,
           AVG(CASE WHEN e.type='practical' THEN a.score ELSE oa.score END) as avg_score,
           MAX(CASE WHEN e.type='practical' THEN a.score ELSE oa.score END) as max_score,
           MIN(CASE WHEN e.type='practical' THEN a.score ELSE oa.score END) as min_score
    FROM exams e 
    LEFT JOIN answers a ON e.id = a.exam_id AND e.type='practical'
    LEFT JOIN objective_attempts oa ON e.id = oa.exam_id AND e.type='objective' AND oa.finished_at IS NOT NULL
    GROUP BY e.id, e.name, e.type, e.created_at
    ORDER BY e.created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Score distribution data (Combined)
$score_distribution = $db->query("
    SELECT 
        CASE 
            WHEN scores.score >= 40 OR (scores.score >= 4 AND scores.score < 10) THEN 'Excellent (40+ / 4+)'
            WHEN scores.score >= 30 OR (scores.score >= 3 AND scores.score < 4) THEN 'Good (30-39 / 3-3.9)'
            WHEN scores.score >= 20 OR (scores.score >= 2 AND scores.score < 3) THEN 'Average (20-29 / 2-2.9)'
            WHEN scores.score >= 10 OR (scores.score >= 1 AND scores.score < 2) THEN 'Below Average (10-19 / 1-1.9)'
            ELSE 'Poor (<10 / <1)'
        END as score_range,
        COUNT(*) as count
    FROM (
        SELECT score FROM answers
        UNION ALL
        SELECT score FROM objective_attempts WHERE finished_at IS NOT NULL
    ) scores
    GROUP BY score_range
    ORDER BY 
        CASE score_range
            WHEN 'Excellent (40+ / 4+)' THEN 1
            WHEN 'Good (30-39 / 3-3.9)' THEN 2
            WHEN 'Average (20-29 / 2-2.9)' THEN 3
            WHEN 'Below Average (10-19 / 1-1.9)' THEN 4
            WHEN 'Poor (<10 / <1)' THEN 5
        END
")->fetchAll(PDO::FETCH_ASSOC);

// Recent activity (last 7 days) (Combined)
try {
  $recent_activity = $db->query("
        SELECT date, SUM(count) as assessments FROM (
            SELECT DATE(created_at) as date, COUNT(*) as count FROM answers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(finished_at) as date, COUNT(*) as count FROM objective_attempts WHERE finished_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(finished_at)
        ) comb GROUP BY date ORDER BY date
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $recent_activity = [];
}

// Total assessments completed
$total_assessments = $db->query("SELECT (SELECT COUNT(*) FROM answers) + (SELECT COUNT(*) FROM objective_attempts WHERE finished_at IS NOT NULL)")->fetchColumn();
$avg_score_res = $db->query("SELECT AVG(score) FROM (SELECT score FROM answers UNION ALL SELECT score FROM objective_attempts WHERE finished_at IS NOT NULL) as all_scores")->fetchColumn();
$avg_score = $avg_score_res ?: 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Analytics - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
              class="navbar-toggler-icon"></span></button>
          <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
          <span class="navbar-brand fw-bold">Analytics Dashboard</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>
    <div class="container-xl">
      <!-- Key Metrics -->
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Students</h5>
            <div class="display-6 fw-bold text-primary"><?= $total_students ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Exams</h5>
            <div class="display-6 fw-bold text-primary"><?= $total_exams ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Active Exams</h5>
            <div class="display-6 fw-bold text-success"><?= $active_exams ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Total Questions</h5>
            <div class="display-6 fw-bold text-primary"><?= $total_questions ?></div>
          </div>
        </div>
      </div>

      <!-- Additional Metrics -->
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Assessments Completed</h5>
            <div class="display-6 fw-bold text-info"><?= $total_assessments ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Average Score</h5>
            <div class="display-6 fw-bold text-warning"><?= number_format($avg_score, 1) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Students with Scores</h5>
            <div class="display-6 fw-bold text-success"><?= count($top_scores) ?></div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="card p-3 text-center shadow-sm">
            <h5 class="fw-bold mb-1">Exams with Data</h5>
            <div class="display-6 fw-bold text-info">
              <?= count(array_filter($exam_performance, function ($e) {
                return $e['students_assessed'] > 0;
              })) ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row 1 -->
      <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
          <div class="card p-4 shadow-sm">
            <h4 class="mb-3">Exam Performance</h4>
            <div class="chart-container">
              <canvas id="examChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card p-4 shadow-sm">
            <h4 class="mb-3">Score Distribution</h4>
            <div class="chart-container">
              <canvas id="scoreChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row 2 -->
      <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
          <div class="card p-4 shadow-sm">
            <h4 class="mb-3">Recent Activity (Last 7 Days)</h4>
            <div class="chart-container">
              <canvas id="activityChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="card p-4 shadow-sm">
            <h4 class="mb-3">Top Performing Students</h4>
            <div class="table-responsive">
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Rank</th>
                    <th>Student</th>
                    <th>Matric No</th>
                    <th>Total Score</th>
                    <th>Exams Taken</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($top_scores as $index => $student): ?>
                    <tr>
                      <td>
                        <span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>"><?= $index + 1 ?></span>
                      </td>
                      <td><?= htmlspecialchars($student['name']) ?></td>
                      <td><?= htmlspecialchars($student['matric_no']) ?></td>
                      <td><strong><?= number_format($student['total_score'], 1) ?></strong></td>
                      <td><?= $student['exams_taken'] ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    // Sidebar toggle
    const sidebar = document.getElementById('sidebarMenu');
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

    // Chart.js configurations
    const examData = <?= json_encode($exam_performance) ?>;
    const scoreData = <?= json_encode($score_distribution) ?>;
    const activityData = <?= json_encode($recent_activity) ?>;

    // Exam Performance Chart
    if (examData.length > 0) {
      new Chart(document.getElementById('examChart'), {
        type: 'bar',
        data: {
          labels: examData.map(e => e.name),
          datasets: [{
            label: 'Students Assessed',
            data: examData.map(e => e.students_assessed),
            backgroundColor: 'rgba(79, 140, 255, 0.8)',
            borderColor: 'rgba(79, 140, 255, 1)',
            borderWidth: 1
          }, {
            label: 'Average Score',
            data: examData.map(e => parseFloat(e.avg_score) || 0),
            backgroundColor: 'rgba(52, 208, 88, 0.8)',
            borderColor: 'rgba(52, 208, 88, 1)',
            borderWidth: 1,
            yAxisID: 'y1'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: {
              beginAtZero: true,
              title: { display: true, text: 'Students' }
            },
            y1: {
              position: 'right',
              beginAtZero: true,
              title: { display: true, text: 'Score' }
            }
          }
        }
      });
    }

    // Score Distribution Chart
    if (scoreData.length > 0) {
      new Chart(document.getElementById('scoreChart'), {
        type: 'doughnut',
        data: {
          labels: scoreData.map(s => s.score_range),
          datasets: [{
            data: scoreData.map(s => s.count),
            backgroundColor: [
              'rgba(52, 208, 88, 0.8)',
              'rgba(79, 140, 255, 0.8)',
              'rgba(251, 191, 36, 0.8)',
              'rgba(239, 68, 68, 0.8)',
              'rgba(156, 163, 175, 0.8)'
            ]
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { position: 'bottom' }
          }
        }
      });
    }

    // Recent Activity Chart
    if (activityData.length > 0) {
      new Chart(document.getElementById('activityChart'), {
        type: 'line',
        data: {
          labels: activityData.map(a => a.date),
          datasets: [{
            label: 'Assessments',
            data: activityData.map(a => a.assessments),
            borderColor: 'rgba(79, 140, 255, 1)',
            backgroundColor: 'rgba(79, 140, 255, 0.1)',
            tension: 0.4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          scales: {
            y: { beginAtZero: true }
          }
        }
      });
    }
  </script>
<!-- sweetalert_custom_injected_v1 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Override global alert
        window.alert = function(msg) {
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
            if(match) {
                let msg = match[1];
                el.removeAttribute('onsubmit');
                el.addEventListener('submit', function(e) {
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
                        if(result.isConfirmed) {
                            let btn = el.querySelector('button[type="submit"]:hover, input[type="submit"]:hover') 
                                      || el.querySelector('button[type="submit"], input[type="submit"]');
                            if(btn && btn.name) {
                                let hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = btn.name;
                                hidden.value = btn.value || '1';
                                el.appendChild(hidden);
                            }
                            HTMLFormElement.prototype.submit.call(el);
                        }
                    });
                });
            }
        });

        // Intercept inline onclick confirms for anchors
        document.querySelectorAll('a[onclick*="return confirm"], button[onclick*="return confirm"]').forEach(el => {
            let match = el.getAttribute('onclick').match(/return confirm\(['"`](.*?)['"`]\)/);
            if(match) {
                let msg = match[1];
                el.removeAttribute('onclick');
                el.addEventListener('click', function(e) {
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
                        if(result.isConfirmed) {
                            if(el.tagName === 'A' && el.href) {
                                window.location.href = el.href;
                            } else if(el.tagName === 'BUTTON') {
                                // if it's a submit button inside a form
                                if(el.type === 'submit' && el.form) {
                                    if(el.name) {
                                        let hidden = document.createElement('input');
                                        hidden.type = 'hidden';
                                        hidden.name = el.name;
                                        hidden.value = el.value || '1';
                                        el.form.appendChild(hidden);
                                    }
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
