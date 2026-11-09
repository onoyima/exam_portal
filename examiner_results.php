<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';
$name = $_SESSION['user'] ?? 'Examiner';
$back_url = 'assess';
$examiner = $db->prepare("SELECT id FROM examiners WHERE username=? AND deleted_at IS NULL");
$examiner->execute([$_SESSION['user']]);
$examiner_id = $examiner->fetchColumn();
$exams = $db->prepare("SELECT e.* FROM exams e JOIN exam_examiners ee ON e.id=ee.exam_id WHERE ee.examiner_id=? AND e.is_active=1 AND e.deleted_at IS NULL ORDER BY e.created_at DESC");
$exams->execute([$examiner_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);
$exam_students = [];
foreach ($exams as $exam) {
  $students = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON s.id=es.student_id WHERE es.exam_id=? AND s.deleted_at IS NULL ORDER BY s.name");
  $students->execute([$exam['id']]);
  $exam_students[$exam['id']] = $students->fetchAll(PDO::FETCH_ASSOC);
}
$scores = [];
foreach ($exams as $exam) {
  foreach ($exam_students[$exam['id']] as $stu) {
    $stu_scores = $db->prepare("SELECT q.text, a.score FROM answers a JOIN questions q ON a.question_id=q.id WHERE a.exam_id=? AND a.student_id=?");
    $stu_scores->execute([$exam['id'], $stu['id']]);
    $scores[$exam['id']][$stu['id']] = $stu_scores->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>Examiner Results - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
</head>
<body>
  <?php include 'examiner_sidebar.php'; ?>
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
          <h1 class="admin-navbar-title">Examiner Results</h1>
        </div>
        <div class="admin-navbar-end">
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
      <nav class="admin-breadcrumb" aria-label="Breadcrumb">
        <ol>
          <li><a href="assess"><i class="bi bi-house"></i></a></li>
          <li class="sep">/</li>
          <li class="current" aria-current="page">Results</li>
        </ol>
      </nav>

      <div class="admin-page-header">
        <h2 class="admin-page-title"><i class="bi bi-clipboard-data"></i> Assigned Exams & Results</h2>
      </div>

      <?php if (empty($exams)): ?>
        <div class="admin-card">
          <div class="admin-card-body">
            <div class="admin-empty">
              <div class="admin-empty-icon"><i class="bi bi-inbox"></i></div>
              <div class="admin-empty-title">No exams assigned</div>
              <div class="admin-empty-desc">You have no active exams assigned to you yet.</div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($exams as $exam): ?>
          <div class="admin-card">
            <div class="admin-card-header">
              <h3 class="admin-card-title">
                <i class="bi bi-journal-check"></i> <?= htmlspecialchars($exam['name']) ?>
              </h3>
              <?php if ($exam['is_active']): ?>
                <span class="admin-badge admin-badge-success"><i class="bi bi-circle-fill badge-xs"></i> Active</span>
              <?php else: ?>
                <span class="admin-badge admin-badge-neutral">Inactive</span>
              <?php endif; ?>
            </div>
            <?php if (empty($exam_students[$exam['id']])): ?>
              <div class="admin-card-body">
                <div class="admin-empty-sm">
                  <i class="bi bi-people"></i>
                  <div>No students assigned to this exam.</div>
                </div>
              </div>
            <?php else: ?>
              <div class="admin-card-body-flush">
                <div class="table-responsive">
                  <table class="admin-table mb-0">
                    <thead>
                      <tr>
                        <th>Student</th>
                        <th>Matric No</th>
                        <th>Scores</th>
                        <th class="text-center">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($exam_students[$exam['id']] as $stu): ?>
                        <tr>
                          <td class="fw-medium"><?= htmlspecialchars($stu['name']) ?></td>
                          <td class="text-muted small"><?= htmlspecialchars($stu['matric_no']) ?></td>
                          <td>
                            <?php if (!empty($scores[$exam['id']][$stu['id']])): ?>
                              <div class="d-flex flex-wrap gap-1">
                                <?php foreach ($scores[$exam['id']][$stu['id']] as $sc): ?>
                                  <span class="admin-badge admin-badge-primary badge-xs">
                                    <?= htmlspecialchars($sc['text']) ?>: <?= htmlspecialchars($sc['score']) ?>
                                  </span>
                                <?php endforeach; ?>
                              </div>
                            <?php else: ?>
                              <span class="text-muted small">No scores</span>
                            <?php endif; ?>
                          </td>
                          <td class="text-center fw-bold">
                            <?php
                            $total = 0;
                            if (!empty($scores[$exam['id']][$stu['id']])) {
                              foreach ($scores[$exam['id']][$stu['id']] as $sc) {
                                $total += floatval($sc['score']);
                              }
                            }
                            echo number_format($total, 1);
                            ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/admin-common.js"></script>
  <script src="assets/js/admin-swal.js"></script>
</body>
</html>
