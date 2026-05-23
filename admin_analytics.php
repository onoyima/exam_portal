<?php
require 'auth.php';
check_auth('admin');
require 'db.php';
$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

// Basic statistics
$total_students = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
$total_exams = $db->query("SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL")->fetchColumn();
$active_exams = $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1 AND deleted_at IS NULL")->fetchColumn();
$total_questions = $db->query("SELECT (SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL) + (SELECT COUNT(*) FROM objective_questions WHERE deleted_at IS NULL)")->fetchColumn();

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
    WHERE s.deleted_at IS NULL
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
    WHERE e.deleted_at IS NULL
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
  <meta charset="UTF-8">
  <title>Analytics - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle d-lg-none" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <a href="<?= $back_url ?>" class="admin-back-btn">
            <i class="bi bi-arrow-left"></i>
          </a>
          <h1 class="admin-navbar-title">Analytics Dashboard</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><?= strtoupper(substr($name, 0, 1)) ?></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Analytics</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title">Analytics Dashboard</h2>
      </div>
      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_students ?></div>
            <div class="admin-stat-label">Total Students</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_exams ?></div>
            <div class="admin-stat-label">Total Exams</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $active_exams ?></div>
            <div class="admin-stat-label">Active Exams</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_questions ?></div>
            <div class="admin-stat-label">Total Questions</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= $total_assessments ?></div>
            <div class="admin-stat-label">Assessments Completed</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= number_format($avg_score, 1) ?></div>
            <div class="admin-stat-label">Average Score</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value"><?= count($top_scores) ?></div>
            <div class="admin-stat-label">Students with Scores</div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="admin-stat-card">
            <div class="admin-stat-value">
              <?= count(array_filter($exam_performance, function ($e) {
                return $e['students_assessed'] > 0;
              })) ?>
            </div>
            <div class="admin-stat-label">Exams with Data</div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
          <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Exam Performance</h3></div>
            <div class="admin-card-body">
              <canvas id="examChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Score Distribution</h3></div>
            <div class="admin-card-body">
              <canvas id="scoreChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4 mb-4">
        <div class="col-12 col-lg-6">
          <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Recent Activity (Last 7 Days)</h3></div>
            <div class="admin-card-body">
              <canvas id="activityChart"></canvas>
            </div>
          </div>
        </div>
        <div class="col-12 col-lg-6">
          <div class="admin-card">
            <div class="admin-card-header"><h3 class="admin-card-title">Top Performing Students</h3></div>
            <div class="admin-card-body">
              <div class="table-responsive">
                <table class="admin-table">
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
                          <span class="admin-badge <?= $index < 3 ? 'admin-badge-warning' : 'admin-badge-neutral' ?>"><?= $index + 1 ?></span>
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
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
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
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
