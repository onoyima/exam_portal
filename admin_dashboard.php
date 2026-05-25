<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';

$success_msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
  validate_csrf();
  $instruction = trim($_POST['student_login_content']);
  $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('student_login_content', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
  $stmt->execute([$instruction, $instruction]);
  $success_msg = "Settings updated successfully!";
}

$student_login_content = $db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'student_login_content'")->fetchColumn() ?: "1. LOGIN WITH YOUR MATRIC NUMBER\n2. Only your Matric Number is needed.\n3. Make sure you submit before you leave.";

// ── KPI Stats ──
$total_students   = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL")->fetchColumn();
$total_exams      = $db->query("SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL")->fetchColumn();
$active_exams     = $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1 AND deleted_at IS NULL")->fetchColumn();
$practical_exams  = $db->query("SELECT COUNT(*) FROM exams WHERE type='practical' AND deleted_at IS NULL")->fetchColumn();
$objective_exams  = $db->query("SELECT COUNT(*) FROM exams WHERE type='objective' AND deleted_at IS NULL")->fetchColumn();
$viva_exams       = $db->query("SELECT COUNT(*) FROM exams WHERE type='viva' AND deleted_at IS NULL")->fetchColumn();
$total_questions  = $db->query("SELECT (SELECT COUNT(*) FROM questions WHERE deleted_at IS NULL)+(SELECT COUNT(*) FROM objective_questions WHERE deleted_at IS NULL)")->fetchColumn();
$staff_count      = $db->query("SELECT COUNT(*) FROM examiners WHERE role='examiner' AND deleted_at IS NULL")->fetchColumn();
$assessments_done = $db->query("SELECT (SELECT COUNT(*) FROM answers)+(SELECT COUNT(*) FROM objective_attempts WHERE finished_at IS NOT NULL)")->fetchColumn();

// ── Trend data ──
try {
  $this_month = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();
} catch (PDOException $e) { $this_month = 0; }
try {
  $students_last_month = $db->query("SELECT COUNT(*) FROM students WHERE deleted_at IS NULL AND MONTH(created_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetchColumn();
} catch (PDOException $e) { $students_last_month = 0; }

$exams_this_month = $db->query("SELECT COUNT(*) FROM exams WHERE deleted_at IS NULL AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())")->fetchColumn();

try {
  $assess_this_month = $db->query("SELECT (SELECT COUNT(*) FROM answers WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()))+(SELECT COUNT(*) FROM objective_attempts WHERE finished_at IS NOT NULL AND MONTH(finished_at)=MONTH(CURDATE()) AND YEAR(finished_at)=YEAR(CURDATE()))")->fetchColumn();
} catch (PDOException $e) { $assess_this_month = 0; }
try {
  $assess_last_month = $db->query("SELECT (SELECT COUNT(*) FROM answers WHERE MONTH(created_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(created_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))+(SELECT COUNT(*) FROM objective_attempts WHERE finished_at IS NOT NULL AND MONTH(finished_at)=MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(finished_at)=YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)))")->fetchColumn();
} catch (PDOException $e) { $assess_last_month = 0; }

try {
  $active_last_month = $db->query("SELECT COUNT(*) FROM exams WHERE is_active=1 AND deleted_at IS NULL AND created_at < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)")->fetchColumn();
} catch (PDOException $e) { $active_last_month = 0; }

// ── Chart data: weekly activity ──
$weekly_activity = [];
try {
  $raw = $db->query("
    SELECT DATE(dt) as day, SUM(cnt) as total FROM (
      SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM answers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)
      UNION ALL
      SELECT DATE(finished_at) as dt, COUNT(*) as cnt FROM objective_attempts WHERE finished_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(finished_at)
    ) t GROUP BY day ORDER BY day
  ")->fetchAll(PDO::FETCH_ASSOC);
  $weekly_activity = $raw;
} catch (PDOException $e) { $weekly_activity = []; }

// ── Chart data: monthly trends (last 6 months) ──
$monthly_trends = [];
try {
  $raw = $db->query("
    SELECT DATE_FORMAT(dt, '%Y-%m') as month, SUM(cnt) as total FROM (
      SELECT DATE(created_at) as dt, COUNT(*) as cnt FROM answers WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE(created_at)
      UNION ALL
      SELECT DATE(finished_at) as dt, COUNT(*) as cnt FROM objective_attempts WHERE finished_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY DATE(finished_at)
    ) t GROUP BY month ORDER BY month
  ")->fetchAll(PDO::FETCH_ASSOC);
  $monthly_trends = $raw;
} catch (PDOException $e) { $monthly_trends = []; }

// ── Recent submissions (one row per exam+student, no duplicates) ──
$recent_activity = [];
try {
  $recent = $db->query("
    (SELECT 'practical' as type, MAX(a.created_at) as dt, s.name as sname, s.matric_no, e.name as ename
     FROM answers a
     JOIN students s ON a.student_id=s.id AND s.deleted_at IS NULL
     JOIN exams e ON a.exam_id=e.id AND e.deleted_at IS NULL AND e.type='practical'
     GROUP BY a.student_id, a.exam_id, s.name, s.matric_no, e.name
     ORDER BY dt DESC LIMIT 4)
    UNION ALL
    (SELECT 'viva' as type, MAX(a.created_at) as dt, s.name as sname, s.matric_no, e.name as ename
     FROM answers a
     JOIN students s ON a.student_id=s.id AND s.deleted_at IS NULL
     JOIN exams e ON a.exam_id=e.id AND e.deleted_at IS NULL AND e.type='viva'
     GROUP BY a.student_id, a.exam_id, s.name, s.matric_no, e.name
     ORDER BY dt DESC LIMIT 4)
    UNION ALL
    (SELECT 'objective' as type, MAX(oa.finished_at) as dt, s.name as sname, s.matric_no, e.name as ename
     FROM objective_attempts oa
     JOIN students s ON oa.student_id=s.id AND s.deleted_at IS NULL
     JOIN exams e ON oa.exam_id=e.id AND e.deleted_at IS NULL
     WHERE oa.finished_at IS NOT NULL
     GROUP BY oa.student_id, oa.exam_id, s.name, s.matric_no, e.name
     ORDER BY dt DESC LIMIT 4)
    ORDER BY dt DESC LIMIT 8
  ")->fetchAll(PDO::FETCH_ASSOC);
  $recent_activity = $recent;
} catch (PDOException $e) { $recent_activity = []; }

// ── Helper for trend display ──
function trend($current, $previous) {
  if ($previous == 0) return ['neutral', '—'];
  $pct = round((($current - $previous) / $previous) * 100, 1);
  if ($pct > 0) return ['up', '+' . $pct . '%'];
  if ($pct < 0) return ['down', $pct . '%'];
  return ['neutral', '0%'];
}
$stud_trend = trend($this_month, $students_last_month);
$exam_trend = trend($exams_this_month, 1);
$active_trend = trend($active_exams, $active_last_month ?: 1);
$assess_trend = trend($assess_this_month, $assess_last_month ?: 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <!-- ═══ ENHANCED NAVBAR ═══ -->
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
            <i class="bi bi-list"></i>
          </button>
          <h1 class="admin-navbar-title d-none d-md-block">Dashboard</h1>
        </div>

        <div class="admin-navbar-center">
          <div class="navbar-search">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Search students, exams..." aria-label="Search">
          </div>
        </div>

        <div class="admin-navbar-end">
          <div class="navbar-date-filter">
            <i class="bi bi-calendar3"></i>
            <span><?= date('M Y') ?></span>
            <i class="bi bi-chevron-down" style="font-size:0.65rem;"></i>
          </div>
          <a href="admin_analytics" class="navbar-icon-btn" title="Analytics">
            <i class="bi bi-bar-chart-line"></i>
          </a>
          <a href="system_chat" class="navbar-icon-btn" title="System Assistant">
            <i class="bi bi-robot"></i>
          </a>
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout">
            <i class="bi bi-box-arrow-right"></i>
          </a>
        </div>
      </div>
    </nav>

    <div class="admin-content">
      <!-- ═══ BREADCRUMB ═══ -->
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Dashboard</li>
        </ol>
      </nav>

      <!-- ═══ WELCOME ═══ -->
      <div class="dash-welcome">
        <h2>Good <?= (int)date('H') < 12 ? 'morning' : ((int)date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= htmlspecialchars(explode(' ', $name)[0]) ?></h2>
        <p>Here's what's happening across your examination platform.</p>
      </div>

      <?php display_auth_errors(); ?>

      <!-- ═══ KPI STATS ROW ═══ -->
      <div class="dash-stats">
        <div class="kpi-card kpi-students">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-people"></i></span>
            <span class="kpi-trend <?= $stud_trend[0] ?>"><i class="bi bi-arrow-<?= $stud_trend[0] === 'up' ? 'up' : ($stud_trend[0] === 'down' ? 'down' : 'right') ?>"></i> <?= $stud_trend[1] ?></span>
          </div>
          <div>
            <div class="kpi-label">Total Students</div>
            <div class="kpi-value"><?= number_format($total_students) ?></div>
          </div>
        </div>

        <div class="kpi-card kpi-exams">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-journal-check"></i></span>
            <span class="kpi-trend <?= $exam_trend[0] ?>"><i class="bi bi-arrow-<?= $exam_trend[0] === 'up' ? 'up' : ($exam_trend[0] === 'down' ? 'down' : 'right') ?>"></i> <?= $exam_trend[1] ?></span>
          </div>
          <div>
            <div class="kpi-label">Total Exams</div>
            <div class="kpi-value"><?= $total_exams ?></div>
          </div>
        </div>

        <div class="kpi-card kpi-active">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-check-circle"></i></span>
            <span class="kpi-trend <?= $active_trend[0] ?>"><i class="bi bi-arrow-<?= $active_trend[0] === 'up' ? 'up' : ($active_trend[0] === 'down' ? 'down' : 'right') ?>"></i> <?= $active_trend[1] ?></span>
          </div>
          <div>
            <div class="kpi-label">Active Exams</div>
            <div class="kpi-value"><?= $active_exams ?></div>
          </div>
        </div>

        <div class="kpi-card kpi-questions">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-patch-question"></i></span>
            <span class="kpi-trend neutral"><i class="bi bi-dash"></i> —</span>
          </div>
          <div>
            <div class="kpi-label">Total Questions</div>
            <div class="kpi-value"><?= number_format($total_questions) ?></div>
          </div>
        </div>

        <div class="kpi-card kpi-staff">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-person-badge"></i></span>
            <span class="kpi-trend neutral"><i class="bi bi-dash"></i> —</span>
          </div>
          <div>
            <div class="kpi-label">Staff</div>
            <div class="kpi-value"><?= $staff_count ?></div>
          </div>
        </div>

        <div class="kpi-card kpi-assessments">
          <div class="kpi-top">
            <span class="kpi-icon"><i class="bi bi-trophy"></i></span>
            <span class="kpi-trend <?= $assess_trend[0] ?>"><i class="bi bi-arrow-<?= $assess_trend[0] === 'up' ? 'up' : ($assess_trend[0] === 'down' ? 'down' : 'right') ?>"></i> <?= $assess_trend[1] ?></span>
          </div>
          <div>
            <div class="kpi-label">Assessments</div>
            <div class="kpi-value"><?= number_format($assessments_done) ?></div>
          </div>
        </div>
      </div>

      <!-- ═══ CHARTS ROW ═══ -->
      <div class="chart-grid">
        <div class="chart-card">
          <div class="chart-card-header">
            <h3 class="chart-card-title"><i class="bi bi-activity text-primary"></i> Weekly Activity</h3>
            <span class="admin-badge admin-badge-neutral">Last 7 days</span>
          </div>
          <div class="chart-card-body">
            <div class="chart-container">
              <canvas id="weeklyChart"></canvas>
            </div>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-card-header">
            <h3 class="chart-card-title"><i class="bi bi-pie-chart text-info"></i> Exam Distribution</h3>
            <span class="admin-badge admin-badge-neutral">By type</span>
          </div>
          <div class="chart-card-body">
            <div class="chart-container-sm">
              <canvas id="examChart"></canvas>
            </div>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-card-header">
            <h3 class="chart-card-title"><i class="bi bi-bar-chart text-warning"></i> Monthly Trends</h3>
            <span class="admin-badge admin-badge-neutral">6 months</span>
          </div>
          <div class="chart-card-body">
            <div class="chart-container">
              <canvas id="monthlyChart"></canvas>
            </div>
          </div>
        </div>

        <div class="chart-card">
          <div class="chart-card-header">
            <h3 class="chart-card-title"><i class="bi bi-grid text-secondary"></i> Exam Breakdown</h3>
            <span class="admin-badge admin-badge-neutral"><?= $total_exams ?> total</span>
          </div>
          <div class="chart-card-body">
            <?php $max_type = max($practical_exams, $objective_exams, $viva_exams, 1); ?>
            <div class="exam-type-bar">
              <span class="exam-type-icon practical"><i class="bi bi-laptop"></i></span>
              <div class="exam-type-info">
                <div class="exam-type-name">Practical</div>
                <div class="exam-type-count"><?= $practical_exams ?> exams</div>
              </div>
              <div class="exam-type-track">
                <div class="exam-type-fill practical" style="width:<?= round(($practical_exams / $max_type) * 100) ?>%"></div>
              </div>
            </div>
            <div class="exam-type-bar">
              <span class="exam-type-icon objective"><i class="bi bi-list-check"></i></span>
              <div class="exam-type-info">
                <div class="exam-type-name">Objective</div>
                <div class="exam-type-count"><?= $objective_exams ?> exams</div>
              </div>
              <div class="exam-type-track">
                <div class="exam-type-fill objective" style="width:<?= round(($objective_exams / $max_type) * 100) ?>%"></div>
              </div>
            </div>
            <div class="exam-type-bar">
              <span class="exam-type-icon viva"><i class="bi bi-mic"></i></span>
              <div class="exam-type-info">
                <div class="exam-type-name">Viva</div>
                <div class="exam-type-count"><?= $viva_exams ?> exams</div>
              </div>
              <div class="exam-type-track">
                <div class="exam-type-fill viva" style="width:<?= round(($viva_exams / $max_type) * 100) ?>%"></div>
              </div>
            </div>
            <div class="mt-3 pt-3 border-top-light">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted small">Active Exams</span>
                <span class="fw-bold text-color-success"><?= $active_exams ?> / <?= $total_exams ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ BOTTOM GRID: Quick Actions + Recent Activity + Settings ═══ -->
      <div class="dash-bottom">
        <!-- Quick Actions -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-lightning-charge text-warning"></i> Quick Actions</h3>
          </div>
          <div class="admin-card-body">
            <div class="dashboard-actions-grid">
              <a href="admin_students" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#e0f2fe;color:#0ea5e9;"><i class="bi bi-people"></i></span>
                <span class="dashboard-action-label">Students</span>
              </a>
              <a href="admin_staff" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#fce4ec;color:#dc3545;"><i class="bi bi-person-badge"></i></span>
                <span class="dashboard-action-label">Staff</span>
              </a>
              <a href="admin_exams" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#e8f5e9;color:#198754;"><i class="bi bi-journal-check"></i></span>
                <span class="dashboard-action-label">Exams</span>
              </a>
              <a href="admin_questions" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#fef3c7;color:#f59e0b;"><i class="bi bi-patch-question"></i></span>
                <span class="dashboard-action-label">Practical Qs</span>
              </a>
              <a href="admin_objective_exams" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#ede9fe;color:#8b5cf6;"><i class="bi bi-list-check"></i></span>
                <span class="dashboard-action-label">Objective</span>
              </a>
              <a href="report" class="dashboard-action-card">
                <span class="dashboard-action-icon" style="background:#e0f2fe;color:#0ea5e9;"><i class="bi bi-graph-up"></i></span>
                <span class="dashboard-action-label">Reports</span>
              </a>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-activity"></i> Recent Submissions</h3>
            <a href="admin_analytics" class="admin-btn admin-btn-sm admin-btn-outline">View All</a>
          </div>
          <div class="admin-card-body">
            <?php if (empty($recent_activity)): ?>
              <div class="admin-empty-sm">
                <i class="bi bi-inbox"></i>
                <div>No recent activity</div>
              </div>
            <?php else: ?>
              <div class="activity-feed">
                <?php foreach ($recent_activity as $act): ?>
                  <div class="activity-item">
                    <span class="activity-dot assessment"></span>
                    <div class="activity-body">
                      <div class="activity-text">
                        <strong><?= htmlspecialchars($act['sname'] ?? 'Student') ?></strong>
                        submitted <strong><?= htmlspecialchars($act['ename']) ?></strong>
                        <span class="admin-badge <?= $act['type'] === 'objective' ? 'admin-badge-info' : 'admin-badge-primary' ?> badge-xs"><?= $act['type'] ?></span>
                      </div>
                      <div class="activity-time"><?= date('M d, Y g:i A', strtotime($act['dt'])) ?></div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- System Settings -->
        <div class="admin-card">
          <div class="admin-card-header">
            <h3 class="admin-card-title"><i class="bi bi-gear"></i> System Settings</h3>
          </div>
          <div class="admin-card-body">
            <?php if ($success_msg): ?>
              <div class="admin-alert admin-alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <span><?= $success_msg ?></span>
                <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
              </div>
            <?php endif; ?>
            <form method="post">
              <?php csrf_field(); ?>
              <div class="admin-form-group">
                <label class="admin-form-label">Student Login Instructions</label>
                <textarea name="student_login_content" class="admin-input" rows="3"><?= htmlspecialchars($student_login_content) ?></textarea>
              </div>
              <button type="submit" name="save_settings" class="admin-btn admin-btn-primary">
                <i class="bi bi-save"></i> Save Settings
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.body.classList.contains('dark-mode');
    const txtColor = isDark ? '#e2e8f0' : '#64748b';
    const gridColor = isDark ? '#334155' : '#e2e8f0';

    // ── Weekly Activity Line Chart ──
    const weeklyData = <?= json_encode($weekly_activity) ?>;
    if (weeklyData.length > 0 && document.getElementById('weeklyChart')) {
      const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
      const labels = weeklyData.map(function(d) {
        const dt = new Date(d.day + 'T00:00:00');
        return days[dt.getDay()] + ' ' + dt.getDate();
      });
      new Chart(document.getElementById('weeklyChart'), {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Submissions',
            data: weeklyData.map(function(d) { return parseInt(d.total); }),
            borderColor: '#198754',
            backgroundColor: 'rgba(25, 135, 84, 0.08)',
            borderWidth: 2.5,
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointBackgroundColor: '#198754',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { color: gridColor }, ticks: { color: txtColor, font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: txtColor, font: { size: 11 }, stepSize: 1 } }
          }
        }
      });
    }

    // ── Exam Distribution Doughnut ──
    const pCount = <?= $practical_exams ?>;
    const oCount = <?= $objective_exams ?>;
    const vCount = <?= $viva_exams ?>;
    if (document.getElementById('examChart')) {
      new Chart(document.getElementById('examChart'), {
        type: 'doughnut',
        data: {
          labels: ['Practical (' + pCount + ')', 'Objective (' + oCount + ')', 'Viva (' + vCount + ')'],
          datasets: [{
            data: [pCount, oCount, vCount],
            backgroundColor: ['#198754', '#0ea5e9', '#f59e0b'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '70%',
          plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 10, padding: 12, font: { size: 11 }, color: txtColor } }
          }
        }
      });
    }

    // ── Monthly Trends Bar Chart ──
    const monthlyData = <?= json_encode($monthly_trends) ?>;
    if (monthlyData.length > 0 && document.getElementById('monthlyChart')) {
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      const labels = monthlyData.map(function(d) {
        const parts = d.month.split('-');
        return months[parseInt(parts[1]) - 1] + ' ' + parts[0];
      });
      new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{
            label: 'Assessments',
            data: monthlyData.map(function(d) { return parseInt(d.total); }),
            backgroundColor: 'rgba(25, 135, 84, 0.7)',
            borderColor: '#198754',
            borderWidth: 1,
            borderRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: false } },
          scales: {
            x: { grid: { display: false }, ticks: { color: txtColor, font: { size: 11 } } },
            y: { beginAtZero: true, grid: { color: gridColor }, ticks: { color: txtColor, font: { size: 11 } } }
          }
        }
      });
    }
  });
  </script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
