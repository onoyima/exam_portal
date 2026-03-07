<?php
require 'auth.php';
check_auth('examiner');
require 'db.php';
$name = $_SESSION['user'] ?? 'Examiner';
// Get examiner id
$examiner = $db->prepare("SELECT id FROM examiners WHERE username=?");
$examiner->execute([$_SESSION['user']]);
$examiner_id = $examiner->fetchColumn();
// Get only active exams assigned to this examiner
$exams = $db->prepare("SELECT e.* FROM exams e JOIN exam_examiners ee ON e.id=ee.exam_id WHERE ee.examiner_id=? AND e.is_active=1 ORDER BY e.created_at DESC");
$exams->execute([$examiner_id]);
$exams = $exams->fetchAll(PDO::FETCH_ASSOC);
// For each exam, get students and their scores
$exam_students = [];
foreach ($exams as $exam) {
  $students = $db->prepare("SELECT s.* FROM students s JOIN exam_students es ON s.id=es.student_id WHERE es.exam_id=? ORDER BY s.name");
  $students->execute([$exam['id']]);
  $exam_students[$exam['id']] = $students->fetchAll(PDO::FETCH_ASSOC);
}
// For each student in each exam, get their scores
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <div class="exam-sidebar d-flex flex-column p-3" id="examSidebar">
    <h4 class="fw-bold mb-4 text-center">Examiner Panel</h4>
    <ul class="nav nav-pills flex-column mb-auto">
      <li><a href="assess" class="nav-link">Assessment Dashboard</a></li>
      <li><a href="examiner_results" class="nav-link active">View Results</a></li>
      <li><a href="chat" class="nav-link">Chat</a></li>
      <li><a href="logout" class="nav-link text-danger">Logout</a></li>
    </ul>

    <!-- System Status -->
    <div class="mt-auto pt-3 border-top">
      <div class="small text-muted">
        <div class="d-flex align-items-center mb-1">
          <span class="status-dot online me-2"></span>
          System Online
        </div>
        <div class="d-flex align-items-center">
          <span class="status-dot online me-2"></span>
          Database Connected
        </div>
      </div>
    </div>
  </div>
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span
              class="navbar-toggler-icon"></span></button>
          <a href="assess" class="btn btn-outline-secondary me-2">&larr; Back</a>
          <span class="navbar-brand fw-bold">Examiner Results</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name, 0, 1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>

    <div class="container-xl">
      <div class="card p-4 shadow-sm mb-4">
        <h4 class="mb-3">Assigned Exams & Results</h4>
        <?php if (empty($exams)): ?>
          <div class="alert alert-warning">No exams assigned to you.</div>
        <?php else: ?>
          <?php foreach ($exams as $exam): ?>
            <div class="mb-4">
              <h5 class="fw-bold text-primary mb-2"><?= htmlspecialchars($exam['name']) ?>
                <?= $exam['is_active'] ? '<span class=\'badge bg-success ms-1\'>Active</span>' : '<span class=\'badge bg-secondary ms-1\'>Inactive</span>' ?>
              </h5>
              <?php if (empty($exam_students[$exam['id']])): ?>
                <div class="alert alert-info">No students assigned to this exam.</div>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table align-middle table-bordered">
                    <thead>
                      <tr>
                        <th>Student</th>
                        <th>Matric No</th>
                        <th>Scores</th>
                        <th>Total Score</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($exam_students[$exam['id']] as $stu): ?>
                        <tr>
                          <td><?= htmlspecialchars($stu['name']) ?></td>
                          <td><?= htmlspecialchars($stu['matric_no']) ?></td>
                          <td>
                            <?php if (!empty($scores[$exam['id']][$stu['id']])): ?>
                              <ul class="mb-0">
                                <?php foreach ($scores[$exam['id']][$stu['id']] as $sc): ?>
                                  <li><?= htmlspecialchars($sc['text']) ?>: <span
                                      class="fw-bold text-success"><?= htmlspecialchars($sc['score']) ?></span></li>
                                <?php endforeach; ?>
                              </ul>
                            <?php else: ?>
                              <span class="text-muted">No scores</span>
                            <?php endif; ?>
                          </td>
                          <td>
                            <?php
                            $total = 0;
                            if (!empty($scores[$exam['id']][$stu['id']])) {
                              foreach ($scores[$exam['id']][$stu['id']] as $sc) {
                                $total += floatval($sc['score']);
                              }
                            }
                            echo '<span class="fw-bold">' . $total . '</span>';
                            ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sidebar = document.getElementById('examSidebar');
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
