<?php
require 'auth.php';
// If already logged in as admin or examiner, redirect to dashboard
if (isset($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard");
    exit;
  } elseif ($_SESSION['role'] === 'examiner') {
    header("Location: assess");
    exit;
  }
}
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  // Check examiner table (includes both admin and examiner users)
  $stmt = $db->prepare("SELECT * FROM examiners WHERE username = ? AND password = ?");
  $stmt->execute([$username, $password]);
  $user = $stmt->fetch();

  if ($user) {
    $_SESSION['user'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $user_type = $user['role'];
    $redirect_url = ($user['role'] === 'admin') ? 'admin_dashboard' : 'assess';

    // Log successful login
    $db->exec("
            CREATE TABLE IF NOT EXISTS login_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                username VARCHAR(100) NOT NULL,
                user_type ENUM('admin', 'examiner', 'student') NOT NULL,
                login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
                logout_time DATETIME NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                status ENUM('success', 'failed') DEFAULT 'success',
                INDEX idx_user_login (user_id, login_time)
            )
        ");

    $stmt = $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$username, $user_type, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);

    header("Location: " . $redirect_url);
    exit;
  }

  // Login failed
  $error = "Invalid credentials";

  // Log failed login attempt
  $db->exec("
        CREATE TABLE IF NOT EXISTS login_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            username VARCHAR(100) NOT NULL,
            user_type ENUM('admin', 'examiner', 'student') NOT NULL,
            login_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            logout_time DATETIME NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            status ENUM('success', 'failed') DEFAULT 'success',
            INDEX idx_user_login (user_id, login_time)
        )
    ");

  $stmt = $db->prepare("INSERT INTO login_history (username, user_type, ip_address, user_agent, status) VALUES (?, ?, ?, ?, 'failed')");
  $stmt->execute([$username, 'unknown', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <title>Login - Assessment Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="assets/favicon.ico" type="image/x-icon">
  <link href="assets/css/bootstrap.min.css" rel="stylesheet">
  <link href="style_enhanced.css" rel="stylesheet">
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container-xl">
      <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="index">
        <img src="assets/favicon.ico" alt="Logo" width="30" height="30" class="d-inline-block align-top">
        <span>Assessment Portal</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item">
            <a class="nav-link" href="services">Services</a>
          </li>
          <li class="nav-item">
            <a class="nav-link active" href="login">Login</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <div class="container-xl">
    <div class="row justify-content-center align-items-center min-vh-100">
      <div class="col-sm-10 col-md-8 col-lg-6 col-xl-4">
        <div class="card shadow-lg">
          <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
              <h2 class="fw-bold text-primary mb-2">Assessment Portal</h2>
              <p class="text-muted mb-0">Sign in to your account</p>
            </div>

            <?php if (isset($error)): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
              <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
              </div>

              <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
              </div>

              <button type="submit" class="btn btn-primary w-100">Sign In</button>
            </form>

            <div class="text-center mt-4">
              <p class="mb-0">Are you a student? <a href="student_login" class="fw-bold text-decoration-none">Go to
                  Student Portal</a></p>
            </div>

            <div class="text-center mt-4">
              <div class="card bg-light">
                <div class="card-body p-3">
                  <small class="text-muted">
                    <strong>Demo Credentials:</strong><br>
                    <div class="mt-2">
                      <div class="mb-1"><strong>Admin:</strong> admin / admin123</div>
                      <div><strong>Examiner:</strong> examiner1 / examiner123</div>
                    </div>
                  </small>
                </div>
              </div>
            </div>
          </div>
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
