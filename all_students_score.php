<?php
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied.");
}

$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';

$all_students = $db->query("
    SELECT s.name, s.matric_no, 
           SUM(a.score) as total_score,
           COUNT(DISTINCT a.exam_id) as exams_taken
    FROM students s
    LEFT JOIN answers a ON s.id = a.student_id
    WHERE s.deleted_at IS NULL
    GROUP BY s.id, s.name, s.matric_no
    ORDER BY total_score DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Student Scores - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
<?php include 'admin_sidebar.php'; ?>
<div class="main-content">
  <nav class="admin-navbar">
    <div class="admin-navbar-inner">
      <div class="admin-navbar-start">
        <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
          <i class="bi bi-list"></i>
        </button>
        <a href="<?= $back_url ?>" class="admin-back-btn" aria-label="Go back">
          <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="admin-navbar-title">Student Scores</h1>
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
        <li class="current" aria-current="page">Student Scores</li>
      </ol>
    </nav>

    <div class="admin-page-header">
      <h2 class="admin-page-title"><i class="bi bi-trophy"></i> All Students Performance</h2>
      <div class="admin-page-actions">
        <span class="admin-badge admin-badge-primary"><?= count($all_students) ?> Students</span>
      </div>
    </div>

    <div class="admin-card">
      <div class="admin-card-header">
        <h3 class="admin-card-title"><i class="bi bi-bar-chart-line"></i> Score Rankings</h3>
      </div>
      <div class="admin-card-body-flush">
        <div class="table-responsive">
          <table class="admin-table mb-0">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Student</th>
                <th>Matric No</th>
                <th class="text-center">Total Score</th>
                <th class="text-center">Exams Taken</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($all_students as $index => $student): ?>
                <tr>
                  <td>
                    <span class="top-rank-badge <?= $index < 3 ? ($index === 0 ? 'gold' : ($index === 1 ? 'silver' : 'bronze')) : 'default' ?>">
                      <?= $index + 1 ?>
                    </span>
                  </td>
                  <td class="fw-medium"><?= htmlspecialchars($student['name']) ?></td>
                  <td class="text-muted small"><?= htmlspecialchars($student['matric_no']) ?></td>
                  <td class="text-center fw-bold"><?= number_format($student['total_score'], 1) ?></td>
                  <td class="text-center">
                    <span class="admin-badge admin-badge-info"><?= $student['exams_taken'] ?> exam<?= $student['exams_taken'] !== 1 ? 's' : '' ?></span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/admin-common.js"></script>
<script src="assets/js/admin-swal.js"></script>
</body>
</html>
