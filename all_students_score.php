<?php
session_start();
require 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied.");
}

$name = $_SESSION['user'] ?? 'Admin';

// Query to get all students with total score and number of exams taken
$all_students = $db->query("
    SELECT s.name, s.matric_no, 
           SUM(a.score) as total_score,
           COUNT(DISTINCT a.exam_id) as exams_taken
    FROM students s
    LEFT JOIN answers a ON s.id = a.student_id
    GROUP BY s.id, s.name, s.matric_no
    ORDER BY total_score DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <title>All Students - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
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
        <span class="navbar-brand fw-bold">All Students Performance</span>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="profile-icon"><?= strtoupper(substr($name,0,1)) ?></span>
        <span class="fw-semibold"><?= htmlspecialchars($name) ?></span>
        <a href="logout" class="btn btn-outline-danger ms-2">Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-xl">
    <div class="card p-4 shadow-sm">
      <h4 class="mb-3">All Students with Scores</h4>
      <div class="table-responsive">
        <table class="table table-striped table-sm">
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
            <?php foreach ($all_students as $index => $student): ?>
              <tr>
                <td><span class="badge bg-<?= $index < 3 ? 'warning' : 'secondary' ?>"><?= $index + 1 ?></span></td>
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

<?php include 'footer.php'; ?>
<script src="assets/js/bootstrap.bundle.min.js"></script>
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
