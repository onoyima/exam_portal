<?php
session_start();
require 'db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied.");
}

$name = $_SESSION['user'] ?? 'Admin';
$exams = $db->query("SELECT * FROM exams ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$selected_exam = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

$students = [];
$scores = [];

if ($selected_exam) {
    // Get students for the selected exam
    $students = $db->query("
        SELECT s.* 
        FROM students s 
        JOIN exam_students es ON s.id = es.student_id 
        WHERE es.exam_id = $selected_exam 
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
        WHERE es.exam_id = $selected_exam 
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
  <title>Student Scores - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>
<body>
  <?php include 'admin_sidebar.php'; ?>
  <div class="main-content">
    <nav class="navbar navbar-light bg-white shadow-sm mb-4">
      <div class="container-fluid d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-primary d-lg-none me-2" id="sidebarToggle"><span class="navbar-toggler-icon"></span></button>
          <a href="admin_dashboard" class="btn btn-outline-secondary me-2">Back</a>
          <span class="navbar-brand fw-bold">Student Scores</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <span class="profile-icon"><?= strtoupper(substr($name,0,1)) ?></span>
          <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
          <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
        </div>
      </div>
    </nav>
    <div class="container-xl">
      <form method="get" class="mb-4">
        <div class="row g-3">
          <div class="col-md-6">
            <label for="exam_id" class="form-label">Select Exam</label>
            <select name="exam_id" id="exam_id" class="form-select" required onchange="this.form.submit()">
              <option value="">-- Select Exam --</option>
              <?php foreach ($exams as $exam): ?>
                <option value="<?= $exam['id'] ?>" <?= $selected_exam==$exam['id']?'selected':'' ?>><?= htmlspecialchars($exam['name']) ?> <?= $exam['is_active'] ? '(Active)' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </form>
      <?php if ($selected_exam && $students): ?>
        <div class="card p-4 shadow-sm">
          <h4 class="mb-3">Scores for Exam: <span class="text-primary"><?= htmlspecialchars($exam_name) ?></span></h4>
          <div class="table-responsive">
            <table class="table align-middle">
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
                      <form method="post" style="display:inline;" onsubmit="return confirm('Reset this student\'s scores for this exam?');">
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
      <?php elseif ($selected_exam): ?>
        <div class="alert alert-warning mt-3">No students or scores found for this exam.</div>
      <?php endif; ?>
      <?php if (isset($reset_success)): ?>
        <div class="alert alert-success mt-3"> <?= htmlspecialchars($reset_success) ?> </div>
      <?php endif; ?>
    </div>
  </div>
  
  <?php include 'footer.php'; ?>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const sidebar = document.getElementById('sidebarMenu');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
      sidebarToggle.onclick = function() {
        sidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarBackdrop.onclick = function() {
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
