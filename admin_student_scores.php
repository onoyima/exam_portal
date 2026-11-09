<?php
require 'auth.php';
check_auth('admin');
require 'db.php';

$name = $_SESSION['user'] ?? 'Admin';
$back_url = 'admin_dashboard';
$type_filter = $_GET['type'] ?? '';

$sql = "SELECT * FROM exams WHERE deleted_at IS NULL";
if ($type_filter === 'viva') {
    $sql .= " AND type='viva'";
} elseif ($type_filter === 'research') {
    $sql .= " AND type='research'";
} else {
    $sql .= " AND (type='practical' OR type IS NULL)";
}
$sql .= " ORDER BY created_at DESC";

$exams = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

$students = [];
$scores = [];

if ($selected_exam) {
    // Get students for the selected exam
    $students = $db->query("
        SELECT s.* 
        FROM students s 
        JOIN exam_students es ON s.id = es.student_id 
        WHERE es.exam_id = $selected_exam AND s.deleted_at IS NULL
        ORDER BY s.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get scores for each student (FIXED)
    foreach ($students as $stu) {
        $stu_id = $stu['id'];

        $stmt = $db->prepare("
            SELECT q.text, a.score 
            FROM answers a 
            JOIN questions q ON a.question_id = q.id 
            WHERE a.exam_id = ? AND a.student_id = ?
        ");
        $stmt->execute([$selected_exam, $stu_id]);
        $scores[$stu_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Handle score reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_score']) && isset($_POST['student_id']) && $selected_exam) {
    $student_id = intval($_POST['student_id']);
    $db->prepare("DELETE FROM answers WHERE exam_id=? AND student_id=?")->execute([$selected_exam, $student_id]);
    $reset_success = "Scores reset for student.";

    // Refresh students and scores after reset
    $students = $db->query("
        SELECT s.* 
        FROM students s 
        JOIN exam_students es ON s.id = es.student_id 
        WHERE es.exam_id = $selected_exam AND s.deleted_at IS NULL
        ORDER BY s.name
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($students as $stu) {
        $stu_id = $stu['id'];

        $stmt = $db->prepare("
            SELECT q.text, a.score 
            FROM answers a 
            JOIN questions q ON a.question_id = q.id 
            WHERE a.exam_id = ? AND a.student_id = ?
        ");
        $stmt->execute([$selected_exam, $stu_id]);
        $scores[$stu_id] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$exam_index = array_search($selected_exam, array_column($exams, 'id'));
$exam_name = ($exam_index !== false && isset($exams[$exam_index]['name'])) ? $exams[$exam_index]['name'] : 'Unknown Exam';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Scores - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="admin-navbar">
      <div class="admin-navbar-inner">
        <div class="admin-navbar-start">
          <button class="admin-navbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar"><i class="bi bi-list"></i></button>
          <h1 class="admin-navbar-title">Student Scores</h1>
        </div>
        <div class="admin-navbar-end">
          <div class="admin-user-badge">
            <span class="admin-user-avatar"><img src="passport_image.php?type=examiner&amp;id=<?= $_SESSION["examiner_id"] ?? 0 ?>" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" onerror="this.remove();var p=this.parentElement;if(p){var s=p.nextElementSibling;if(s){var c=s.textContent.trim().charAt(0);p.textContent=c?c.toUpperCase():&quot;?&quot;}}"></span>
            <span class="admin-user-name"><?= htmlspecialchars($name) ?></span>
          </div>
          <a href="logout" class="admin-logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i></a>
        </div>
      </div>
    </nav>
    <div class="admin-content">
      <nav class="admin-breadcrumb">
        <ol>
          <li><a href="admin_dashboard"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Student Scores</li>
        </ol>
      </nav>
      <form method="get" class="mb-4">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="exam_id" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id" class="form-select" required onchange="this.form.submit()">
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>" <?= $selected_exam==$exam['id']?'selected':'' ?>>
                    <?= htmlspecialchars($exam['name']) ?> 
                    [<?= strtoupper($exam['type'] ?? 'practical') ?>]
                    <?= $exam['is_active'] ? '(Active)' : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
            <?php if ($type_filter): ?>
                <input type="hidden" name="type" value="<?= htmlspecialchars($type_filter) ?>">
            <?php endif; ?>
          </div>
        </div>
      </form>
      <?php if ($selected_exam && $students): ?>
        <div class="admin-card">
          <div class="admin-card-header"><h3 class="admin-card-title">Scores for Exam: <span class="text-primary"><?= htmlspecialchars($exam_name) ?></span></h3></div>
          <div class="admin-card-body-flush">
          <div class="table-responsive">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Matric No</th>
                  <th>Scores</th>
                  <th>Total Score</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $stu): ?>
                  <tr>
                    <td><?= htmlspecialchars($stu['name']) ?></td>
                    <td><?= htmlspecialchars($stu['matric_no']) ?></td>
                    <td>
                      <?php if (!empty($scores[$stu['id']])): ?>
                        <ul class="mb-0">
                          <?php foreach ($scores[$stu['id']] as $sc): ?>
                            <li><?= htmlspecialchars($sc['text']) ?>: <span class="fw-bold text-success"><?= htmlspecialchars($sc['score']) ?></span></li>
                          <?php endforeach; ?>
                        </ul>
                      <?php else: ?>
                        <span class="text-muted">No scores</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php
                        $total = 0;
                        if (!empty($scores[$stu['id']])) {
                          foreach ($scores[$stu['id']] as $sc) {
                            $total += floatval($sc['score']);
                          }
                        }
                        echo '<span class="fw-bold">' . $total . '</span>';
                      ?>
                    </td>
                    <td>
                       <form method="post" class="d-inline" data-swal="Reset this student's scores for this exam?">
                        <input type="hidden" name="student_id" value="<?= $stu['id'] ?>">
                        <button type="submit" name="reset_score" class="btn btn-sm btn-danger">Reset Score</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        </div>
      <?php elseif ($selected_exam): ?>
        <div class="admin-alert admin-alert-warning">
          <i class="bi bi-exclamation-triangle-fill"></i>
          <span>No students or scores found for this exam.</span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
      <?php if (isset($reset_success)): ?>
        <div class="admin-alert admin-alert-success">
          <i class="bi bi-check-circle-fill"></i>
          <span><?= htmlspecialchars($reset_success) ?></span>
          <button class="admin-alert-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <?php include 'footer.php'; ?>
  
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>


</html> 
